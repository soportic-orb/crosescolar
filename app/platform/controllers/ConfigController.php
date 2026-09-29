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
use Cros\Platform\Platform;
use Cros\Platform\Site;

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

        $domain = $this->site($key);

        View::render('platform/console/settings', [
            'title' => $group['title'],
            'groupKey' => $key,
            'group' => $group,
            'values' => $this->values($key, $domain),
            'perSite' => Site::isPerSite($key),
            'domain' => $domain,
            'domains' => Platform::domains(),
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

        $domain = $this->site($key);
        $errors = SettingsForm::save($group['fields']);
        Console::log('config_update', 'settings', null, ['grup' => $key, 'domini' => $domain]);

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
        redirect(url('/configuracio/' . $key, $domain !== '' ? ['domini' => $domain] : []));
    }

    /**
     * De quina pàgina pública s'estan tocant els textos.
     *
     * Els grups que no van per domini no en tenen cap: són de tota la
     * plataforma i es desen com sempre. Torna '' en aquest cas.
     */
    private function site(string $group): string
    {
        if (!Site::isPerSite($group)) {
            Settings::scope();

            return '';
        }
        $domain = Platform::validDomain((string) ($_GET['domini'] ?? ''));
        Site::activate($domain);

        return $domain;
    }

    /**
     * Els valors que s'han d'ensenyar al formulari.
     * Amb un domini triat, els seus; si encara no en té cap de propi, els
     * que hereta.
     *
     * @return array<string,string>
     */
    private function values(string $group, string $domain): array
    {
        $values = Settings::load();
        if ($domain === '') {
            return $values;
        }
        $fields = (array) (Settings::group($group)['fields'] ?? []);
        foreach (array_keys($fields) as $name) {
            $values[(string) $name] = (string) Settings::get((string) $name);
        }

        return $values;
    }
}
