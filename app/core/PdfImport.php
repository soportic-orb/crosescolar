<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Importa una pàgina d'un PDF existent perquè es pugui fer servir com a fons
 * (la maqueta dels dorsals). Fa d'analitzador de PDF: llegeix la taula
 * d'objectes (clàssica o en flux), els fluxos d'objectes i els filtres més
 * habituals, i copia la pàgina al document de sortida com a XObject.
 *
 * No admet PDF protegits amb contrasenya: en aquest cas mostra un avís clar.
 */
class PdfImport
{
    private string $raw;
    /** @var array<int,array{offset?:int,stream?:int,index?:int}> */
    private array $offsets = [];
    /** @var array<int,mixed> */
    private array $cache = [];
    /** @var array<int,array> objectes ja copiats: origen => destí */
    private array $copied = [];
    private array $trailer = [];
    private array $pages = [];

    public function __construct(string $fileOrBytes)
    {
        $raw = is_file($fileOrBytes) ? (string) file_get_contents($fileOrBytes) : $fileOrBytes;
        if (!str_starts_with($raw, '%PDF-')) {
            // Alguns fitxers porten brossa al davant.
            $start = strpos($raw, '%PDF-');
            if ($start === false) {
                throw new \RuntimeException('El fitxer no és un PDF vàlid.');
            }
            $raw = substr($raw, $start);
        }
        $this->raw = $raw;
        $this->readXref();
        if (isset($this->trailer['/Encrypt'])) {
            throw new \RuntimeException('El PDF està protegit amb contrasenya o encriptat. Torneu a desar-lo sense protecció.');
        }
        $this->readPages();
    }

    /** Nombre de pàgines del document. */
    public function pageCount(): int
    {
        return count($this->pages);
    }

    /** Mida d'una pàgina en mil·límetres: ['width' => …, 'height' => …]. */
    public function pageSize(int $number = 1): array
    {
        $page = $this->pages[$number - 1] ?? null;
        if (!$page) {
            throw new \RuntimeException('La pàgina ' . $number . ' no existeix al PDF.');
        }
        [$width, $height] = $this->boxSize($page);
        return ['width' => $width / Pdf::MM, 'height' => $height / Pdf::MM];
    }

    /**
     * Copia una pàgina al document de sortida.
     * @return array{object:int,width:float,height:float} mides en mil·límetres
     */
    public function page(Pdf $pdf, int $number = 1): array
    {
        $page = $this->pages[$number - 1] ?? null;
        if (!$page) {
            throw new \RuntimeException('La pàgina ' . $number . ' no existeix al PDF.');
        }

        $box = $this->normalizedBox($page);
        [$width, $height] = $this->boxSize($page);
        $rotation = ((int) $this->numberValue($page['/Rotate'] ?? 0) % 360 + 360) % 360;

        $content = $this->pageContent($page);
        $resources = isset($page['/Resources'])
            ? $this->copyValue($page['/Resources'], $pdf)
            : '<< >>';

        // La matriu situa la caixa a l'origen i aplica el gir de la pàgina.
        $matrix = match ($rotation) {
            90 => sprintf('[0 1 -1 0 %.4F %.4F]', $box[3], -$box[0]),
            180 => sprintf('[-1 0 0 -1 %.4F %.4F]', $box[2], $box[3]),
            270 => sprintf('[0 -1 1 0 %.4F %.4F]', -$box[1], $box[2]),
            default => sprintf('[1 0 0 1 %.4F %.4F]', -$box[0], -$box[1]),
        };
        $bbox = sprintf('[%.4F %.4F %.4F %.4F]', $box[0], $box[1], $box[2], $box[3]);

        $object = $pdf->addStream($content, sprintf(
            '/Type /XObject /Subtype /Form /FormType 1 /BBox %s /Matrix %s /Resources %s',
            $bbox,
            $matrix,
            $resources
        ));

        return [
            'object' => $object,
            'width' => $width / Pdf::MM,
            'height' => $height / Pdf::MM,
        ];
    }

    /* ------------------------------------------------ Estructura del fitxer */

