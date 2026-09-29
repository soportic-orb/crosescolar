<?php
declare(strict_types=1);

namespace Cros\Payments;

use Cros\Core\Settings;
use RuntimeException;

/**
 * PayPal, amb l'API de comandes (Orders v2).
 *
 * El camí és: es crea una comanda, s'envia qui paga a l'enllaç d'aprovació i,
 * quan torna, se'n fa la captura. La captura és el moment en què els diners
 * canvien de mà; abans només hi ha una promesa.
 */
class PaypalGateway extends Gateway
{
    public static function key(): string
    {
        return 'paypal';
    }

    public static function label(): string
    {
        return 'PayPal';
    }

    public static function configured(): bool
    {
        return self::clientId() !== '' && self::secret() !== '';
    }

    public static function testing(): bool
    {
        return self::mode() !== 'live';
    }

    public static function mode(): string
    {
        return (string) Settings::get('paypal_mode', 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    private static function base(): string
    {
        return self::mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function clientId(): string
    {
        return trim((string) Settings::get('paypal_client_id', ''));
    }

    private static function secret(): string
    {
        return trim((string) Settings::get('paypal_secret', ''));
    }

    public static function begin(array $payment, array $items): array
    {
        $currency = strtoupper((string) ($payment['currency'] ?? 'EUR'));
        $order = self::request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $payment['code'],
                'description' => mb_substr(Checkout::concept($payment), 0, 127),
                'custom_id' => (string) $payment['code'],
                'amount' => [
                    'currency_code' => $currency,
                    'value' => self::amount((int) $payment['total_cents']),
                ],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'user_action' => 'PAY_NOW',
                'return_url' => Checkout::returnUrl($payment),
                'cancel_url' => Checkout::cancelUrl($payment),
            ]]],
        ]);

        $url = '';
        foreach ((array) ($order['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'payer-action' || ($link['rel'] ?? '') === 'approve') {
                $url = (string) $link['href'];
                break;
            }
        }
        if ($url === '') {
            throw new RuntimeException('PayPal no ha donat cap enllaç per anar a pagar.');
        }

        return ['mode' => 'redirect', 'url' => $url, 'fields' => [], 'ref' => (string) ($order['id'] ?? '')];
    }

    public static function check(array $payment, array $input): array
    {
        $orderId = (string) ($input['token'] ?? ($payment['gateway_ref'] ?? ''));
        if ($orderId === '') {
            return self::outcome('pending', '', 'Sense comanda de PayPal.');
        }
        try {
            $order = self::request('GET', '/v2/checkout/orders/' . rawurlencode($orderId));
            $status = (string) ($order['status'] ?? '');
            // Aprovada però encara no cobrada: és el moment de la captura.
            if ($status === 'APPROVED') {
                $order = self::request('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', []);
                $status = (string) ($order['status'] ?? '');
            }
        } catch (\Throwable $e) {
            return self::outcome('pending', '', $e->getMessage());
        }

        $capture = $order['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captureId = (string) ($capture['id'] ?? '');

        if ($status === 'COMPLETED') {
            return self::outcome('paid', $captureId, 'Cobrat per PayPal.');
        }
        if (in_array($status, ['VOIDED', 'DECLINED'], true)) {
            return self::outcome('failed', $captureId, 'PayPal ha rebutjat el pagament.');
        }

        return self::outcome('pending', $captureId, 'PayPal encara no l\'ha donat per cobrat (' . $status . ').');
    }

    public static function refund(array $payment, int $cents): array
    {
        $capture = (string) ($payment['gateway_payment'] ?? '');
        if ($capture === '') {
            return ['ok' => false, 'error' => 'Aquest pagament no té cap captura de PayPal associada.'];
        }
        $body = [];
        if ($cents > 0 && $cents < (int) $payment['total_cents']) {
            $body['amount'] = [
                'currency_code' => strtoupper((string) $payment['currency']),
                'value' => self::amount($cents),
            ];
        }
        try {
            self::request('POST', '/v2/payments/captures/' . rawurlencode($capture) . '/refund', $body);

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Els cèntims, dits com els vol PayPal. */
    private static function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** El testimoni d'accés, que dura una estona i es demana amb les claus. */
    private static function token(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $response = self::call('POST', '/v1/oauth2/token', 'grant_type=client_credentials', [
            'Authorization: Basic ' . base64_encode(self::clientId() . ':' . self::secret()),
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        return $cached = (string) ($response['access_token'] ?? '');
    }

    /** @param array<string,mixed> $body */
    private static function request(string $method, string $path, array $body = []): array
    {
        if (!self::configured()) {
            throw new RuntimeException('PayPal no està configurat.');
        }

        return self::call($method, $path, $body === [] && $method === 'GET' ? null : json_encode($body), [
            'Authorization: Bearer ' . self::token(),
            'Content-Type: application/json',
            'Prefer: return=representation',
        ]);
    }

    /** @param array<int,string> $headers */
    private static function call(string $method, string $path, ?string $body, array $headers): array
    {
        $ch = curl_init(self::base() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('No s\'ha pogut parlar amb PayPal: ' . $error);
        }
        $data = json_decode((string) $raw, true);
        $data = is_array($data) ? $data : [];
        if ($status >= 400) {
            $message = (string) ($data['message'] ?? ($data['error_description'] ?? 'Error de PayPal'));
            throw new RuntimeException($message . ' (HTTP ' . $status . ')');
        }

        return $data;
    }
}
