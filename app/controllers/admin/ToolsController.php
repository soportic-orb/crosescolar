<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\Mailer;
use Cros\Core\Stripe;
use Cros\Models\Ticket;

/** Eines: validació de tiquets, registres i proves de configuració. */
class ToolsController extends Controller
{
    /** Pantalla de validació de tiquets (lector de QR). */
    public function scanner(): void
    {
        Auth::requireLogin();
        $this->adminView('tools/scanner', [
            'title' => 'Validació de tiquets',
            'result' => null,
            'stats' => [
                'valid' => (int) Db::val('SELECT COUNT(*) FROM tickets WHERE status = \'valid\'', [], 0),
                'used' => (int) Db::val('SELECT COUNT(*) FROM tickets WHERE status = \'used\'', [], 0),
            ],
            'recent' => Db::all('SELECT t.*, tt.name AS type_name FROM tickets t LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id WHERE t.status = \'used\' ORDER BY t.used_at DESC LIMIT 10'),
        ]);
    }

    /** Validació per formulari (o AJAX des del lector). */
    public function validateTicket(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $code = (string) input('code');
        $code = preg_replace('#^.*/validar/#', '', trim($code)) ?? $code;
        $result = Ticket::validate($code, input_bool('check_only') !== 1);

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            json_out($result);
        }
        $this->adminView('tools/scanner', [
            'title' => 'Validació de tiquets',
            'result' => $result,
            'stats' => [
                'valid' => (int) Db::val('SELECT COUNT(*) FROM tickets WHERE status = \'valid\'', [], 0),
                'used' => (int) Db::val('SELECT COUNT(*) FROM tickets WHERE status = \'used\'', [], 0),
            ],
            'recent' => Db::all('SELECT t.*, tt.name AS type_name FROM tickets t LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id WHERE t.status = \'used\' ORDER BY t.used_at DESC LIMIT 10'),
        ]);
    }

    /** Enllaç del codi QR: mostra l'estat del tiquet (cal sessió iniciada). */
    public function validateLink(array $params): void
    {
        Auth::requireLogin();
        $result = Ticket::validate((string) $params['code'], false);
        $this->adminView('tools/ticket-status', [
            'title' => 'Tiquet ' . strtoupper((string) $params['code']),
            'result' => $result,
        ]);
    }

    public function activity(): void
    {
        Auth::requireLogin();
        $this->adminView('tools/activity', [
            'title' => 'Registre d\'activitat',
            'rows' => Db::all(
                'SELECT a.*, u.name AS user_name FROM activity_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.created_at DESC LIMIT 200'
            ),
        ]);
    }

    public function emails(): void
    {
        Auth::requireLogin();
        $this->adminView('tools/emails', [
            'title' => 'Correus enviats',
            'rows' => Db::all('SELECT * FROM email_log ORDER BY created_at DESC LIMIT 100'),
        ]);
    }

    public function testEmail(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $to = (string) input('to', (string) (Auth::user()['email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Adreça no vàlida.');
            redirect('/admin/correus');
        }
        $ok = Mailer::send($to, 'Prova de correu — ' . setting('site_name', 'Cros Escolar'),
            '<p>Aquest és un correu de prova enviat des del panell del Cros Escolar La Granada.</p>'
            . '<p>Si el rebeu, la configuració de correu funciona correctament.</p>');
        flash($ok ? 'success' : 'error', $ok ? 'Correu de prova enviat a ' . $to : 'No s\'ha pogut enviar el correu. Reviseu la configuració.');
        redirect('/admin/correus');
    }

    public function testStripe(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        try {
            Stripe::ping();
            flash('success', 'Connexió amb Stripe correcta (mode ' . Stripe::mode() . ').');
        } catch (\Throwable $e) {
            flash('error', 'Stripe: ' . $e->getMessage());
        }
        redirect('/admin/configuracio/payments');
    }
}
