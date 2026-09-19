<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Auth;
use Cros\Core\Db;
use Cros\Core\Pdf;

/** Resultats de la cursa: arribades a meta i classificacions. */
class RaceResult
{
    /**
     * Registra l'arribada d'un participant a partir del número de dorsal.
     * @return array{status:string,message:string,result:?array}
     */
    public static function addByBib(string $bib): array
    {
        $bib = trim($bib);
        if ($bib === '' || !ctype_digit(ltrim($bib, '0') === '' ? '0' : ltrim($bib, '0'))) {
            return ['status' => 'error', 'message' => 'Indiqueu un número de dorsal.', 'result' => null];
        }
        $registration = Registration::findByBib((int) $bib);
        if (!$registration) {
            return ['status' => 'error', 'message' => 'No hi ha cap participant amb el dorsal ' . (int) $bib . '.', 'result' => null];
        }
        return self::add((int) $registration['id']);
    }

    /** Registra l'arribada d'una inscripció. */
    public static function add(int $registrationId): array
    {
        $registration = Registration::find($registrationId);
        if (!$registration) {
            return ['status' => 'error', 'message' => 'La inscripció no existeix.', 'result' => null];
        }
        $existing = Db::one('SELECT * FROM results WHERE registration_id = :id', ['id' => $registrationId]);
        if ($existing) {
            return [
                'status' => 'warning',
                'message' => sprintf(
                    'El dorsal %s (%s) ja constava a meta en la posició %d de la seva categoria.',
                    Bib::number($registration),
                    trim($registration['first_name'] . ' ' . $registration['last_name']),
                    (int) $existing['position']
                ),
                'result' => self::find((int) $existing['id']),
            ];
        }

        $categoryId = $registration['category_id'] !== null ? (int) $registration['category_id'] : null;
        $position = (int) Db::val(
            'SELECT COUNT(*) FROM results WHERE ' . ($categoryId === null ? 'category_id IS NULL' : 'category_id = :cat'),
            $categoryId === null ? [] : ['cat' => $categoryId],
            0
        ) + 1;
        $arrival = (int) Db::val('SELECT COALESCE(MAX(arrival_seq), 0) FROM results', [], 0) + 1;

        $id = Db::insert('results', [
            'registration_id' => $registrationId,
            'category_id' => $categoryId,
            'position' => $position,
            'arrival_seq' => $arrival,
            'status' => 'finished',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        Auth::logActivity('result_add', 'result', $id, ['dorsal' => $registration['bib_number']]);

        return [
            'status' => 'ok',
            'message' => sprintf(
                '%s · %s — posició %d de %s',
                Bib::number($registration),
                trim($registration['first_name'] . ' ' . $registration['last_name']),
                $position,
                $registration['category_name'] ?? 'sense categoria'
            ),
            'result' => self::find($id),
        ];
    }

    public static function find(int $id): ?array
    {
        return Db::one(self::baseQuery() . ' WHERE res.id = :id', ['id' => $id]);
    }

    /** Esborra una arribada i recalcula les posicions de la categoria. */
    public static function remove(int $id): void
    {
        $result = self::find($id);
        if (!$result) {
            return;
        }
        Db::delete('results', 'id = :id', ['id' => $id]);
        self::renumber($result['category_id'] !== null ? (int) $result['category_id'] : null);
        Auth::logActivity('result_delete', 'result', $id, ['dorsal' => $result['bib_number']]);
    }

    /** Puja o baixa una posició dins de la categoria. */
    public static function move(int $id, int $direction): void
    {
        $result = self::find($id);
        if (!$result) {
            return;
        }
        $categoryId = $result['category_id'] !== null ? (int) $result['category_id'] : null;
        $target = (int) $result['position'] + ($direction < 0 ? -1 : 1);
        $neighbour = Db::one(
            'SELECT * FROM results WHERE position = :position AND '
            . ($categoryId === null ? 'category_id IS NULL' : 'category_id = :cat'),
            $categoryId === null ? ['position' => $target] : ['position' => $target, 'cat' => $categoryId]
        );
        if (!$neighbour) {
            return;
        }
        Db::update('results', ['position' => (int) $result['position'], 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $neighbour['id']]);
        Db::update('results', ['position' => $target, 'updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    /** Recalcula les posicions d'una categoria perquè siguin consecutives. */
    public static function renumber(?int $categoryId): void
    {
        $rows = Db::all(
            'SELECT id FROM results WHERE ' . ($categoryId === null ? 'category_id IS NULL' : 'category_id = :cat')
            . ' ORDER BY position ASC, arrival_seq ASC',
            $categoryId === null ? [] : ['cat' => $categoryId]
        );
        $position = 1;
        foreach ($rows as $row) {
            Db::update('results', ['position' => $position++], 'id = :id', ['id' => $row['id']]);
        }
    }

    /** Consulta base amb les dades del participant. */
    private static function baseQuery(): string
    {
        return 'SELECT res.*, r.first_name, r.last_name, r.bib_number, r.school, r.class_group, r.birth_year,
                       c.name AS category_name, c.sort_order AS category_order,
                       c.medals AS category_medals, c.winners AS category_winners
                FROM results res
                JOIN registrations r ON r.id = res.registration_id
                LEFT JOIN categories c ON c.id = res.category_id';
    }

    /** Arribades en ordre invers (les últimes primer). */
    public static function recent(int $limit = 15): array
    {
        return Db::all(self::baseQuery() . ' ORDER BY res.arrival_seq DESC LIMIT ' . max(1, $limit));
    }

    /** Totes les arribades per ordre d'arribada a meta. */
    public static function arrivals(): array
    {
        return Db::all(self::baseQuery() . ' ORDER BY res.arrival_seq ASC');
    }

    /** Resultats agrupats per categoria i ordenats per posició. */
    public static function byCategory(?int $categoryId = null): array
    {
        $sql = self::baseQuery();
        $params = [];
        if ($categoryId !== null) {
            $sql .= ' WHERE res.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        $sql .= ' ORDER BY COALESCE(c.sort_order, 9999) ASC, c.id ASC, res.position ASC';
        $grouped = [];
        foreach (Db::all($sql, $params) as $row) {
            $key = $row['category_id'] === null ? 0 : (int) $row['category_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => $key,
                    'name' => $row['category_name'] ?? 'Sense categoria',
                    // Medalles: cada categoria decideix si en mostra i a quants.
                    'medals' => (int) ($row['category_medals'] ?? 1) === 1,
                    'winners' => max(0, min(50, (int) ($row['category_winners'] ?? 3))),
                    'rows' => [],
                ];
            }
            $grouped[$key]['rows'][] = $row;
        }
        return array_values($grouped);
    }

    /** Resum per al panell. */
    public static function stats(): array
    {
        return [
            'total' => (int) Db::val('SELECT COUNT(*) FROM results', [], 0),
            'registrations' => (int) Db::val('SELECT COUNT(*) FROM registrations', [], 0),
            'categories' => (int) Db::val('SELECT COUNT(DISTINCT category_id) FROM results', [], 0),
            'last' => Db::one(self::baseQuery() . ' ORDER BY res.arrival_seq DESC LIMIT 1'),
        ];
    }

    /** Files per exportar a CSV. */
    public static function csvRows(): array
    {
        $rows = [];
        foreach (self::arrivals() as $row) {
            $rows[] = [
                $row['arrival_seq'],
                $row['position'],
                Bib::number($row),
                trim($row['first_name'] . ' ' . $row['last_name']),
                $row['category_name'] ?? '',
                $row['school'] ?? '',
                $row['class_group'] ?? '',
                dt($row['created_at']),
            ];
        }
        return $rows;
    }

    /**
     * Classificació en PDF.
     * @param int|null $categoryId  categoria concreta, o null per a totes
     * @param bool $arrivalOrder    true = llistat únic per ordre d'arribada
     */
    public static function pdf(?int $categoryId = null, bool $arrivalOrder = false): string
    {
        $pdf = new Pdf(['title' => 'Resultats · ' . setting('site_name', 'Cros Escolar La Granada')]);

        if ($arrivalOrder) {
            $rows = self::arrivals();
            self::pdfSection($pdf, 'Ordre d\'arribada a meta', $rows, true);
            return $pdf->output();
        }

        $groups = self::byCategory($categoryId);
        if (!$groups) {
            self::pdfSection($pdf, 'Resultats', [], false);
            return $pdf->output();
        }
        foreach ($groups as $group) {
            self::pdfSection($pdf, $group['name'], $group['rows'], false);
        }
        return $pdf->output();
    }

    /** Dibuixa una pàgina (o més) amb una classificació. */
    private static function pdfSection(Pdf $pdf, string $title, array $rows, bool $arrivalOrder): void
    {
        $pdf->addPage('a4');
        $y = self::pdfHeader($pdf, $title, count($rows));

        $columns = $arrivalOrder
            ? [['Arribada', 22, 'right'], ['Dorsal', 20, 'right'], ['Participant', 70, 'left'], ['Categoria', 42, 'left'], ['Pos. cat.', 20, 'right']]
            : [['Posició', 20, 'right'], ['Dorsal', 20, 'right'], ['Participant', 75, 'left'], ['Escola', 55, 'left'], ['Any', 15, 'right']];

        $y = self::pdfTableHeader($pdf, $columns, $y);
        $line = 0;
        foreach ($rows as $row) {
            if ($y > 275) {
                $pdf->addPage('a4');
                $y = self::pdfHeader($pdf, $title . ' (continuació)', count($rows));
                $y = self::pdfTableHeader($pdf, $columns, $y);
                $line = 0;
            }
            if ($line % 2 === 1) {
                $pdf->setColorHex('#f2f7ef');
                $pdf->rect(15, $y - 4.6, 180, 6.4, 'F');
            }
            $name = trim($row['first_name'] . ' ' . $row['last_name']);
            $values = $arrivalOrder
                ? [(string) $row['arrival_seq'], Bib::number($row), $name, (string) ($row['category_name'] ?? ''), (string) $row['position']]
                : [(string) $row['position'], Bib::number($row), $name, (string) ($row['school'] ?? ''), (string) ($row['birth_year'] ?? '')];

            $x = 15.0;
            foreach ($columns as $index => [$label, $width, $align]) {
                $pdf->setFont($index <= 1 ? 'helvetica-bold' : 'helvetica', 10);
                $pdf->setColorHex($index === 0 ? '#2f6b3c' : '#17261c');
                $pdf->text($align === 'right' ? $x + $width - 2 : $x + 1, $y, $values[$index] ?? '', [
                    'align' => $align === 'right' ? 'right' : 'left',
                    'max_width' => $width - 2,
                ]);
                $x += $width;
            }
            $y += 6.4;
            $line++;
        }

        if (!$rows) {
            $pdf->setFont('helvetica', 11);
            $pdf->setColorHex('#4a5b50');
            $pdf->text(15, $y + 4, 'Encara no hi ha cap arribada registrada.');
        }
    }

    private static function pdfHeader(Pdf $pdf, string $title, int $total): float
    {
        $pdf->setColorHex('#2f6b3c');
        $pdf->rect(0, 0, 210, 26, 'F');
        $pdf->setFont('helvetica-bold', 15);
        $pdf->setColorHex('#ffffff');
        $pdf->text(15, 12, setting('site_name', 'Cros Escolar La Granada'));
        $pdf->setFont('helvetica', 9.5);
        $pdf->text(15, 19, ucfirst(ca_date(setting('event_date', ''), true)) . ' · ' . setting('event_place', ''));
        $pdf->setFont('helvetica', 9);
        $pdf->text(195, 12, 'Classificació', ['align' => 'right']);
        $pdf->text(195, 19, $total . ' participants', ['align' => 'right']);

        $pdf->setFont('helvetica-bold', 17);
        $pdf->setColorHex('#12301c');
        $pdf->text(15, 40, $title);
        return 50.0;
    }

    private static function pdfTableHeader(Pdf $pdf, array $columns, float $y): float
    {
        $pdf->setColorHex('#e5efe1');
        $pdf->rect(15, $y - 5, 180, 7, 'F');
        $pdf->setFont('helvetica-bold', 8.5);
        $pdf->setColorHex('#2f6b3c');
        $x = 15.0;
        foreach ($columns as [$label, $width, $align]) {
            $pdf->text($align === 'right' ? $x + $width - 2 : $x + 1, $y, mb_strtoupper($label), [
                'align' => $align === 'right' ? 'right' : 'left',
            ]);
            $x += $width;
        }
        return $y + 7.5;
    }
}
