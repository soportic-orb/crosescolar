<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\LoginLink;
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

    /**
     * Entrada amb un enllaç d'un sol ús.
     * L'envia la plataforma quan s'estrena un cros, quan s'han perdut les
     * claus o quan cal donar suport.
     */
    public function link(array $params): void
    {
        $result = LoginLink::consume((string) ($params['token'] ?? ''));
        if ($result === null) {
            flash('error', 'Aquest enllaç ja s\'ha fet servir o ha caducat. Demaneu-ne un de nou.');
            redirect('/admin/acces');
        }
        $purpose = (string) $result['link']['purpose'];
        Auth::login($result['user']);
        // Que consti al registre del web de qui és: si algú hi entra des de la
        // plataforma, qui el gestiona ho ha de poder veure.
        Auth::logActivity('login_link', 'user', (int) $result['user']['id'], [
            'motiu' => LoginLink::PURPOSES[$purpose] ?? $purpose,
            'nota' => (string) ($result['link']['note'] ?? ''),
        ]);

        if ($purpose === 'support') {
            flash('success', 'Heu entrat amb un enllaç de suport de la plataforma. Ha quedat apuntat al registre.');
            redirect('/admin');
        }

        $_SESSION['admin_set_password'] = true;
        redirect('/admin/clau');
    }

    /** Pantalla per posar-se una contrasenya després d'entrar amb un enllaç. */
    public function showPassword(): void
    {
        Auth::requireLogin();
        View::render('admin/set-password', [
            'title' => 'Poseu-vos una contrasenya',
            'user' => Auth::user(),
            'first' => !empty($_SESSION['admin_set_password']),
        ], 'layouts/minimal');
    }

    /** Desa la contrasenya nova. */
    public function savePassword(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (mb_strlen($password) < 8) {
            flash('error', 'La contrasenya ha de tenir com a mínim 8 caràcters.');
            redirect('/admin/clau');
        }
        if ($password !== $confirm) {
            flash('error', 'Les contrasenyes no coincideixen.');
            redirect('/admin/clau');
        }

        Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => Auth::id()]);
        Auth::logActivity('password_set', 'user', Auth::id());
        unset($_SESSION['admin_set_password']);
        flash('success', 'Contrasenya desada. Ja podeu fer servir el panell.');
        redirect('/admin');
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
