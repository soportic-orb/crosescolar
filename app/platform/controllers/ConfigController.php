<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Core\SettingsForm;
use Cros\Core\Tenancy;
use Cros\Core\View;
use Cros\Platform\Console;

/** La configuració de la plataforma: nom, imatge, correu, vigilància… */
class ConfigController extends Controller
{
    public function index(): void
    {
        Console::requireLogin();
        redirect('/configuracio/general');
    }

    public function edit(array $params): void
    {
        Console::requireLogin();
        $key = (string) $params['group'];
        $group = Settings::group($key);
        if (!$group) {
            throw new HttpException(404, 'Aquesta secció de configuració no existeix.');
        }

        View::render('platform/console/settings', [
            'title' => $group['title'],
            'groupKey' => $key,
            'group' => $group,
            'values' => Settings::load(),
            'platformFile' => Tenancy::settings(CROS_ROOT),
        ], 'layouts/console');
    }

    public function update(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $key = (string) $params['group'];
        $group = Settings::group($key);
        if (!$group) {
            throw new HttpException(404, 'Aquesta secció de configuració no existeix.');
        }

        $errors = SettingsForm::save($group['fields']);
        Console::log('config_update', 'settings', null, ['grup' => $key]);

        // Una prova de correu, si l'han demanat des del mateix formulari.
        if (($_POST['provar_correu'] ?? '') === '1' && !$errors) {
            Settings::load(true);
            $to = (string) setting('mail_admin_notify', '');
            if ($to === '') {
                $errors[] = 'Indiqueu on han d\'arribar els avisos per poder provar-ho.';
            } else {
                $sent = Mailer::send($to, 'Prova del correu de la plataforma',
                    '<p>Si llegiu això, el correu de la plataforma funciona.</p>');
                $sent
                    ? flash('success', 'Prova enviada a ' . $to . '.')
                    : flash('error', 'No s\'ha pogut enviar la prova. Reviseu les dades del servidor de correu.');
            }
        }

        flash($errors ? 'error' : 'success', $errors ? implode(' ', $errors) : 'Configuració desada.');
        redirect('/configuracio/' . $key);
    }
}
