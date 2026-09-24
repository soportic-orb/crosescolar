<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Settings;
use Cros\Core\SettingsForm;

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

        $errors = SettingsForm::save($group['fields']);

        Auth::logActivity('settings_update', 'settings', 0, ['group' => $key]);
        if ($errors) {
            flash('error', implode(' ', $errors));
        } else {
            flash('success', 'Configuració desada.');
        }
        redirect('/admin/configuracio/' . $key);
    }

}
