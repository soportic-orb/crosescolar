<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Models\Menu;

/** Ordre i visibilitat dels apartats del menú del web públic. */
class MenuController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->adminView('menu/index', [
            'title' => 'Menú del web',
            'items' => Menu::all(),
        ]);
    }

    public function update(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $this->saveForm();
        Auth::logActivity('menu_update', 'menu', 0, ['visibles' => count((array) ($_POST['active'] ?? []))]);
        flash('success', 'Menú desat.');
        redirect('/admin/menu');
    }

    public function move(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        // Les fletxes envien el formulari sencer: primer es desa el que s'hi
        // hagi tocat, perquè moure una fila no faci perdre els altres canvis.
        $this->saveForm();
        Menu::move((string) $params['key'], input('direccio') === 'baixa' ? 1 : -1);
        $this->back('/admin/menu');
    }

    private function saveForm(): void
    {
        Menu::save(
            array_map('strval', (array) ($_POST['active'] ?? [])),
            array_map('strval', (array) ($_POST['label'] ?? []))
        );
    }
}
