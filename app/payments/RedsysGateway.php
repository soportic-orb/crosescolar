<?php
declare(strict_types=1);

namespace Cros\Payments;

use Cros\Core\Settings;
use RuntimeException;

/**
 * El TPV de Redsys, que és el que fan servir la majoria de bancs d'aquí.
 *
 * No té API: s'hi envia un formulari amb tres camps —la versió de la signatura,
 * els paràmetres en JSON i base64, i la signatura— i el TPV respon de dues
 * maneres. El navegador torna a l'adreça d'acceptació, que serveix per ensenyar
 * el resultat, i el servidor de Redsys envia a part una «notificació en línia»
 * amb els mateixos camps. **La bona és la notificació**: si algú tanca el
 * navegador després de pagar, l'única que arriba és aquella.
 *
 * La signatura és un HMAC-SHA256 amb una clau que surt de xifrar el número de
 * comanda amb la clau del comerç en 3DES. Ho fa l'openssl que ja porta el PHP.
 */
class RedsysGateway extends Gateway
{
    private const URL_TEST = 'https://sis-t.redsys.es:25443/sis/realizarPago';
    private const URL_LIVE = 'https://sis.redsys.es/sis/realizarPago';

    /** Codis de resposta que volen dir «cobrat». */
    private const OK_MAX = 99;

    public static function key(): string
    {
        return 'redsys';
    }

    public static function label(): string
    {
        return 'Redsys (TPV del banc)';
    }

    public static function configured(): bool
    {
        return self::merchant() !== '' && self::secret() !== '';
    }

    public static function testing(): bool
    {
        return (string) Settings::get('redsys_mode', 'test') !== 'live';
    }

    private static function merchant(): string
    {
        return preg_replace('/\D/', '', (string) Settings::get('redsys_merchant_code', '')) ?? '';
    }

    private static function terminal(): string
    {
        $terminal = preg_replace('/\D/', '', (string) Settings::get('redsys_terminal', '1')) ?? '1';

        return $terminal !== '' ? $terminal : '1';
    }

    private static function secret(): string
    {
        return trim((string) Settings::get('redsys_key', ''));
    }

    private static function endpoint(): string
    {
        return self::testing() ? self::URL_TEST : self::URL_LIVE;
    }

    public static function begin(array $payment, array $items): array
    {
        if (!self::configured()) {
            throw new RuntimeException('El TPV de Redsys no està configurat.');
        }
        $order = self::orderNumber($payment);
        $params = [
            'DS_MERCHANT_AMOUNT' => (string) (int) $payment['total_cents'],
            'DS_MERCHANT_ORDER' => $order,
            'DS_MERCHANT_MERCHANTCODE' => self::merchant(),
            'DS_MERCHANT_CURRENCY' => '978', // euro
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_TERMINAL' => self::terminal(),
            'DS_MERCHANT_MERCHANTURL' => Checkout::notifyUrl('redsys'),
            'DS_MERCHANT_URLOK' => Checkout::returnUrl($payment),
            'DS_MERCHANT_URLKO' => Checkout::cancelUrl($payment),
            'DS_MERCHANT_PRODUCTDESCRIPTION' => mb_substr(Checkout::concept($payment), 0, 125),
            'DS_MERCHANT_TITULAR' => mb_substr((string) $payment['payer_name'], 0, 60),
            'DS_MERCHANT_MERCHANTNAME' => mb_substr((string) Settings::get('redsys_name',
                Settings::get('site_name', 'Cros Escolar')), 0, 25),
            'DS_MERCHANT_MERCHANTDATA' => (string) $payment['code'],
        ];
        $encoded = base64_encode((string) json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'mode' => 'form',
            'url' => self::endpoint(),
            'fields' => [
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => $encoded,
                'Ds_Signature' => self::sign($order, $encoded),
            ],
            'ref' => $order,
        ];
    }

    public static function check(array $payment, array $input): array
    {
        $encoded = (string) ($input['Ds_MerchantParameters'] ?? '');
        if ($encoded === '') {
            // El navegador torna per l'adreça d'acceptació sense cap dada: el
            // que val és la notificació, que arriba pel seu compte.
            return self::outcome('pending', '', 'Esperant la confirmació del TPV.');
        }
        $params = json_decode((string) base64_decode(strtr($encoded, '-_', '+/'), true), true);
        $params = is_array($params) ? array_change_key_case($params, CASE_UPPER) : [];
        $order = (string) ($params['DS_ORDER'] ?? '');
        if ($order === '') {
            return self::outcome('failed', '', 'El TPV ha respost sense número de comanda.');
        }
        $expected = self::sign($order, $encoded);
        $given = strtr((string) ($input['Ds_Signature'] ?? ''), '-_', '+/');
        if (!hash_equals($expected, strtr($given, '-_', '+/'))) {
            return self::outcome('failed', $order, 'La signatura del TPV no quadra.');
        }

        $response = (int) ($params['DS_RESPONSE'] ?? 9999);
        $authorisation = (string) ($params['DS_AUTHORISATIONCODE'] ?? '');
        if ($response >= 0 && $response <= self::OK_MAX) {
            return self::outcome('paid', $authorisation !== '' ? $authorisation : $order, 'Cobrat pel TPV.');
        }

        return self::outcome('failed', $authorisation, 'El TPV ha denegat el pagament (codi ' . $response . ').');
    }

    /**
     * Un número de comanda com el vol Redsys: dotze caràcters, dels quals els
     * quatre primers han de ser xifres.
     */
    private static function orderNumber(array $payment): string
    {
        $number = str_pad((string) ((int) $payment['id'] % 10000), 4, '0', STR_PAD_LEFT);

        return $number . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', (string) $payment['code']) . '00000000', 0, 8));
    }

    /** El pagament d'un número de comanda que torna el TPV. */
    public static function findOrder(string $encoded): string
    {
        $params = json_decode((string) base64_decode(strtr($encoded, '-_', '+/'), true), true);
        $params = is_array($params) ? array_change_key_case($params, CASE_UPPER) : [];

        // A la petició el camp es diu DS_MERCHANT_MERCHANTDATA i a la resposta
        // Ds_MerchantData: es miren tots dos i s'acaba abans.
        return (string) ($params['DS_MERCHANTDATA'] ?? ($params['DS_MERCHANT_MERCHANTDATA'] ?? ''));
    }

    /** HMAC-SHA256 amb la clau derivada del número de comanda. */
    private static function sign(string $order, string $encodedParams): string
    {
        $key = base64_decode(self::secret(), true);
        if ($key === false || $key === '') {
            throw new RuntimeException('La clau del TPV no és una cadena base64 vàlida.');
        }
        // 3DES amb vector d'inicialització a zero, tal com ho demana Redsys.
        // El número de comanda s'ha d'omplir fins a un múltiple de 8 bytes.
        $padded = $order . str_repeat("\0", (8 - strlen($order) % 8) % 8);
        $derived = openssl_encrypt($padded, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8));
        if ($derived === false) {
            throw new RuntimeException('No s\'ha pogut calcular la signatura del TPV.');
        }

        return base64_encode(hash_hmac('sha256', $encodedParams, $derived, true));
    }
}
