<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

use Cros\Core\Db;

/**
 * Les imatges que es posen als correus de l'editor.
 *
 * Es comprova que de debò són imatges, es redueixen a 1.200 px d'amplada (el
 * doble dels 600 px del correu, per a les pantalles de més resolució) i les
 * WebP es passen a JPEG o PNG, que l'Outlook no les mostra.
 *
 * Es desen a uploads/correus/ amb un nom que no es pot endevinar, perquè les
 * serveixi directament el servidor web des del domini que envia: un correu
 * només pot carregar imatges d'una adreça pública.
 */
final class Images
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MAX_WIDTH = 1200;

    public const FOLDER = 'correus';

    /**
     * Desa una imatge i en torna el codi (img:…) i les mides.
     *
     * @return array{src:string,token:string,width:int,height:int}
     */
    public static function store(string $path, string $name, ?int $userId = null): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('No s\'ha rebut cap imatge.');
        }
        if (filesize($path) > self::MAX_BYTES) {
            throw new \RuntimeException('«' . $name . '» ocupa massa (com a molt ' . intdiv(self::MAX_BYTES, 1048576) . ' MB).');
        }
        $info = @getimagesize($path);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
        if (!$info || !isset($types[$info[2]])) {
            throw new \RuntimeException('«' . $name . '» no és una imatge JPG, PNG, GIF o WebP.');
        }

        return self::storeData((string) file_get_contents($path), $name, $userId);
    }

    /**
     * El mateix, a partir del contingut (la IA en torna una així).
     *
     * @return array{src:string,token:string,width:int,height:int}
     */
    public static function storeData(string $data, string $name, ?int $userId = null): array
    {
        $info = @getimagesizefromstring($data);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
        if (!$info || !isset($types[$info[2]])) {
            throw new \RuntimeException('«' . $name . '» no és una imatge JPG, PNG, GIF o WebP.');
        }
        [$width, $height] = $info;
        $mime = $types[$info[2]];
        // Les GIF es deixen com són (poden ser animades); la resta es redueix si cal.
        if ($mime !== 'image/gif' && ($width > self::MAX_WIDTH || $mime === 'image/webp') && function_exists('imagecreatefromstring')) {
            $converted = self::resize($data, (int) $width, (int) $height, $mime);
            if ($converted) {
                [$data, $mime, $width, $height] = $converted;
            }
        }
        if ($mime === 'image/webp') {
            throw new \RuntimeException('Les imatges WebP no es veuen a l\'Outlook: deseu-la com a JPG o PNG.');
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'][$mime];
        $token = self::token();
        $dir = CROS_UPLOADS . '/' . self::FOLDER;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No es pot desar la imatge: la carpeta uploads/' . self::FOLDER . ' no es pot crear.');
        }
        $file = $token . '.' . $ext;
        if (@file_put_contents($dir . '/' . $file, $data) === false) {
            throw new \RuntimeException('No es pot desar la imatge a uploads/' . self::FOLDER . '.');
        }
        Db::insert('platform_mailing_images', [
            'token' => $token,
            'file' => self::FOLDER . '/' . $file,
            'mime' => $mime,
            'original_name' => mb_substr($name, 0, 190),
            'width' => (int) $width,
            'height' => (int) $height,
            'size' => strlen($data),
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['src' => 'img:' . $token, 'token' => $token, 'width' => (int) $width, 'height' => (int) $height];
    }

    /**
     * Les adreces públiques d'uns quants codis (img:…), servides des d'un domini.
     *
     * @param array<int,string> $refs
     * @return array<string,string>
     */
    public static function urls(array $refs, string $base): array
    {
        $tokens = [];
        foreach ($refs as $ref) {
            if (preg_match('/^img:([A-Za-z0-9]{40})$/', $ref, $m)) {
                $tokens[] = $m[1];
            }
        }
        if (!$tokens) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($tokens), '?'));
        $out = [];
        foreach (Db::all('SELECT token, file FROM platform_mailing_images WHERE token IN (' . $marks . ')', $tokens) as $row) {
            $out['img:' . $row['token']] = rtrim($base, '/') . '/uploads/' . $row['file'];
        }

        return $out;
    }

    /** El fitxer d'un codi (img:…), o '' si no n'hi ha cap. */
    public static function file(string $src): string
    {
        if (!preg_match('/^img:([A-Za-z0-9]{40})$/', $src, $m)) {
            return '';
        }
        $file = (string) Db::val('SELECT file FROM platform_mailing_images WHERE token = :t', ['t' => $m[1]], '');

        return $file !== '' && is_file(CROS_UPLOADS . '/' . $file) ? CROS_UPLOADS . '/' . $file : '';
    }

    private static function token(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $out = '';
        for ($i = 0; $i < 40; $i++) {
            $out .= $chars[random_int(0, 61)];
        }

        return $out;
    }

    /** @return array{0:string,1:string,2:int,3:int}|null */
    private static function resize(string $data, int $width, int $height, string $mime): ?array
    {
        $src = @imagecreatefromstring($data);
        if (!$src) {
            return null;
        }
        $w = min($width, self::MAX_WIDTH);
        $h = max(1, (int) round($height * $w / max(1, $width)));
        $dst = imagecreatetruecolor($w, $h);
        $alpha = $mime === 'image/png' || ($mime === 'image/webp' && self::hasAlpha($src, $width, $height));
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        } else {
            imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $width, $height);
        ob_start();
        $alpha ? imagepng($dst, null, 7) : imagejpeg($dst, null, 86);
        $out = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out !== '' ? [$out, $alpha ? 'image/png' : 'image/jpeg', $w, $h] : null;
    }

    /** Té transparència? (es mira una mostra de punts) */
    private static function hasAlpha(\GdImage $img, int $w, int $h): bool
    {
        $step = max(1, (int) floor(min($w, $h) / 20));
        for ($x = 0; $x < $w; $x += $step) {
            for ($y = 0; $y < $h; $y += $step) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
