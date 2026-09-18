<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Pdf;
use Cros\Core\PdfImport;

/** Generació dels dorsals en PDF sobre la maqueta configurada al panell. */
class Bib
{
    /** Número de dorsal formatat (001, 002…). */
    public static function number($registration): string
    {
        $number = is_array($registration) ? ($registration['bib_number'] ?? null) : $registration;
        if ($number === null || $number === '') {
            return '—';
        }
        $digits = max(1, min(6, (int) setting('bib_digits', '3')));
        return str_pad((string) (int) $number, $digits, '0', STR_PAD_LEFT);
    }

    /** Ruta de la maqueta, o null si no n'hi ha cap de vàlida. */
    public static function templateFile(): ?string
    {
        $file = (string) setting('bib_template', '');
        if ($file === '') {
            return null;
        }
        $path = upload_path($file);
        return is_file($path) ? $path : null;
    }

    /**
     * Genera el PDF amb un dorsal per participant.
     * @param array<int,array> $registrations
     */
    public static function pdf(array $registrations): string
    {
        $pdf = new Pdf(['title' => 'Dorsals · ' . setting('site_name', 'Cros Escolar La Granada')]);
        $template = null;
        $size = self::pageSize();

        $templateFile = self::templateFile();
        if ($templateFile !== null) {
            try {
                $import = new PdfImport($templateFile);
                $page = max(1, (int) setting('bib_template_page', '1'));
                $page = min($page, $import->pageCount());
                $dimensions = $import->pageSize($page);
                $size = [$dimensions['width'], $dimensions['height']];
                // La maqueta s'importa una sola vegada i es reutilitza a cada pàgina.
                $pdf->addPage($size);
                $template = $import->page($pdf, $page);
            } catch (\Throwable $e) {
                log_line('bibs', 'No s\'ha pogut llegir la maqueta', ['error' => $e->getMessage()]);
                $template = null;
            }
        }

        $first = $template !== null;
        foreach ($registrations as $registration) {
            if ($first) {
                $first = false; // la primera pàgina ja s'ha creat en importar la maqueta
            } else {
                $pdf->addPage($size);
            }
            if ($template !== null) {
                $pdf->useTemplate($template);
            }
            self::drawFields($pdf, $registration, $size[0]);
        }
        if (!$registrations) {
            $pdf->addPage($size);
        }
        return $pdf->output();
    }

    /** Dorsal d'exemple per previsualitzar el disseny. */
    public static function sample(): string
    {
        return self::pdf([[
            'first_name' => 'Laia',
            'last_name' => 'Ferrer Miró',
            'bib_number' => 1,
            'category_name' => 'Aleví (3r-4t)',
        ]]);
    }

    /** Mida de pàgina quan no hi ha maqueta. */
    private static function pageSize(): array
    {
        $key = strtolower((string) setting('bib_page_size', 'a5'));
        return Pdf::SIZES[$key] ?? Pdf::SIZES['a5'];
    }

    private static function drawFields(Pdf $pdf, array $registration, float $pageWidth): void
    {
        $name = trim(($registration['first_name'] ?? '') . ' ' . ($registration['last_name'] ?? ''));
        $category = (string) ($registration['category_name'] ?? '');

        self::drawField($pdf, 'bib_number', self::number($registration), $pageWidth);
        self::drawField($pdf, 'bib_name', $name, $pageWidth);
        self::drawField($pdf, 'bib_category', $category, $pageWidth);
    }

    private static function drawField(Pdf $pdf, string $prefix, string $value, float $pageWidth): void
    {
        // Sense valor per defecte explícit: així s'agafa el de l'esquema de configuració.
        if ($value === '' || $value === '—' || (string) setting($prefix . '_show') !== '1') {
            return;
        }
        $x = (float) setting($prefix . '_x');
        $y = (float) setting($prefix . '_y');
        $size = max(4.0, (float) setting($prefix . '_size'));
        $align = (string) setting($prefix . '_align');
        $bold = (string) setting($prefix . '_bold') === '1';

        $pdf->setFont($bold ? 'helvetica-bold' : 'helvetica', $size);
        $pdf->setColorHex((string) setting($prefix . '_color'));
        $pdf->text($x, $y, $value, [
            'align' => in_array($align, ['left', 'center', 'right'], true) ? $align : 'center',
            'max_width' => max(20.0, $pageWidth - 16.0),
        ]);
    }
}
