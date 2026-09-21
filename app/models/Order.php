<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Auth;
use Cros\Core\Db;
use Cros\Core\Mailer;

/** Comandes de tiquets del punt de recàrrega. */
class Order
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        return Db::one('SELECT * FROM orders WHERE token = :token', ['token' => $token]);
    }

    public static function findByCode(string $code): ?array
    {
        return Db::one('SELECT * FROM orders WHERE code = :code', ['code' => mb_strtoupper($code)]);
    }

    public static function findBySession(string $sessionId): ?array
    {
        return Db::one('SELECT * FROM orders WHERE stripe_session_id = :id', ['id' => $sessionId]);
    }

    /** Comanda a partir del codi i el correu (consulta pública). */
    public static function findForLookup(string $code, string $email): ?array
    {
        return Db::one(
            'SELECT * FROM orders WHERE code = :code AND LOWER(email) = :email',
            ['code' => mb_strtoupper(trim($code)), 'email' => mb_strtolower(trim($email))]
        );
    }

    public static function items(int $orderId): array
    {
        return Db::all('SELECT * FROM order_items WHERE order_id = :id ORDER BY id ASC', ['id' => $orderId]);
    }

    public static function tickets(int $orderId): array
    {
        return Db::all(
            'SELECT t.*, tt.name AS type_name, tt.description AS type_description
             FROM tickets t LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
             WHERE t.order_id = :id ORDER BY t.id ASC',
            ['id' => $orderId]
        );
    }

    /**
     * Crea una comanda pendent amb els seus articles.
     * @param array<int,int> $quantities  [ticket_type_id => quantitat]
     */
    public static function create(array $buyer, array $quantities, string $paymentMethod = 'stripe'): array
    {
        $items = [];
        $total = 0;
        foreach ($quantities as $typeId => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) {
                continue;
            }
            $type = TicketType::find((int) $typeId);
            if (!$type || (int) $type['active'] !== 1) {
                throw new \RuntimeException('Un dels tiquets seleccionats ja no està disponible.');
            }
            $max = max(1, (int) $type['max_per_order']);
            if ($qty > $max) {
                throw new \RuntimeException('Només es poden comprar ' . $max . ' unitats de «' . $type['name'] . '» per comanda.');
            }
            if (!TicketType::hasStock($type, $qty)) {
                throw new \RuntimeException('No queden prou existències de «' . $type['name'] . '».');
            }
            $subtotal = (int) $type['price_cents'] * $qty;
            $total += $subtotal;
            $items[] = [
                'ticket_type_id' => (int) $type['id'],
                'name' => $type['name'],
                'unit_price_cents' => (int) $type['price_cents'],
                'qty' => $qty,
                'subtotal_cents' => $subtotal,
            ];
        }
        if (!$items) {
            throw new \RuntimeException('Cal seleccionar com a mínim un tiquet.');
        }

        $orderId = Db::transaction(function () use ($buyer, $items, $total, $paymentMethod) {
            $id = Db::insert('orders', [
                'code' => self::generateCode(),
                'token' => random_token(24),
                'name' => $buyer['name'],
                'email' => mb_strtolower($buyer['email']),
                'phone' => $buyer['phone'] ?? null,
                'notes' => $buyer['notes'] ?? null,
                'total_cents' => $total,
                'currency' => (string) setting('payments_currency', 'EUR'),
                'status' => $total === 0 ? 'paid' : 'pending',
                'payment_method' => $paymentMethod,
                'paid_at' => $total === 0 ? date('Y-m-d H:i:s') : null,
                'ip' => client_ip(),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            foreach ($items as $item) {
                Db::insert('order_items', array_merge($item, ['order_id' => $id]));
            }
            return $id;
        });

        $order = self::find($orderId);
        if ($order && $order['status'] === 'paid') {
            self::generateTickets($order);
        }
        return $order ?? [];
    }

    /** Marca una comanda com a pagada (idempotent) i genera els tiquets. */
    public static function markPaid(array $order, array $data = []): array
    {
        if ($order['status'] === 'paid') {
            return $order;
        }
        Db::update('orders', array_filter([
            'status' => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'stripe_payment_intent' => $data['payment_intent'] ?? $order['stripe_payment_intent'] ?? null,
            'stripe_session_id' => $data['session_id'] ?? $order['stripe_session_id'] ?? null,
        ], fn ($value) => $value !== null), 'id = :id', ['id' => $order['id']]);

        $order = self::find((int) $order['id']) ?? $order;
        self::generateTickets($order);
        self::sendConfirmation($order);
        log_line('orders', 'Comanda pagada', ['code' => $order['code'], 'total' => $order['total_cents']]);
        return $order;
    }

    /** Cancel·la una comanda. */
    public static function cancel(array $order, string $reason = ''): void
    {
        Db::update('orders', [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'notes' => trim((string) ($order['notes'] ?? '') . ($reason ? "\n" . $reason : '')),
        ], 'id = :id', ['id' => $order['id']]);
        Db::update('tickets', ['status' => 'void'], 'order_id = :id AND status = \'valid\'', ['id' => $order['id']]);
    }

    /** Genera un tiquet per cada unitat comprada (idempotent). */
    public static function generateTickets(array $order): array
    {
        $existing = (int) Db::val('SELECT COUNT(*) FROM tickets WHERE order_id = :id', ['id' => $order['id']], 0);
        if ($existing > 0) {
            return self::tickets((int) $order['id']);
        }
        foreach (self::items((int) $order['id']) as $item) {
            for ($i = 0; $i < (int) $item['qty']; $i++) {
                Db::insert('tickets', [
                    'order_id' => (int) $order['id'],
                    'order_item_id' => (int) $item['id'],
                    'ticket_type_id' => $item['ticket_type_id'] !== null ? (int) $item['ticket_type_id'] : null,
                    'code' => self::generateTicketCode(),
                    'status' => 'valid',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        return self::tickets((int) $order['id']);
    }

    /** Envia el correu amb els tiquets. */
    public static function sendConfirmation(array $order): bool
    {
        $tickets = self::tickets((int) $order['id']);
        $sent = Mailer::sendTemplate(
            $order['email'],
            'Els teus tiquets — ' . setting('site_name', 'Cros Escolar La Granada'),
            'order-confirmation',
            [
                'order' => $order,
                'items' => self::items((int) $order['id']),
                'tickets' => $tickets,
            ]
        );
        $notify = (string) setting('mail_admin_notify', '');
        if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            Mailer::sendTemplate($notify, 'Nova comanda ' . $order['code'], 'order-admin', [
                'order' => $order,
                'items' => self::items((int) $order['id']),
            ]);
        }
        return $sent;
    }

    /** URL pública amb els tiquets de la comanda. */
    public static function ticketsUrl(array $order): string
    {
        return url('/tiquets/' . $order['token']);
    }

    public static function generateCode(): string
    {
        do {
            $code = 'CR' . date('y') . '-' . random_code(5);
        } while (Db::val('SELECT 1 FROM orders WHERE code = :code', ['code' => $code]));
        return $code;
    }

    public static function generateTicketCode(): string
    {
        do {
            $code = 'T' . random_code(9);
        } while (Db::val('SELECT 1 FROM tickets WHERE code = :code', ['code' => $code]));
        return $code;
    }

    /** Estadístiques per al tauler. */
    public static function stats(): array
    {
        $paid = Db::one('SELECT COUNT(*) AS orders, COALESCE(SUM(total_cents),0) AS revenue FROM orders WHERE status = \'paid\'') ?: [];
        return [
            'orders_paid' => (int) ($paid['orders'] ?? 0),
            'revenue_cents' => (int) ($paid['revenue'] ?? 0),
            'orders_pending' => (int) Db::val('SELECT COUNT(*) FROM orders WHERE status = \'pending\'', [], 0),
            'tickets_total' => (int) Db::val('SELECT COUNT(*) FROM tickets t JOIN orders o ON o.id = t.order_id WHERE o.status = \'paid\'', [], 0),
            'tickets_used' => (int) Db::val('SELECT COUNT(*) FROM tickets WHERE status = \'used\'', [], 0),
            'registrations' => (int) Db::val('SELECT COUNT(*) FROM registrations r WHERE ' . Registration::ACTIVE, [], 0),
        ];
    }
}
