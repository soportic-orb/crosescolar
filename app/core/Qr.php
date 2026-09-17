<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Generador de codis QR en PHP pur (mode byte, versions 1-10).
 * S'utilitza per als tiquets de l'esmorzar; no requereix cap llibreria externa.
 */
class Qr
{
    /** [nivell][versió] => [codewords ECC per bloc, blocs grup 1, dades grup 1, blocs grup 2, dades grup 2] */
    private const ECC_TABLE = [
        'L' => [
            1 => [7, 1, 19, 0, 0], 2 => [10, 1, 34, 0, 0], 3 => [15, 1, 55, 0, 0], 4 => [20, 1, 80, 0, 0],
            5 => [26, 1, 108, 0, 0], 6 => [18, 2, 68, 0, 0], 7 => [20, 2, 78, 0, 0], 8 => [24, 2, 97, 0, 0],
            9 => [30, 2, 116, 0, 0], 10 => [18, 2, 68, 2, 69],
        ],
        'M' => [
            1 => [10, 1, 16, 0, 0], 2 => [16, 1, 28, 0, 0], 3 => [26, 1, 44, 0, 0], 4 => [18, 2, 32, 0, 0],
            5 => [24, 2, 43, 0, 0], 6 => [16, 4, 27, 0, 0], 7 => [18, 4, 31, 0, 0], 8 => [22, 2, 38, 2, 39],
            9 => [22, 3, 36, 2, 37], 10 => [26, 4, 43, 1, 44],
        ],
        'Q' => [
            1 => [13, 1, 13, 0, 0], 2 => [22, 1, 22, 0, 0], 3 => [18, 2, 17, 0, 0], 4 => [26, 2, 24, 0, 0],
            5 => [18, 2, 15, 2, 16], 6 => [24, 4, 19, 0, 0], 7 => [18, 2, 14, 4, 15], 8 => [22, 4, 18, 2, 19],
            9 => [20, 4, 16, 4, 17], 10 => [24, 6, 19, 2, 20],
        ],
        'H' => [
            1 => [17, 1, 9, 0, 0], 2 => [28, 1, 16, 0, 0], 3 => [22, 2, 13, 0, 0], 4 => [16, 4, 9, 0, 0],
            5 => [22, 2, 11, 2, 12], 6 => [28, 4, 15, 0, 0], 7 => [26, 4, 13, 1, 14], 8 => [26, 4, 14, 2, 15],
            9 => [24, 4, 12, 4, 13], 10 => [28, 6, 15, 2, 16],
        ],
    ];

    /** Posicions dels patrons d'alineació per versió. */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private const ECC_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    private static array $expTable = [];
    private static array $logTable = [];

