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

            if ($type === 'faqs') {
                Settings::set($name, self::faqs($name));
                continue;
            }

            if ($type === 'features') {
                Settings::set($name, self::features($name));
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

    /**
     * Una llista de preguntes i respostes, tal com arriba del formulari.
     *
     * Es desa com un sol valor en JSON: per a una llista que sempre és curta
     * no val la pena una taula, i així s'edita tot en una pantalla. Les files
     * sense pregunta es descarten, que és la manera de treure'n una.
     */
    private static function faqs(string $name): string
    {
        $questions = (array) ($_POST[$name . '_q'] ?? []);
        $answers = (array) ($_POST[$name . '_a'] ?? []);
        $rows = [];
        foreach ($questions as $i => $question) {
            $question = trim((string) $question);
            $answer = trim((string) ($answers[$i] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $rows[] = ['q' => mb_substr($question, 0, 300), 'a' => mb_substr($answer, 0, 2000)];
        }

        return $rows === [] ? '' : (string) json_encode($rows, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Les funcionalitats que s'ensenyen al web, amb la mateixa idea que les
     * preguntes freqüents: un sol valor en JSON. Les files sense títol o sense
     * explicació es descarten, que és la manera de treure'n una.
     */
    private static function features(string $name): string
    {
        $icons = (array) ($_POST[$name . '_icon'] ?? []);
        $titles = (array) ($_POST[$name . '_title'] ?? []);
        $texts = (array) ($_POST[$name . '_text'] ?? []);
        $rows = [];
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            $text = trim((string) ($texts[$i] ?? ''));
            if ($title === '' || $text === '') {
                continue;
            }
            $icon = trim((string) ($icons[$i] ?? ''));
            $rows[] = [
                'icon' => \Cros\Core\Icons::exists($icon) ? $icon : '',
                'title' => mb_substr($title, 0, 150),
                'text' => mb_substr($text, 0, 600),
            ];
        }

        return $rows === [] ? '' : (string) json_encode($rows, JSON_UNESCAPED_UNICODE);
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
