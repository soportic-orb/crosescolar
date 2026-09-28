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

        // Cada dorsal se centra dins del seu espai del full: el que sobra queda
        // com a marge, de manera que la línia de retallar passi per un tros en
        // blanc i els dos dorsals es vegin separats de debò.
        $stacked = $sheet['axis'] === 'y';
        $twoUp = $sheet['per_sheet'] > 1;
        $slotWidth = $twoUp && !$stacked ? $sheet['offset'] : $sheet['sheet'][0];
        $slotHeight = $twoUp && $stacked ? $sheet['offset'] : $sheet['sheet'][1];
        $insetX = max(0.0, ($slotWidth - $size[0]) / 2);
        $insetY = max(0.0, ($slotHeight - $size[1]) / 2);

        $first = $template !== null;
        $slot = 0;
        $pending = false;
        foreach ($registrations as $registration) {
            if ($first) {
                $first = false;
            } elseif ($slot === 0) {
                $pdf->addPage($sheet['sheet']);
            }
            $step = $slot * $sheet['offset'];
            $top = ($stacked ? $step : 0.0) + $insetY;
            $left = ($stacked ? 0.0 : $step) + $insetX;
            if ($template !== null && $placement !== null) {
                $pdf->useTemplate($template, $placement[0] + $left, $placement[1] + $top, $placement[2], $placement[3], $rotate);
            }
            self::drawFields($pdf, $registration, $size[0], $top, $left);
            $slot = ($slot + 1) % $sheet['per_sheet'];
            $pending = true;
            // La marca de retallar es dibuixa quan el full ja és ple: així no
            // hi ha cap maqueta que li passi per sobre i l'amagui.
            if ($twoUp && $slot === 0) {
                self::cutMark($pdf, $sheet);
                $pending = false;
            }
        }
        // L'últim full pot haver quedat a mitges (un nombre senar de dorsals):
        // també ha de dir per on es retalla.
        if ($twoUp && $pending) {
            self::cutMark($pdf, $sheet);
        }
        if (!$registrations) {
            $pdf->addPage($sheet['sheet']);
        }
        return $pdf->output();
    }

    /**
     * Quants dorsals hi caben a cada full, quina mida té el full i per on es
     * parteix.
     *
     * En un A4 hi caben dos dorsals de mitja pàgina, i hi caben de dues
     * maneres segons com sigui el disseny:
     *
     *   - un dorsal apaïsat (un A5 de 210×148 mm) → full A4 vertical, un a
     *     dalt i un a baix, i es retalla per una línia horitzontal;
     *   - un dorsal vertical (un A5 de 148×210 mm) → full A4 apaïsat, un a
     *     cada banda, i es retalla per una línia vertical.
     *
     * Si el disseny és més gran que mig A4 de qualsevol de les dues maneres,
     * se'n continua fent un per full.
     *
     * @param array{0:float,1:float} $size mida del dorsal en mm
     * @return array{sheet:array{0:float,1:float},per_sheet:int,offset:float,axis:string}
     */
    public static function sheet(array $size, bool $force = false): array
    {
        $one = ['sheet' => $size, 'per_sheet' => 1, 'offset' => 0.0, 'axis' => 'y'];
        if (!$force && setting('bib_two_per_sheet', '0') !== '1') {
            return $one;
        }
        [$a4Width, $a4Height] = Pdf::SIZES['a4'];
        $half = $a4Height / 2;
        // Mig mil·límetre de marge per als dissenys fets clavats a la mida.
        $slack = 0.5;
        if ($size[0] <= $a4Width + $slack && $size[1] <= $half + $slack) {
            return ['sheet' => [$a4Width, $a4Height], 'per_sheet' => 2, 'offset' => $half, 'axis' => 'y'];
        }
        if ($size[0] <= $half + $slack && $size[1] <= $a4Width + $slack) {
            return ['sheet' => [$a4Height, $a4Width], 'per_sheet' => 2, 'offset' => $half, 'axis' => 'x'];
        }

        return $one;
    }

    /**
     * Com sortirà el PDF que es descarrega la família: quantes còpies del
     * dorsal porta cada full i quina mida té el full.
     *
     * Serveix per explicar-ho al web abans de descarregar-lo, de manera que
     * l'avís digui sempre el que el document porta de debò.
     *
     * @return array{per_sheet:int,sheet:array,name:string,axis:string,upright:bool}
     */
    public static function familySheet(): array
    {
        // Es recorda mentre dura la petició per no haver de tornar a llegir la
        // maqueta, però si el disseny canvia el resultat es torna a calcular.
        static $cache = [];
        $key = implode('|', [
            (string) setting('bib_template', ''),
            (string) setting('bib_template_page', '1'),
            (string) setting('bib_page_size', 'a5'),
            (string) setting('bib_orientation', 'auto'),
            (string) setting('bib_template_rotate', '0'),
        ]);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $sheet = self::sheet(self::describe()['page'], true);

        return $cache[$key] = [
            'per_sheet' => $sheet['per_sheet'],
            'sheet' => $sheet['sheet'],
            'name' => self::sizeName($sheet['sheet']),
            // Com queden els dos dorsals al full: un sobre l'altre («y») o un
            // al costat de l'altre («x»). Els avisos ho expliquen amb aquestes
            // paraules, i el full va vertical o apaïsat segons això.
            'axis' => $sheet['axis'],
            'upright' => $sheet['sheet'][1] >= $sheet['sheet'][0],
        ];
    }

    /** Nom de la mida d'un full («A4», «A5»…) o les seves mides en mil·límetres. */
    public static function sizeName(array $size): string
    {
        foreach (Pdf::SIZES as $key => $known) {
            if (abs($size[0] - $known[0]) < 1 && abs($size[1] - $known[1]) < 1) {
                return strtoupper($key);
            }
            // La mateixa mida girada: un A5 apaïsat continua sent un A5.
            if (abs($size[0] - $known[1]) < 1 && abs($size[1] - $known[0]) < 1) {
                return strtoupper($key);
            }
        }

        return round($size[0]) . ' × ' . round($size[1]) . ' mm';
    }

    /**
     * Marca per on s'ha de retallar el full: una línia de punts d'una banda a
     * l'altra amb unes tisores al començament.
     *
     * Va horitzontal quan els dos dorsals estan un sobre l'altre i vertical
     * quan estan de costat.
     *
     * @param array{sheet:array{0:float,1:float},offset:float,axis:string} $sheet
     */
    private static function cutMark(Pdf $pdf, array $sheet): void
    {
        $at = $sheet['offset'];
        [$width, $height] = $sheet['sheet'];
        // Una franja en blanc a banda i banda de la línia: encara que els dos
        // dorsals s'acabin tocant, queden clarament separats i la línia no passa
        // per sobre de cap disseny.
        $band = 3.2;
        $pdf->setColorHex('#ffffff');
        $pdf->setFont('helvetica', 7);
        if ($sheet['axis'] === 'y') {
            $pdf->rect(0, $at - $band, $width, $band * 2, 'F');
            $pdf->setStrokeColor(120, 130, 122);
            self::scissors($pdf, 13.0, $at, 2.0);
            $pdf->dashedLine(19.0, $at, $width - 30.0, $at);
            $pdf->setColorHex('#78827a');
            $pdf->text($width - 8.0, $at + 1.2, 'Retalleu per aquí', ['align' => 'right']);
        } else {
            $pdf->rect($at - $band, 0, $band * 2, $height, 'F');
            $pdf->setStrokeColor(120, 130, 122);
            self::scissors($pdf, $at, 13.0, 2.0, true);
            $pdf->dashedLine($at, 19.0, $at, $height - 30.0);
            $pdf->setColorHex('#78827a');
            // Girat 90°, que és l'única manera que càpiga al costat de la línia.
            $pdf->text($at - 1.2, $height - 8.0, 'Retalleu per aquí', ['angle' => 90]);
        }
        $pdf->setStrokeColor(0, 0, 0);
    }

    /**
     * Unes tisores petites: dues fulles creuades i dues anelles.
     * Apunten cap on va la línia de retallar.
     */
    private static function scissors(Pdf $pdf, float $x, float $y, float $size, bool $vertical = false): void
    {
        $blade = $size * 1.9;
        if ($vertical) {
            $pdf->line($x, $y - $size * 0.2, $x - $size * 1.1, $y + $blade, 0.28);
            $pdf->line($x, $y - $size * 0.2, $x + $size * 1.1, $y + $blade, 0.28);
            $pdf->circle($x - $size * 0.62, $y - $size * 0.75, $size * 0.6, 'D', 0.28);
            $pdf->circle($x + $size * 0.62, $y - $size * 0.75, $size * 0.6, 'D', 0.28);
            return;
        }
        $pdf->line($x - $size * 0.2, $y, $x + $blade, $y - $size * 1.1, 0.28);
        $pdf->line($x - $size * 0.2, $y, $x + $blade, $y + $size * 1.1, 0.28);
        $pdf->circle($x - $size * 0.75, $y - $size * 0.62, $size * 0.6, 'D', 0.28);
        $pdf->circle($x - $size * 0.75, $y + $size * 0.62, $size * 0.6, 'D', 0.28);
    }

    /**
     * Dorsal d'exemple per previsualitzar el disseny.
     *
     * Ensenya el full tal com sortirà de la impressora: si al full hi van dos
     * dorsals, la prova en porta dos i la línia de retallar, que és el que
     * es vol comprovar abans de gastar paper.
     */
    public static function sample(): string
    {
        $sample = [
            'first_name' => 'Laia',
            'last_name' => 'Ferrer Miró',
            'bib_number' => 1,
            'category_name' => 'Aleví',
            'category_gender' => 'femeni',
            'category_year_from' => 2016,
            'category_year_to' => 2017,
            'school' => 'Escola La Granada',
        ];

        return self::pdf(array_fill(0, max(1, self::sheet(self::describe()['page'])['per_sheet']), $sample));
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

    private static function drawFields(Pdf $pdf, array $registration, float $pageWidth, float $top = 0.0, float $left = 0.0): void
    {
        self::drawField($pdf, 'bib_number', self::number($registration), $pageWidth, $top, $left);
        self::drawField($pdf, 'bib_name', self::name($registration), $pageWidth, $top, $left);
        self::drawField($pdf, 'bib_category', self::category($registration), $pageWidth, $top, $left);
        self::drawField($pdf, 'bib_school', trim((string) ($registration['school'] ?? '')), $pageWidth, $top, $left);
    }

    private static function drawField(Pdf $pdf, string $prefix, string $value, float $pageWidth, float $top = 0.0, float $left = 0.0): void
    {
        // Sense valor per defecte explícit: així s'agafa el de l'esquema de configuració.
        if ($value === '' || $value === '—' || (string) setting($prefix . '_show') !== '1') {
            return;
        }
        // Les posicions es configuren dins del dorsal; «top» i «left» diuen on
        // comença el dorsal dins del full quan no l'ocupa sencer.
        $x = (float) setting($prefix . '_x') + $left;
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
