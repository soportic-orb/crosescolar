<?php
declare(strict_types=1);

namespace Cros\Controllers\Admin;

use Cros\Core\Auth;
use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\HttpException;
use Cros\Models\Billing;
use Cros\Models\Order;
use Cros\Models\Payment;
use Cros\Models\Registration;
use Cros\Payments\Gateways;

/** Els cobraments del cros, vistos des del panell. */
class PaymentsController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();
        $filters = [
            'status' => (string) ($_GET['estat'] ?? ''),
            'concept' => (string) ($_GET['concepte'] ?? ''),
            'search' => trim((string) ($_GET['q'] ?? '')),
        ];

        $this->adminView('payments/index', [
            'title' => 'Cobraments',
            'payments' => Payment::search($filters),
            'filters' => $filters,
            'totals' => Payment::totals(),
            'gateway' => Gateways::chosen(),
            'ready' => Gateways::ready(),
            'billing' => Billing::missing(),
            'documentType' => Billing::documentType(),
        ]);
    }

    public function show(array $params): void
    {
        Auth::requireLogin();
        $payment = $this->payment((int) $params['id']);

        $this->adminView('payments/show', [
            'title' => 'Cobrament ' . $payment['code'],
            'payment' => $payment,
            'items' => Payment::items((int) $payment['id']),
            'document' => Billing::document((int) $payment['id']),
            'order' => (string) $payment['concept'] === 'order' && !empty($payment['reference_id'])
                ? Order::find((int) $payment['reference_id']) : null,
            'registration' => (string) $payment['concept'] === 'registration' && !empty($payment['reference_id'])
                ? Registration::find((int) $payment['reference_id']) : null,
        ]);
    }

    /** Cobrar a mà, tornar els diners o emetre el document. */
    public function action(array $params): void
    {
        Auth::requireLogin();
        $this->checkCsrf();
        $payment = $this->payment((int) $params['id']);
        $id = (int) $payment['id'];

        switch ((string) input('action')) {
            case 'paid':
                // Algú ha pagat en mà o per transferència: queda igual de
                // cobrat, i el document s'emet igual.
                if ((string) $payment['status'] !== 'pending') {
                    flash('error', 'Aquest cobrament ja no està pendent.');
                    break;
                }
                Db::update('payments', ['gateway' => (string) ($payment['gateway'] ?? '') ?: 'manual'],
                    'id = :id', ['id' => $id]);
                Payment::markPaid(Payment::find($id) ?? $payment, '', 'Cobrat a mà des del panell.');
                Auth::logActivity('payment_manual', 'payment', $id, ['code' => $payment['code']]);
                flash('success', 'Cobrament donat per pagat.');
                break;

            case 'refund':
                $cents = (int) round(((float) str_replace(',', '.', (string) input('amount', '0'))) * 100);
                $result = Payment::refund($payment, $cents);
                Auth::logActivity('payment_refund', 'payment', $id, ['code' => $payment['code'], 'ok' => $result['ok']]);
                flash($result['ok'] ? 'success' : 'error', $result['ok']
                    ? 'S\'han tornat els diners.'
                    : 'No s\'han pogut tornar: ' . $result['error']);
                break;

            case 'document':
                $document = Billing::issue($payment);
                flash($document ? 'success' : 'error', $document
                    ? 'Document ' . $document['full_number'] . ' emès.'
                    : 'Només es poden emetre documents de cobraments pagats.');
                break;

            case 'cancel':
                Payment::cancel($payment, 'Anul·lat des del panell.');
                flash('success', 'Cobrament anul·lat.');
                break;

            default:
                flash('error', 'Acció desconeguda.');
        }

        $this->back('/admin/pagaments/' . $id);
    }

    /** El rebut o la factura en PDF. */
    public function document(array $params): void
    {
        Auth::requireLogin();
        $payment = $this->payment((int) $params['id']);
        $document = Billing::document((int) $payment['id']);
        if (!$document) {
            throw new HttpException(404, 'Aquest cobrament encara no té cap document.');
        }
        $pdf = Billing::pdf($document);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . Billing::filename($document) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /** Tots els documents emesos, per a qui porta la comptabilitat. */
    public function documents(): void
    {
        Auth::requireLogin();
        $this->adminView('payments/documents', [
            'title' => 'Rebuts i factures',
            'documents' => Billing::recent(),
            'complete' => Billing::complete(),
            'missing' => Billing::missing(),
            'documentType' => Billing::documentType(),
        ]);
    }

    private function payment(int $id): array
    {
        $payment = Payment::find($id);
        if (!$payment) {
            throw new HttpException(404, 'Aquest cobrament no existeix.');
        }

        return $payment;
    }
}
