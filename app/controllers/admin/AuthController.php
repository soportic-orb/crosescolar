<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\View;

/** Accés al panell d'administració. */
class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }
        View::render('admin/login', ['title' => 'Accés al panell'], 'layouts/minimal');
    }

    public function login(): void
    {
        $this->checkCsrf();
        $email = (string) input('email');
        $error = Auth::attempt($email, (string) ($_POST['password'] ?? ''));
        if ($error !== null) {
            flash('error', $error);
            set_old(['email' => $email]);
            redirect('/admin/acces');
        }
        $target = (string) ($_SESSION['admin_redirect'] ?? '/admin');
        unset($_SESSION['admin_redirect']);
        redirect($target !== '' && str_contains($target, '/admin') ? $target : '/admin');
    }

    public function logout(): void
    {
        Auth::logout();
        flash('success', 'Heu tancat la sessió.');
        redirect('/admin/acces');
    }

    public function profile(): void
    {
        Auth::requireLogin();
        $this->adminView('profile', ['title' => 'El meu compte', 'user' => Auth::user()]);
    }

    public function updateProfile(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $user = Auth::user();
        $data = ['name' => (string) input('name'), 'email' => mb_strtolower((string) input('email'))];
        $errors = $this->validate(['name' => 'required|max:120', 'email' => 'required|email'], $data);

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if ($password !== '') {
            if (mb_strlen($password) < 8) {
                $errors['password'] = 'La contrasenya ha de tenir com a mínim 8 caràcters.';
            } elseif ($password !== $confirm) {
                $errors['password'] = 'Les contrasenyes no coincideixen.';
            }
        }
        $taken = Db::val('SELECT 1 FROM users WHERE email = :email AND id <> :id', ['email' => $data['email'], 'id' => $user['id']]);
        if ($taken) {
            $errors['email'] = 'Ja hi ha un altre compte amb aquesta adreça.';
        }

        if ($errors) {
            flash('error', reset($errors));
            set_old($data);
            redirect('/admin/perfil');
        }

        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        Db::update('users', $data, 'id = :id', ['id' => $user['id']]);
        Auth::logActivity('profile_update', 'user', (int) $user['id']);
        flash('success', 'Dades actualitzades.');
        redirect('/admin/perfil');
    }
}
