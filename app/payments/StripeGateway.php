<?php
declare(strict_types=1);

namespace Cros\Payments;

use Cros\Core\Stripe;

/**
 * Stripe, amb Checkout: el pagament es fa a la seva pàgina i aquí no hi toca
 * mai cap número de targeta.
 */
class StripeGateway extends Gateway
{
    public static function key(): string
    {
        return 'stripe';
    }

    public static function label(): string
    {
        return 'Stripe (targeta, Bizum i altres)';
    }

    public static function configured(): bool
    {
        return Stripe::configured();
    }

    public static function testing(): bool
    {
        return Stripe::mode() !== 'live';
    }

    public static function begin(array $payment, array $items): array
    {
        $currency = strtolower((string) ($payment['currency'] ?? 'EUR'));
        $lines = [];
        foreach ($items as $index => $item) {
            $lines[$index] = [
                'quantity' => max(1, (int) $item['qty']),
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) $item['unit_price_cents'],
                    'product_data' => ['name' => mb_substr((string) $item['description'], 0, 120)],
                ],
            ];
        }
        if (!$lines) {
            $lines[0] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => (int) $payment['total_cents'],
                    'product_data' => ['name' => mb_substr(Checkout::concept($payment), 0, 120)],
                ],
            ];
        }

        $back = Checkout::returnUrl($payment);
        $session = Stripe::createCheckoutSession([
            'mode' => 'payment',
            'success_url' => $back . (str_contains($back, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => Checkout::cancelUrl($payment),
            'customer_email' => (string) $payment['payer_email'],
            'client_reference_id' => (string) $payment['code'],
            'line_items' => $lines,
            'metadata' => ['payment_id' => (string) $payment['id'], 'payment_code' => (string) $payment['code']],
            'payment_intent_data' => [
                'description' => mb_substr(Checkout::concept($payment), 0, 200),
                'metadata' => ['payment_code' => (string) $payment['code']],
            ],
            'expires_at' => time() + 3600,
        ], 'pay-' . $payment['id'] . '-' . substr((string) $payment['token'], 0, 8));

        return [
            'mode' => 'redirect',
            'url' => (string) ($session['url'] ?? ''),
            'fields' => [],
            'ref' => (string) ($session['id'] ?? ''),
        ];
    }

    public static function check(array $payment, array $input): array
    {
        $sessionId = (string) ($input['session_id'] ?? ($payment['gateway_ref'] ?? ''));
        if ($sessionId === '') {
            return self::outcome('pending', '', 'Sense sessió de pagament.');
        }
        try {
            $session = Stripe::retrieveSession($sessionId);
        } catch (\Throwable $e) {
            return self::outcome('pending', '', $e->getMessage());
        }
        $status = (string) ($session['payment_status'] ?? '');
        $intent = $session['payment_intent'] ?? null;
        $intentId = is_array($intent) ? (string) ($intent['id'] ?? '') : (string) $intent;

        if ($status === 'paid') {
            return self::outcome('paid', $intentId, 'Cobrat per Stripe.');
        }
        if (($session['status'] ?? '') === 'expired') {
            return self::outcome('failed', $intentId, 'La sessió de pagament ha caducat.');
        }

        return self::outcome('pending', $intentId, 'Stripe encara no l\'ha donat per cobrat.');
    }

    public static function refund(array $payment, int $cents): array
    {
        $intent = (string) ($payment['gateway_payment'] ?? '');
        if ($intent === '') {
            return ['ok' => false, 'error' => 'Aquest pagament no té cap cobrament de Stripe associat.'];
        }
        try {
            Stripe::refund($intent, $cents > 0 ? $cents : null);

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
