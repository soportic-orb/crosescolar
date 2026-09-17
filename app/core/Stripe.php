<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Client mínim de l'API de Stripe (sense dependències externes).
 * Fa servir Stripe Checkout: el pagament es fa a la pàgina segura de Stripe.
 */
class Stripe
{
    private const API_BASE = 'https://api.stripe.com/v1/';
    private const API_VERSION = '2024-06-20';

    /** Mode actiu: test o live. */
    public static function mode(): string
    {
        return setting('stripe_mode', 'test') === 'live' ? 'live' : 'test';
    }

    public static function publishableKey(): string
    {
        return (string) setting(self::mode() === 'live' ? 'stripe_pk_live' : 'stripe_pk_test', '');
    }

    public static function secretKey(): string
    {
        return (string) setting(self::mode() === 'live' ? 'stripe_sk_live' : 'stripe_sk_test', '');
    }

    public static function webhookSecret(): string
    {
        return (string) setting(self::mode() === 'live' ? 'stripe_webhook_live' : 'stripe_webhook_test', '');
    }

    /** Hi ha credencials configurades? */
    public static function configured(): bool
    {
        return self::secretKey() !== '' && self::publishableKey() !== '';
    }

    /** Crea una sessió de Checkout i retorna la resposta de Stripe. */
    public static function createCheckoutSession(array $params, ?string $idempotencyKey = null): array
    {
        return self::request('POST', 'checkout/sessions', $params, $idempotencyKey);
    }

    /** Recupera una sessió de Checkout. */
    public static function retrieveSession(string $sessionId, array $expand = []): array
    {
        $query = [];
        foreach ($expand as $index => $value) {
            $query['expand'][$index] = $value;
        }
        $path = 'checkout/sessions/' . rawurlencode($sessionId);
        if ($query) {
            $path .= '?' . http_build_query($query);
        }
        return self::request('GET', $path);
    }

    /** Recupera un PaymentIntent. */
    public static function retrievePaymentIntent(string $id): array
    {
        return self::request('GET', 'payment_intents/' . rawurlencode($id));
    }

    /** Fa una devolució total o parcial. */
    public static function refund(string $paymentIntent, ?int $amountCents = null): array
    {
        $params = ['payment_intent' => $paymentIntent];
        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }
        return self::request('POST', 'refunds', $params);
    }

    /** Comprova que les credencials són vàlides. */
    public static function ping(): array
    {
        return self::request('GET', 'balance');
    }

    /**
     * Valida la signatura d'un webhook i retorna l'esdeveniment descodificat.
     * @throws \RuntimeException si la signatura no és vàlida.
     */
    public static function constructEvent(string $payload, string $signatureHeader, string $secret, int $tolerance = 300): array
    {
        if ($secret === '') {
            throw new \RuntimeException('No hi ha cap secret de webhook configurat.');
        }
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pieces = explode('=', trim($part), 2);
            if (count($pieces) !== 2) {
                continue;
            }
            if ($pieces[0] === 't') {
                $timestamp = (int) $pieces[1];
            } elseif ($pieces[0] === 'v1') {
                $signatures[] = $pieces[1];
            }
        }
        if (!$timestamp || !$signatures) {
            throw new \RuntimeException('Capçalera de signatura no vàlida.');
        }
        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            throw new \RuntimeException('La signatura del webhook ha caducat.');
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $valid = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            throw new \RuntimeException('La signatura del webhook no coincideix.');
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new \RuntimeException('El contingut del webhook no és JSON vàlid.');
        }
        return $event;
    }

    /** Petició HTTP a l'API de Stripe. */
    private static function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $secret = self::secretKey();
        if ($secret === '') {
            throw new \RuntimeException('No s\'han configurat les credencials de Stripe.');
        }
        $url = self::API_BASE . $path;
        $headers = [
            'Authorization: Bearer ' . $secret,
            'Stripe-Version: ' . self::API_VERSION,
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($idempotencyKey) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $body = $params ? self::encodeParams($params) : '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'CrosEscolar/' . app_version(),
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                throw new \RuntimeException('No s\'ha pogut connectar amb Stripe: ' . $error);
            }
        } else {
            $context = stream_context_create(['http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ]]);
            $response = @file_get_contents($url, false, $context);
            $status = 0;
            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                    $status = (int) $m[1];
                }
            }
            if ($response === false) {
                throw new \RuntimeException('No s\'ha pogut connectar amb Stripe.');
            }
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Resposta no vàlida de Stripe.');
        }
        if ($status >= 400) {
            $message = $data['error']['message'] ?? 'Error desconegut de Stripe';
            log_line('stripe', 'Error API', ['status' => $status, 'path' => $path, 'message' => $message]);
            throw new \RuntimeException($message);
        }
        return $data;
    }

    /** Codifica paràmetres amb el format que espera Stripe. */
    private static function encodeParams(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }
}
