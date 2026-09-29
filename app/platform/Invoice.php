<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Pdf;
use Cros\Core\Settings;

/**
 * Les factures **de la plataforma** als seus clients.
 *
 * Porten les dades fiscals de qui manté la plataforma i la seva numeració, i
 * són a la base de dades de la plataforma. Les que emet cada cros als seus
 * participants són una altra cosa, viuen en una altra banda i les fa
 * {@see \Cros\Models\Billing}. Els dos sistemes no comparteixen ni taula, ni
 * numeració, ni dades: aquí no hi ha cap dada de cap client, i allà no n'hi ha
 * cap de la plataforma.
 */
class Invoice
{
    /** Els camps fiscals que una factura necessita sí o sí. */
    private const REQUIRED = [
        'platform_billing_entity', 'platform_billing_nif', 'platform_billing_address',
        'platform_billing_town', 'platform_billing_postcode',
    ];

    /**
     * Les dades fiscals de la plataforma.
     * @return array<string,string>
     */
    public static function issuer(): array
    {
        return [
            'entity' => trim((string) Settings::get('platform_billing_entity', ''))
                ?: trim((string) Settings::get('platform_legal_entity', Settings::get('site_name', ''))),
            'nif' => trim((string) Settings::get('platform_billing_nif', Settings::get('platform_legal_nif', ''))),
            'address' => trim((string) Settings::get('platform_billing_address', '')),
            'postcode' => trim((string) Settings::get('platform_billing_postcode', '')),
            'town' => trim((string) Settings::get('platform_billing_town', '')),
            'province' => trim((string) Settings::get('platform_billing_province', '')),
            'country' => trim((string) Settings::get('platform_billing_country', 'Espanya')),
            'email' => trim((string) Settings::get('platform_billing_email', Platform::notifyEmail())),
            'phone' => trim((string) Settings::get('platform_billing_phone', '')),
            'notes' => trim((string) Settings::get('platform_billing_notes', '')),
            'tax_note' => trim((string) Settings::get('platform_billing_tax_note', '')),
        ];
    }

    public static function complete(): bool
    {
        foreach (self::REQUIRED as $key) {
            if (trim((string) Settings::get($key, '')) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<int,string> */
    public static function missing(): array
    {
        $labels = [
            'platform_billing_entity' => 'el nom fiscal',
            'platform_billing_nif' => 'el NIF',
            'platform_billing_address' => 'l\'adreça',
            'platform_billing_town' => 'la població',
            'platform_billing_postcode' => 'el codi postal',
        ];
        $missing = [];
        foreach (self::REQUIRED as $key) {
            if (trim((string) Settings::get($key, '')) === '') {
                $missing[] = $labels[$key];
            }
        }

        return $missing;
    }

    public static function series(): string
    {
        $series = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) Settings::get('platform_billing_series', '')) ?? '');

        return $series !== '' ? mb_substr($series, 0, 10) : 'A';
    }

