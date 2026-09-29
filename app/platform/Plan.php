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

    /**
     * Els números del pagament, desglossats.
     *
     * El preu que es configura és la **base imposable**. A sobre s'hi calculen
     * els dos impostos, cadascun per separat i cadascun activable:
     *
     *   - L'**IVA** se suma: és el que es repercuteix a qui paga.
     *   - L'**IRPF** es resta: és una retenció que el client no ens paga a
     *     nosaltres sinó a Hisenda en nom nostre. Per això el total que es
     *     cobra amb targeta és més petit del que diu la factura de base + IVA.
     *
     * Si el client és un particular o una entitat que no reté, l'IRPF es deixa
     * desactivat i no surt enlloc.
     *
     * @return array{base:int,vat_rate:float,vat:int,irpf_rate:float,irpf:int,total:int}
     */
    public static function amounts(?int $base = null): array
    {
        $base = max(0, $base ?? self::price());
        $vatRate = self::vatRate();
        $irpfRate = self::irpfRate();
        $vat = (int) round($base * $vatRate / 100);
        $irpf = (int) round($base * $irpfRate / 100);

        return [
            'base' => $base,
            'vat_rate' => $vatRate,
            'vat' => $vat,
            'irpf_rate' => $irpfRate,
            'irpf' => $irpf,
            'total' => max(0, $base + $vat - $irpf),
        ];
    }

    /** El que es cobra de debò amb la targeta. */
    public static function total(): int
    {
        return self::amounts()['total'];
    }

    /** El tipus d'IVA que s'aplica, o 0 si està desactivat. */
    public static function vatRate(): float
    {
        if (!Settings::bool('plan_vat_enabled', true)) {
            return 0.0;
        }
        // «plan_tax_rate» és com es deia abans que hi hagués l'IRPF pel mig.
        $rate = (string) Settings::get('plan_vat_rate', Settings::get('plan_tax_rate', '21'));

        return self::rate($rate);
    }

    /** El tipus de retenció d'IRPF, o 0 si està desactivada. */
    public static function irpfRate(): float
    {
        return Settings::bool('plan_irpf_enabled') ? self::rate((string) Settings::get('plan_irpf_rate', '15')) : 0.0;
    }

    private static function rate(string $value): float
    {
        return max(0.0, min(100.0, (float) str_replace(',', '.', trim($value))));
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
