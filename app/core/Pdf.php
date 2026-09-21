<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Generador de PDF en PHP pur (sense dependències externes).
 *
 * Admet text amb les tipografies estàndard, colors, línies, rectangles,
 * imatges JPEG i PNG, i pàgines importades d'altres PDF (vegeu PdfImport),
 * que és el que permet fer servir una maqueta per als dorsals.
 *
 * Totes les mesures públiques són en mil·límetres, amb l'origen a la
 * cantonada superior esquerra de la pàgina.
 */
class Pdf
{
    public const MM = 72 / 25.4;

    /** Mides de pàgina en mil·límetres. */
    public const SIZES = [
        'a3' => [297.0, 420.0],
        'a4' => [210.0, 297.0],
        'a5' => [148.0, 210.0],
        'a6' => [105.0, 148.0],
        'letter' => [215.9, 279.4],
    ];

    /** @var array<int,string> cossos dels objectes indexats pel seu número */
    private array $objects = [];
    private int $objectCount = 0;

    /** @var array<int,array> pàgines */
    private array $pages = [];
    private int $current = -1;

    /** @var array<string,int> tipografies utilitzades => número d'objecte */
    private array $fonts = [];
    private static ?array $widths = null;

    private string $font = 'helvetica';
    private float $fontSize = 12.0;
    private array $fill = [0, 0, 0];
    private array $stroke = [0, 0, 0];
    private array $info;
    private bool $compress = true;

    public function __construct(array $info = [])
    {
        $this->info = $info + [
            'title' => '',
            'author' => '',
            'creator' => 'Cros Escolar La Granada',
        ];
        $this->compress = function_exists('gzcompress');
    }

    /* ------------------------------------------------------------ Pàgines */

    /**
     * Afegeix una pàgina nova.
     * @param string|array $size nom («a4», «a5»…), o [amplada, alçada] en mm
     */
    /**
     * Canvia la mida de la pàgina actual sense perdre'n el contingut.
     * Serveix quan la mida definitiva no es coneix fins després de crear-la.
     *
     * @param array{0:float,1:float}|string $size
     */
    public function resizePage($size): void
    {
        if ($this->current < 0) {
            return;
        }
        $dimensions = is_string($size)
            ? (self::SIZES[strtolower($size)] ?? self::SIZES['a4'])
            : [(float) $size[0], (float) $size[1]];
        $this->pages[$this->current]['width'] = $dimensions[0];
        $this->pages[$this->current]['height'] = $dimensions[1];
    }

    public function addPage($size = 'a4', string $orientation = 'portrait'): void
    {
        if (is_string($size)) {
            $dimensions = self::SIZES[strtolower($size)] ?? self::SIZES['a4'];
        } else {
            $dimensions = [(float) $size[0], (float) $size[1]];
        }
        if (strtolower($orientation) === 'landscape' && $dimensions[0] < $dimensions[1]) {
            $dimensions = [$dimensions[1], $dimensions[0]];
        }
        $this->pages[] = [
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'content' => '',
            'xobjects' => [],
        ];
        $this->current = count($this->pages) - 1;
    }

    public function pageWidth(): float
    {
        return $this->pages[$this->current]['width'] ?? 210.0;
    }

