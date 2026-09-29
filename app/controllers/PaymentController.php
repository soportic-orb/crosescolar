<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Models\Billing;
use Cros\Models\Order;
use Cros\Models\Payment;
use Cros\Models\Registration;
use Cros\Payments\Checkout;
use Cros\Payments\Gateways;
use Cros\Payments\RedsysGateway;

/**
 * El camí públic d'un cobrament: anar a pagar, tornar-ne i descarregar-ne el
 * rebut o la factura.
 *
 * Les adreces porten el testimoni del pagament, que és llarg i aleatori: qui
 * el té pot veure aquell cobrament i cap més. No cal cap sessió, perquè qui
 * compra un tiquet no té compte enlloc.
 */
class PaymentController extends Controller
{
    /** La pantalla que se'n va a la passarel·la. */
    public function start(array $params): void
    {
        $payment = $this->payment((string) $params['token']);
        if ((string) $payment['status'] === 'paid') {
            redirect('/pagament/' . $payment['token']);
        }
        if (!Gateways::ready()) {
            flash('error', 'Ara mateix no es pot pagar en línia. Poseu-vos en contacte amb l\'organització.');
            redirect('/pagament/' . $payment['token']);
        }

        try {
            $instruction = Checkout::begin($payment);
        } catch (\Throwable $e) {
            log_line('payments', 'No s\'ha pogut començar el cobrament', [
                'code' => $payment['code'], 'error' => $e->getMessage(),
            ]);
            flash('error', 'No s\'ha pogut iniciar el pagament: ' . $e->getMessage());
            redirect('/pagament/' . $payment['token']);
            return;
        }

        if (($instruction['mode'] ?? 'redirect') === 'redirect') {
            redirect((string) $instruction['url']);
        }

        // El TPV vol un formulari: es pinta i s'envia sol.
        $this->view('public/payment-form', [
            'title' => 'Anem a pagar',
            'payment' => $payment,
            'action' => (string) $instruction['url'],
            'fields' => (array) $instruction['fields'],
        ]);
    }

    /** L'estat d'un cobrament, amb el botó de pagar si encara no s'ha fet. */
    public function show(array $params): void
    {
        $payment = $this->payment((string) $params['token']);
        $this->view('public/payment', [
            'title' => 'El vostre pagament',
            'payment' => $payment,
            'items' => Payment::items((int) $payment['id']),
            'document' => Billing::document((int) $payment['id']),
            'ready' => Gateways::ready(),
        ]);
    }

    /** Torna el navegador des de la passarel·la. */
    public function returned(array $params): void
    {
        $payment = $this->payment((string) $params['token']);
        $result = Checkout::finish($payment, array_merge($_GET, $_POST));
        $payment = $result['payment'];

        if ($result['status'] === 'paid') {
            $this->view('public/payment-done', [
                'title' => 'Pagament fet',
                'payment' => $payment,
                'items' => Payment::items((int) $payment['id']),
                'document' => Billing::document((int) $payment['id']),
                'order' => (string) $payment['concept'] === 'order' && !empty($payment['reference_id'])
                    ? Order::find((int) $payment['reference_id']) : null,
                'registration' => (string) $payment['concept'] === 'registration' && !empty($payment['reference_id'])
                    ? Registration::find((int) $payment['reference_id']) : null,
            ]);
            return;
        }

        $this->view('public/payment', [
            'title' => $result['status'] === 'failed' ? 'El pagament no ha anat bé' : 'Pagament en procés',
            'payment' => $payment,
            'items' => Payment::items((int) $payment['id']),
            'document' => null,
            'ready' => Gateways::ready(),
            'waiting' => $result['status'] === 'pending',
        ]);
    }

    /** Qui s'ha fet enrere abans de pagar. */
    public function cancelled(array $params): void
    {
        $payment = $this->payment((string) $params['token']);
        if ((string) $payment['status'] === 'pending') {
            Payment::cancel($payment, 'Cancel·lat des de la passarel·la.');
            $payment = Payment::findByToken((string) $params['token']) ?? $payment;
        }

        $this->view('public/payment', [
            'title' => 'Pagament cancel·lat',
            'payment' => $payment,
            'items' => Payment::items((int) $payment['id']),
            'document' => null,
            'ready' => Gateways::ready(),
            'cancelled' => true,
        ]);
    }

    /**
     * L'avís del servidor de la passarel·la, sense navegador pel mig.
     *
     * És el que mana: si algú tanca la finestra just després de pagar, aquest
     * és l'únic avís que arriba.
     */
    public function notify(array $params): void
    {
        $gateway = (string) ($params['gateway'] ?? '');
        if (!isset(Gateways::MODULES[$gateway])) {
            json_out(['error' => 'Passarel·la desconeguda'], 404);
        }
        $input = array_merge($_GET, $_POST);
        $payment = $this->resolve($gateway, $input);
        if (!$payment) {
            log_line('payments', 'Avís sense pagament conegut', ['gateway' => $gateway]);
            json_out(['error' => 'Pagament desconegut'], 404);
        }

        try {
            $result = Checkout::finish($payment, $input);
        } catch (\Throwable $e) {
            log_line('payments', 'Error atenent l\'avís de la passarel·la', [
                'gateway' => $gateway, 'code' => $payment['code'], 'error' => $e->getMessage(),
            ]);
            json_out(['error' => 'Error intern'], 500);
            return;
        }

        // Redsys vol un 200 i prou; la resta se'n fan creus del cos.
        json_out(['status' => $result['status']]);
    }

    /** El rebut o la factura en PDF. */
    public function document(array $params): void
    {
        $payment = $this->payment((string) $params['token']);
        $document = Billing::document((int) $payment['id']);
        if (!$document) {
            throw new HttpException(404, 'Aquest pagament encara no té cap document.');
        }
        $pdf = Billing::pdf($document);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . Billing::filename($document) . '"');
        header('Content-Length: ' . strlen($pdf));
        header('X-Robots-Tag: noindex, nofollow');
        echo $pdf;
        exit;
    }

    /** De quin pagament parla un avís de la passarel·la. */
    private function resolve(string $gateway, array $input): ?array
    {
        if ($gateway === 'redsys') {
            $encoded = (string) ($input['Ds_MerchantParameters'] ?? '');
            $code = $encoded !== '' ? RedsysGateway::findOrder($encoded) : '';
            if ($code !== '') {
                return Payment::findByCode($code);
            }
        }
        foreach (['session_id', 'token', 'order_id'] as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value !== '') {
                $found = Payment::findByRef($value);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function payment(string $token): array
    {
        $payment = Payment::findByToken($token);
        if (!$payment) {
            throw new HttpException(404, 'Aquest pagament no existeix.');
        }

        return $payment;
    }
}
