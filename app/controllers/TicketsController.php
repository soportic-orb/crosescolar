<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Qr;
use Cros\Models\Content;
use Cros\Models\Order;
use Cros\Models\Payment;
use Cros\Models\Ticket;
use Cros\Models\TicketType;
use Cros\Payments\Gateways;

/** Punt de recàrrega: informació, venda i consulta dels tiquets. */
class TicketsController extends Controller
{
    /** El web mostra la botiga o només informació? */
    public static function saleMode(): bool
    {
        return (string) setting('tickets_public_mode', 'info') === 'sale';
    }

    /** La venda està oberta? */
    public static function salesOpen(): bool
    {
        if (!self::saleMode() || setting('tickets_enabled', '1') !== '1') {
            return false;
        }
        $deadline = (string) setting('tickets_deadline', '');
        if ($deadline !== '' && strtotime($deadline . ' 23:59:59') < time()) {
            return false;
        }
        return true;
    }

    public function index(): void
    {
        $types = TicketType::all();
        $this->view('public/tickets', [
            'title' => setting('tickets_title', 'Tiquets del punt de recàrrega'),
            'description' => excerpt(strip_tags((string) setting('tickets_intro', '')), 160),
            'types' => $types,
            'saleMode' => self::saleMode(),
            'salesOpen' => self::salesOpen() && $types !== [],
            'stripeReady' => Gateways::ready(),
            'errors' => [],
            'sponsors' => Content::sponsorsByTier(),
        ]);
    }

    /** Crea la comanda i el cobrament, i envia qui compra a la passarel·la. */
    public function checkout(): void
    {
        $this->checkCsrf();

        if (!self::salesOpen()) {
            flash('error', 'La venda de tiquets està tancada.');
            redirect('/punt-de-recarrega');
        }
        if (trim((string) input('website')) !== '') { // trampa per a robots
            redirect('/punt-de-recarrega');
        }

        $buyer = [
            'name' => (string) input('name'),
            'email' => (string) input('email'),
            'phone' => (string) input('phone'),
            'notes' => mb_substr((string) input('notes'), 0, 500),
        ];
        $errors = $this->validate([
            'name' => 'required|max:150',
            'email' => 'required|email|max:190',
            'phone' => 'max:40',
        ], $buyer);
        if (input_bool('terms') !== 1) {
            $errors['terms'] = 'Cal acceptar les condicions per continuar.';
        }

        $quantities = [];
        foreach ((array) ($_POST['qty'] ?? []) as $typeId => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                $quantities[(int) $typeId] = $qty;
            }
        }
        $maxTotal = max(1, (int) setting('tickets_max_per_order', '20'));
        if (!$quantities) {
            $errors['qty'] = 'Trieu com a mínim un tiquet.';
        } elseif (array_sum($quantities) > $maxTotal) {
            $errors['qty'] = 'Com a màxim es poden comprar ' . $maxTotal . ' tiquets per comanda.';
        }

        if ($errors) {
            set_old(array_merge($buyer, ['qty' => $quantities]));
            $this->view('public/tickets', [
                'title' => setting('tickets_title', 'Tiquets del punt de recàrrega'),
                'types' => TicketType::all(),
                'saleMode' => true,
                'salesOpen' => true,
                'stripeReady' => Gateways::ready(),
                'errors' => $errors,
                'quantities' => $quantities,
                'sponsors' => Content::sponsorsByTier(),
            ]);
            return;
        }

        try {
            $order = Order::create($buyer, $quantities);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/punt-de-recarrega');
            return;
        }

        if ((int) $order['total_cents'] === 0) {
            flash('success', 'Comanda registrada.');
            redirect('/tiquets/' . $order['token']);
        }

        if (!Gateways::ready()) {
            flash('error', 'El pagament en línia no està disponible en aquest moment. Contacteu amb l\'organització.');
            redirect('/punt-de-recarrega');
        }