    public function pageHeight(): float
    {
        return $this->pages[$this->current]['height'] ?? 297.0;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /* ------------------------------------------------------------- Estils */

    /** Tipografia: helvetica, helvetica-bold, helvetica-oblique, times, times-bold. */
    public function setFont(string $font, float $size): void
    {
        $font = strtolower($font);
        $this->font = isset(self::widths()[$font]) ? $font : 'helvetica';
        $this->fontSize = $size;
    }

    public function setColor(int $r, int $g, int $b): void
    {
        $this->fill = [$r, $g, $b];
    }

    /** Accepta «#rrggbb» o «#rgb». */
    public function setColorHex(string $hex): void
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return;
        }
        $this->setColor((int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2)));
    }

    public function setStrokeColor(int $r, int $g, int $b): void
    {
        $this->stroke = [$r, $g, $b];
    }

    /* --------------------------------------------------------------- Text */

    /**
     * Escriu text. La coordenada Y és la línia de base del text.
     * @param array{align?:string,width?:float,max_width?:float} $options
     *   align: left|center|right (amb «width» o dins de la pàgina)
     *   max_width: si el text és més ample, se'n redueix la mida
     */
    public function text(float $x, float $y, string $text, array $options = []): void
    {
        $text = $this->encode($text);
        if ($text === '') {
            return;
        }
        $size = $this->fontSize;
        $maxWidth = (float) ($options['max_width'] ?? 0);
        if ($maxWidth > 0) {
            $width = $this->rawWidth($text, $size);
            if ($width > $maxWidth) {
                $size = max(4.0, $size * $maxWidth / $width);
            }
        }
        $align = strtolower((string) ($options['align'] ?? 'left'));
        $boxWidth = (float) ($options['width'] ?? 0);
        $textWidth = $this->rawWidth($text, $size);
        if ($align === 'center') {
            $x = $boxWidth > 0 ? $x + ($boxWidth - $textWidth) / 2 : $x - $textWidth / 2;
        } elseif ($align === 'right') {
            $x = $boxWidth > 0 ? $x + $boxWidth - $textWidth : $x - $textWidth;
        }

        $this->useFont();
        $this->write(sprintf(
            "BT /F%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n",
            self::fontKey($this->font),
            $size,
            $this->colorString($this->fill),
            $x * self::MM,
            ($this->pageHeight() - $y) * self::MM,
            $this->escape($text)
        ));
    }

    /** Amplada d'un text en mil·límetres. */
    public function textWidth(string $text, ?float $size = null): float
    {
        return $this->rawWidth($this->encode($text), $size ?? $this->fontSize) ;
    }

    /**
     * Escriu un paràgraf dins d'una amplada, amb salts de línia automàtics.
     * Retorna la Y de la línia següent.
     */
    public function textBlock(float $x, float $y, float $width, string $text, float $lineHeight = 1.35): float
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($this->textWidth($candidate) > $width && $line !== '') {
                $this->text($x, $y, $line);
                $y += $this->fontSize / self::MM * $lineHeight;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $this->text($x, $y, $line);
            $y += $this->fontSize / self::MM * $lineHeight;
        }
        return $y;
    }

    /* ------------------------------------------------------------ Dibuixos */

    public function line(float $x1, float $y1, float $x2, float $y2, float $thickness = 0.2): void
    {
        $this->write(sprintf(
            "%.2F w %s RG %.2F %.2F m %.2F %.2F l S\n",
            $thickness * self::MM,
            $this->colorString($this->stroke),
            $x1 * self::MM,
            ($this->pageHeight() - $y1) * self::MM,
            $x2 * self::MM,
            ($this->pageHeight() - $y2) * self::MM
        ));
    }

    public function rect(float $x, float $y, float $width, float $height, string $style = 'F', float $thickness = 0.2): void
    {
        $operator = match (strtoupper($style)) {
            'F' => 'f',
            'D' => 'S',
            default => 'B',
        };
        $this->write(sprintf(
            "%.2F w %s rg %s RG %.2F %.2F %.2F %.2F re %s\n",
            $thickness * self::MM,
            $this->colorString($this->fill),
            $this->colorString($this->stroke),
            $x * self::MM,
            ($this->pageHeight() - $y - $height) * self::MM,
            $width * self::MM,
            $height * self::MM,
            $operator
        ));
    }

    /* ------------------------------------------------- Imatges i maquetes */

    /** Insereix una imatge JPEG o PNG. Si no s'indica l'alçada, es manté la proporció. */
    public function image(string $file, float $x, float $y, float $width, ?float $height = null): void
    {
        $data = is_file($file) ? (string) file_get_contents($file) : $file;
        $image = $this->parseImage($data);
        if (!$image) {
            return;
        }
        $height = $height ?? $width * $image['height'] / max(1, $image['width']);
        $name = 'I' . $image['object'];
        $this->pages[$this->current]['xobjects'][$name] = $image['object'];
        $this->write(sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
            $width * self::MM,
            $height * self::MM,
            $x * self::MM,
            ($this->pageHeight() - $y - $height) * self::MM,
            $name
        ));
    }

    /**
     * Dibuixa una plantilla importada (vegeu PdfImport::page()).
     * Si no s'indiquen mides, s'utilitzen les de la plantilla. Amb $rotate (0, 90,
     * 180 o 270 graus en sentit antihorari) la plantilla es gira per omplir
     * l'espai indicat, que ja ha de tenir les mides de la plantilla girada.
     */
    public function useTemplate(array $template, float $x = 0, float $y = 0, ?float $width = null, ?float $height = null, int $rotate = 0): void
    {
        $rotate = ((int) round($rotate / 90) * 90 % 360 + 360) % 360;
        $turned = in_array($rotate, [90, 270], true);
        $width = $width ?? ($turned ? $template['height'] : $template['width']);
        $height = $height ?? ($turned ? $template['width'] : $template['height']);

        // Mides de la plantilla i de l'espai de destí, en punts.
        $u = max(0.01, (float) $template['width']) * self::MM;
        $v = max(0.01, (float) $template['height']) * self::MM;
        $w = $width * self::MM;
        $h = $height * self::MM;
        $left = $x * self::MM;
        $bottom = ($this->pageHeight() - $y - $height) * self::MM;

        // Matriu que situa la plantilla dins de l'espai indicat amb el gir demanat.
        $matrix = match ($rotate) {
            90 => [0, $h / $u, -$w / $v, 0, $left + $w, $bottom],
            180 => [-$w / $u, 0, 0, -$h / $v, $left + $w, $bottom + $h],
            270 => [0, -$h / $u, $w / $v, 0, $left, $bottom + $h],
            default => [$w / $u, 0, 0, $h / $v, $left, $bottom],
        };

        $name = 'T' . $template['object'];
        $this->pages[$this->current]['xobjects'][$name] = $template['object'];
        $this->write(sprintf(
            "q %.4F %.4F %.4F %.4F %.2F %.2F cm /%s Do Q\n",
            $matrix[0],
            $matrix[1],
            $matrix[2],
            $matrix[3],
            $matrix[4],
            $matrix[5],
            $name
        ));
    }

    /* ------------------------------------------------------------- Sortida */

    /** Genera el document i el retorna com a cadena binària. */
    public function output(): string
    {
        if (!$this->pages) {
            $this->addPage();
        }

        $pageObjects = [];
        $pagesObject = $this->reserve();

        foreach ($this->pages as $page) {
            $contentObject = $this->addStream($page['content']);
            $resources = "/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]";
            if ($this->fonts) {
                $fonts = [];
                foreach ($this->fonts as $key => $object) {
                    $fonts[] = '/F' . self::fontKey($key) . ' ' . $object . ' 0 R';
                }
                $resources .= " /Font <<" . implode(' ', $fonts) . ">>";
            }
            if ($page['xobjects']) {
                $xobjects = [];
                foreach ($page['xobjects'] as $name => $object) {
                    $xobjects[] = '/' . $name . ' ' . $object . ' 0 R';
                }
                $resources .= " /XObject <<" . implode(' ', $xobjects) . ">>";
            }
            $pageObjects[] = $this->add(sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << %s >> /Contents %d 0 R >>",
                $pagesObject,
                $page['width'] * self::MM,
                $page['height'] * self::MM,
                $resources,
                $contentObject
            ));
        }

        $kids = implode(' ', array_map(fn ($n) => $n . ' 0 R', $pageObjects));
        $this->set($pagesObject, sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', $kids, count($pageObjects)));

        $infoObject = $this->add(sprintf(
            '<< /Title (%s) /Author (%s) /Creator (%s) /Producer (%s) /CreationDate (D:%s) >>',
            $this->escape($this->encode((string) $this->info['title'])),
            $this->escape($this->encode((string) $this->info['author'])),
            $this->escape($this->encode((string) $this->info['creator'])),
            $this->escape($this->encode('Cros Escolar La Granada')),
            date('YmdHis')
        ));
        $catalog = $this->add(sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesObject));

        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        for ($i = 1; $i <= $this->objectCount; $i++) {
            $offsets[$i] = strlen($out);
            $out .= $i . " 0 obj\n" . ($this->objects[$i] ?? '<< >>') . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($this->objectCount + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $this->objectCount; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $out .= sprintf(
            "trailer\n<< /Size %d /Root %d 0 R /Info %d 0 R /ID [<%s> <%s>] >>\nstartxref\n%d\n%%%%EOF",
            $this->objectCount + 1,
            $catalog,
            $infoObject,
            $id = md5($out . microtime()),
            $id,
            $xref
        );
        return $out;
    }

    /** Desa el document en un fitxer. */
    public function save(string $path): bool
    {
        return (bool) file_put_contents($path, $this->output());
    }

    /** Envia el document al navegador. */
    public function send(string $filename, bool $download = true): void
    {
        $data = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $data;
    }

    /* ---------------------------------------------- Gestió dels objectes */

    /** Reserva un número d'objecte per omplir-lo més tard. */
    public function reserve(): int
    {
        $this->objects[++$this->objectCount] = '<< >>';
        return $this->objectCount;
    }

    /** Afegeix un objecte i retorna el seu número. */
    public function add(string $body): int
    {
        $this->objects[++$this->objectCount] = $body;
        return $this->objectCount;
    }

    /** Substitueix el cos d'un objecte reservat. */
    public function set(int $object, string $body): void
    {
        $this->objects[$object] = $body;
    }

    /** Afegeix un objecte de flux, comprimint-lo si és possible. */
    public function addStream(string $content, string $extra = '', bool $allowCompression = true): int
    {
        $filter = '';
        if ($allowCompression && $this->compress && $content !== '') {
            $compressed = gzcompress($content, 6);
            if ($compressed !== false && strlen($compressed) < strlen($content)) {
                $content = $compressed;
                $filter = ' /Filter /FlateDecode';
            }
        }
        return $this->add(sprintf(
            "<< /Length %d%s%s >>\nstream\n%s\nendstream",
            strlen($content),
            $filter,
            $extra !== '' ? ' ' . $extra : '',
            $content
        ));
    }

    /* -------------------------------------------------------- Ajudes internes */

    private function write(string $operators): void
    {
        if ($this->current < 0) {
            $this->addPage();
        }
        $this->pages[$this->current]['content'] .= $operators;
    }

    /** Nom del recurs de la tipografia dins del PDF (només lletres). */
    private static function fontKey(string $font): string
    {
        return preg_replace('/[^a-z]/', '', $font) ?: 'helvetica';
    }

    private function useFont(): void
    {
        if (isset($this->fonts[$this->font])) {
            return;
        }
        $base = match ($this->font) {
            'helvetica-bold' => 'Helvetica-Bold',
            'helvetica-oblique' => 'Helvetica-Oblique',
            'times' => 'Times-Roman',
            'times-bold' => 'Times-Bold',
            default => 'Helvetica',
        };
        $this->fonts[$this->font] = $this->add(sprintf(
            '<< /Type /Font /Subtype /Type1 /BaseFont /%s /Encoding /WinAnsiEncoding >>',
            $base
        ));
    }

    private static function widths(): array
    {
        if (self::$widths === null) {
            self::$widths = require __DIR__ . '/font-widths.php';
        }
        return self::$widths;
    }

    /** Amplada en mm d'un text ja codificat. */
    private function rawWidth(string $text, float $size): float
    {
        $table = self::widths()[$this->font] ?? self::widths()['helvetica'];
        $total = 0;
        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            $total += $table[ord($text[$i])] ?? 500;
        }
        return $total / 1000 * $size / self::MM;
    }

    /** Converteix d'UTF-8 a la codificació del PDF (WinAnsi / cp1252). */
    private function encode(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $converted = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $text);
        if ($converted === false) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        }
        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) ?? '' : $converted;
    }

    private function escape(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => '\\n']);
    }

    private function colorString(array $color): string
    {
        return sprintf('%.3F %.3F %.3F', $color[0] / 255, $color[1] / 255, $color[2] / 255);
    }

    /** Crea l'objecte d'una imatge JPEG o PNG. */
    private function parseImage(string $data): ?array
    {
        if (str_starts_with($data, "\xFF\xD8")) {
            return $this->parseJpeg($data);
        }
        if (str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            return $this->parsePng($data);
        }
        return null;
    }

    private function parseJpeg(string $data): ?array
    {
        $info = @getimagesizefromstring($data);
        if (!$info) {
            return null;
        }
        $channels = $info['channels'] ?? 3;
        $space = $channels === 1 ? '/DeviceGray' : ($channels === 4 ? '/DeviceCMYK' : '/DeviceRGB');
        $object = $this->add(sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent %d /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            $info[0],
            $info[1],
            $space,
            $info['bits'] ?? 8,
            strlen($data),
            $data
        ));
        return ['object' => $object, 'width' => $info[0], 'height' => $info[1]];
    }

    /** Converteix el PNG amb GD per admetre qualsevol variant (paleta, alfa, entrellaçat). */
    private function parsePng(string $data): ?array
    {
        $info = @getimagesizefromstring($data);
        if (!$info || !function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($data);
        if (!$image) {
            return null;
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $pixels = '';
        $alpha = '';
        $hasAlpha = false;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $pixels .= chr(($color >> 16) & 0xFF) . chr(($color >> 8) & 0xFF) . chr($color & 0xFF);
                $transparency = ($color >> 24) & 0x7F;
                if ($transparency > 0) {
                    $hasAlpha = true;
                }
                $alpha .= chr(255 - (int) round($transparency * 255 / 127));
            }
        }
        imagedestroy($image);

        $smask = '';
        if ($hasAlpha) {
            $maskObject = $this->addStream($alpha, sprintf(
                '/Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8',
                $width,
                $height
            ));
            $smask = sprintf(' /SMask %d 0 R', $maskObject);
        }
        $object = $this->addStream($pixels, sprintf(
            '/Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8%s',
            $width,
            $height,
            $smask
        ));
        return ['object' => $object, 'width' => $width, 'height' => $height];
    }
}
