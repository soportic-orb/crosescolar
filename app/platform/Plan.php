<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Settings;

/**
 * El pla de pagament de la plataforma: què costa tenir el web publicat.
 *
 * Donar-se d'alta i preparar el cros no costa res —es pot trastejar tant com
 * calgui—, i el pagament arriba el dia que el client vol que el seu web es
 * vegi. És un pagament únic per instància.
 */
class Plan
{
    /** Es cobra l'activació? */
    public static function enabled(): bool
    {
        return Settings::bool('plan_enabled') && self::price() > 0;
    }

    /** Quant costa, en cèntims. */
    public static function price(): int
    {
        return max(0, (int) Settings::get('plan_price_cents', '0'));
    }

    public static function name(): string
    {
        return trim((string) Settings::get('plan_name', '')) ?: 'Activació del web';
    }

    public static function description(): string
    {
        return trim((string) Settings::get('plan_description', ''));
    }

    /** L'impost que hi va inclòs, en tant per cent. */
    public static function taxRate(): float
    {
        return max(0.0, min(100.0, (float) str_replace(',', '.', (string) Settings::get('plan_tax_rate', '0'))));
    }

    /** Es pot cobrar ara mateix? Cal el pla actiu i el Stripe de la plataforma. */
    public static function ready(): bool
    {
        return self::enabled() && \Cros\Core\Stripe::configured();
    }

    /**
     * Aquesta instància ha de pagar per publicar-se?
     *
     * S'ha de cridar amb la connexió de la plataforma oberta.
     */
    public static function due(array $instance): bool
    {
        if (!self::enabled()) {
            return false;
        }

        return empty($instance['activated_at']);
    }

    /** Dona una instància per activada. */
    public static function activate(int $instanceId): void
    {
        \Cros\Core\Db::update('instances', [
            'activated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND activated_at IS NULL', ['id' => $instanceId]);
    }
}
