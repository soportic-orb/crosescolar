<?php
declare(strict_types=1);

namespace Cros\Payments;

use Cros\Core\Settings;

/**
 * Les passarel·les que hi ha i quina mana.
 *
 * N'hi pot haver una de sola activa. És a posta: qui cobra ha de saber sempre
 * per on li entren els diners, i tenir-ne dues obertes a la vegada acaba amb
 * mitja comptabilitat en un lloc i mitja en un altre.
 */
class Gateways
{
    /** @var array<string,class-string<Gateway>> */
    public const MODULES = [
        'stripe' => StripeGateway::class,
        'paypal' => PaypalGateway::class,
        'redsys' => RedsysGateway::class,
    ];

    /** Quina passarel·la s'ha triat, o '' si cap. */
    public static function chosen(): string
    {
        $key = (string) Settings::get('payments_gateway', '');

        return isset(self::MODULES[$key]) ? $key : '';
    }

    /** La passarel·la activa, si n'hi ha i està configurada. */
    public static function active(): ?string
    {
        $key = self::chosen();

        return $key !== '' && self::module($key)::configured() ? $key : null;
    }

    /** Es pot cobrar ara mateix? */
    public static function ready(): bool
    {
        return self::active() !== null;
    }

    /** @return class-string<Gateway> */
    public static function module(string $key): string
    {
        return self::MODULES[$key] ?? StripeGateway::class;
    }

    /** Com se'n diu una passarel·la, tingui's o no activada. */
    public static function label(string $key): string
    {
        return isset(self::MODULES[$key]) ? self::MODULES[$key]::label() : $key;
    }

    /**
     * Totes, amb el seu estat, per pintar-ho al panell.
     * @return array<int,array{key:string,label:string,chosen:bool,configured:bool,testing:bool}>
     */
    public static function all(): array
    {
        $chosen = self::chosen();
        $list = [];
        foreach (self::MODULES as $key => $class) {
            $list[] = [
                'key' => $key,
                'label' => $class::label(),
                'chosen' => $key === $chosen,
                'configured' => $class::configured(),
                'testing' => $class::testing(),
            ];
        }

        return $list;
    }

    /** Les opcions del selector de la configuració. @return array<string,string> */
    public static function options(): array
    {
        $options = ['' => 'Cap: no es cobra res en línia'];
        foreach (self::MODULES as $key => $class) {
            $options[$key] = $class::label();
        }

        return $options;
    }
}
