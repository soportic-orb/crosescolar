<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\Stripe;
use Cros\Models\Order;
use Cros\Models\Ticket;
use Cros\Models\TicketType;

/** Comandes de tiquets. */
class OrdersController extends Controller
{
    private const PER_PAGE = 30;

    public function index(): void
    {
        Auth::requireLogin();
        $status = (string) input('estat');
        $search = trim((string) input('q'));
        $page = max(1, (int) input('p', 1));

        $where = [];
        $params = [];
        if (in_array($status, ['pending', 'paid', 'cancelled', 'refunded'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(code LIKE :q OR name LIKE :q OR email LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Db::val('SELECT COUNT(*) FROM orders' . $clause, $params, 0);
        $orders = Db::all(
            'SELECT * FROM orders' . $clause . ' ORDER BY created_at DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE),
            $params
        );

        $this->adminView('orders/index', [
            'title' => 'Comandes',
            'orders' => $orders,
            'status' => $status,
            'search' => $search,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
            'stats' => Order::stats(),
        ]);
    }

    public function show(array $params): void
    {
        Auth::requireLogin();
        $order = Order::find((int) $params['id']);
        if (!$order) {
            abort(404, 'Comanda no trobada.');
        }
        $this->adminView('orders/show', [
            'title' => 'Comanda ' . $order['code'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'tickets' => Order::tickets((int) $order['id']),
        ]);
    }

    public function markPaid(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $order = Order::find((int) $params['id']);
        if (!$order) {
            abort(404);
        }
        Order::markPaid($order, []);
        Auth::logActivity('order_paid', 'order', (int) $order['id'], ['code' => $order['code']]);
        flash('success', 'La comanda s\'ha marcat com a pagada i s\'han enviat els tiquets.');
        redirect('/admin/comandes/' . $order['id']);
    }

    public function cancel(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $order = Order::find((int) $params['id']);
        if (!$order) {
            abort(404);
        }
        $refund = input_bool('refund') === 1;
        if ($refund && $order['status'] === 'paid' && !empty($order['stripe_payment_intent'])) {
            try {
                Stripe::refund((string) $order['stripe_payment_intent']);
                Db::update('orders', ['status' => 'refunded', 'refunded_cents' => (int) $order['total_cents'], 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $order['id']]);
                Db::update('tickets', ['status' => 'void'], 'order_id = :id', ['id' => $order['id']]);
                flash('success', 'Devolució feta i tiquets anul·lats.');
                Auth::logActivity('order_refund', 'order', (int) $order['id'], ['code' => $order['code']]);
                redirect('/admin/comandes/' . $order['id']);
            } catch (\Throwable $e) {
                flash('error', 'No s\'ha pogut fer la devolució: ' . $e->getMessage());
                redirect('/admin/comandes/' . $order['id']);
            }
        }
        Order::cancel($order, 'Cancel·lada des del panell.');
        Auth::logActivity('order_cancel', 'order', (int) $order['id'], ['code' => $order['code']]);
        flash('success', 'Comanda cancel·lada.');
        redirect('/admin/comandes/' . $order['id']);
    }

    public function resend(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $order = Order::find((int) $params['id']);
        if (!$order) {
            abort(404);
        }
        $sent = Order::sendConfirmation($order);
        flash($sent ? 'success' : 'error', $sent
            ? 'Correu reenviat a ' . $order['email']
            : 'No s\'ha pogut enviar el correu. Reviseu la configuració de correu.');
        redirect('/admin/comandes/' . $order['id']);
    }

    public function destroy(array $params): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $order = Order::find((int) $params['id']);
        if (!$order) {
            abort(404);
        }
        Db::delete('tickets', 'order_id = :id', ['id' => $order['id']]);
        Db::delete('order_items', 'order_id = :id', ['id' => $order['id']]);
        Db::delete('orders', 'id = :id', ['id' => $order['id']]);
        Auth::logActivity('order_delete', 'order', (int) $order['id'], ['code' => $order['code']]);
        flash('success', 'Comanda esborrada.');
        redirect('/admin/comandes');
    }

    public function resetTicket(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $ticket = Db::one('SELECT * FROM tickets WHERE id = :id', ['id' => (int) $params['id']]);
        if (!$ticket) {
            abort(404);
        }
        Ticket::reset((int) $ticket['id']);
        flash('success', 'Tiquet restablert com a vàlid.');
        redirect('/admin/comandes/' . $ticket['order_id']);
    }

    /** Venda manual (taquilla). */
    public function create(): void
    {
        Auth::requireLogin();
        $this->adminView('orders/create', [
            'title' => 'Nova comanda manual',
            'types' => TicketType::all(false),
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();

        $buyer = [
            'name' => (string) input('name'),
            'email' => (string) input('email'),
            'phone' => (string) input('phone'),
            'notes' => (string) input('notes'),
        ];
        $errors = $this->validate(['name' => 'required|max:150', 'email' => 'email|max:190'], $buyer);
        $quantities = [];
        foreach ((array) ($_POST['qty'] ?? []) as $typeId => $qty) {
            if ((int) $qty > 0) {
                $quantities[(int) $typeId] = (int) $qty;
            }
        }
        if (!$quantities) {
            $errors['qty'] = 'Indiqueu alguna quantitat.';
        }
        if ($errors) {
            flash('error', reset($errors));
            $this->adminView('orders/create', [
                'title' => 'Nova comanda manual',
                'types' => TicketType::all(false),
                'errors' => $errors,
            ]);
            return;
        }

        $method = in_array((string) input('method'), ['cash', 'transfer', 'manual'], true) ? (string) input('method') : 'cash';
        if ($buyer['email'] === '') {
            $buyer['email'] = 'sense-correu@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'local');
        }
        try {
            $order = Order::create($buyer, $quantities, $method);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('/admin/comandes/nova');
            return;
        }
        if (input_bool('mark_paid') === 1) {
            $order = Order::markPaid($order, []);
        }
        Auth::logActivity('order_manual', 'order', (int) $order['id'], ['code' => $order['code']]);
        flash('success', 'Comanda creada: ' . $order['code']);
        redirect('/admin/comandes/' . $order['id']);
    }

    /** Exportació CSV de comandes i tiquets. */
    public function export(): void
    {
        Auth::requireLogin();
        $rows = Db::all(
            'SELECT o.code, o.name, o.email, o.phone, o.status, o.payment_method, o.total_cents, o.created_at, o.paid_at,
                    oi.name AS item, oi.qty, oi.unit_price_cents
             FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
             ORDER BY o.created_at DESC'
        );
        $this->csv('comandes-' . date('Y-m-d') . '.csv', [
            'Comanda', 'Nom', 'Correu', 'Telèfon', 'Estat', 'Mètode', 'Total (€)', 'Data', 'Pagament', 'Article', 'Unitats', 'Preu unitat (€)',
        ], array_map(fn ($r) => [
            $r['code'], $r['name'], $r['email'], $r['phone'], $r['status'], $r['payment_method'],
            number_format(((int) $r['total_cents']) / 100, 2, ',', ''), $r['created_at'], $r['paid_at'],
            $r['item'], $r['qty'], $r['unit_price_cents'] !== null ? number_format(((int) $r['unit_price_cents']) / 100, 2, ',', '') : '',
        ], $rows));
    }

    /** Envia un CSV al navegador. */
    private function csv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM per a Excel
        fputcsv($out, $header, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
