<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Xifratge simètric per a dades sensibles (claus de Stripe, contrasenya SMTP...).
 * Utilitza AES-256-GCM amb la clau de l'aplicació (app_key de config.php).
 */
class Crypto
{
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';

    /** Genera una clau nova en base64. */
    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    private static function key(): string
    {
        $key = (string) config('app_key', '');
        $raw = $key !== '' ? base64_decode($key, true) : false;
        if ($raw === false || strlen($raw) < 32) {
            // Sense clau vàlida es fa servir una derivació estable per no perdre dades.
            $raw = hash('sha256', 'cros-fallback|' . config('db.name', '') . '|' . config('db.user', ''), true);
        }
        return substr($raw, 0, 32);
    }

    /**
     * Resum amb clau d'un valor curt (codis d'un sol ús, etc.).
     * Serveix per no desar el valor en clar sense haver de guardar cap altra clau.
     */
    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    public static function encrypt(?string $plain): string
    {
        if ($plain === null || $plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return '';
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!str_starts_with($value, self::PREFIX)) {
            return $value; // valor encara en text pla
        }
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /** Emmascara un secret per mostrar-lo al panell. */
    public static function mask(string $secret): string
    {
        $length = strlen($secret);
        if ($length === 0) {
            return '';
        }
        if ($length <= 8) {
            return str_repeat('•', $length);
        }
        return substr($secret, 0, 4) . str_repeat('•', max(4, $length - 8)) . substr($secret, -4);
    }
}
