<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Tenancy;
use Cros\Models\Activation;
use Cros\Platform\Bridge;
use Cros\Platform\Charge;
use Cros\Platform\Instance;
use Cros\Platform\Invoice;
use Cros\Platform\Plan;

/**
 * L'activació del web, al panell del client.
 *
 * Tot el que toca dades va dins d'un Bridge::run(): els pagaments a la
 * plataforma viuen a la seva base de dades i no a la del client. Els diners
 * que el client cobra als seus participants són una altra cosa i tenen el seu
 * propi apartat, a «Cobraments».
 */
class ActivationController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $this->ensureAvailable();

        $this->adminView('activation/index', [
            'title' => 'Activació del web',
            'status' => Activation::status(),
            'payments' => Activation::payments(),
            'invoices' => $this->invoices(),
        ]);
    }

    /**
     * La pantalla de pagament, amb el formulari de la targeta aquí mateix.
     *
     * Qui publica el web no ha de marxar enlloc: veu el preu, hi posa la
     * targeta i llestos. El número de la targeta, això sí, va directament de
     * qui el teclegi a Stripe; pel nostre servidor no hi passa mai.
     */
    public function checkout(): void
    {
        Auth::requireAdmin();
        $this->ensureAvailable();

        $status = Activation::status();
        if (!$status['applies'] || $status['paid']) {
            redirect('/admin/activacio');
        }
        if (!$status['ready']) {
            flash('error', 'La plataforma encara no té el cobrament a punt. Escriviu-nos i ho mirem.');
            redirect('/admin/activacio');
        }

        $slug = Tenancy::slugOf();
        $payer = $this->payer();
        $pagament = [];
        try {
            $pagament = Bridge::run(static function () use ($slug, $payer): array {
                $instance = Instance::bySlug($slug);
                if (!$instance) {
                    throw new \RuntimeException('Aquest web no consta a la plataforma.');
                }
                $charge = Charge::forActivation($instance, $payer);

                return Charge::intent($charge) + ['code' => (string) $charge['code']];
            }, null, true);
        } catch (\Throwable $e) {
            log_line('activation', 'No s\'ha pogut preparar el pagament', ['error' => $e->getMessage()]);
            flash('error', 'No s\'ha pogut preparar el pagament: ' . $e->getMessage());
            redirect('/admin/activacio');
        }

        if (trim((string) ($pagament['client_secret'] ?? '')) === ''
            || trim((string) ($pagament['publishable'] ?? '')) === ''
        ) {
            flash('error', 'No s\'ha pogut preparar el pagament. Torneu-ho a provar d\'aquí una estona.');
            redirect('/admin/activacio');
        }

        $this->adminView('activation/checkout', [
            'title' => 'Publicar el web',
            'status' => $status,
            'pagament' => $pagament,
            'payer' => $payer,
        ]);
    }

    /** Comença el pagament i se'n va a la pàgina segura de Stripe. */
    public function pay(): void
    {
        Auth::requireAdmin();
        $this->checkCsrf();
        $this->ensureAvailable();

        $slug = Tenancy::slugOf();
        $payer = $this->payer();
        $url = '';
        $error = '';
        try {
            $url = Bridge::run(static function () use ($slug, $payer): string {
                $instance = Instance::bySlug($slug);
                if (!$instance) {
                    throw new \RuntimeException('Aquest web no consta a la plataforma.');
                }
                if (!Plan::enabled()) {
                    throw new \RuntimeException('Ara mateix no cal pagar res per publicar el web.');
                }
                if (!empty($instance['activated_at'])) {
                    throw new \RuntimeException('Aquest web ja està activat.');
                }
                $charge = Charge::forActivation($instance, $payer);

                return Charge::checkout(
                    $charge,
                    url('/admin/activacio/tornada'),
                    url('/admin/activacio')
                );
            }, null, true);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        if ($error !== '' || $url === '') {
            flash('error', $error !== '' ? $error : 'No s\'ha pogut començar el pagament.');
            redirect('/admin/activacio');
        }
        redirect($url);
    }

    /** Torna de Stripe. */
    public function returned(): void
    {
        Auth::requireLogin();
        $this->ensureAvailable();
        $session = (string) input('session_id');
        $slug = Tenancy::slugOf();

        $status = Bridge::run(static function () use ($slug, $session): string {
            $instance = Instance::bySlug($slug);
            $charge = $instance ? Charge::pendingActivation((int) $instance['id']) : null;
            if (!$charge && $session !== '') {
                $charge = Charge::findBySession($session);
            }
            if (!$charge) {
                return 'unknown';
            }

            return Charge::confirm($charge, $session)['status'];
        }, null, true);

        if ($status === 'paid') {
            flash('success', 'Pagament rebut. Ja podeu publicar el web quan vulgueu.');
        } elseif ($status === 'pending') {
            flash('info', 'El pagament s\'està comprovant. En pocs segons hauria de quedar confirmat.');
        } elseif ($status === 'failed') {
            flash('error', 'El pagament no s\'ha pogut fer. No s\'ha carregat res.');
        }
        redirect('/admin/activacio');
    }

    /** La factura d'un pagament, en PDF. */
    public function invoice(array $params): void
    {
        Auth::requireLogin();
        $this->ensureAvailable();
        $slug = Tenancy::slugOf();
        $id = (int) $params['id'];

        [$pdf, $name] = Bridge::run(static function () use ($slug, $id): array {
            $instance = Instance::bySlug($slug);
            $charge = Charge::find($id);
            // Ningú no ha de poder demanar la factura d'un altre client.
            if (!$instance || !$charge || (int) $charge['instance_id'] !== (int) $instance['id']) {
                return ['', ''];
            }
            $invoice = Invoice::forPayment($id);

            return $invoice ? [Invoice::pdf($invoice), Invoice::filename($invoice)] : ['', ''];
        }, null, true);

        if ($pdf === '') {
            throw new HttpException(404, 'Aquesta factura no existeix.');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** Les factures que hi ha, per pagament. @return array<int,array<string,mixed>> */
    private function invoices(): array
    {
        $slug = Tenancy::slugOf();
        try {
            return Bridge::run(static function () use ($slug): array {
                $instance = Instance::bySlug($slug);
                if (!$instance) {
                    return [];
                }
                $invoices = [];
                foreach (Charge::forInstance((int) $instance['id']) as $charge) {
                    $invoice = Invoice::forPayment((int) $charge['id']);
                    if ($invoice) {
                        $invoices[(int) $charge['id']] = $invoice;
                    }
                }

                return $invoices;
            });
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Les dades fiscals de qui paga, tal com les té el cros.
     *
     * S'han de llegir **abans** d'entrar al pont: a dins, la configuració
     * carregada és la de la plataforma i aquests valors no hi serien.
     *
     * @return array<string,string>
     */
    private function payer(): array
    {
        return [
            'name' => trim((string) setting('billing_entity', setting('legal_entity', setting('site_name', '')))),
            'email' => trim((string) setting('billing_email', setting('contact_email', ''))),
            'nif' => trim((string) setting('billing_nif', '')),
            'address' => trim((string) setting('billing_address', '')),
            'postcode' => trim((string) setting('billing_postcode', '')),
            'town' => trim((string) setting('billing_town', '')),
        ];
    }

    private function ensureAvailable(): void
    {
        if (Tenancy::slugOf() === '' || !Bridge::available()) {
            throw new HttpException(404, 'Aquesta instal·lació no va per plataforma.');
        }
    }
}
