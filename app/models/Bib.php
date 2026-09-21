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

    /**
     * Les famílies poden descarregar el seu dorsal?
     * Si no, no n'ha de quedar cap rastre al web públic ni als correus.
     */
    public static function publicDownload(): bool
    {
        return setting('bib_public_download', '1') === '1';
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
        $layout = self::layout();
        $size = $layout['size'];
        $rotate = $layout['rotate'];
        $placement = null;

        $templateFile = self::templateFile();
        if ($templateFile !== null) {
            try {
                $import = new PdfImport($templateFile);
                $page = max(1, (int) setting('bib_template_page', '1'));
                $page = min($page, $import->pageCount());
                $dimensions = $import->pageSize($page);
                $layout = self::layout([$dimensions['width'], $dimensions['height']]);
                $size = $layout['size'];
                $rotate = $layout['rotate'];
                $placement = self::fit($layout['template'], $size);
                // La maqueta s'importa una sola vegada i es reutilitza a cada pàgina.
                $pdf->addPage($size);
                $template = $import->page($pdf, $page);
            } catch (\Throwable $e) {
                log_line('bibs', 'No s\'ha pogut llegir la maqueta', ['error' => $e->getMessage()]);
                $template = null;
                $size = self::pageSize();
            }
        }

        $first = $template !== null;
        foreach ($registrations as $registration) {
            if ($first) {
                $first = false; // la primera pàgina ja s'ha creat en importar la maqueta
            } else {
                $pdf->addPage($size);
            }
            if ($template !== null && $placement !== null) {
                $pdf->useTemplate($template, $placement[0], $placement[1], $placement[2], $placement[3], $rotate);
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

    /**
     * Mida de la pàgina i gir que cal aplicar a la maqueta.
     *
     * Amb «automàtic» el dorsal surt amb la mida de la maqueta. Si es demana una
     * orientació que la maqueta no té (un disseny apaïsat desat en un PDF vertical,
     * per exemple), la maqueta es gira 90° perquè ompli la pàgina.
     *
     * @param array{0:float,1:float}|null $template mida de la maqueta en mm
     * @return array{size:array,rotate:int,template:array}
     */
    public static function layout(?array $template = null): array
    {
        $rotate = self::templateRotation();
        if ($template === null) {
            return ['size' => self::pageSize(), 'rotate' => $rotate, 'template' => self::pageSize()];
        }
        $natural = in_array($rotate, [90, 270], true)
            ? [(float) $template[1], (float) $template[0]]
            : [(float) $template[0], (float) $template[1]];
        if (self::orient($natural) !== $natural) {
            $rotate = ($rotate + 90) % 360;
            $natural = [$natural[1], $natural[0]];
        }
        return ['size' => $natural, 'rotate' => $rotate, 'template' => $natural];
    }

    /**
     * Resum per al panell: mida de la maqueta i mida que tindrà el dorsal.
     * @return array{template:?array,page:array,rotate:int,error:string}
     */
    public static function describe(): array
    {
        $file = self::templateFile();
        if ($file === null) {
            $layout = self::layout();
            return ['template' => null, 'page' => $layout['size'], 'rotate' => 0, 'error' => ''];
        }
        try {
            $import = new PdfImport($file);
            $page = min(max(1, (int) setting('bib_template_page', '1')), $import->pageCount());
            $dimensions = $import->pageSize($page);
            $template = [$dimensions['width'], $dimensions['height']];
            $layout = self::layout($template);
            return ['template' => $template, 'page' => $layout['size'], 'rotate' => $layout['rotate'], 'error' => ''];
        } catch (\Throwable $e) {
            $layout = self::layout();
            return ['template' => null, 'page' => $layout['size'], 'rotate' => 0, 'error' => $e->getMessage()];
        }
    }

    /** Mida de pàgina quan no hi ha maqueta, amb l'orientació configurada. */
    private static function pageSize(): array
    {
        $key = strtolower((string) setting('bib_page_size', 'a5'));
        return self::orient(Pdf::SIZES[$key] ?? Pdf::SIZES['a5']);
    }

    /** Gir que cal aplicar a la maqueta (0, 90, 180 o 270 graus). */
    private static function templateRotation(): int
    {
        $rotate = (int) setting('bib_template_rotate', '0');
        return in_array($rotate, [90, 180, 270], true) ? $rotate : 0;
    }

    /**
     * Aplica l'orientació triada al panell a una mida [amplada, alçada].
     * Amb «auto» la mida es manté tal com ve de la maqueta.
     */
    private static function orient(array $size): array
    {
        $orientation = (string) setting('bib_orientation', 'auto');
        [$width, $height] = [(float) $size[0], (float) $size[1]];
        return match ($orientation) {
            'portrait' => [min($width, $height), max($width, $height)],
            'landscape' => [max($width, $height), min($width, $height)],
            default => [$width, $height],
        };
    }

    /**
     * Situa la maqueta dins de la pàgina sense deformar-la (centrada si sobra espai).
     * @return array{0:float,1:float,2:float,3:float} x, y, amplada i alçada en mm
     */
    private static function fit(array $template, array $page): array
    {
        $scale = min($page[0] / max(0.01, $template[0]), $page[1] / max(0.01, $template[1]));
        $width = $template[0] * $scale;
        $height = $template[1] * $scale;
        return [($page[0] - $width) / 2, ($page[1] - $height) / 2, $width, $height];
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
