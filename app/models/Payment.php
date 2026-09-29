<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Db;
use Cros\Payments\Gateways;

/**
 * Un cobrament del cros: tant els tiquets del punt de recàrrega com les
 * inscripcions.
 *
 * Tot el que es cobra passa per aquí. Abans cada cosa se les entenia pel seu
 * compte amb Stripe; ara la passarel·la és un mòdul que es tria i el que
 * queda apuntat és sempre igual, que és el que després permet emetre'n un
 * rebut o una factura sense mirar d'on venia.
 *
 * Els preus s'escriuen **amb l'impost inclòs**, que és com els diu tothom qui
 * ven un tiquet d'esmorzar. De cara al document, l'impost se'n desglossa cap
 * enrere.
 */
class Payment
{
    public const STATUSES = [
        'pending' => 'Pendent',
        'paid' => 'Cobrat',
        'failed' => 'Fallit',
        'cancelled' => 'Anul·lat',
        'refunded' => 'Retornat',
    ];

    public const CONCEPTS = [
        'order' => 'Tiquets',
        'registration' => 'Inscripció',
    ];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM payments WHERE id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        return Db::one('SELECT * FROM payments WHERE token = :token', ['token' => $token]);
    }

    public static function findByCode(string $code): ?array
    {
        return Db::one('SELECT * FROM payments WHERE code = :code', ['code' => mb_strtoupper(trim($code))]);
    }

    public static function findByRef(string $ref): ?array
    {
        return $ref === '' ? null : Db::one('SELECT * FROM payments WHERE gateway_ref = :ref', ['ref' => $ref]);
    }

    /** El pagament d'una comanda o d'una inscripció. */
    public static function forConcept(string $concept, int $referenceId): ?array
    {
        return Db::one(
            'SELECT * FROM payments WHERE concept = :c AND reference_id = :id ORDER BY id DESC LIMIT 1',
            ['c' => $concept, 'id' => $referenceId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $paymentId): array
    {
        return Db::all('SELECT * FROM payment_items WHERE payment_id = :id ORDER BY id ASC', ['id' => $paymentId]);
    }

    /**
     * Crea un cobrament pendent.
     *
     * @param array<string,mixed> $data
     * @param array<int,array{description:string,qty:int,unit_price_cents:int}> $items
     */
    public static function create(array $data, array $items): array
    {
        $total = 0;
        $clean = [];
        foreach ($items as $item) {
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $unit = max(0, (int) ($item['unit_price_cents'] ?? 0));
            $subtotal = $unit * $qty;
            $total += $subtotal;
            $clean[] = [
                'description' => mb_substr(trim((string) $item['description']), 0, 190),
                'qty' => $qty,
                'unit_price_cents' => $unit,
                'subtotal_cents' => $subtotal,
            ];
        }

        $rate = self::taxRate();
        $tax = self::taxOf($total, $rate);
        $id = Db::insert('payments', [
            'code' => self::code(),
            'token' => bin2hex(random_bytes(24)),
            'concept' => isset(self::CONCEPTS[$data['concept'] ?? '']) ? (string) $data['concept'] : 'order',
            'reference_id' => isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            'payer_name' => mb_substr(trim((string) ($data['payer_name'] ?? '')), 0, 190),
            'payer_email' => mb_strtolower(mb_substr(trim((string) ($data['payer_email'] ?? '')), 0, 190)),
            'payer_phone' => mb_substr(trim((string) ($data['payer_phone'] ?? '')), 0, 40) ?: null,
            'payer_nif' => mb_substr(trim((string) ($data['payer_nif'] ?? '')), 0, 30) ?: null,
            'payer_address' => mb_substr(trim((string) ($data['payer_address'] ?? '')), 0, 255) ?: null,
            'subtotal_cents' => $total - $tax,
            'tax_rate' => $rate,
            'tax_cents' => $tax,
            'total_cents' => $total,
            'currency' => (string) setting('payments_currency', 'EUR'),
            'status' => 'pending',
            'document_type' => Billing::documentType(),
            'ip' => client_ip(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        foreach ($clean as $item) {
            Db::insert('payment_items', $item + ['payment_id' => $id]);
        }

        return self::find($id) ?? [];
    }

    /** Apunta quina passarel·la se n'ocupa i amb quina referència. */
    public static function attach(int $id, string $gateway, string $ref): void
    {
        Db::update('payments', [
            'gateway' => $gateway,
            'gateway_ref' => $ref !== '' ? mb_substr($ref, 0, 190) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /**
     * Dona el cobrament per bo.
     *
     * Es pot cridar dues vegades sense fer cap mal: el navegador i l'avís del
     * servidor de la passarel·la solen arribar tots dos, i de vegades alhora.
     */
    public static function markPaid(array $payment, string $gatewayPayment = '', string $detail = ''): array
    {
        if ((string) $payment['status'] === 'paid') {
            return $payment;
        }
        Db::update('payments', [
            'status' => 'paid',
            'gateway_payment' => $gatewayPayment !== '' ? mb_substr($gatewayPayment, 0, 190) : ($payment['gateway_payment'] ?? null),
            'gateway_detail' => $detail !== '' ? mb_substr($detail, 0, 255) : ($payment['gateway_detail'] ?? null),
            'paid_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND status <> :paid', ['id' => $payment['id'], 'paid' => 'paid']);

        $payment = self::find((int) $payment['id']) ?? $payment;
        log_line('payments', 'Cobrament fet', [
            'code' => $payment['code'], 'total' => $payment['total_cents'], 'gateway' => $payment['gateway'],
        ]);

        // El document primer: si després falla res de la comanda o de la
        // inscripció, el rebut de qui ha pagat ja existeix.
        try {
            Billing::issue($payment);
        } catch (\Throwable $e) {
            log_line('payments', 'No s\'ha pogut emetre el document', ['code' => $payment['code'], 'error' => $e->getMessage()]);
        }
        self::deliver($payment);

        return self::find((int) $payment['id']) ?? $payment;
    }

    /** Fa el que toqui amb el que s'acaba de pagar. */
    private static function deliver(array $payment): void
    {
        try {
            if ((string) $payment['concept'] === 'order' && !empty($payment['reference_id'])) {
                $order = Order::find((int) $payment['reference_id']);
                if ($order) {
                    Order::markPaid($order, [
                        'payment_intent' => (string) ($payment['gateway_payment'] ?? '') ?: null,
                        'session_id' => (string) ($payment['gateway_ref'] ?? '') ?: null,
                    ]);
                }
            }
            if ((string) $payment['concept'] === 'registration' && !empty($payment['reference_id'])) {
                Registration::confirmPayment((int) $payment['reference_id'], $payment);
            }
        } catch (\Throwable $e) {
            log_line('payments', 'Error entregant el que s\'ha pagat', [
                'code' => $payment['code'], 'error' => $e->getMessage(),
            ]);
        }
    }

    public static function markFailed(array $payment, string $detail = ''): void
    {
        if (in_array((string) $payment['status'], ['paid', 'refunded'], true)) {
            return;
        }
        Db::update('payments', [
            'status' => 'failed',
            'gateway_detail' => mb_substr($detail, 0, 255) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $payment['id']]);
    }

    public static function cancel(array $payment, string $detail = ''): void
    {
        if ((string) $payment['status'] !== 'pending') {
            return;
        }
        Db::update('payments', [
            'status' => 'cancelled',
            'gateway_detail' => mb_substr($detail, 0, 255) ?: null,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $payment['id']]);
    }

    /**
     * Torna els diners per la mateixa passarel·la per on van entrar.
     * @return array{ok:bool,error:string}
     */
    public static function refund(array $payment, int $cents = 0): array
    {
        if ((string) $payment['status'] !== 'paid') {
            return ['ok' => false, 'error' => 'Aquest cobrament no està pagat.'];
        }
        $gateway = (string) ($payment['gateway'] ?? '');
        if ($gateway === '' || !isset(Gateways::MODULES[$gateway])) {
            return ['ok' => false, 'error' => 'No se sap per quina passarel·la es va cobrar.'];
        }
        $result = Gateways::module($gateway)::refund($payment, $cents);
        if (!$result['ok']) {
            return $result;
        }
        $refunded = $cents > 0 ? min($cents, (int) $payment['total_cents']) : (int) $payment['total_cents'];
        Db::update('payments', [
            'status' => 'refunded',
            'refunded_cents' => $refunded,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $payment['id']]);
        log_line('payments', 'Diners retornats', ['code' => $payment['code'], 'cents' => $refunded]);

        return ['ok' => true, 'error' => ''];
    }

    /**
     * Els cobraments d'una adreça, per ensenyar-los a qui els ha fet.
     * @return array<int,array<string,mixed>>
     */
    public static function forEmail(string $email, int $limit = 100): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return [];
        }

        return Db::all(
            'SELECT * FROM payments WHERE LOWER(payer_email) = :email AND status IN (\'paid\', \'refunded\')
             ORDER BY paid_at DESC, id DESC LIMIT ' . max(1, $limit),
            ['email' => $email]
        );
    }

    /**
     * Els cobraments del panell, amb filtres.
     * @return array<int,array<string,mixed>>
     */
    public static function search(array $filters = [], int $limit = 200): array
    {
        $where = [];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        $concept = (string) ($filters['concept'] ?? '');
        if (isset(self::CONCEPTS[$concept])) {
            $where[] = 'concept = :concept';
            $params['concept'] = $concept;
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(code LIKE :q OR payer_name LIKE :q OR payer_email LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return Db::all(
            'SELECT * FROM payments' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY id DESC LIMIT ' . max(1, $limit),
            $params
        );
    }

    /** @return array<string,int> */
    public static function totals(): array
    {
        $row = Db::one(
            "SELECT COUNT(*) AS n,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                    SUM(CASE WHEN status = 'paid' THEN total_cents ELSE 0 END) AS cents,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(refunded_cents) AS refunded
             FROM payments"
        ) ?? [];

        return [
            'count' => (int) ($row['n'] ?? 0),
            'paid' => (int) ($row['paid'] ?? 0),
            'cents' => (int) ($row['cents'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'refunded' => (int) ($row['refunded'] ?? 0),
        ];
    }

    /** L'impost que s'aplica, en tant per cent. */
    public static function taxRate(): float
    {
        return max(0.0, min(100.0, (float) str_replace(',', '.', (string) setting('billing_tax_rate', '0'))));
    }

    /** L'impost que hi ha a dins d'un preu amb l'impost inclòs. */
    public static function taxOf(int $totalCents, float $rate): int
    {
        if ($rate <= 0 || $totalCents <= 0) {
            return 0;
        }

        return $totalCents - (int) round($totalCents / (1 + $rate / 100));
    }

    /** Un codi que es pugui dir per telèfon: P-2026-0147. */
    public static function code(): string
    {
        $year = date('Y');
        $count = (int) Db::val('SELECT COUNT(*) FROM payments WHERE code LIKE :like', ['like' => 'P-' . $year . '-%'], 0);
        do {
            $count++;
            $code = 'P-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        } while (Db::val('SELECT 1 FROM payments WHERE code = :code', ['code' => $code]));

        return $code;
    }
}