    public static function forPayment(int $paymentId): ?array
    {
        return Db::one('SELECT * FROM platform_invoices WHERE payment_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $paymentId]);
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM platform_invoices WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 200): array
    {
        return Db::all(
            'SELECT i.*, p.code AS payment_code, p.slug
             FROM platform_invoices i LEFT JOIN platform_payments p ON p.id = i.payment_id
             ORDER BY i.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /** Emet la factura d'un cobrament. Si ja en tenia, es queda la que hi ha. */
    public static function issue(array $charge): ?array
    {
        $existing = self::forPayment((int) $charge['id']);
        if ($existing) {
            return $existing;
        }
        if ((string) $charge['status'] !== 'paid') {
            return null;
        }
        $series = self::series();
        $year = (int) date('Y');
        $number = self::nextNumber($series, $year);
        $address = trim(implode(', ', array_filter([
            (string) ($charge['payer_address'] ?? ''),
            trim(((string) ($charge['payer_postcode'] ?? '')) . ' ' . ((string) ($charge['payer_town'] ?? ''))),
        ])));

        $id = Db::insert('platform_invoices', [
            'payment_id' => (int) $charge['id'],
            'series' => $series,
            'number' => $number,
            'full_number' => $series . '-' . $year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'issued_on' => date('Y-m-d'),
            'issuer' => json_encode(self::issuer(), JSON_UNESCAPED_UNICODE),
            'customer_name' => mb_substr((string) $charge['payer_name'], 0, 190),
            'customer_nif' => mb_substr((string) ($charge['payer_nif'] ?? ''), 0, 30) ?: null,
            'customer_address' => mb_substr($address, 0, 255) ?: null,
            'customer_email' => mb_substr((string) $charge['payer_email'], 0, 190),
            'subtotal_cents' => (int) $charge['subtotal_cents'],
            'tax_rate' => (float) $charge['tax_rate'],
            'tax_cents' => (int) $charge['tax_cents'],
            'irpf_rate' => (float) ($charge['irpf_rate'] ?? 0),
            'irpf_cents' => (int) ($charge['irpf_cents'] ?? 0),
            'total_cents' => (int) $charge['total_cents'],
            'currency' => (string) $charge['currency'],
            'notes' => mb_substr(trim((string) Settings::get('platform_billing_notes', '')), 0, 500) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::find($id);
    }

    /** El número següent d'una sèrie i un any, sense forats. */
    public static function nextNumber(string $series, int $year): int
    {
        $sql = Db::driver() === 'sqlite'
            ? 'INSERT INTO platform_invoice_counters (series, year, next_number) VALUES (:s, :y, 2)
               ON CONFLICT(series, year) DO UPDATE SET next_number = next_number + 1'
            : 'INSERT INTO platform_invoice_counters (series, year, next_number) VALUES (:s, :y, 2)
               ON DUPLICATE KEY UPDATE next_number = next_number + 1';
        Db::q($sql, ['s' => $series, 'y' => $year]);
        $next = (int) Db::val('SELECT next_number FROM platform_invoice_counters WHERE series = :s AND year = :y',
            ['s' => $series, 'y' => $year], 2);

        return max(1, $next - 1);
    }

    /** La factura en PDF. */
    public static function pdf(array $invoice): string
    {
        $issuer = json_decode((string) ($invoice['issuer'] ?? ''), true);
        $issuer = is_array($issuer) ? $issuer : self::issuer();
        $charge = Charge::find((int) $invoice['payment_id']) ?? [];

        $pdf = new Pdf(['title' => 'Factura ' . $invoice['full_number'], 'author' => (string) $issuer['entity']]);
        $pdf->addPage('a4');
        $colour = (string) Settings::get('color_primary', '#1f4f5f');
        $x = 18.0;
        $right = 192.0;

        $pdf->setColorHex($colour);
        $pdf->setFont('helvetica-bold', 16);
        $pdf->text($x, 22, (string) $issuer['entity']);
        $pdf->setColor(70, 80, 74);
        $pdf->setFont('helvetica', 9);
        $y = 28.0;
        $town = trim(trim((string) $issuer['postcode'] . ' ' . (string) $issuer['town'])
            . ((string) $issuer['province'] !== '' ? ' (' . $issuer['province'] . ')' : ''));
        foreach (array_values(array_filter([
            (string) $issuer['nif'] !== '' ? 'NIF ' . $issuer['nif'] : '',
            (string) $issuer['address'],
            $town,
            (string) $issuer['country'],
            trim(implode(' · ', array_filter([(string) $issuer['email'], (string) $issuer['phone']]))),
        ], static fn (string $line): bool => trim($line) !== '')) as $line) {
            $pdf->text($x, $y, $line);
            $y += 4.4;
        }

        $pdf->setColorHex($colour);
        $pdf->setFont('helvetica-bold', 20);
        $pdf->text($right, 22, 'FACTURA', ['align' => 'right']);
        $pdf->setColor(40, 50, 44);
        $pdf->setFont('helvetica', 10);
        $pdf->text($right, 29, 'Número: ' . $invoice['full_number'], ['align' => 'right']);
        $pdf->text($right, 34, 'Data: ' . ca_date((string) $invoice['issued_on']), ['align' => 'right']);

        $y = max($y, 40.0) + 6;
        $pdf->setStrokeColor(210, 220, 212);
        $pdf->line($x, $y, $right, $y, 0.4);
        $y += 8;

        $pdf->setColor(90, 100, 94);
        $pdf->setFont('helvetica', 9);
        $pdf->text($x, $y, 'DADES DEL CLIENT');
        $y += 5.5;
        $pdf->setColor(25, 35, 29);
        $pdf->setFont('helvetica-bold', 11);
        $pdf->text($x, $y, (string) $invoice['customer_name']);
        $y += 5;
        $pdf->setFont('helvetica', 9.5);
        $pdf->setColor(60, 70, 64);
        foreach (array_filter([
            (string) ($invoice['customer_nif'] ?? ''),
            (string) ($invoice['customer_address'] ?? ''),
            (string) ($invoice['customer_email'] ?? ''),
        ]) as $line) {
            $pdf->text($x, $y, $line);
            $y += 4.6;
        }

        $y += 6;
        $pdf->setColorHex($colour);
        $pdf->rect($x, $y - 5, $right - $x, 7.5, 'F');
        $pdf->setColor(255, 255, 255);
        $pdf->setFont('helvetica-bold', 9);
        $pdf->text($x + 3, $y, 'Concepte');
        $pdf->text($right - 3, $y, 'Import', ['align' => 'right']);
        $y += 9;

        $pdf->setColor(30, 40, 34);
        $pdf->setFont('helvetica', 9.5);
        $pdf->text($x + 3, $y, (string) ($charge['description'] ?? 'Servei de la plataforma'), ['max_width' => 130]);
        $pdf->text($right - 3, $y, money((int) $invoice['total_cents']), ['align' => 'right']);
        $y += 8;

        $pdf->setStrokeColor(210, 220, 212);
        $pdf->line($x + 100, $y, $right, $y, 0.3);
        $y += 6;

        $rate = (float) $invoice['tax_rate'];
        $irpfRate = (float) ($invoice['irpf_rate'] ?? 0);
        $percent = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . ' %';
        $pdf->setFont('helvetica', 9.5);
        $pdf->setColor(60, 70, 64);
        if ($rate > 0 || $irpfRate > 0) {
            $pdf->text($x + 140, $y, 'Base imposable', ['align' => 'right']);
            $pdf->text($right - 3, $y, money((int) $invoice['subtotal_cents']), ['align' => 'right']);
            $y += 5.4;
        }
        if ($rate > 0) {
            $pdf->text($x + 140, $y, 'IVA ' . $percent($rate), ['align' => 'right']);
            $pdf->text($right - 3, $y, money((int) $invoice['tax_cents']), ['align' => 'right']);
            $y += 5.4;
        }
        if ($irpfRate > 0) {
            // La retenció es resta: el client la ingressa a Hisenda en nom
            // nostre i per això no ens la paga a nosaltres.
            $pdf->text($x + 140, $y, 'Retenció IRPF ' . $percent($irpfRate), ['align' => 'right']);
            $pdf->text($right - 3, $y, '−' . money((int) $invoice['irpf_cents']), ['align' => 'right']);
            $y += 5.4;
        }
        $pdf->setFont('helvetica-bold', 12);
        $pdf->setColorHex($colour);
        $pdf->text($x + 140, $y + 1, 'Total', ['align' => 'right']);
        $pdf->text($right - 3, $y + 1, money((int) $invoice['total_cents']), ['align' => 'right']);
        $y += 12;

        $pdf->setFont('helvetica', 9);
        $pdf->setColor(90, 100, 94);
        foreach (array_values(array_filter([
            $rate <= 0 ? (string) ($issuer['tax_note'] ?? '') : '',
            $irpfRate > 0 ? 'La retenció d\'IRPF l\'ingressa el client a Hisenda en nom de qui emet aquesta factura.' : '',
            !empty($charge['paid_at']) ? 'Pagada el ' . ca_date(substr((string) $charge['paid_at'], 0, 10)) . ' amb targeta.' : '',
            (string) ($invoice['notes'] ?? ''),
            'Referència del pagament: ' . (string) ($charge['code'] ?? ''),
        ], static fn (string $line): bool => trim($line) !== '')) as $line) {
            $y = $pdf->textBlock($x, $y, $right - $x, $line) + 1.5;
        }

        return $pdf->output();
    }

    public static function filename(array $invoice): string
    {
        return 'factura-' . strtolower((string) $invoice['full_number']) . '.pdf';
    }
}
