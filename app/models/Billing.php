<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Db;
use Cros\Core\Pdf;
use Cros\Core\Settings;
use RuntimeException;

/**
 * Els rebuts i les factures que emet **el cros**, a nom seu.
 *
 * Això no té res a veure amb el que factura la plataforma per allotjar-li el
 * web: són dos sistemes separats a posta, cadascun amb les seves dades
 * fiscals, la seva numeració i la seva base de dades. Aquí no hi ha —ni hi ha
 * d'haver mai— cap dada de qui manté la plataforma: qui compra un tiquet o
 * s'inscriu a la cursa tracta amb l'entitat organitzadora i amb ningú més.
 *
 * Un rebut i una factura es fan igual; la diferència és què exigeix cadascun.
 * Per emetre **factura** cal que l'entitat tingui totes les dades fiscals
 * posades, perquè una factura sense NIF ni adreça no serveix per a res. El
 * rebut, en canvi, sempre es pot emetre.
 */
class Billing
{
    public const TYPES = ['receipt' => 'Rebut', 'invoice' => 'Factura'];

    /** Els camps fiscals que una factura necessita sí o sí. */
    private const REQUIRED = ['billing_entity', 'billing_nif', 'billing_address', 'billing_town', 'billing_postcode'];

    /**
     * Les dades fiscals de l'entitat organitzadora.
     * @return array<string,string>
     */
    public static function issuer(): array
    {
        $fallbackName = (string) Settings::get('legal_entity', Settings::get('site_name', ''));

        return [
            'entity' => trim((string) Settings::get('billing_entity', '')) ?: trim($fallbackName),
            'nif' => trim((string) Settings::get('billing_nif', '')),
            'address' => trim((string) Settings::get('billing_address', '')),
            'postcode' => trim((string) Settings::get('billing_postcode', '')),
            'town' => trim((string) Settings::get('billing_town', '')),
            'province' => trim((string) Settings::get('billing_province', '')),
            'country' => trim((string) Settings::get('billing_country', 'Espanya')),
            'email' => trim((string) Settings::get('billing_email', Settings::get('contact_email', ''))),
            'phone' => trim((string) Settings::get('billing_phone', '')),
            'notes' => trim((string) Settings::get('billing_notes', '')),
            'tax_note' => trim((string) Settings::get('billing_tax_note', '')),
        ];
    }

    /** Hi ha prou dades fiscals per poder emetre factura? */
    public static function complete(): bool
    {
        foreach (self::REQUIRED as $key) {
            if (trim((string) Settings::get($key, '')) === '') {
                return false;
            }
        }

        return true;
    }

    /** Quins camps falten, per poder-ho dir al panell. @return array<int,string> */
    public static function missing(): array
    {
        $labels = [
            'billing_entity' => 'el nom fiscal de l\'entitat',
            'billing_nif' => 'el NIF',
            'billing_address' => 'l\'adreça',
            'billing_town' => 'la població',
            'billing_postcode' => 'el codi postal',
        ];
        $missing = [];
        foreach (self::REQUIRED as $key) {
            if (trim((string) Settings::get($key, '')) === '') {
                $missing[] = $labels[$key];
            }
        }

        return $missing;
    }

    /**
     * Què s'emet de cada venda.
     *
     * Si s'ha demanat factura però falten dades fiscals, se n'emet un rebut:
     * val més un rebut correcte que una factura que no ho és.
     */
    public static function documentType(): string
    {
        $wanted = (string) Settings::get('billing_document', 'receipt');

        return $wanted === 'invoice' && self::complete() ? 'invoice' : 'receipt';
    }