    private function readXref(): void
    {
        $position = strrpos($this->raw, 'startxref');
        if ($position === false) {
            $this->rebuildXref();
            return;
        }
        $offset = (int) trim(substr($this->raw, $position + 9, 40));
        $seen = [];
        while ($offset > 0 && $offset < strlen($this->raw) && !isset($seen[$offset])) {
            $seen[$offset] = true;
            $next = $this->readXrefSection($offset);
            $offset = $next;
        }
        if (!$this->offsets || !isset($this->trailer['/Root'])) {
            $this->rebuildXref();
        }
    }

    /** Llegeix una secció de la taula d'objectes i retorna l'anterior (/Prev). */
    private function readXrefSection(int $offset): int
    {
        $position = $offset;
        $this->skipWhitespace($position);

        if (substr($this->raw, $position, 4) === 'xref') {
            $position += 4;
            // Taula clàssica
            while (true) {
                $this->skipWhitespace($position);
                if (substr($this->raw, $position, 7) === 'trailer') {
                    $position += 7;
                    $this->skipWhitespace($position);
                    $trailer = $this->parseValue($position);
                    $dict = $trailer['dict'] ?? [];
                    foreach ($dict as $key => $value) {
                        if (!isset($this->trailer[$key])) {
                            $this->trailer[$key] = $value;
                        }
                    }
                    // Fitxers híbrids: també porten una taula en flux.
                    if (isset($dict['/XRefStm'])) {
                        $this->readXrefSection((int) $this->numberValue($dict['/XRefStm']));
                    }
                    return isset($dict['/Prev']) ? (int) $this->numberValue($dict['/Prev']) : 0;
                }
                if (!preg_match('/\G(\d+)\s+(\d+)/', $this->raw, $m, 0, $position)) {
                    return 0;
                }
                $start = (int) $m[1];
                $count = (int) $m[2];
                $position += strlen($m[0]);
                $this->skipWhitespace($position);
                for ($i = 0; $i < $count; $i++) {
                    $entry = substr($this->raw, $position, 20);
                    if (preg_match('/(\d{10})\s+(\d{5})\s+([nf])/', $entry, $em)) {
                        $number = $start + $i;
                        if ($em[3] === 'n' && !isset($this->offsets[$number])) {
                            $this->offsets[$number] = ['offset' => (int) $em[1]];
                        }
                    }
                    $position += 20;
                    // Algunes taules fan servir salts de línia d'una sola posició.
                    if (!preg_match('/^\d{10}/', substr($this->raw, $position, 10)) && $i + 1 < $count) {
                        $this->skipWhitespace($position);
                    }
                }
            }
        }

        // Taula en flux (PDF 1.5 o superior)
        $object = $this->parseIndirectAt($position);
        if (!$object || !isset($object['dict'])) {
            return 0;
        }
        $dict = $object['dict'];
        foreach (['/Root', '/Info', '/Encrypt', '/ID', '/Size'] as $key) {
            if (isset($dict[$key]) && !isset($this->trailer[$key])) {
                $this->trailer[$key] = $dict[$key];
            }
        }
        $data = $this->decodeStream($object);
        $w = array_map(fn ($v) => (int) $this->numberValue($v), $dict['/W']['arr'] ?? []);
        if (count($w) < 3) {
            return 0;
        }
        $index = [];
        foreach ($dict['/Index']['arr'] ?? [] as $value) {
            $index[] = (int) $this->numberValue($value);
        }
        if (!$index) {
            $index = [0, (int) $this->numberValue($dict['/Size'] ?? 0)];
        }
        $rowLength = array_sum($w);
        $position = 0;
        for ($section = 0; $section < count($index); $section += 2) {
            $start = $index[$section];
            $count = $index[$section + 1] ?? 0;
            for ($i = 0; $i < $count; $i++) {
                if ($position + $rowLength > strlen($data)) {
                    break 2;
                }
                $fields = [];
                foreach ($w as $size) {
                    $value = 0;
                    for ($b = 0; $b < $size; $b++) {
                        $value = ($value << 8) | ord($data[$position++]);
                    }
                    $fields[] = $size === 0 ? null : $value;
                }
                $type = $fields[0] ?? 1;
                if ($type === null) {
                    $type = 1;
                }
                $number = $start + $i;
                if (isset($this->offsets[$number])) {
                    continue;
                }
                if ($type === 1) {
                    $this->offsets[$number] = ['offset' => (int) $fields[1]];
                } elseif ($type === 2) {
                    $this->offsets[$number] = ['stream' => (int) $fields[1], 'index' => (int) $fields[2]];
                }
            }
        }
        return isset($dict['/Prev']) ? (int) $this->numberValue($dict['/Prev']) : 0;
    }