    /**
     * Matriu de mòduls (1 = negre, 0 = blanc).
     * @return array<int,array<int,int>>
     */
    public static function matrix(string $text, string $level = 'M'): array
    {
        $level = isset(self::ECC_TABLE[$level]) ? $level : 'M';
        $version = self::chooseVersion($text, $level);
        $codewords = self::buildCodewords($text, $version, $level);

        $size = 17 + 4 * $version;
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::placeFunctionPatterns($matrix, $reserved, $version, $size);
        self::placeData($matrix, $reserved, $codewords, $size);

        $best = null;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($matrix, $reserved, $mask, $size);
            self::placeFormatInfo($candidate, $level, $mask, $size);
            if ($version >= 7) {
                self::placeVersionInfo($candidate, $version, $size);
            }
            $penalty = self::penalty($candidate, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $candidate;
            }
        }
        foreach ($best as $row => $cells) {
            foreach ($cells as $col => $value) {
                $best[$row][$col] = (int) ($value ?? 0);
            }
        }
        return $best;
    }

    /** Codi QR en format SVG. */
    public static function svg(string $text, int $scale = 6, int $margin = 4, string $level = 'M', string $dark = '#14361f'): string
    {
        $matrix = self::matrix($text, $level);
        $size = count($matrix);
        $dimension = ($size + $margin * 2) * $scale;
        $paths = [];
        foreach ($matrix as $row => $cells) {
            foreach ($cells as $col => $value) {
                if ($value) {
                    $paths[] = 'M' . (($col + $margin) * $scale) . ' ' . (($row + $margin) * $scale)
                        . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dimension . '" height="' . $dimension . '" '
            . 'viewBox="0 0 ' . $dimension . ' ' . $dimension . '" shape-rendering="crispEdges" role="img" aria-label="Codi QR">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path fill="' . $dark . '" d="' . implode('', $paths) . '"/></svg>';
    }

    /** Codi QR en format PNG (binari). */
    public static function png(string $text, int $scale = 6, int $margin = 4, string $level = 'M'): string
    {
        $matrix = self::matrix($text, $level);
        $size = count($matrix);
        $dimension = ($size + $margin * 2) * $scale;

        if (!function_exists('imagecreatetruecolor')) {
            return self::svg($text, $scale, $margin, $level);
        }
        $image = imagecreatetruecolor($dimension, $dimension);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 20, 54, 31);
        imagefilledrectangle($image, 0, 0, $dimension, $dimension, $white);
        foreach ($matrix as $row => $cells) {
            foreach ($cells as $col => $value) {
                if ($value) {
                    $x = ($col + $margin) * $scale;
                    $y = ($row + $margin) * $scale;
                    imagefilledrectangle($image, $x, $y, $x + $scale - 1, $y + $scale - 1, $black);
                }
            }
        }
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return (string) ob_get_clean();
    }

    /** Imatge QR incrustada com a data URI (per a correus i impressió). */
    public static function dataUri(string $text, int $scale = 6, int $margin = 4, string $level = 'M'): string
    {
        return 'data:image/png;base64,' . base64_encode(self::png($text, $scale, $margin, $level));
    }

    /** Capacitat de dades (en codewords) d'una versió i nivell. */
    private static function dataCapacity(int $version, string $level): int
    {
        [, $blocks1, $data1, $blocks2, $data2] = self::ECC_TABLE[$level][$version];
        return $blocks1 * $data1 + $blocks2 * $data2;
    }

    private static function chooseVersion(string $text, string $level): int
    {
        $length = strlen($text);
        for ($version = 1; $version <= 10; $version++) {
            $countBits = $version < 10 ? 8 : 16;
            $required = (int) ceil((4 + $countBits + $length * 8) / 8);
            if ($required <= self::dataCapacity($version, $level)) {
                return $version;
            }
        }
        throw new \RuntimeException('El text és massa llarg per generar el codi QR.');
    }

    /** Construeix els codewords finals (dades + correcció d'errors intercalades). */
    private static function buildCodewords(string $text, int $version, string $level): array
    {
        [$ecPerBlock, $blocks1, $data1, $blocks2, $data2] = self::ECC_TABLE[$level][$version];
        $capacity = self::dataCapacity($version, $level);

        $bits = '0100'; // mode byte
        $countBits = $version < 10 ? 8 : 16;
        $bits .= str_pad(decbin(strlen($text)), $countBits, '0', STR_PAD_LEFT);
        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }
        $totalBits = $capacity * 8;
        $bits .= str_repeat('0', min(4, max(0, $totalBits - strlen($bits))));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }
        $data = [];
        foreach (str_split($bits, 8) as $byte) {
            $data[] = bindec($byte);
        }
        $pad = [0xEC, 0x11];
        $index = 0;
        while (count($data) < $capacity) {
            $data[] = $pad[$index % 2];
            $index++;
        }

        // Divisió en blocs
        $dataBlocks = [];
        $eccBlocks = [];
        $offset = 0;
        for ($i = 0; $i < $blocks1; $i++) {
            $block = array_slice($data, $offset, $data1);
            $offset += $data1;
            $dataBlocks[] = $block;
            $eccBlocks[] = self::reedSolomon($block, $ecPerBlock);
        }
        for ($i = 0; $i < $blocks2; $i++) {
            $block = array_slice($data, $offset, $data2);
            $offset += $data2;
            $dataBlocks[] = $block;
            $eccBlocks[] = self::reedSolomon($block, $ecPerBlock);
        }

        // Intercalat
        $result = [];
        $maxData = max($data1, $data2);
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($eccBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private static function initGf(): void
    {
        if (self::$expTable) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$expTable[$i] = $x;
            self::$logTable[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 256; $i < 512; $i++) {
            self::$expTable[$i] = self::$expTable[$i - 255];
        }
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /** Calcula els codewords de correcció d'errors d'un bloc. */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        self::initGf();
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $index => $coefficient) {
                $next[$index] ^= self::gfMul($coefficient, 1);
                $next[$index + 1] ^= self::gfMul($coefficient, self::$expTable[$i]);
            }
            $generator = $next;
        }
        $remainder = array_merge($data, array_fill(0, $ecCount, 0));
        $dataCount = count($data);
        for ($i = 0; $i < $dataCount; $i++) {
            $factor = $remainder[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($generator as $index => $coefficient) {
                $remainder[$i + $index] ^= self::gfMul($coefficient, $factor);
            }
        }
        return array_slice($remainder, $dataCount, $ecCount);
    }

    private static function placeFunctionPatterns(array &$matrix, array &$reserved, int $version, int $size): void
    {
        $finder = function (int $row, int $col) use (&$matrix, &$reserved, $size): void {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $row + $r;
                    $cc = $col + $c;
                    if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                        continue;
                    }
                    $inside = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                        || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6))
                        || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
                    $matrix[$rr][$cc] = $inside ? 1 : 0;
                    $reserved[$rr][$cc] = true;
                }
            }
        };
        $finder(0, 0);
        $finder(0, $size - 7);
        $finder($size - 7, 0);

        // Patrons de temps
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = ($i % 2 === 0) ? 1 : 0;
            $matrix[6][$i] = $bit;
            $reserved[6][$i] = true;
            $matrix[$i][6] = $bit;
            $reserved[$i][6] = true;
        }

        // Patrons d'alineació
        $positions = self::ALIGNMENT[$version] ?? [];
        foreach ($positions as $row) {
            foreach ($positions as $col) {
                if (($row === 6 && $col === 6) || ($row === 6 && $col === $size - 7) || ($row === $size - 7 && $col === 6)) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $value = (max(abs($r), abs($c)) !== 1) ? 1 : 0;
                        $matrix[$row + $r][$col + $c] = $value;
                        $reserved[$row + $r][$col + $c] = true;
                    }
                }
            }
        }

        // Mòdul fosc
        $matrix[$size - 8][8] = 1;
        $reserved[$size - 8][8] = true;

        // Zones reservades per a la informació de format
        for ($i = 0; $i < 9; $i++) {
            if ($matrix[8][$i] === null) {
                $matrix[8][$i] = 0;
            }
            $reserved[8][$i] = true;
            if ($matrix[$i][8] === null) {
                $matrix[$i][8] = 0;
            }
            $reserved[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            if ($matrix[8][$size - 1 - $i] === null) {
                $matrix[8][$size - 1 - $i] = 0;
            }
            $reserved[$size - 1 - $i][8] = true;
            if ($matrix[$size - 1 - $i][8] === null) {
                $matrix[$size - 1 - $i][8] = 0;
            }
        }

        // Zones reservades per a la informació de versió
        if ($version >= 7) {
            for ($i = 0; $i < 18; $i++) {
                $row = intdiv($i, 3);
                $col = $i % 3 + $size - 11;
                $matrix[$row][$col] = 0;
                $reserved[$row][$col] = true;
                $matrix[$col][$row] = 0;
                $reserved[$col][$row] = true;
            }
        }
    }

    private static function placeData(array &$matrix, array $reserved, array $codewords, int $size): void
    {
        $bits = '';
        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }
        $length = strlen($bits);
        $index = 0;
        $row = $size - 1;
        $direction = -1;

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            while (true) {
                for ($offset = 0; $offset < 2; $offset++) {
                    $currentCol = $col - $offset;
                    if (!$reserved[$row][$currentCol]) {
                        $matrix[$row][$currentCol] = $index < $length ? (int) $bits[$index] : 0;
                        $index++;
                    }
                }
                $row += $direction;
                if ($row < 0 || $row >= $size) {
                    $row -= $direction;
                    $direction = -$direction;
                    break;
                }
            }
        }
    }

    private static function applyMask(array $matrix, array $reserved, int $mask, int $size): array
    {
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($reserved[$row][$col]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($row + $col) % 2 === 0,
                    1 => $row % 2 === 0,
                    2 => $col % 3 === 0,
                    3 => ($row + $col) % 3 === 0,
                    4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
                    5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
                    6 => ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0,
                    default => (((($row + $col) % 2) + (($row * $col) % 3)) % 2) === 0,
                };
                if ($flip) {
                    $matrix[$row][$col] = (int) $matrix[$row][$col] ^ 1;
                }
            }
        }
        return $matrix;
    }

    private static function placeFormatInfo(array &$matrix, string $level, int $mask, int $size): void
    {
        $data = (self::ECC_BITS[$level] << 3) | $mask;
        $remainder = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if ((($remainder >> $i) & 1) === 1) {
                $remainder ^= 0x537 << ($i - 10);
            }
        }
        $bits = (($data << 10) | $remainder) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> $i) & 1;
            if ($i < 6) {
                $matrix[$i][8] = $bit;
            } elseif ($i < 8) {
                $matrix[$i + 1][8] = $bit;
            } else {
                $matrix[$size - 15 + $i][8] = $bit;
            }
            if ($i < 8) {
                $matrix[8][$size - 1 - $i] = $bit;
            } elseif ($i === 8) {
                $matrix[8][7] = $bit;
            } else {
                $matrix[8][14 - $i] = $bit;
            }
        }
        $matrix[$size - 8][8] = 1;
    }

    private static function placeVersionInfo(array &$matrix, int $version, int $size): void
    {
        $remainder = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if ((($remainder >> $i) & 1) === 1) {
                $remainder ^= 0x1F25 << ($i - 12);
            }
        }
        $bits = ($version << 12) | $remainder;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($bits >> $i) & 1;
            $row = intdiv($i, 3);
            $col = $i % 3 + $size - 11;
            $matrix[$row][$col] = $bit;
            $matrix[$col][$row] = $bit;
        }
    }

    /** Penalització d'una màscara (regles 1-4 de l'estàndard). */
    private static function penalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // Regla 1: 5 o més mòduls consecutius del mateix color
        for ($i = 0; $i < $size; $i++) {
            $runRow = 1;
            $runCol = 1;
            for ($j = 1; $j < $size; $j++) {
                $runRow = $matrix[$i][$j] === $matrix[$i][$j - 1] ? $runRow + 1 : 1;
                if ($runRow === 5) {
                    $penalty += 3;
                } elseif ($runRow > 5) {
                    $penalty++;
                }
                $runCol = $matrix[$j][$i] === $matrix[$j - 1][$i] ? $runCol + 1 : 1;
                if ($runCol === 5) {
                    $penalty += 3;
                } elseif ($runCol > 5) {
                    $penalty++;
                }
            }
        }

        // Regla 2: blocs 2x2 del mateix color
        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $value = $matrix[$row][$col];
                if ($value === $matrix[$row][$col + 1] && $value === $matrix[$row + 1][$col] && $value === $matrix[$row + 1][$col + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Regla 3: patrons 1:1:3:1:1 amb zona clara
        $patterns = [[1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0], [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1]];
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size - 10; $col++) {
                foreach ($patterns as $pattern) {
                    $matchRow = true;
                    $matchCol = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($matrix[$row][$col + $k] !== $pattern[$k]) {
                            $matchRow = false;
                        }
                        if ($matrix[$col + $k][$row] !== $pattern[$k]) {
                            $matchCol = false;
                        }
                        if (!$matchRow && !$matchCol) {
                            break;
                        }
                    }
                    if ($matchRow) {
                        $penalty += 40;
                    }
                    if ($matchCol) {
                        $penalty += 40;
                    }
                }
            }
        }

        // Regla 4: proporció de mòduls foscos
        $dark = 0;
        foreach ($matrix as $cells) {
            foreach ($cells as $value) {
                $dark += (int) $value;
            }
        }
        $ratio = ($dark * 100) / ($size * $size);
        $penalty += (int) (abs($ratio - 50) / 5) * 10;

        return $penalty;
    }
}
