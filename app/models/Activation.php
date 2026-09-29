<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Tenancy;
use Cros\Platform\Bridge;
use Cros\Platform\Charge;
use Cros\Platform\Instance;
use Cros\Platform\Plan;

/**
 * L'activació del web, vista des del panell del client.
 *
 * Preparar el cros no costa res; el pagament arriba el dia que el web s'ha de
 * publicar. Tot el que hi ha aquí viu a la base de dades de la plataforma i
 * s'hi arriba pel pont ({@see Bridge}): el client no té —ni ha de tenir— cap
 * taula de facturació de la plataforma a casa seva.
 *
 * Compte de no barrejar-ho amb {@see Billing}, que és el que el cros factura
 * als seus participants. Són dues coses diferents amb dos jocs de dades
 * fiscals que no s'han de creuar mai.
 */
class Activation
{
    /**
     * L'estat de l'activació d'aquest web.
     *
     * @return array{applies:bool,paid:bool,due:bool,ready:bool,price:int,name:string,
     *               description:string,instance:array<string,mixed>|null,pending:array<string,mixed>|null}
     */
    public static function status(?string $root = null): array
    {
        $buit = ['applies' => false, 'paid' => false, 'due' => false, 'ready' => false,
            'price' => 0, 'name' => '', 'description' => '', 'instance' => null, 'pending' => null];
        $slug = Tenancy::slugOf();
        if ($slug === '' || !Bridge::available($root)) {
            return $buit;
        }
        try {
            return Bridge::run(static function () use ($slug, $buit): array {
                $instance = Instance::bySlug($slug);
                if (!$instance) {
                    return $buit;
                }
                $paid = !empty($instance['activated_at']);

                return [
                    'applies' => Plan::enabled(),
                    'paid' => $paid,
                    'due' => Plan::due($instance),
                    'ready' => Plan::ready(),
                    'price' => Plan::price(),
                    'name' => Plan::name(),
                    'description' => Plan::description(),
                    'instance' => $instance,
                    'pending' => Charge::pendingActivation((int) $instance['id']),
                ];
            }, $root, true);
        } catch (\Throwable $e) {
            // Si la plataforma no respon, el client no s'ha de quedar tancat
            // fora del seu panell: es deixa passar i ja s'hi tornarà.
            log_line('activation', 'No s\'ha pogut mirar l\'activació', ['error' => $e->getMessage()]);

            return $buit;
        }
    }

    /** Es pot publicar el web ara mateix? */
    public static function canPublish(?string $root = null): bool
    {
        $status = self::status($root);

        return !$status['applies'] || !$status['due'];
    }

    /** Els pagaments fets, per ensenyar-los al panell del client. */
    public static function payments(?string $root = null): array
    {
        $slug = Tenancy::slugOf();
        if ($slug === '' || !Bridge::available($root)) {
            return [];
        }
        try {
            return Bridge::run(static function () use ($slug): array {
                $instance = Instance::bySlug($slug);

                return $instance ? Charge::forInstance((int) $instance['id']) : [];
            }, $root);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