        // El cobrament es crea aquí i la passarel·la ja se'n cuida el mòdul
        // que hi hagi actiu: aquest controlador no sap si és Stripe, PayPal o
        // el TPV del banc, i tant se li'n dona.
        $items = [];
        foreach (Order::items((int) $order['id']) as $item) {
            $items[] = [
                'description' => (string) $item['name'],
                'qty' => (int) $item['qty'],
                'unit_price_cents' => (int) $item['unit_price_cents'],
            ];
        }
        $payment = Payment::create([
            'concept' => 'order',
            'reference_id' => (int) $order['id'],
            'payer_name' => (string) $order['name'],
            'payer_email' => (string) $order['email'],
            'payer_phone' => (string) ($order['phone'] ?? ''),
        ], $items);
        \Cros\Core\Db::update('orders', [
            'payment_id' => (int) $payment['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $order['id']]);

        redirect('/pagament/' . $payment['token'] . '/anar');
    }

    /**
     * Retorn de l'antiga adreça de Stripe.
     *
     * Els cobraments d'ara van per /pagament/…, però una sessió oberta abans
     * d'actualitzar encara pot tornar per aquí, i un enllaç d'un correu vell
     * també. Es reencamina al cobrament, i si no se'n troba cap es mira la
     * comanda com abans.
     */
    public function success(): void
    {
        $sessionId = (string) input('session_id');
        if ($sessionId !== '') {
            $payment = Payment::findByRef($sessionId);
            if ($payment) {
                redirect('/pagament/' . $payment['token'] . '/tornada?session_id=' . rawurlencode($sessionId));
            }
        }

        $order = $sessionId !== '' ? Order::findBySession($sessionId) : null;
        $paid = $order !== null && (string) $order['status'] === 'paid';

        $this->view('public/tickets-success', [
            'title' => $paid ? 'Compra confirmada' : 'Pagament en procés',
            'order' => $order,
            'paid' => $paid,
            'tickets' => $order ? Order::tickets((int) $order['id']) : [],
            'items' => $order ? Order::items((int) $order['id']) : [],
        ]);
    }

    public function cancelled(): void
    {
        $order = Order::findByCode((string) input('comanda'));
        $this->view('public/tickets-cancelled', [
            'title' => 'Pagament cancel·lat',
            'order' => $order,
        ]);
    }

    public function lookup(): void
    {
        $this->view('public/tickets-lookup', ['title' => 'Els meus tiquets', 'errors' => []]);
    }

    public function lookupSubmit(): void
    {
        $this->checkCsrf();
        $attempts = (int) ($_SESSION['lookup_attempts'] ?? 0);
        if ($attempts > 12) {
            flash('error', 'Massa intents. Torneu-ho a provar d\'aquí una estona.');
            redirect('/els-meus-tiquets');
        }
        $_SESSION['lookup_attempts'] = $attempts + 1;

        $code = (string) input('code');
        $email = (string) input('email');
        $order = Order::findForLookup($code, $email);
        if (!$order) {
            flash('error', 'No hem trobat cap comanda amb aquestes dades. Reviseu el codi i el correu electrònic.');
            set_old(['code' => $code, 'email' => $email]);
            redirect('/els-meus-tiquets');
        }
        unset($_SESSION['lookup_attempts']);
        redirect('/tiquets/' . $order['token']);
    }

    /** Pàgina amb els tiquets d'una comanda. */
    public function show(array $params): void
    {
        $order = Order::findByToken((string) $params['token']);
        if (!$order) {
            abort(404, 'No hem trobat aquesta comanda.');
        }
        $this->view('public/tickets-show', [
            'title' => 'Comanda ' . $order['code'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'tickets' => Order::tickets((int) $order['id']),
            'noindex' => true,
        ]);
    }

    /** Versió per imprimir. */
    public function printable(array $params): void
    {
        $order = Order::findByToken((string) $params['token']);
        if (!$order) {
            abort(404, 'No hem trobat aquesta comanda.');
        }
        $this->view('public/tickets-print', [
            'title' => 'Tiquets ' . $order['code'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'tickets' => Order::tickets((int) $order['id']),
            'noindex' => true,
        ], 'layouts/print');
    }

    /** Imatge PNG del codi QR d'un tiquet. */
    public function qr(array $params): void
    {
        $code = preg_replace('/[^A-Z0-9-]/', '', strtoupper((string) $params['code'])) ?? '';
        $ticket = Ticket::findByCode($code);
        if (!$ticket) {
            abort(404, 'Codi no trobat.');
        }
        $png = Qr::png(Ticket::qrPayload($ticket), 6, 3, 'M');
        header('Content-Type: image/png');
        header('X-Robots-Tag: noindex, noimageindex');
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . strlen($png));
        echo $png;
        exit;
    }
}