    /** La sèrie que es fa servir per a cada mena de document. */
    public static function series(string $type): string
    {
        $key = $type === 'invoice' ? 'billing_series_invoice' : 'billing_series_receipt';
        $series = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) Settings::get($key, '')) ?? '');

        return $series !== '' ? mb_substr($series, 0, 10) : ($type === 'invoice' ? 'F' : 'R');
    }

    public static function document(int $paymentId): ?array
    {
        return Db::one('SELECT * FROM billing_documents WHERE payment_id = :id ORDER BY id DESC LIMIT 1',
            ['id' => $paymentId]);
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM billing_documents WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 200): array
    {
        return Db::all(
            'SELECT d.*, p.code AS payment_code, p.status AS payment_status
             FROM billing_documents d LEFT JOIN payments p ON p.id = d.payment_id
             ORDER BY d.id DESC LIMIT ' . max(1, $limit)
        );
    }

    /**
     * Emet el document d'un cobrament. Si ja en tenia, es queda el que hi ha:
     * un document emès no es torna a emetre mai.
     */
    public static function issue(array $payment): ?array
    {
        $existing = self::document((int) $payment['id']);
        if ($existing) {
            return $existing;
        }
        if ((string) $payment['status'] !== 'paid') {
            return null;
        }

        // El que val és el que hi hagi configurat el dia que s'emet, no el que
        // hi havia quan es va començar el cobrament: entremig poden haver-se
        // acabat d'omplir les dades fiscals.
        $type = self::documentType();
        $series = self::series($type);
        $year = (int) date('Y');
        $number = self::nextNumber($series, $year);
        $issuer = self::issuer();

        $id = Db::insert('billing_documents', [
            'payment_id' => (int) $payment['id'],
            'type' => $type,
            'series' => $series,
            'number' => $number,
            'full_number' => $series . '-' . $year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'issued_on' => date('Y-m-d'),
            'issuer' => json_encode($issuer, JSON_UNESCAPED_UNICODE),
            'customer_name' => mb_substr((string) $payment['payer_name'], 0, 190),
            'customer_nif' => mb_substr((string) ($payment['payer_nif'] ?? ''), 0, 30) ?: null,
            'customer_address' => mb_substr((string) ($payment['payer_address'] ?? ''), 0, 255) ?: null,
            'customer_email' => mb_substr((string) $payment['payer_email'], 0, 190),
            'subtotal_cents' => (int) $payment['subtotal_cents'],
            'tax_rate' => (float) $payment['tax_rate'],
            'tax_cents' => (int) $payment['tax_cents'],
            'total_cents' => (int) $payment['total_cents'],
            'currency' => (string) $payment['currency'],
            'notes' => mb_substr($issuer['notes'], 0, 500) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Db::update('payments', ['document_type' => $type, 'updated_at' => date('Y-m-d H:i:s')],
            'id = :id', ['id' => $payment['id']]);

        return self::find($id);
    }

    /**
     * El número següent d'una sèrie i un any.
     *
     * Es guarda en una taula de comptadors i no es calcula amb un MAX(): així
     * dues vendes alhora no poden acabar amb el mateix número, que és el
     * primer que mira qui revisa una comptabilitat.
     */
    public static function nextNumber(string $series, int $year): int
    {
        $sql = Db::driver() === 'sqlite'
            ? 'INSERT INTO billing_counters (series, year, next_number) VALUES (:s, :y, 2)
               ON CONFLICT(series, year) DO UPDATE SET next_number = next_number + 1'
            : 'INSERT INTO billing_counters (series, year, next_number) VALUES (:s, :y, 2)
               ON DUPLICATE KEY UPDATE next_number = next_number + 1';
        Db::q($sql, ['s' => $series, 'y' => $year]);
        $next = (int) Db::val('SELECT next_number FROM billing_counters WHERE series = :s AND year = :y',
            ['s' => $series, 'y' => $year], 2);

        return max(1, $next - 1);
    }

    /** El document en PDF, a punt de descarregar. */
    public static function pdf(array $document): string
    {
        $issuer = json_decode((string) ($document['issuer'] ?? ''), true);
        $issuer = is_array($issuer) ? $issuer : self::issuer();
        $items = Payment::items((int) $document['payment_id']);
        $payment = Payment::find((int) $document['payment_id']) ?? [];
        $type = (string) $document['type'];
        $title = self::TYPES[$type] ?? 'Rebut';

        $pdf = new Pdf(['title' => $title . ' ' . $document['full_number'], 'author' => (string) $issuer['entity']]);
        $pdf->addPage('a4');
        $colour = (string) Settings::get('color_primary', '#2f6b3c');
        $x = 18.0;
        $right = 192.0;

        // Capçalera: qui emet. Mai ningú més.
        $pdf->setColorHex($colour);
        $pdf->setFont('helvetica-bold', 16);
        $pdf->text($x, 22, (string) $issuer['entity']);
        $pdf->setColor(70, 80, 74);
        $pdf->setFont('helvetica', 9);
        $y = 28.0;
        foreach (self::issuerLines($issuer) as $line) {
            $pdf->text($x, $y, $line);
            $y += 4.4;
        }

        $pdf->setColorHex($colour);
        $pdf->setFont('helvetica-bold', 20);
        $pdf->text($right, 22, mb_strtoupper($title), ['align' => 'right']);
        $pdf->setColor(40, 50, 44);
        $pdf->setFont('helvetica', 10);
        $pdf->text($right, 29, 'Número: ' . $document['full_number'], ['align' => 'right']);
        $pdf->text($right, 34, 'Data: ' . ca_date((string) $document['issued_on']), ['align' => 'right']);

        $y = max($y, 40.0) + 6;
        $pdf->setStrokeColor(210, 220, 212);
        $pdf->line($x, $y, $right, $y, 0.4);
        $y += 8;

        // A qui va.
        $pdf->setColor(90, 100, 94);
        $pdf->setFont('helvetica', 9);
        $pdf->text($x, $y, mb_strtoupper('Dades de qui paga'));
        $y += 5.5;
        $pdf->setColor(25, 35, 29);
        $pdf->setFont('helvetica-bold', 11);
        $pdf->text($x, $y, (string) $document['customer_name']);
        $y += 5;
        $pdf->setFont('helvetica', 9.5);
        $pdf->setColor(60, 70, 64);
        foreach (array_filter([
            (string) ($document['customer_nif'] ?? ''),
            (string) ($document['customer_address'] ?? ''),
            (string) ($document['customer_email'] ?? ''),
        ]) as $line) {
            $pdf->text($x, $y, $line);
            $y += 4.6;
        }

        $y += 6;
        // El detall.
        $pdf->setColorHex($colour);
        $pdf->rect($x, $y - 5, $right - $x, 7.5, 'F');
        $pdf->setColor(255, 255, 255);
        $pdf->setFont('helvetica-bold', 9);
        $pdf->text($x + 3, $y, 'Concepte');
        $pdf->text($x + 112, $y, 'Unitats', ['align' => 'right', 'width' => 0]);
        $pdf->text($x + 140, $y, 'Preu', ['align' => 'right']);
        $pdf->text($right - 3, $y, 'Import', ['align' => 'right']);
        $y += 9;

        $pdf->setColor(30, 40, 34);
        $pdf->setFont('helvetica', 9.5);
        foreach ($items as $item) {
            $pdf->text($x + 3, $y, (string) $item['description'], ['max_width' => 100]);
            $pdf->text($x + 112, $y, (string) (int) $item['qty'], ['align' => 'right']);
            $pdf->text($x + 140, $y, money((int) $item['unit_price_cents']), ['align' => 'right']);
            $pdf->text($right - 3, $y, money((int) $item['subtotal_cents']), ['align' => 'right']);
            $y += 6;
        }
        if (!$items) {
            $pdf->text($x + 3, $y, (string) (($payment['concept'] ?? '') === 'registration' ? 'Inscripció a la cursa' : 'Compra'));
            $pdf->text($right - 3, $y, money((int) $document['total_cents']), ['align' => 'right']);
            $y += 6;
        }

        $y += 2;
        $pdf->setStrokeColor(210, 220, 212);
        $pdf->line($x + 100, $y, $right, $y, 0.3);
        $y += 6;

        $rate = (float) $document['tax_rate'];
        $pdf->setFont('helvetica', 9.5);
        $pdf->setColor(60, 70, 64);
        if ($rate > 0) {
            $pdf->text($x + 140, $y, 'Base imposable', ['align' => 'right']);
            $pdf->text($right - 3, $y, money((int) $document['subtotal_cents']), ['align' => 'right']);
            $y += 5.4;
            $pdf->text($x + 140, $y, 'IVA ' . rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',') . ' %', ['align' => 'right']);
            $pdf->text($right - 3, $y, money((int) $document['tax_cents']), ['align' => 'right']);
            $y += 5.4;
        }
        $pdf->setFont('helvetica-bold', 12);
        $pdf->setColorHex($colour);
        $pdf->text($x + 140, $y + 1, 'Total', ['align' => 'right']);
        $pdf->text($right - 3, $y + 1, money((int) $document['total_cents']), ['align' => 'right']);
        $y += 12;

        // El peu: com s'ha pagat i el que calgui dir.
        $pdf->setFont('helvetica', 9);
        $pdf->setColor(90, 100, 94);
        $notes = array_values(array_filter([
            $rate <= 0 ? ((string) ($issuer['tax_note'] ?? '')) : '',
            !empty($payment['paid_at']) ? 'Pagat el ' . ca_date(substr((string) $payment['paid_at'], 0, 10))
                . ' amb ' . self::gatewayLabel((string) ($payment['gateway'] ?? '')) . '.' : '',
            (string) ($document['notes'] ?? ''),
            'Referència del cobrament: ' . (string) ($payment['code'] ?? ''),
        ], static fn (string $line): bool => trim($line) !== ''));
        foreach ($notes as $line) {
            $y = $pdf->textBlock($x, $y, $right - $x, $line) + 1.5;
        }

        return $pdf->output();
    }

    /** Com se'n diu la passarel·la, per al peu del document. */
    private static function gatewayLabel(string $key): string
    {
        return $key === '' ? 'targeta' : \Cros\Payments\Gateways::label($key);
    }

    /** @return array<int,string> */
    private static function issuerLines(array $issuer): array
    {
        $town = trim(trim((string) $issuer['postcode'] . ' ' . (string) $issuer['town'])
            . ((string) $issuer['province'] !== '' ? ' (' . $issuer['province'] . ')' : ''));

        return array_values(array_filter([
            (string) $issuer['nif'] !== '' ? 'NIF ' . $issuer['nif'] : '',
            (string) $issuer['address'],
            $town,
            (string) $issuer['country'],
            trim(implode(' · ', array_filter([(string) $issuer['email'], (string) $issuer['phone']]))),
        ], static fn (string $line): bool => trim($line) !== ''));
    }

    /** Com es diu el fitxer que es descarrega. */
    public static function filename(array $document): string
    {
        $type = (string) $document['type'] === 'invoice' ? 'factura' : 'rebut';

        return $type . '-' . strtolower((string) $document['full_number']) . '.pdf';
    }
}
