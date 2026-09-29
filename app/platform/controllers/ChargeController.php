<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Stripe;
use Cros\Core\View;
use Cros\Platform\Charge;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\Invoice;
use Cros\Platform\Plan;

/** Els cobraments que fa la plataforma als seus clients. */
class ChargeController extends Controller
{
    public function index(): void
    {
        Console::requireLogin();
        $filters = [
            'status' => (string) ($_GET['estat'] ?? ''),
            'search' => trim((string) ($_GET['q'] ?? '')),
        ];

        View::render('platform/console/charges/index', [
            'title' => 'Pagaments',
            'charges' => Charge::search($filters),
            'filters' => $filters,
            'totals' => Charge::totals(),
            'plan' => [
                'enabled' => Plan::enabled(),
                'ready' => Plan::ready(),
                'price' => Plan::price(),
                'name' => Plan::name(),
                'stripe' => Stripe::configured(),
                'testing' => Stripe::mode() !== 'live',
            ],
            'missing' => Invoice::missing(),
        ], 'layouts/console');
    }

    public function show(array $params): void
    {
        Console::requireLogin();
        $charge = $this->charge((int) $params['id']);

        View::render('platform/console/charges/show', [
            'title' => 'Pagament ' . $charge['code'],
            'charge' => $charge,
            'invoice' => Invoice::forPayment((int) $charge['id']),
            'instance' => !empty($charge['instance_id']) ? Instance::find((int) $charge['instance_id']) : null,
        ], 'layouts/console');
    }

    /** Cobrar a mà, tornar els diners o emetre la factura. */
    public function action(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $charge = $this->charge((int) $params['id']);
        $id = (int) $charge['id'];

        switch ((string) input('action')) {
            case 'paid':
                if ((string) $charge['status'] !== 'pending') {
                    flash('error', 'Aquest pagament ja no està pendent.');
                    break;
                }
                Charge::markPaid($charge, '', 'Cobrat fora de línia (transferència o en mà).');
                Console::log('charge_manual', 'payment', $id, ['code' => $charge['code']]);
                flash('success', 'Pagament donat per fet i web activat.');
                break;

            case 'refund':
                $cents = (int) round(((float) str_replace(',', '.', (string) input('amount', '0'))) * 100);
                $result = Charge::refund($charge, $cents);
                Console::log('charge_refund', 'payment', $id, ['code' => $charge['code'], 'ok' => $result['ok']]);
                flash($result['ok'] ? 'success' : 'error', $result['ok']
                    ? 'S\'han tornat els diners.' : 'No s\'han pogut tornar: ' . $result['error']);
                break;

            case 'invoice':
                $invoice = Invoice::issue($charge);
                flash($invoice ? 'success' : 'error', $invoice
                    ? 'Factura ' . $invoice['full_number'] . ' emesa.'
                    : 'Només es poden facturar els pagaments fets.');
                break;

            case 'cancel':
                Charge::cancel($charge, 'Anul·lat des del panell.');
                flash('success', 'Pagament anul·lat.');
                break;

            default:
                flash('error', 'Acció desconeguda.');
        }

        $this->back('/pagaments/' . $id);
    }

    public function invoice(array $params): void
    {
        Console::requireLogin();
        $charge = $this->charge((int) $params['id']);
        $invoice = Invoice::forPayment((int) $charge['id']);
        if (!$invoice) {
            throw new HttpException(404, 'Aquest pagament encara no té factura.');
        }
        $pdf = Invoice::pdf($invoice);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . Invoice::filename($invoice) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    public function invoices(): void
    {
        Console::requireLogin();
        View::render('platform/console/charges/invoices', [
            'title' => 'Factures emeses',
            'invoices' => Invoice::recent(),
            'complete' => Invoice::complete(),
            'missing' => Invoice::missing(),
        ], 'layouts/console');
    }

    private function charge(int $id): array
    {
        $charge = Charge::find($id);
        if (!$charge) {
            throw new HttpException(404, 'Aquest pagament no existeix.');
        }

        return $charge;
    }
}
