<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Motius de vinya del Penedès: la fulla de parra i el carràs de raïm.
 *
 * Són siluetes dibuixades sobre una quadrícula de 100×100 i s'utilitzen com a
 * decoració (capçaleres, separadors i la franja inferior de la portada). Hereten
 * el color de qui les conté, de manera que serveixen tant sobre fons clar com fosc.
 */
class Vines
{
    /** Fulla de parra: cinc lòbuls, sinus marcats i base cordada. */
    public const LEAF = 'M50 5L54.8 17.7Q56 21 57.5 24.2L60.5 30.8Q62 34 64.8 32L73.2 26Q76 24 78.8 26.1L89.2 33.9Q92 36 89.3 38.3L80.7 45.7Q78 48 75.7 50.6L72.3 54.4Q70 57 73.1 58.7L82.9 64.3Q86 66 84.6 69.2L79.4 80.8Q78 84 74.5 83.4L63.5 81.6Q60 81 57.4 83.3L52.6 87.7Q50 90 47.4 87.7L42.6 83.3Q40 81 36.5 81.6L25.5 83.4Q22 84 20.6 80.8L15.4 69.2Q14 66 17.1 64.3L26.9 58.7Q30 57 27.7 54.4L24.3 50.6Q22 48 19.3 45.7L10.7 38.3Q8 36 10.8 33.9L21.2 26.1Q24 24 26.8 26L35.2 32Q38 34 39.5 30.8L42.5 24.2Q44 21 45.2 17.7Z';

    /** Pecíol de la fulla, per quan es dibuixa sencera. */
    public const STALK = 'M50 88V99';

    /** Carràs de raïm: la tija i els grans. */
    public const GRAPES = 'M50 26c0-9 5-15 15-18';
    private const BERRIES = [[39, 33, 11], [61, 33, 11], [28, 52, 11], [50, 52, 11], [72, 52, 11], [39, 70, 11], [61, 70, 11], [50, 86, 10]];

    /** Una fulla, amb pecíol opcional. */
    public static function leaf(array $options = []): string
    {
        $stalk = $options['stalk'] ?? false;
        return '<path d="' . self::LEAF . '"/>'
            . ($stalk ? '<path d="' . self::STALK . '" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" fill="none"/>' : '');
    }

    /** Un carràs de raïm. */
    public static function grapes(): string
    {
        $svg = '<path d="' . self::GRAPES . '" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" fill="none"/>';
        foreach (self::BERRIES as [$x, $y, $r]) {
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '"/>';
        }
        return $svg;
    }

    /**
     * Un motiu solt dins d'un grup, amb posició, mida i gir.
     * La mida és l'amplada en unitats del SVG que el conté.
     */
    public static function motif(string $kind, float $x, float $y, float $size, float $rotate = 0, float $opacity = 1): string
    {
        $scale = $size / 100;
        $shape = $kind === 'grapes' ? self::grapes() : self::leaf();
        return sprintf(
            '<g transform="translate(%s %s) rotate(%s) scale(%s) translate(-50 -50)"%s>%s</g>',
            round($x, 1),
            round($y, 1),
            round($rotate, 1),
            round($scale, 4),
            $opacity < 1 ? ' opacity="' . $opacity . '"' : '',
            $shape
        );
    }

    /**
     * Franja de fulles i carrassos repartits al llarg d'una amplada.
     * S'utilitza a les capçaleres i als separadors.
     */
    public static function band(float $width, float $baseline, array $options = []): string
    {
        $step = (float) ($options['step'] ?? 120);
        $size = (float) ($options['size'] ?? 46);
        $seed = (int) ($options['seed'] ?? 7);
        $svg = '';
        $i = 0;
        for ($x = $step / 2; $x < $width; $x += $step) {
            $i++;
            // Variacions estables (sense atzar) perquè el dibuix no balli entre pàgines.
            $wave = sin(($i + $seed) * 1.7);
            $kind = $i % 3 === 0 ? 'grapes' : 'leaf';
            $scale = $kind === 'grapes' ? 0.72 : 1.0;
            $svg .= self::motif(
                $kind,
                $x,
                $baseline - $wave * $size * 0.12,
                $size * $scale * (0.85 + 0.15 * abs($wave)),
                $kind === 'grapes' ? $wave * 6 : $wave * 22,
                0.75 + 0.25 * abs($wave)
            );
        }
        return $svg;
    }
}
