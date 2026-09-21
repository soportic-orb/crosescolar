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
     * PDF per a la família: de cada participant, dues còpies del seu dorsal en
     * un full A4 vertical, una a dalt i una a baix, amb la línia per retallar.
     * Així en tenen un per al pit i un altre de recanvi.
     *
     * @param array<int,array> $registrations
     */
    public static function familyPdf(array $registrations): string
    {
        return self::pdf($registrations, true);
    }

    /**
     * Genera el PDF amb un dorsal per participant.
     *
     * @param array<int,array> $registrations
     * @param bool $duplicate dues còpies de cada dorsal, amb la línia de retallar
     */
    public static function pdf(array $registrations, bool $duplicate = false): string
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

        $sheet = self::sheet($size, $duplicate);
        if ($duplicate) {
            // De cada participant, dues còpies seguides.
            $doubled = [];
            foreach ($registrations as $registration) {
                $doubled[] = $registration;
                $doubled[] = $registration;
            }
            $registrations = $doubled;
        }
        // La primera pàgina ja s'ha creat en importar la maqueta: si ara resulta
        // que al full n'hi caben dos, cal que tingui la mida del full.
        if ($template !== null && $sheet['per_sheet'] > 1) {
            $pdf->resizePage($sheet['sheet']);
        }

        $first = $template !== null;
        $slot = 0;
        foreach ($registrations as $registration) {
            if ($first) {
                $first = false;
            } elseif ($slot === 0) {
                $pdf->addPage($sheet['sheet']);
            }
            $top = $slot * $sheet['offset'];
            if ($template !== null && $placement !== null) {
                $pdf->useTemplate($template, $placement[0], $placement[1] + $top, $placement[2], $placement[3], $rotate);
            }
            self::drawFields($pdf, $registration, $size[0], $top);
            // La línia de retallar va entre les dues meitats, un cop dibuixada la de dalt.
            if ($duplicate && $sheet['per_sheet'] > 1 && $slot === 0) {
                self::cutMark($pdf, $sheet['offset'], $sheet['sheet'][0]);
            }
            $slot = ($slot + 1) % $sheet['per_sheet'];
        }
        if (!$registrations) {
            $pdf->addPage($sheet['sheet']);
        }
        return $pdf->output();
    }

    /**
     * Quants dorsals hi caben a cada full i quina mida té el full.
     *
     * Amb l'opció activada i un dorsal que ocupi com a molt mig A4 (un A5
     * apaïsat, 210×148 mm), se n'imprimeixen dos per full A4 vertical, un a
     * dalt i un a baix. Si el dorsal és més gran, se'n continua fent un per full.
     *
     * @param array{0:float,1:float} $size mida del dorsal en mm
     * @return array{sheet:array{0:float,1:float},per_sheet:int,offset:float}
     */
    public static function sheet(array $size, bool $force = false): array
    {
        $one = ['sheet' => $size, 'per_sheet' => 1, 'offset' => 0.0];
        if (!$force && setting('bib_two_per_sheet', '0') !== '1') {
            return $one;
        }
        [$a4Width, $a4Height] = Pdf::SIZES['a4'];
        $half = $a4Height / 2;
        // Mig mil·límetre de marge per als dissenys fets clavats a la mida.
        if ($size[0] > $a4Width + 0.5 || $size[1] > $half + 0.5) {
            return $one;
        }

        return ['sheet' => [$a4Width, $a4Height], 'per_sheet' => 2, 'offset' => $half];
    }

    /**
     * Marca per on s'ha de retallar el full: una línia de punts d'una banda a
     * l'altra amb unes tisores al començament.
     */
    private static function cutMark(Pdf $pdf, float $y, float $width): void
    {
        $pdf->setStrokeColor(120, 130, 122);
        self::scissors($pdf, 14.0, $y, 2.1);
        $pdf->dashedLine(20.0, $y, $width - 8.0, $y);
        $pdf->setFont('helvetica', 7.5);
        $pdf->setColorHex('#78827a');
        $pdf->text($width - 8.0, $y - 1.6, 'Retalleu per aquí', ['align' => 'right']);
        $pdf->setStrokeColor(0, 0, 0);
    }

    /** Unes tisores petites: dues fulles creuades i dues anelles. */
    private static function scissors(Pdf $pdf, float $x, float $y, float $size): void
    {
        $blade = $size * 1.9;
        $pdf->line($x - $size * 0.2, $y, $x + $blade, $y - $size * 1.1, 0.28);
        $pdf->line($x - $size * 0.2, $y, $x + $blade, $y + $size * 1.1, 0.28);
        $pdf->circle($x - $size * 0.75, $y - $size * 0.62, $size * 0.6, 'D', 0.28);
        $pdf->circle($x - $size * 0.75, $y + $size * 0.62, $size * 0.6, 'D', 0.28);
    }

    /** Dorsal d'exemple per previsualitzar el disseny. */
    public static function sample(): string
    {
        return self::pdf([[
            'first_name' => 'Laia',
            'last_name' => 'Ferrer Miró',
            'bib_number' => 1,
            'category_name' => 'Aleví',
            'category_gender' => 'femeni',
            'category_year_from' => 2016,
            'category_year_to' => 2017,
            'school' => 'Escola La Granada',
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

    /**
     * El nom que va al dorsal: el nom sencer o només el de pila, segons
     * l'opció «Només el nom, sense cognoms».
     */
    public static function name(array $registration): string
    {
        $first = trim((string) ($registration['first_name'] ?? ''));
        if (setting('bib_name_first_only', '0') === '1') {
            return $first;
        }

        return trim($first . ' ' . trim((string) ($registration['last_name'] ?? '')));
    }

    /**
     * La categoria tal com surt al dorsal: el nom, el gènere si no és mixta i
     * els anys de naixement. Per exemple «Infantil masculí (2013–2014)» o
     * «Prebenjamí femení (2020)».
     */
    public static function category(array $registration): string
    {
        return Content::title($registration);
    }

    private static function drawFields(Pdf $pdf, array $registration, float $pageWidth, float $top = 0.0): void
    {
        self::drawField($pdf, 'bib_number', self::number($registration), $pageWidth, $top);
        self::drawField($pdf, 'bib_name', self::name($registration), $pageWidth, $top);
        self::drawField($pdf, 'bib_category', self::category($registration), $pageWidth, $top);
        self::drawField($pdf, 'bib_school', trim((string) ($registration['school'] ?? '')), $pageWidth, $top);
    }

    private static function drawField(Pdf $pdf, string $prefix, string $value, float $pageWidth, float $top = 0.0): void
    {
        // Sense valor per defecte explícit: així s'agafa el de l'esquema de configuració.
        if ($value === '' || $value === '—' || (string) setting($prefix . '_show') !== '1') {
            return;
        }
        $x = (float) setting($prefix . '_x');
        // Les posicions es configuren dins del dorsal; «top» diu on comença
        // el dorsal dins del full quan n'hi ha més d'un.
        $y = (float) setting($prefix . '_y') + $top;
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
