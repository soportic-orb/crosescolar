<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Captcha propi: una imatge amb uns quants caràcters que cal escriure.
 *
 * No fa servir cap servei de fora. Els de Google o similars envien a un altre
 * servidor l'adreça i el comportament de qui omple el formulari, i la política
 * de privadesa diu que aquí no se n'envia res a tercers.
 *
 * La resposta es desa a la sessió i serveix un sol cop: s'esborra tant si
 * s'encerta com si no, perquè un robot no la pugui provar mil vegades. Cada
 * formulari té la seva, i caduca al cap d'una estona.
 *
 * La imatge es dibuixa amb GD. Si el servidor no en té, el captcha passa a ser
 * una suma escrita en text: menys dura per a un robot fet a mida, però el
 * formulari no es queda mai sense poder-se enviar.
 */
final class Captcha
{
    /** Caràcters que no es confonen entre ells: ni 0/O, ni 1/I/l, ni 5/S. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    /** Quants caràcters té cada repte. */
    private const LENGTH = 5;

    /** Quants minuts val un repte. */
    private const MINUTES = 20;

    /** El repte d'un formulari, si n'hi ha un de vàlid; si no, en crea un de nou. */
    private static function challenge(string $form, bool $fresh = false): array
    {
        $current = $_SESSION['captcha'][$form] ?? null;
        $alive = is_array($current) && (int) ($current['at'] ?? 0) > time() - self::MINUTES * 60;
        if (!$fresh && $alive) {
            return $current;
        }

        if (self::drawable()) {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $challenge = ['kind' => 'image', 'answer' => $code, 'at' => time()];
        } else {
            $a = random_int(2, 9);
            $b = random_int(2, 9);
            $challenge = ['kind' => 'sum', 'answer' => (string) ($a + $b), 'question' => $a . ' + ' . $b, 'at' => time()];
        }
        $_SESSION['captcha'][$form] = $challenge;

        return $challenge;
    }

    /** Prepara un repte nou per a un formulari que es mostra ara. */
    public static function issue(string $form): void
    {
        self::challenge($form, true);
    }

    /** Hi ha imatge, o és la suma en text? */
    public static function isImage(string $form): bool
    {
        return (string) (self::challenge($form)['kind'] ?? '') === 'image';
    }

    /** La pregunta en text, quan no hi ha imatge. */
    public static function question(string $form): string
    {
        return (string) (self::challenge($form)['question'] ?? '');
    }

    /**
     * La resposta és bona?
     *
     * Serveixi o no, el repte es gasta: la propera vegada que es mostri el
     * formulari, n'hi haurà un altre.
     *
     * Amb $minSeconds, també cal que hagi passat aquesta estona des que es va
     * mostrar el formulari: una persona triga uns segons a omplir-lo, i un
     * robot l'envia en el mateix moment que el rep.
     */
    public static function check(string $form, string $answer, int $minSeconds = 0): bool
    {
        $challenge = $_SESSION['captcha'][$form] ?? null;
        unset($_SESSION['captcha'][$form]);
        $at = is_array($challenge) ? (int) ($challenge['at'] ?? 0) : 0;
        if ($at <= time() - self::MINUTES * 60 || time() - $at < $minSeconds) {
            return false;
        }
        $expected = strtoupper((string) ($challenge['answer'] ?? ''));
        $given = strtoupper(preg_replace('/\s+/', '', $answer) ?? '');

        return $expected !== '' && hash_equals($expected, $given);
    }

    /** Es pot dibuixar la imatge en aquest servidor? */
    public static function drawable(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    /**
     * La imatge del repte d'un formulari, en PNG.
     *
     * Cada lletra es gira una mica i s'amplia: queda gruixuda i irregular, que
     * és el que costa de llegir a un programa i no a una persona. A sobre hi van
     * ratlles i punts que no deixen retallar-les.
     */
    public static function png(string $form): string
    {
        $challenge = self::challenge($form);
        $code = (string) ($challenge['kind'] ?? '') === 'image' ? (string) $challenge['answer'] : '?????';

        $width = 190;
        $height = 64;
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 244, 247, 241));

        // Soroll de fons: ratlles suaus que travessen les lletres.
        for ($i = 0; $i < 7; $i++) {
            $shade = imagecolorallocate($image, random_int(170, 210), random_int(185, 215), random_int(170, 205));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $shade);
        }

        // Cada lletra es dibuixa amb la lletra de GD, petita, en una capsa a
        // part; després se'n copien només els punts de tinta, girats i
        // ampliats. Així no queda cap vora del fons de la capsa i el soroll de
        // sota es continua veient entre els traços.
        $step = ($width - 24) / strlen($code);
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $glyph = imagecreatetruecolor(10, 16);
            imagefill($glyph, 0, 0, imagecolorallocate($glyph, 255, 255, 255));
            imagestring($glyph, 5, 1, 0, $code[$i], imagecolorallocate($glyph, 0, 0, 0));

            $ink = imagecolorallocate($image, random_int(20, 60), random_int(60, 100), random_int(30, 60));
            $scale = random_int(26, 31) / 10;           // de 2,6 a 3,1 vegades més gran
            $angle = deg2rad(random_int(-22, 22));
            $cx = 12 + $i * $step + $step / 2 + random_int(-3, 3);
            $cy = $height / 2 + random_int(-5, 5);
            $cos = cos($angle);
            $sin = sin($angle);
            $dot = (int) ceil($scale);
            for ($gy = 0; $gy < 16; $gy++) {
                for ($gx = 0; $gx < 10; $gx++) {
                    if ((imagecolorat($glyph, $gx, $gy) & 0xFF) > 127) {
                        continue; // fons de la capsa, no tinta
                    }
                    $dx = ($gx - 5) * $scale;
                    $dy = ($gy - 8) * $scale;
                    $px = (int) round($cx + $dx * $cos - $dy * $sin);
                    $py = (int) round($cy + $dx * $sin + $dy * $cos);
                    imagefilledrectangle($image, $px, $py, $px + $dot - 1, $py + $dot - 1, $ink);
                }
            }
            imagedestroy($glyph);
        }

        // I a sobre, punts i dues ratlles fosques.
        for ($i = 0; $i < 220; $i++) {
            $dot = imagecolorallocate($image, random_int(90, 170), random_int(110, 180), random_int(90, 160));
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $dot);
        }
        for ($i = 0; $i < 2; $i++) {
            $dark = imagecolorallocate($image, random_int(40, 80), random_int(80, 110), random_int(50, 80));
            imagesetthickness($image, 2);
            imageline($image, 0, random_int(10, $height - 10), $width, random_int(10, $height - 10), $dark);
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
