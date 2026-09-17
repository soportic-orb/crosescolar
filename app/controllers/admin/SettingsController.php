<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Html;
use Cros\Core\Settings;
use Cros\Core\Uploader;

/** Edició dels textos i les opcions del web. */
class SettingsController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        redirect('/admin/configuracio/general');
    }

    public function edit(array $params): void
    {
        Auth::requireLogin();
        $key = (string) $params['group'];
        $group = Settings::group($key);
        if (!$group) {
            abort(404, 'Secció de configuració desconeguda.');
        }
        if (!empty($group['admin_only'])) {
            Auth::requireAdmin();
        }
        $this->adminView('settings', [
            'title' => $group['title'],
            'groupKey' => $key,
            'group' => $group,
            'values' => Settings::load(),
        ]);
    }

    /** Commutador ràpid del mode «web en preparació». */
    public function toggleComingSoon(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $enable = input_bool('enable');
        Settings::set('coming_soon', (string) $enable);
        Auth::logActivity('coming_soon', 'settings', 0, ['actiu' => $enable]);
        flash('success', $enable === 1
            ? 'El web queda amagat: els visitants veuran l\'avís «' . setting('coming_soon_title', 'Aviat publicarem el web') . '».'
            : 'El web ja és visible per a tothom.');
        $this->back('/admin');
    }

    public function update(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $key = (string) $params['group'];
        $group = Settings::group($key);
        if (!$group) {
            abort(404, 'Secció de configuració desconeguda.');
        }
        if (!empty($group['admin_only'])) {
            Auth::requireAdmin();
        }

        $errors = [];
        foreach ($group['fields'] as $name => $field) {
            $type = $field['type'] ?? 'text';

            if (in_array($type, ['image', 'file'], true)) {
                if (input_bool($name . '_remove') === 1) {
                    Uploader::delete((string) setting($name, ''));
                    Settings::set($name, '');
                }
                if (Uploader::has($name)) {
                    try {
                        $old = (string) setting($name, '');
                        $path = $type === 'image'
                            ? Uploader::image($_FILES[$name], $field['folder'] ?? 'media', (int) ($field['max_width'] ?? 1920), (int) ($field['max_height'] ?? 1920))
                            : Uploader::document($_FILES[$name], $field['folder'] ?? 'documents');
                        Settings::set($name, $path);
                        if ($old !== '' && $old !== $path) {
                            Uploader::delete($old);
                        }
                    } catch (\RuntimeException $e) {
                        $errors[] = $field['label'] . ': ' . $e->getMessage();
                    }
                }
                continue;
            }

            if ($type === 'bool') {
                Settings::set($name, (string) input_bool($name));
                continue;
            }

            $raw = $_POST[$name] ?? null;
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

        Auth::logActivity('settings_update', 'settings', 0, ['group' => $key]);
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            flash('success', 'Configuració desada.');
        }
        redirect('/admin/configuracio/' . $key);
    }
}