    /** Reconstrueix la taula d'objectes llegint tot el fitxer (fitxers malmesos). */
    private function rebuildXref(): void
    {
        if (preg_match_all('/(\d+)\s+(\d+)\s+obj\b/', $this->raw, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $match) {
                $this->offsets[(int) $matches[1][$i][0]] = ['offset' => (int) $match[1]];
            }
        }
        if (!isset($this->trailer['/Root']) && preg_match_all('/trailer\s*<</', $this->raw, $m, PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($m[0]) as $match) {
                $position = (int) $match[1] + 7;
                $value = $this->parseValue($position);
                if (isset($value['dict']['/Root'])) {
                    $this->trailer += $value['dict'];
                    break;
                }
            }
        }
        if (!isset($this->trailer['/Root'])) {
            // Busca el catàleg directament.
            foreach (array_keys($this->offsets) as $number) {
                $object = $this->getObject($number);
                if (isset($object['dict']['/Type']['name']) && $object['dict']['/Type']['name'] === 'Catalog') {
                    $this->trailer['/Root'] = ['ref' => [$number, 0]];
                    break;
                }
            }
        }
        if (!isset($this->trailer['/Root'])) {
            throw new \RuntimeException('No s\'ha pogut llegir l\'estructura del PDF.');
        }
    }

    /** Llegeix l'arbre de pàgines amb els atributs heretats. */
    private function readPages(): void
    {
        $root = $this->resolve($this->trailer['/Root'] ?? null);
        $pagesRef = $root['dict']['/Pages'] ?? null;
        $inherited = ['/Resources', '/MediaBox', '/CropBox', '/Rotate'];
        $walk = function ($nodeRef, array $inheritedValues) use (&$walk, $inherited): void {
            $node = $this->resolve($nodeRef);
            $dict = $node['dict'] ?? [];
            foreach ($inherited as $key) {
                if (isset($dict[$key])) {
                    $inheritedValues[$key] = $dict[$key];
                }
            }
            $type = $dict['/Type']['name'] ?? '';
            if ($type === 'Page' || (!isset($dict['/Kids']) && isset($dict['/Contents']))) {
                $this->pages[] = $dict + $inheritedValues;
                return;
            }
            foreach ($dict['/Kids']['arr'] ?? [] as $kid) {
                if (count($this->pages) > 500) {
                    return;
                }
                $walk($kid, $inheritedValues);
            }
        };
        if ($pagesRef !== null) {
            $walk($pagesRef, []);
        }
        if (!$this->pages) {
            throw new \RuntimeException('El PDF no conté cap pàgina llegible.');
        }
    }

    /** Contingut complet d'una pàgina, ja descomprimit. */
    private function pageContent(array $page): string
    {
        $contents = $page['/Contents'] ?? null;
        if ($contents === null) {
            return '';
        }
        $resolved = $this->resolve($contents);
        $parts = [];
        if (isset($resolved['arr'])) {
            foreach ($resolved['arr'] as $item) {
                $stream = $this->resolve($item);
                if (isset($stream['stream'])) {
                    $parts[] = $this->decodeStream($stream);
                }
            }
        } elseif (isset($resolved['stream'])) {
            $parts[] = $this->decodeStream($resolved);
        }
        return implode("\n", $parts);
    }

    /* ------------------------------------------------------ Còpia d'objectes */

