<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Desa un grup de camps de configuració tal com arriba d'un formulari.
 *
 * El fan servir tant el panell d'un cros com el de la plataforma: els camps
 * canvien, però la manera de tractar-los —imatges, textos amb format, números,
 * secrets que no s'esborren si es deixen en blanc— és la mateixa.
 */
class SettingsForm
{
    /**
     * @param array<string,array<string,mixed>> $fields
     * @return array<int,string> els problemes que s'hagin trobat
     */
    public static function save(array $fields): array
    {
        $errors = [];
        $overrides = self::pastedCoords($fields);

        foreach ($fields as $name => $field) {
            $type = $field['type'] ?? 'text';

            if (in_array($type, ['image', 'file'], true)) {
                $error = self::saveUpload($name, $field, $type);
                if ($error !== '') {
                    $errors[] = $error;
                }
                continue;
            }

            if ($type === 'bool') {
                Settings::set($name, (string) input_bool($name));
                continue;
            }

            $raw = $overrides[$name] ?? ($_POST[$name] ?? null);
            if ($raw === null) {
                continue;
            }
            $value = is_string($raw) ? trim($raw) : (string) $raw;

            if ($type === 'password' && $value === '') {
                continue; // no s'esborra el secret si es deixa buit
            }
            if ($type === 'html') {
                $value = Html::clean($value);
            }
            if ($type === 'select' && isset($field['options']) && !array_key_exists($value, $field['options'])) {
                continue;
            }
            if ($type === 'coord') {
                $coord = Map::coord($value, (string) ($field['axis'] ?? 'lat'));
                if ($coord === null) {
                    $errors[] = $field['label'] . ': cal una coordenada vàlida (p. ex. 41,376699).';
                    continue;
                }
                $value = $coord;
            }
            if ($type === 'number' && $value !== '' && !is_numeric($value)) {
                $errors[] = $field['label'] . ': cal un valor numèric.';
                continue;
            }
            if ($type === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $field['label'] . ': l\'adreça no és vàlida.';
                continue;
            }
            Settings::set($name, $value);
        }

        return $errors;
    }

    /** Una imatge o un document: es puja, o es treu si ho han demanat. */
    private static function saveUpload(string $name, array $field, string $type): string
    {
        if (input_bool($name . '_remove') === 1) {
            Uploader::delete((string) setting($name, ''));
            Settings::set($name, '');
        }
        if (!Uploader::has($name)) {
            return '';
        }
        try {
            $old = (string) setting($name, '');
            $path = $type === 'image'
                ? Uploader::image(
                    $_FILES[$name],
                    $field['folder'] ?? 'media',
                    (int) ($field['max_width'] ?? 1920),
                    (int) ($field['max_height'] ?? 1920)
                )
                : Uploader::document($_FILES[$name], $field['folder'] ?? 'documents');
            Settings::set($name, $path);
            if ($old !== '' && $old !== $path) {
                Uploader::delete($old);
            }
        } catch (\RuntimeException $e) {
            return $field['label'] . ': ' . $e->getMessage();
        }

        return '';
    }

    /**
     * Si s'enganxa un enllaç d'un mapa o el parell sencer de coordenades a
     * qualsevol dels dos camps, s'omplen tots dos amb el punt que s'hi ha trobat.
     *
     * @return array<string,string>
     */
    private static function pastedCoords(array $fields): array
    {
        $axes = [];
        $pair = null;
        foreach ($fields as $name => $field) {
            if (($field['type'] ?? '') !== 'coord') {
                continue;
            }
            $axes[$name] = ($field['axis'] ?? 'lat') === 'lng' ? 'lng' : 'lat';
            if ($pair === null && is_string($_POST[$name] ?? null)) {
                $pair = Map::parse($_POST[$name]);
            }
        }
        if ($pair === null || count($axes) < 2) {
            return [];
        }

        $values = [];
        foreach ($axes as $name => $axis) {
            $values[$name] = Map::format($pair[$axis]);
        }

        return $values;
    }
}
