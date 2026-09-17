<?php
declare(strict_types=1);

namespace Cros\Controllers;

use Cros\Core\Controller;
use Cros\Core\Db;
use Cros\Core\Stripe;
use Cros\Models\Order;

/** Recepció dels esdeveniments de Stripe. */
class WebhookController extends Controller
{
    public function stripe(): void
    {
        $payload = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        try {
            $event = Stripe::constructEvent($payload, $signature, Stripe::webhookSecret());
        } catch (\Throwable $e) {
            log_line('stripe', 'Webhook rebutjat', ['error' => $e->getMessage()]);
            json_out(['error' => 'Signatura no vàlida'], 400);
            return;
        }

        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        try {
            switch ($type) {
                case 'checkout.session.completed':
                case 'checkout.session.async_payment_succeeded':
                    if (($object['payment_status'] ?? '') === 'paid') {
                        $order = $this->resolveOrder($object);
                        if ($order) {
                            Order::markPaid($order, [
                                'payment_intent' => is_array($object['payment_intent'] ?? null) ? ($object['payment_intent']['id'] ?? null) : ($object['payment_intent'] ?? null),
                                'session_id' => $object['id'] ?? null,
                            ]);
                        }
                    }
                    break;

                case 'checkout.session.expired':
                case 'checkout.session.async_payment_failed':
                    $order = $this->resolveOrder($object);
                    if ($order && $order['status'] === 'pending') {
                        Order::cancel($order, 'Sessió de pagament caducada o fallida.');
                    }
                    break;

                case 'charge.refunded':
                    $intent = (string) ($object['payment_intent'] ?? '');
                    if ($intent !== '') {
                        $order = Db::one('SELECT * FROM orders WHERE stripe_payment_intent = :intent', ['intent' => $intent]);
                        if ($order) {
                            Db::update('orders', [
                                'status' => 'refunded',
                                'refunded_cents' => (int) ($object['amount_refunded'] ?? 0),
                                'updated_at' => date('Y-m-d H:i:s'),
                            ], 'id = :id', ['id' => $order['id']]);
                            Db::update('tickets', ['status' => 'void'], 'order_id = :id AND status = \'valid\'', ['id' => $order['id']]);
                        }
                    }
                    break;
            }
        } catch (\Throwable $e) {
            log_line('stripe', 'Error processant el webhook', ['type' => $type, 'error' => $e->getMessage()]);
            json_out(['error' => 'Error intern'], 500);
            return;
        }

        json_out(['received' => true]);
    }

    private function resolveOrder(array $session): ?array
    {
        if (!empty($session['id'])) {
            $order = Order::findBySession((string) $session['id']);
            if ($order) {
                return $order;
            }
        }
        if (!empty($session['metadata']['order_id'])) {
            $order = Order::find((int) $session['metadata']['order_id']);
            if ($order) {
                return $order;
            }
        }
        if (!empty($session['client_reference_id'])) {
            return Order::findByCode((string) $session['client_reference_id']);
        }
        return null;
    }
}
