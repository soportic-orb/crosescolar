<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * La marca del web: el logotip o la icona que va al costat del nom.
 *
 * No tothom té logotip. Una cursa que es munta en una tarda no en té cap, i
 * demanar-ne un abans de poder publicar res seria posar-hi una barrera per a
 * no res. Per això es pot triar una icona d'esport, que sempre queda bé i no
 * s'ha de dissenyar.
 */
class Brand
{
    /** Quina icona s'ha triat. */
    public static function icon(): string
    {
        $icon = (string) Settings::get('site_icon', 'run');

        return Icons::isSport($icon) ? $icon : 'run';
    }

    /** Hi ha logotip pujat i s'ha demanat fer-lo servir? */
    public static function usesLogo(): bool
    {
        return (string) Settings::get('brand_mark', 'icon') === 'logo'
            && trim((string) Settings::get('logo', '')) !== '';
    }

    /**
     * La marca, a punt de posar a la plantilla.
     * Torna la imatge si n'hi ha, i si no la icona triada.
     */
    public static function mark(int $size = 24, string $class = 'icon'): string
    {
        if (self::usesLogo()) {
            $logo = (string) Settings::get('logo', '');

            return '<img src="' . e(upload_url($logo)) . '" alt="'
                . e((string) Settings::get('site_name', '')) . '" style="max-width:'
                . $size . 'px;max-height:' . $size . 'px">';
        }

        return Icons::svg(self::icon(), $class, $size);
    }
}
