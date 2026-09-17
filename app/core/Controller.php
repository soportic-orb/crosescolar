<?php
declare(strict_types=1);

namespace Cros\Core;

/** Classe base dels controladors. */
abstract class Controller
{
    /** Renderitza una vista pública. */
    protected function view(string $template, array $data = [], string $layout = 'layouts/public'): void
    {
        View::render($template, $data, $layout);
    }

    /** Renderitza una vista del panell d'administració. */
    protected function adminView(string $template, array $data = []): void
    {
        View::render('admin/' . $template, $data, 'layouts/admin');
    }

    /** Comprova el token CSRF dels formularis. */
    protected function checkCsrf(): void
    {
        Csrf::verify();
    }

    /** Torna a la pàgina anterior amb un missatge. */
    protected function back(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = parse_url(base_url(), PHP_URL_HOST);
        if ($referer && parse_url($referer, PHP_URL_HOST) === $host) {
            redirect($referer);
        }
        redirect($fallback);
    }

    /** Validació senzilla: retorna la llista d'errors. */
    protected function validate(array $rules, array $data): array
    {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $value = trim((string) ($data[$field] ?? ''));
            $checks = explode('|', $rule);
            foreach ($checks as $check) {
                [$name, $param] = array_pad(explode(':', $check, 2), 2, null);
                $failed = match ($name) {
                    'required' => $value === '',
                    'email' => $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL),
                    'min' => $value !== '' && mb_strlen($value) < (int) $param,
                    'max' => mb_strlen($value) > (int) $param,
                    'numeric' => $value !== '' && !is_numeric($value),
                    'year' => $value !== '' && (!ctype_digit($value) || (int) $value < 1900 || (int) $value > (int) date('Y')),
                    'accepted' => !in_array($value, ['1', 'on', 'true', 'yes'], true),
                    default => false,
                };
                if ($failed) {
                    $errors[$field] = match ($name) {
                        'required' => 'Aquest camp és obligatori.',
                        'email' => 'L\'adreça electrònica no és vàlida.',
                        'min' => 'Ha de tenir com a mínim ' . $param . ' caràcters.',
                        'max' => 'No pot superar els ' . $param . ' caràcters.',
                        'numeric' => 'Ha de ser un número.',
                        'year' => 'L\'any de naixement no és vàlid.',
                        'accepted' => 'Cal acceptar aquesta condició per continuar.',
                        default => 'Valor no vàlid.',
                    };
                    break;
                }
            }
        }
        return $errors;
    }
}