    /** Converteix un valor de l'origen en text PDF, copiant els objectes referenciats. */
    private function copyValue($value, Pdf $pdf): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.') ?: '0';
        }
        if (isset($value['name'])) {
            return '/' . $value['name'];
        }
        if (isset($value['str'])) {
            return '(' . strtr($value['str'], ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r']) . ')';
        }
        if (isset($value['hex'])) {
            return '<' . $value['hex'] . '>';
        }
        if (isset($value['arr'])) {
            $items = array_map(fn ($item) => $this->copyValue($item, $pdf), $value['arr']);
            return '[' . implode(' ', $items) . ']';
        }
        if (isset($value['ref'])) {
            return $this->copyObject((int) $value['ref'][0], $pdf) . ' 0 R';
        }
        if (isset($value['dict'])) {
            $parts = [];
            foreach ($value['dict'] as $key => $item) {
                $parts[] = $key . ' ' . $this->copyValue($item, $pdf);
            }
            return '<< ' . implode(' ', $parts) . ' >>';
        }
        return 'null';
    }

    /** Copia un objecte indirecte i retorna el número que té al document de sortida. */
    private function copyObject(int $number, Pdf $pdf): int
    {
        if (isset($this->copied[$number])) {
            return $this->copied[$number];
        }
        $target = $pdf->reserve();
        $this->copied[$number] = $target;

        $object = $this->getObject($number);
        if ($object === null) {
            $pdf->set($target, 'null');
            return $target;
        }

        if (isset($object['stream'])) {
            $dict = $object['dict'];
            unset($dict['/Length']);
            $data = $object['stream'];
            $parts = [];
            foreach ($dict as $key => $item) {
                $parts[] = $key . ' ' . $this->copyValue($item, $pdf);
            }
            $parts[] = '/Length ' . strlen($data);
            $pdf->set($target, '<< ' . implode(' ', $parts) . " >>\nstream\n" . $data . "\nendstream");
            return $target;
        }

        $pdf->set($target, $this->copyValue($object, $pdf));
        return $target;
    }

    /* --------------------------------------------------- Lectura d'objectes */

    /** Objecte indirecte pel seu número. */
    private function getObject(int $number)
    {
        if (array_key_exists($number, $this->cache)) {
            return $this->cache[$number];
        }
        $this->cache[$number] = null; // evita bucles
        $entry = $this->offsets[$number] ?? null;
        if (!$entry) {
            return null;
        }
        if (isset($entry['offset'])) {
            $value = $this->parseIndirectAt($entry['offset'], $number);
        } else {
            $value = $this->objectFromStream((int) $entry['stream'], (int) $entry['index'], $number);
        }
        return $this->cache[$number] = $value;
    }

    /** Llegeix «n g obj … endobj» a una posició concreta. */
    private function parseIndirectAt(int $offset, ?int $expected = null)
    {
        if ($offset <= 0 || $offset >= strlen($this->raw)) {
            return null;
        }
        $position = $offset;
        $this->skipWhitespace($position);
        if (!preg_match('/\G(\d+)\s+(\d+)\s+obj/', $this->raw, $m, 0, $position)) {
            // Alguns fitxers tenen desplaçaments lleugerament desviats.
            if ($expected !== null && preg_match('/(?<![0-9])' . $expected . '\s+\d+\s+obj/', $this->raw, $m2, PREG_OFFSET_CAPTURE)) {
                $position = (int) $m2[0][1];
                if (!preg_match('/\G(\d+)\s+(\d+)\s+obj/', $this->raw, $m, 0, $position)) {
                    return null;
                }
            } else {
                return null;
            }
        }
        $position += strlen($m[0]);
        $value = $this->parseValue($position);

        $this->skipWhitespace($position);
        if (substr($this->raw, $position, 6) === 'stream') {
            $position += 6;
            if (substr($this->raw, $position, 2) === "\r\n") {
                $position += 2;
            } elseif ($this->raw[$position] === "\n" || $this->raw[$position] === "\r") {
                $position += 1;
            }
            $length = isset($value['dict']['/Length']) ? (int) $this->numberValue($value['dict']['/Length']) : 0;
            $data = substr($this->raw, $position, $length);
            // Si la longitud no quadra, es busca «endstream».
            $end = strpos($this->raw, 'endstream', $position);
            if ($length <= 0 || ($end !== false && $end < $position + $length)) {
                $data = $end === false ? '' : rtrim(substr($this->raw, $position, $end - $position), "\r\n");
            }
            $value['stream'] = $data;
        }
        return $value;
    }

    /** Objecte guardat dins d'un flux d'objectes (ObjStm). */
    private function objectFromStream(int $streamNumber, int $index, int $wanted)
    {
        $container = $this->getObject($streamNumber);
        if (!$container || !isset($container['stream'])) {
            return null;
        }
        $data = $this->decodeStream($container);
        $count = (int) $this->numberValue($container['dict']['/N'] ?? 0);
        $first = (int) $this->numberValue($container['dict']['/First'] ?? 0);
        $header = substr($data, 0, $first);
        if (!preg_match_all('/(\d+)\s+(\d+)/', $header, $matches, PREG_SET_ORDER)) {
            return null;
        }
        foreach ($matches as $i => $match) {
            if ($i >= $count) {
                break;
            }
            if ((int) $match[1] !== $wanted && $i !== $index) {
                continue;
            }
            if ((int) $match[1] !== $wanted) {
                continue;
            }
            $position = $first + (int) $match[2];
            return $this->parseValue($position, $data);
        }
        return null;
    }

    /* ----------------------------------------------------------- Analitzador */

    /** Llegeix un valor PDF a partir de la posició indicada. */
    private function parseValue(int &$position, ?string $source = null)
    {
        $raw = $source ?? $this->raw;
        $this->skipWhitespace($position, $raw);
        if ($position >= strlen($raw)) {
            return null;
        }
        $char = $raw[$position];

        if ($char === '<' && ($raw[$position + 1] ?? '') === '<') {
            $position += 2;
            $dict = [];
            while (true) {
                $this->skipWhitespace($position, $raw);
                if (substr($raw, $position, 2) === '>>') {
                    $position += 2;
                    break;
                }
                if ($position >= strlen($raw)) {
                    break;
                }
                if ($raw[$position] !== '/') {
                    $position++;
                    continue;
                }
                $key = '/' . $this->parseName($position, $raw);
                $dict[$key] = $this->parseValue($position, $raw);
            }
            return ['dict' => $dict];
        }

        if ($char === '[') {
            $position++;
            $items = [];
            while (true) {
                $this->skipWhitespace($position, $raw);
                if (($raw[$position] ?? ']') === ']') {
                    $position++;
                    break;
                }
                $items[] = $this->parseValue($position, $raw);
            }
            return ['arr' => $items];
        }

        if ($char === '/') {
            return ['name' => $this->parseName($position, $raw)];
        }

        if ($char === '(') {
            return ['str' => $this->parseString($position, $raw)];
        }

        if ($char === '<') {
            $end = strpos($raw, '>', $position);
            $hex = $end === false ? '' : substr($raw, $position + 1, $end - $position - 1);
            $position = $end === false ? strlen($raw) : $end + 1;
            return ['hex' => preg_replace('/[^0-9A-Fa-f]/', '', $hex) ?? ''];
        }

        if (preg_match('/\G(true|false|null)/', $raw, $m, 0, $position)) {
            $position += strlen($m[1]);
            return $m[1] === 'true' ? true : ($m[1] === 'false' ? false : null);
        }

        if (preg_match('/\G([+-]?[\d.]+)/', $raw, $m, 0, $position)) {
            // Referència indirecta?
            if (preg_match('/\G(\d+)\s+(\d+)\s+R(?![a-zA-Z])/', $raw, $ref, 0, $position)) {
                $position += strlen($ref[0]);
                return ['ref' => [(int) $ref[1], (int) $ref[2]]];
            }
            $position += strlen($m[1]);
            return str_contains($m[1], '.') ? (float) $m[1] : (int) $m[1];
        }

        $position++;
        return null;
    }

    private function parseName(int &$position, string $raw): string
    {
        $position++; // salta la barra
        $name = '';
        while ($position < strlen($raw)) {
            $char = $raw[$position];
            if (str_contains(" \t\r\n\f\0/[]<>(){}%", $char)) {
                break;
            }
            if ($char === '#' && preg_match('/^[0-9A-Fa-f]{2}/', substr($raw, $position + 1, 2), $hex)) {
                $name .= chr((int) hexdec($hex[0]));
                $position += 3;
                continue;
            }
            $name .= $char;
            $position++;
        }
        return $name;
    }

    private function parseString(int &$position, string $raw): string
    {
        $position++; // salta el parèntesi
        $depth = 1;
        $out = '';
        while ($position < strlen($raw)) {
            $char = $raw[$position];
            if ($char === '\\') {
                $next = $raw[$position + 1] ?? '';
                $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C", '(' => '(', ')' => ')', '\\' => '\\'];
                if (isset($map[$next])) {
                    $out .= $map[$next];
                    $position += 2;
                    continue;
                }
                if (preg_match('/^[0-7]{1,3}/', substr($raw, $position + 1, 3), $oct)) {
                    $out .= chr((int) octdec($oct[0]));
                    $position += 1 + strlen($oct[0]);
                    continue;
                }
                $position += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $position++;
                    break;
                }
            }
            $out .= $char;
            $position++;
        }
        return $out;
    }

    private function skipWhitespace(int &$position, ?string $source = null): void
    {
        $raw = $source ?? $this->raw;
        while ($position < strlen($raw)) {
            $char = $raw[$position];
            if ($char === '%') { // comentari
                while ($position < strlen($raw) && $raw[$position] !== "\n" && $raw[$position] !== "\r") {
                    $position++;
                }
                continue;
            }
            if (!str_contains(" \t\r\n\f\0", $char)) {
                return;
            }
            $position++;
        }
    }

    /* ------------------------------------------------------------- Filtres */

    /** Descomprimeix el contingut d'un flux. */
    private function decodeStream(array $object): string
    {
        $data = $object['stream'] ?? '';
        $dict = $object['dict'] ?? [];
        $filters = [];
        $filter = $this->resolve($dict['/Filter'] ?? null);
        if (isset($filter['name'])) {
            $filters[] = $filter['name'];
        } elseif (isset($filter['arr'])) {
            foreach ($filter['arr'] as $item) {
                $resolved = $this->resolve($item);
                if (isset($resolved['name'])) {
                    $filters[] = $resolved['name'];
                }
            }
        }
        $parms = $this->resolve($dict['/DecodeParms'] ?? ($dict['/DP'] ?? null));
        $parmsList = isset($parms['arr']) ? $parms['arr'] : [$parms];

        foreach ($filters as $index => $name) {
            $data = match ($name) {
                'FlateDecode', 'Fl' => $this->inflate($data),
                'ASCIIHexDecode', 'AHx' => (string) hex2bin(preg_replace('/[^0-9A-Fa-f]/', '', explode('>', $data)[0]) ?? ''),
                'ASCII85Decode', 'A85' => $this->ascii85($data),
                'RunLengthDecode', 'RL' => $this->runLength($data),
                'DCTDecode', 'JPXDecode', 'CCITTFaxDecode', 'JBIG2Decode' => $data, // imatges: es copien tal qual
                default => throw new \RuntimeException('El PDF utilitza un filtre no admès: ' . $name),
            };
            $parm = $this->resolve($parmsList[$index] ?? null);
            if (isset($parm['dict']['/Predictor'])) {
                $data = $this->undoPredictor($data, $parm['dict']);
            }
        }
        return $data;
    }

    private function inflate(string $data): string
    {
        $out = @gzuncompress($data);
        if ($out === false) {
            $out = @gzinflate($data);
        }
        if ($out === false) {
            $out = @gzinflate(substr($data, 2));
        }
        if ($out === false) {
            throw new \RuntimeException('No s\'ha pogut descomprimir el contingut del PDF.');
        }
        return $out;
    }

    private function ascii85(string $data): string
    {
        $data = preg_replace('/\s/', '', $data) ?? '';
        $data = preg_replace('/^<~/', '', $data) ?? $data;
        $end = strpos($data, '~>');
        if ($end !== false) {
            $data = substr($data, 0, $end);
        }
        $out = '';
        $chunk = [];
        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            if ($data[$i] === 'z' && !$chunk) {
                $out .= "\0\0\0\0";
                continue;
            }
            $chunk[] = ord($data[$i]) - 33;
            if (count($chunk) === 5) {
                $value = 0;
                foreach ($chunk as $digit) {
                    $value = $value * 85 + $digit;
                }
                $out .= pack('N', $value);
                $chunk = [];
            }
        }
        if ($chunk) {
            $missing = 5 - count($chunk);
            for ($i = 0; $i < $missing; $i++) {
                $chunk[] = 84;
            }
            $value = 0;
            foreach ($chunk as $digit) {
                $value = $value * 85 + $digit;
            }
            $out .= substr(pack('N', $value), 0, 4 - $missing);
        }
        return $out;
    }

    private function runLength(string $data): string
    {
        $out = '';
        $i = 0;
        while ($i < strlen($data)) {
            $length = ord($data[$i++]);
            if ($length === 128) {
                break;
            }
            if ($length < 128) {
                $out .= substr($data, $i, $length + 1);
                $i += $length + 1;
            } else {
                $out .= str_repeat($data[$i] ?? '', 257 - $length);
                $i++;
            }
        }
        return $out;
    }

    /** Desfà els predictors PNG/TIFF de les taules d'objectes comprimides. */
    private function undoPredictor(string $data, array $parms): string
    {
        $predictor = (int) $this->numberValue($parms['/Predictor'] ?? 1);
        if ($predictor <= 1) {
            return $data;
        }
        $colors = (int) $this->numberValue($parms['/Colors'] ?? 1);
        $bits = (int) $this->numberValue($parms['/BitsPerComponent'] ?? 8);
        $columns = (int) $this->numberValue($parms['/Columns'] ?? 1);
        $bpp = max(1, (int) ceil($colors * $bits / 8));
        $rowLength = (int) ceil($colors * $bits * $columns / 8);

        if ($predictor === 2) {
            return $data; // TIFF amb 8 bits: no cal fer res per a les taules d'objectes
        }

        $out = '';
        $previous = str_repeat("\0", $rowLength);
        $position = 0;
        while ($position + 1 <= strlen($data)) {
            $type = ord($data[$position++]);
            $row = substr($data, $position, $rowLength);
            if ($row === '') {
                break;
            }
            $position += strlen($row);
            $row = str_pad($row, $rowLength, "\0");
            $decoded = '';
            for ($i = 0; $i < $rowLength; $i++) {
                $raw = ord($row[$i]);
                $left = $i >= $bpp ? ord($decoded[$i - $bpp]) : 0;
                $up = ord($previous[$i]);
                $upLeft = $i >= $bpp ? ord($previous[$i - $bpp]) : 0;
                $value = match ($type) {
                    0 => $raw,
                    1 => $raw + $left,
                    2 => $raw + $up,
                    3 => $raw + (int) floor(($left + $up) / 2),
                    4 => $raw + $this->paeth($left, $up, $upLeft),
                    default => $raw,
                };
                $decoded .= chr($value & 0xFF);
            }
            $out .= $decoded;
            $previous = $decoded;
        }
        return $out;
    }

    private function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        if ($pa <= $pb && $pa <= $pc) {
            return $a;
        }
        return $pb <= $pc ? $b : $c;
    }

    /* ------------------------------------------------------------- Ajudes */

    /** Resol una referència indirecta. */
    private function resolve($value)
    {
        $guard = 0;
        while (is_array($value) && isset($value['ref']) && $guard++ < 32) {
            $value = $this->getObject((int) $value['ref'][0]);
        }
        return is_array($value) ? $value : ($value === null ? [] : ['value' => $value]);
    }

    private function numberValue($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_array($value) && isset($value['ref'])) {
            $resolved = $this->getObject((int) $value['ref'][0]);
            return is_int($resolved) || is_float($resolved) ? (float) $resolved : 0.0;
        }
        if (is_array($value) && isset($value['value'])) {
            return (float) $value['value'];
        }
        return 0.0;
    }

    /** Caixa de la pàgina normalitzada [x0, y0, x1, y1] en punts. */
    private function normalizedBox(array $page): array
    {
        $box = $page['/CropBox'] ?? ($page['/MediaBox'] ?? null);
        $resolved = $this->resolve($box);
        $values = [];
        foreach ($resolved['arr'] ?? [] as $item) {
            $values[] = $this->numberValue($item);
        }
        if (count($values) !== 4) {
            $values = [0.0, 0.0, 595.28, 841.89]; // A4 per defecte
        }
        return [
            min($values[0], $values[2]),
            min($values[1], $values[3]),
            max($values[0], $values[2]),
            max($values[1], $values[3]),
        ];
    }

    /** Mida visible de la pàgina en punts, tenint en compte el gir. */
    private function boxSize(array $page): array
    {
        $box = $this->normalizedBox($page);
        $width = $box[2] - $box[0];
        $height = $box[3] - $box[1];
        $rotation = ((int) $this->numberValue($page['/Rotate'] ?? 0) % 360 + 360) % 360;
        return in_array($rotation, [90, 270], true) ? [$height, $width] : [$width, $height];
    }
}
