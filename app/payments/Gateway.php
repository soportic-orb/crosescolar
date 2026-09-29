<?php
declare(strict_types=1);

namespace Cros\Payments;

/**
 * Una passarel·la de pagament.
 *
 * Totes fan el mateix vist des de fora —endur-se qui paga a un lloc segur i
 * tornar-lo amb un sí o un no—, però per dins no s'assemblen gens: Stripe i
 * PayPal donen una adreça on anar, i el TPV de Redsys vol un formulari amb
 * tres camps signats. Per això {@see self::begin()} no torna una adreça sinó
 * què cal fer: anar-hi o enviar-hi un formulari.
 *
 * Qui n'afegeixi una de nova només ha de fer aquests quatre mètodes i
 * apuntar-la a {@see Gateways::MODULES}.
 */
abstract class Gateway
{
    /** Nom intern, el que es desa a la configuració i al pagament. */
    abstract public static function key(): string;

    /** Com se'n diu a les pantalles. */
    abstract public static function label(): string;

    /** Té totes les dades que li calen per funcionar? */
    abstract public static function configured(): bool;

    /**
     * Comença el cobrament.
     *
     * @param array<string,mixed> $payment  la fila de payments
     * @param array<int,array<string,mixed>> $items
     * @return array{mode:string,url:string,fields:array<string,string>,ref:string}
     *         mode 'redirect' (aneu a url) o 'form' (envieu fields a url per POST)
     */
    abstract public static function begin(array $payment, array $items): array;

    /**
     * Mira com ha acabat.
     *
     * Es crida tant quan torna el navegador com quan ho diu el servidor de la
     * passarel·la, i ha de poder-se cridar dues vegades sense fer cap mal.
     *
     * @param array<string,mixed> $payment
     * @param array<string,mixed> $input  el que ha arribat ($_GET o $_POST)
     * @return array{status:string,payment:string,detail:string}
     *         status 'paid', 'pending' o 'failed'
     */
    abstract public static function check(array $payment, array $input): array;

    /**
     * Torna els diners. Qui no ho sàpiga fer, que ho digui.
     * @return array{ok:bool,error:string}
     */
    public static function refund(array $payment, int $cents): array
    {
        return ['ok' => false, 'error' => 'Aquesta passarel·la no permet tornar els diners des d\'aquí.'];
    }

    /** Està en mode de proves? Surt a les pantalles per no confondre ningú. */
    public static function testing(): bool
    {
        return true;
    }

    /** Un resultat buit, per no repetir-lo a cada mòdul. */
    protected static function outcome(string $status, string $payment = '', string $detail = ''): array
    {
        return ['status' => $status, 'payment' => $payment, 'detail' => mb_substr($detail, 0, 255)];
    }
}
