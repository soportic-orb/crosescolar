<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Settings;
use Cros\Core\Tenancy;
use Cros\Platform\Bridge;
use Cros\Platform\Platform;
use Cros\Platform\Site;

/**
 * La imatge de fons de la pantalla d'accés al panell.
 *
 * És la mateixa que la portada de la plataforma, perquè qui entra al seu
 * panell reconegui de seguida on és. Però aquella imatge viu a la base de
 * dades de la plataforma i el web d'un client no la té: s'hi arriba pel pont
 * i es desa aquí, que una pantalla d'accés no pot dependre que la plataforma
 * respongui ni obrir-hi una connexió cada cop que algú hi entra.
 *
 * Si mai falla, es torna el que hi hagi desat; i si no hi ha res desat, la
 * pantalla es queda amb el seu degradat verd de sempre, que ja funcionava.
 */
class Backdrop
{
    /** Cada quant es torna a mirar, en segons. */
    private const EVERY = 43200; // mig dia

    /**
     * La imatge de fons i el vel que hi va a sobre.
     *
     * @return array{url:string,overlay:float}
     */
    public static function login(?string $root = null): array
    {
        $overlay = max(0, min(95, (int) Settings::get('platform_backdrop_overlay', '72'))) / 100;
        $url = trim((string) Settings::get('platform_backdrop', ''));
        if (self::stale()) {
            $fresh = self::fetch($root);
            if ($fresh !== null) {
                $url = $fresh['url'];
                $overlay = $fresh['overlay'];
            }
        }

        return ['url' => $url, 'overlay' => $overlay];
    }

    /** Fa prou que no s'ha mirat? */
    private static function stale(): bool
    {
        $when = (int) Settings::get('platform_backdrop_at', '0');

        return $when + self::EVERY < time();
    }

    /**
     * Va a buscar-la a la plataforma i la desa.
     * @return array{url:string,overlay:float}|null null si no s'ha pogut
     */
    private static function fetch(?string $root): ?array
    {
        if (Tenancy::slugOf() === '' || !Bridge::available($root)) {
            return null;
        }
        try {
            $dades = Bridge::run(static function () use ($root): array {
                // La imatge és la del domini principal, que és la cara de la
                // plataforma; no la del domini per on hagi entrat el client.
                $domini = Platform::domain($root);
                $fitxer = trim((string) Site::value($domini, 'platform_hero_image'));
                $vel = (int) (Site::value($domini, 'platform_hero_overlay', '72') ?: '72');

                return [
                    'file' => $fitxer,
                    'overlay' => max(0, min(95, $vel)),
                    'domain' => $domini,
                ];
            }, $root, true);
        } catch (\Throwable $e) {
            log_line('activation', 'No s\'ha pogut mirar la imatge de la plataforma', ['error' => $e->getMessage()]);

            return null;
        }

        $fitxer = (string) $dades['file'];
        $url = $fitxer === '' ? '' : (preg_match('#^https?://#i', $fitxer) === 1
            ? $fitxer
            : 'https://' . $dades['domain'] . '/uploads/' . ltrim($fitxer, '/'));

        Settings::set('platform_backdrop', $url);
        Settings::set('platform_backdrop_overlay', (string) $dades['overlay']);
        Settings::set('platform_backdrop_at', (string) time());

        return ['url' => $url, 'overlay' => ((int) $dades['overlay']) / 100];
    }
}
