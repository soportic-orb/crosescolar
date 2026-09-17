<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;

/** Usuaris del panell d'administració. */
class UsersController extends Controller
{
    public function index(): void
    {
        Auth::requireAdmin();
        $this->adminView('users/index', [
            'title' => 'Usuaris',
            'rows' => Db::all('SELECT * FROM users ORDER BY name ASC'),
        ]);
    }

    public function create(): void
    {
        Auth::requireAdmin();
        $this->adminView('users/form', [
            'title' => 'Nou usuari',
            'row' => ['id' => 0, 'role' => 'editor', 'active' => 1],
            'isNew' => true,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $data = [
            'name' => (string) input('name'),
            'email' => mb_strtolower((string) input('email')),
            'role' => input('role') === 'admin' ? 'admin' : 'editor',
            'active' => input_bool('active'),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $errors = $this->validate(['name' => 'required|max:120', 'email' => 'required|email'], $data);
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'La contrasenya ha de tenir com a mínim 8 caràcters.';
        }
        if (Db::val('SELECT 1 FROM users WHERE email = :email', ['email' => $data['email']])) {
            $errors['email'] = 'Ja existeix un usuari amb aquesta adreça.';
        }
        if ($errors) {
            flash('error', reset($errors));
            $this->adminView('users/form', ['title' => 'Nou usuari', 'row' => $data, 'isNew' => true, 'errors' => $errors]);
            return;
        }
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $id = Db::insert('users', $data);
        Auth::logActivity('user_create', 'user', $id);
        flash('success', 'Usuari creat.');
        redirect('/admin/usuaris');
    }

    public function edit(array $params): void
    {
        Auth::requireAdmin();
        $row = Db::one('SELECT * FROM users WHERE id = :id', ['id' => (int) $params['id']]);
        if (!$row) {
            abort(404, 'Usuari no trobat.');
        }
        $this->adminView('users/form', ['title' => $row['name'], 'row' => $row, 'isNew' => false, 'errors' => []]);
    }

    public function update(array $params): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $row = Db::one('SELECT * FROM users WHERE id = :id', ['id' => (int) $params['id']]);
        if (!$row) {
            abort(404);
        }
        $data = [
            'name' => (string) input('name'),
            'email' => mb_strtolower((string) input('email')),
            'role' => input('role') === 'admin' ? 'admin' : 'editor',
            'active' => input_bool('active'),
        ];
        $errors = $this->validate(['name' => 'required|max:120', 'email' => 'required|email'], $data);
        if (Db::val('SELECT 1 FROM users WHERE email = :email AND id <> :id', ['email' => $data['email'], 'id' => $row['id']])) {
            $errors['email'] = 'Ja existeix un altre usuari amb aquesta adreça.';
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'] = 'La contrasenya ha de tenir com a mínim 8 caràcters.';
        }
        // No es pot desactivar ni degradar l'últim administrador
        if (((int) $row['id'] === Auth::id()) && ($data['role'] !== 'admin' || $data['active'] !== 1)) {
            $errors['role'] = 'No podeu retirar-vos els permisos a vós mateix.';
        }
        if ($errors) {
            flash('error', reset($errors));
            $this->adminView('users/form', ['title' => $row['name'], 'row' => array_merge($row, $data), 'isNew' => false, 'errors' => $errors]);
            return;
        }
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        Db::update('users', $data, 'id = :id', ['id' => $row['id']]);
        Auth::logActivity('user_update', 'user', (int) $row['id']);
        flash('success', 'Usuari actualitzat.');
        redirect('/admin/usuaris');
    }

    public function destroy(array $params): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        if ($id === Auth::id()) {
            flash('error', 'No podeu esborrar el vostre propi compte.');
            redirect('/admin/usuaris');
        }
        $admins = (int) Db::val('SELECT COUNT(*) FROM users WHERE role = \'admin\' AND active = 1', [], 0);
        $target = Db::one('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        if ($target && $target['role'] === 'admin' && $admins <= 1) {
            flash('error', 'Ha d\'existir com a mínim un administrador.');
            redirect('/admin/usuaris');
        }
        Db::delete('users', 'id = :id', ['id' => $id]);
        Auth::logActivity('user_delete', 'user', $id);
        flash('success', 'Usuari esborrat.');
        redirect('/admin/usuaris');
    }
}
