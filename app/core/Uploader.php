<?php
declare(strict_types=1);

namespace Cros\Core;

/** Gestió de fitxers pujats des del panell d'administració. */
class Uploader
{
    public const MAX_BYTES = 8388608; // 8 MB

    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const DOC_TYPES = [
        'application/pdf' => 'pdf',
    ];

    /**
     * Puja una imatge a uploads/$folder i retorna la ruta relativa.
     * @throws \RuntimeException si el fitxer no és vàlid.
     */
    public static function image(array $file, string $folder, int $maxWidth = 1920, int $maxHeight = 1920): string
    {
        self::validate($file);
        $mime = self::mime($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mime])) {
            throw new \RuntimeException('El fitxer ha de ser una imatge (JPG, PNG, WEBP, GIF o SVG).');
        }
        $extension = self::IMAGE_TYPES[$mime];
        $target = self::targetPath($file['name'], $folder, $extension);

        if ($extension === 'svg') {
            $svg = self::sanitizeSvg((string) file_get_contents($file['tmp_name']));
            file_put_contents(upload_path($target), $svg);
            return $target;
        }

        if (!self::resize($file['tmp_name'], upload_path($target), $extension, $maxWidth, $maxHeight)) {
            if (!move_uploaded_file($file['tmp_name'], upload_path($target))
                && !rename($file['tmp_name'], upload_path($target))) {
                throw new \RuntimeException('No s\'ha pogut desar la imatge. Comproveu els permisos de la carpeta uploads.');
            }
        }
        @chmod(upload_path($target), 0644);
        return $target;
    }

    /** Puja un document (PDF). */
    public static function document(array $file, string $folder = 'documents'): string
    {
        self::validate($file);
        $mime = self::mime($file['tmp_name']);
        if (!isset(self::DOC_TYPES[$mime])) {
            throw new \RuntimeException('Només s\'admeten documents PDF.');
        }
        $target = self::targetPath($file['name'], $folder, self::DOC_TYPES[$mime]);
        if (!move_uploaded_file($file['tmp_name'], upload_path($target))
            && !rename($file['tmp_name'], upload_path($target))) {
            throw new \RuntimeException('No s\'ha pogut desar el document.');
        }
        @chmod(upload_path($target), 0644);
        return $target;
    }

    /** Esborra un fitxer pujat. */
    public static function delete(?string $path): void
    {
        if (!$path) {
            return;
        }
        $full = realpath(upload_path($path));
        $base = realpath(CROS_UPLOADS);
        if ($full && $base && str_starts_with($full, $base) && is_file($full)) {
            @unlink($full);
        }
    }

    /** Hi ha un fitxer pujat en aquest camp? */
    public static function has(string $field): bool
    {
        return isset($_FILES[$field]) && is_array($_FILES[$field])
            && (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && (string) ($_FILES[$field]['name'] ?? '') !== '';
    }

    private static function validate(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El fitxer és massa gran.',
                UPLOAD_ERR_PARTIAL => 'La pujada s\'ha interromput.',
                UPLOAD_ERR_NO_FILE => 'No s\'ha seleccionat cap fitxer.',
                default => 'Error en pujar el fitxer (codi ' . $error . ').',
            });
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('El fitxer supera els ' . (self::MAX_BYTES / 1048576) . ' MB permesos.');
        }
        if (!is_readable($file['tmp_name'] ?? '')) {
            throw new \RuntimeException('No s\'ha pogut llegir el fitxer pujat.');
        }
    }

    private static function mime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mime;
        }
        return (string) mime_content_type($path);
    }

    private static function targetPath(string $originalName, string $folder, string $extension): string
    {
        $folder = trim(preg_replace('/[^a-z0-9_\-\/]/i', '', $folder) ?? '', '/') ?: 'media';
        $dir = upload_path($folder);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No s\'ha pogut crear la carpeta de destinació: uploads/' . $folder);
        }
        $base = slugify(pathinfo($originalName, PATHINFO_FILENAME));
        $name = mb_substr($base, 0, 60) . '-' . substr(bin2hex(random_bytes(4)), 0, 6) . '.' . $extension;
        return $folder . '/' . $name;
    }

    /** Redimensiona i reescriu la imatge (elimina metadades). */
    private static function resize(string $source, string $target, string $extension, int $maxWidth, int $maxHeight): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }
        $info = @getimagesize($source);
        if (!$info) {
            return false;
        }
        [$width, $height] = $info;
        $image = match ($extension) {
            'jpg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
            'gif' => @imagecreatefromgif($source),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if (!$image) {
            return false;
        }
        $ratio = min($maxWidth / max($width, 1), $maxHeight / max($height, 1), 1);
        $newWidth = (int) max(1, round($width * $ratio));
        $newHeight = (int) max(1, round($height * $ratio));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        if (in_array($extension, ['png', 'webp', 'gif'], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
        }
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $ok = match ($extension) {
            'jpg' => imagejpeg($canvas, $target, 85),
            'png' => imagepng($canvas, $target, 6),
            'gif' => imagegif($canvas, $target),
            'webp' => function_exists('imagewebp') ? imagewebp($canvas, $target, 85) : false,
            default => false,
        };
        imagedestroy($canvas);
        imagedestroy($image);
        return (bool) $ok;
    }

    /** Elimina scripts i esdeveniments d'un SVG. */
    public static function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<\s*(foreignObject|iframe|embed|object)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $svg) ?? $svg;
        $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? $svg;
        $svg = preg_replace('/(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*\2/i', '', $svg) ?? $svg;
        return $svg;
    }
}
