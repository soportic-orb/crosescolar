<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Stripe;
use RuntimeException;

/**
 * Els cobraments **de la plataforma** als seus clients.
 *
 * No s'ha de confondre amb els cobraments que fa cada cros als seus
 * participants: aquells viuen a la base de dades del client, els cobra amb la
 * passarel·la que ell hagi triat i els documenta amb les seves dades fiscals.
 * Aquests els cobra la plataforma amb el seu propi Stripe i els factura amb
 * les seves. Són dos sistemes separats i no s'han de tocar mai.
 */
class Charge
{
    public const STATUSES = [
        'pending' => 'Pendent',
        'paid' => 'Pagat',
        'failed' => 'Fallit',
        'cancelled' => 'Anul·lat',
        'refunded' => 'Retornat',
    ];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM platform_payments WHERE id = :id', ['id' => $id]);
    }

    public static function findByToken(string $token): ?array
    {
        return Db::one('SELECT * FROM platform_payments WHERE token = :token', ['token' => $token]);
    }

    public static function findBySession(string $sessionId): ?array
    {
        return $sessionId === '' ? null
            : Db::one('SELECT * FROM platform_payments WHERE stripe_session_id = :id', ['id' => $sessionId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forInstance(int $instanceId): array
    {
        return Db::all(
            'SELECT * FROM platform_payments WHERE instance_id = :id ORDER BY id DESC',
            ['id' => $instanceId]
        );
    }

    /** Hi ha un cobrament pagat d'activació per a aquesta instància? */
    public static function activationPaid(int $instanceId): bool
    {
        return (bool) Db::val(
            "SELECT 1 FROM platform_payments WHERE instance_id = :id AND concept = 'activation' AND status = 'paid'",
            ['id' => $instanceId]
        );
    }

    /** Un cobrament d'activació començat i encara sense acabar. */
    public static function pendingActivation(int $instanceId): ?array
    {
        return Db::one(
            "SELECT * FROM platform_payments WHERE instance_id = :id AND concept = 'activation'
               AND status = 'pending' ORDER BY id DESC LIMIT 1",
            ['id' => $instanceId]
        );
    }

    /**
     * Prepara el cobrament de l'activació d'una instància.
     * @param array<string,mixed> $instance
     * @param array<string,mixed> $client  dades fiscals de qui paga
     */
    public static function forActivation(array $instance, array $client = []): array
    {
        $existing = self::pendingActivation((int) $instance['id']);
        if ($existing) {
            return $existing;
        }
        // Qui paga decideix si hi ha retenció: només retenen les persones
        // jurídiques. La fitxa del client és qui ho diu, i queda apuntat al
        // cobrament perquè la factura emesa no canviï mai més.
        $record = !empty($instance['client_id']) ? Client::find((int) $instance['client_id']) : null;
        $amounts = Plan::amountsFor($record);

        $id = Db::insert('platform_payments', [
            'code' => self::code(),
            'token' => bin2hex(random_bytes(24)),
            'instance_id' => (int) $instance['id'],
            'client_id' => !empty($instance['client_id']) ? (int) $instance['client_id'] : null,
            'slug' => (string) $instance['slug'],
            'concept' => 'activation',
            'description' => mb_substr(Plan::name() . ' · ' . (string) $instance['site_name'], 0, 190),
            'payer_name' => mb_substr(trim((string) ($client['name'] ?? $instance['site_name'])), 0, 190),
            'payer_email' => mb_strtolower(mb_substr(trim((string) ($client['email'] ?? $instance['admin_email'])), 0, 190)),
            'payer_nif' => mb_substr(trim((string) ($client['nif'] ?? '')), 0, 30) ?: null,
            'payer_address' => mb_substr(trim((string) ($client['address'] ?? '')), 0, 255) ?: null,
            'payer_postcode' => mb_substr(trim((string) ($client['postcode'] ?? '')), 0, 20) ?: null,
            'payer_town' => mb_substr(trim((string) ($client['town'] ?? '')), 0, 120) ?: null,
            'payer_kind' => Client::kindOf($record),
            'subtotal_cents' => $amounts['base'],
            'tax_rate' => $amounts['vat_rate'],
            'tax_cents' => $amounts['vat'],
            'irpf_rate' => $amounts['irpf_rate'],
            'irpf_cents' => $amounts['irpf'],
            'total_cents' => $amounts['total'],
            'currency' => 'EUR',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return self::find($id) ?? [];
    }

    /**
     * Obre la sessió de pagament i torna l'adreça on cal enviar qui paga.
     * Fa servir el Stripe **de la plataforma**.
     */
    public static function checkout(array $charge, string $returnUrl, string $cancelUrl): string
    {
        if (!Stripe::configured()) {
            throw new RuntimeException('La plataforma no té Stripe configurat.');
        }
        $session = Stripe::createCheckoutSession([
            'mode' => 'payment',
            'success_url' => $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'customer_email' => (string) $charge['payer_email'],
            'client_reference_id' => (string) $charge['code'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower((string) $charge['currency']),
                    'unit_amount' => (int) $charge['total_cents'],
                    'product_data' => ['name' => mb_substr((string) $charge['description'], 0, 120)],
                ],
            ]],
            'metadata' => ['charge_id' => (string) $charge['id'], 'charge_code' => (string) $charge['code']],
            'expires_at' => time() + 3600,
        ], 'plat-' . $charge['id'] . '-' . substr((string) $charge['token'], 0, 8));

        Db::update('platform_payments', [
            'stripe_session_id' => (string) ($session['id'] ?? '') ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $charge['id']]);

        return (string) ($session['url'] ?? '');
    }

    /**
     * Prepara un cobrament per pagar-lo sense sortir del web.
     *
     * Torna la clau pública i el secret del PaymentIntent, que és el que
     * necessita Stripe.js per dibuixar el formulari de la targeta. El número
     * de la targeta va directament de qui la teclegi a Stripe: aquí no hi
     * passa mai.
     *
     * @return array{client_secret:string,publishable:string,intent:string}
     */
    public static function intent(array $charge): array
    {
        if (!Stripe::configured()) {
            throw new RuntimeException('La plataforma no té Stripe configurat.');
        }
        $existing = trim((string) ($charge['stripe_payment_intent'] ?? ''));
        $intent = [];
        if ($existing !== '') {
            try {
                $intent = Stripe::retrievePaymentIntent($existing);
            } catch (\Throwable $e) {
                $intent = [];
            }
            // Un que ja s'hagi cobrat o s'hagi cancel·lat no es pot reaprofitar.
            if (in_array((string) ($intent['status'] ?? ''), ['succeeded', 'canceled', ''], true)
                || (int) ($intent['amount'] ?? 0) !== (int) $charge['total_cents']
            ) {
                $intent = [];
            }
        }
        if ($intent === []) {
            $intent = Stripe::createPaymentIntent([
                'amount' => (int) $charge['total_cents'],
                'currency' => strtolower((string) $charge['currency']),
                'description' => mb_substr((string) $charge['description'], 0, 120),
                'receipt_email' => (string) $charge['payer_email'],
                'automatic_payment_methods' => ['enabled' => 'true'],
                'metadata' => ['charge_id' => (string) $charge['id'], 'charge_code' => (string) $charge['code']],
            ], 'plat-pi-' . $charge['id'] . '-' . substr((string) $charge['token'], 0, 8));
            Db::update('platform_payments', [
                'stripe_payment_intent' => (string) ($intent['id'] ?? '') ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $charge['id']]);
        }

        return [
            'client_secret' => (string) ($intent['client_secret'] ?? ''),
            'publishable' => Stripe::publishableKey(),
            'intent' => (string) ($intent['id'] ?? ''),
        ];
    }

    /**
     * Mira com ha acabat el pagament i ho desa.
     *
     * Serveix tant si s'ha pagat a la pàgina de Stripe (una sessió) com si
     * s'ha pagat aquí mateix amb el formulari de la targeta (un PaymentIntent).
     *
     * @return array{status:string,charge:array<string,mixed>}
     */
    public static function confirm(array $charge, string $sessionId = ''): array
    {
        if ((string) $charge['status'] === 'paid') {
            return ['status' => 'paid', 'charge' => $charge];
        }
        $sessionId = $sessionId !== '' ? $sessionId : (string) ($charge['stripe_session_id'] ?? '');
        if ($sessionId === '') {
            return self::confirmIntent($charge);
        }
        try {
            $session = Stripe::retrieveSession($sessionId);
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut comprovar el pagament', [
                'code' => $charge['code'], 'error' => $e->getMessage(),
            ]);

            return ['status' => 'pending', 'charge' => $charge];
        }

        if ((string) ($session['payment_status'] ?? '') === 'paid') {
            $intent = $session['payment_intent'] ?? null;

            return ['status' => 'paid', 'charge' => self::markPaid($charge,
                is_array($intent) ? (string) ($intent['id'] ?? '') : (string) $intent)];
        }
        if ((string) ($session['status'] ?? '') === 'expired') {
            Db::update('platform_payments', [
                'status' => 'failed',
                'detail' => 'La sessió de pagament ha caducat.',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id AND status = :s', ['id' => $charge['id'], 's' => 'pending']);

            return ['status' => 'failed', 'charge' => self::find((int) $charge['id']) ?? $charge];
        }

        return ['status' => 'pending', 'charge' => $charge];
    }

    /**
     * El mateix, però mirant el PaymentIntent del pagament de dins del web.
     * @return array{status:string,charge:array<string,mixed>}
     */
    private static function confirmIntent(array $charge): array
    {
        $id = trim((string) ($charge['stripe_payment_intent'] ?? ''));
        if ($id === '') {
            return ['status' => 'pending', 'charge' => $charge];
        }
        try {
            $intent = Stripe::retrievePaymentIntent($id);
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut comprovar el pagament', [
                'code' => $charge['code'], 'error' => $e->getMessage(),
            ]);

            return ['status' => 'pending', 'charge' => $charge];
        }
        $status = (string) ($intent['status'] ?? '');
        if ($status === 'succeeded') {
            return ['status' => 'paid', 'charge' => self::markPaid($charge, $id)];
        }
        if ($status === 'canceled') {
            Db::update('platform_payments', [
                'status' => 'failed',
                'detail' => 'El pagament s\'ha cancel·lat.',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id AND status = :s', ['id' => $charge['id'], 's' => 'pending']);

            return ['status' => 'failed', 'charge' => self::find((int) $charge['id']) ?? $charge];
        }

        return ['status' => 'pending', 'charge' => $charge];
    }

    /**
     * Dona el cobrament per bo: activa la instància i n'emet la factura.
     * Es pot cridar dues vegades sense fer cap mal.
     */
    public static function markPaid(array $charge, string $intent = '', string $detail = ''): array
    {
        if ((string) $charge['status'] === 'paid') {
            return $charge;
        }
        Db::update('platform_payments', [
            'status' => 'paid',
            'stripe_payment_intent' => $intent !== '' ? $intent : ($charge['stripe_payment_intent'] ?? null),
            'detail' => $detail !== '' ? mb_substr($detail, 0, 255) : ($charge['detail'] ?? null),
            'paid_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND status <> :paid', ['id' => $charge['id'], 'paid' => 'paid']);

        $charge = self::find((int) $charge['id']) ?? $charge;
        if ((string) $charge['concept'] === 'activation' && !empty($charge['instance_id'])) {
            Plan::activate((int) $charge['instance_id']);
        }
        try {
            Invoice::issue($charge);
        } catch (\Throwable $e) {
            log_line('platform', 'No s\'ha pogut emetre la factura', [
                'code' => $charge['code'], 'error' => $e->getMessage(),
            ]);
        }
        Platform::log('payment_paid', 'payment', (int) $charge['id'], [
            'code' => $charge['code'], 'total' => $charge['total_cents'],
        ]);

        return self::find((int) $charge['id']) ?? $charge;
    }

    public static function cancel(array $charge, string $detail = ''): void
    {
        if ((string) $charge['status'] !== 'pending') {
            return;
        }
        Db::update('platform_payments', [
            'status' => 'cancelled',
            'detail' => mb_substr($detail, 0, 255) ?: null,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $charge['id']]);
    }

    /** @return array{ok:bool,error:string} */
    public static function refund(array $charge, int $cents = 0): array
    {
        if ((string) $charge['status'] !== 'paid') {
            return ['ok' => false, 'error' => 'Aquest cobrament no està pagat.'];
        }
        $intent = (string) ($charge['stripe_payment_intent'] ?? '');
        if ($intent === '') {
            return ['ok' => false, 'error' => 'No hi ha cap cobrament de Stripe associat.'];
        }
        try {
            Stripe::refund($intent, $cents > 0 ? $cents : null);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        Db::update('platform_payments', [
            'status' => 'refunded',
            'refunded_cents' => $cents > 0 ? min($cents, (int) $charge['total_cents']) : (int) $charge['total_cents'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $charge['id']]);

        return ['ok' => true, 'error' => ''];
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
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(code LIKE :q OR payer_name LIKE :q OR payer_email LIKE :q OR slug LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return Db::all(
            'SELECT * FROM platform_payments' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY id DESC LIMIT ' . max(1, $limit),
            $params
        );
    }

    /** @return array<string,int> */
    public static function totals(): array
    {
        try {
            $row = Db::one(
                "SELECT COUNT(*) AS n,
                        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                        SUM(CASE WHEN status = 'paid' THEN total_cents ELSE 0 END) AS cents,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending
                 FROM platform_payments"
            ) ?? [];
        } catch (\Throwable $e) {
            $row = [];
        }

        return [
            'count' => (int) ($row['n'] ?? 0),
            'paid' => (int) ($row['paid'] ?? 0),
            'cents' => (int) ($row['cents'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }

    /** Un codi que es pugui dir per telèfon: A-2026-0021. */
    public static function code(): string
    {
        $year = date('Y');
        $count = (int) Db::val('SELECT COUNT(*) FROM platform_payments WHERE code LIKE :like',
            ['like' => 'A-' . $year . '-%'], 0);
        do {
            $count++;
            $code = 'A-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
        } while (Db::val('SELECT 1 FROM platform_payments WHERE code = :code', ['code' => $code]));

        return $code;
    }
}
