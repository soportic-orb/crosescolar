<?php
declare(strict_types=1);

namespace Cros\Platform\Mail;

use Cros\Platform\Platform;
use Cros\Platform\Site;

/**
 * Com es veu un correu segons el domini que l'envia: el nom, el logotip i els
 * colors de la seva pàgina pública. És el que omple els colors i els textos
 * que una plantilla deixa en blanc.
 */
final class Brand
{
    /**
     * @return array{domain:string,base:string,name:string,entity:string,logo:string,primary:string,accent:string}
     */
    public static function of(string $domain): array
    {
        $domain = Platform::validDomain($domain);
        $logo = trim(Site::value($domain, 'platform_logo'));
        // Un SVG no es veu a Gmail ni a l'Outlook: només els logotips en imatge.
        if ($logo !== '' && preg_match('/\.svg$/i', $logo)) {
            $logo = '';
        }
        $base = 'https://' . $domain;

        return [
            'domain' => $domain,
            'base' => $base,
            'name' => Site::value($domain, 'site_name', 'EsportWeb'),
            'entity' => trim(Site::value($domain, 'platform_legal_entity')),
            'logo' => $logo !== '' ? (preg_match('#^https://#', $logo) ? $logo : $base . '/uploads/' . ltrim($logo, '/')) : '',
            'primary' => self::hex(Site::value($domain, 'color_primary'), '#1f4f5f'),
            'accent' => self::hex(Site::value($domain, 'platform_color_accent'), '#2f7d84'),
        ];
    }

    /**
     * Les adreces de les imatges d'un document, per a l'editor i per al correu.
     *
     * @return array<string,string> src => adreça pública
     */
    public static function images(array $doc, string $domain): array
    {
        $brand = self::of($domain);
        $out = ['brand:logo' => $brand['logo']];
        foreach (Images::urls(Doc::imageRefs($doc), $brand['base']) as $src => $url) {
            $out[$src] = $url;
        }

        return $out;
    }

    private static function hex(string $value, string $default): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', trim($value)) ? strtolower(trim($value)) : $default;
    }
}
