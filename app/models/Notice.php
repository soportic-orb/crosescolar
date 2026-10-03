<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Html;
use Cros\Core\Settings;

/**
 * Avisos puntuals del web: la barra de dalt de tot, la finestra emergent de la
 * portada i l'avís emergent amb icona. Tots es configuren a Configuració →
 * Avisos.
 *
 * Cada avís porta una clau curta calculada a partir del seu contingut: quan
 * l'organització canvia el missatge o la imatge, la clau canvia i l'avís torna
 * a sortir encara que el visitant l'hagués tancat.
 */
class Notice
{
    /**
     * Barra d'avís de dalt de tot, o null si no n'hi ha cap per ensenyar.
     * @return array{text:string,url:string,label:string,bg:string,color:string,dismissible:bool,key:string}|null
     */
    public static function bar(): ?array
    {
        if (!Settings::bool('topbar_enabled')) {
            return null;
        }
        $text = trim((string) setting('topbar_text', ''));
        if ($text === '') {
            return null; // Una barra sense missatge no és cap avís.
        }
        $url = self::url((string) setting('topbar_url', ''));
        $label = trim((string) setting('topbar_link_label', ''));

        return [
            'text' => $text,
            'url' => $url,
            'label' => $url !== '' ? ($label !== '' ? $label : 'Més informació') : '',
            'bg' => self::color((string) setting('topbar_bg', '#c8552b'), '#c8552b'),
            'color' => self::color((string) setting('topbar_color', '#ffffff'), '#ffffff'),
            'dismissible' => Settings::bool('topbar_dismissible', true),
            'key' => self::key([$text, $url, $label]),
        ];
    }

    /**
     * Finestra emergent de la portada, o null si no n'hi ha cap.
     * @return array{image:string,alt:string,url:string,once:bool,key:string}|null
     */
    public static function popup(): ?array
    {
        if (!Settings::bool('popup_enabled')) {
            return null;
        }
        $image = trim((string) setting('popup_image', ''));
        if ($image === '' || !is_file(upload_path($image))) {
            return null; // Sense imatge no hi ha cartell.
        }
        $url = self::url((string) setting('popup_url', ''));

        return [
            'image' => $image,
            'alt' => trim((string) setting('popup_alt', '')),
            'url' => $url,
            'once' => Settings::bool('popup_once', true),
            'key' => self::key([$image, $url]),
        ];
    }

    /**
     * Icones que es poden triar per a l'avís emergent.
     *
     * Són poques i a posta: qui escriu l'avís ha de poder dir de què va amb un
     * cop d'ull a la llista, no triar entre dues-centes icones que es
     * confonen.
     *
     * @var array<string,string>
     */
    public const ICONS = [
        'alert' => 'Alerta',
        'info' => 'Informació',
        'danger' => 'Perill',
        'star' => 'Important',
        'calendar' => 'Calendari',
        'flag' => 'Bandera',
    ];

    /**
     * Avís emergent amb icona, o null si no n'hi ha cap per ensenyar.
     * @return array{icon:string,color:string,title:string,text:string,url:string,label:string,once:bool,key:string}|null
     */
    public static function alert(): ?array
    {
        if (!Settings::bool('alert_enabled')) {
            return null;
        }
        $text = trim((string) setting('alert_text', ''));
        $title = trim((string) setting('alert_title', ''));
        if ($text === '' && $title === '') {
            return null; // Un avís sense res escrit no és cap avís.
        }
        $url = self::url((string) setting('alert_url', ''));
        $label = trim((string) setting('alert_link_label', ''));

        return [
            'icon' => self::icon((string) setting('alert_icon', 'alert')),
            'color' => self::color((string) setting('alert_icon_color', '#c8552b'), '#c8552b'),
            'title' => $title,
            'text' => $text,
            'url' => $url,
            'label' => $url !== '' ? ($label !== '' ? $label : 'Més informació') : '',
            'once' => Settings::bool('alert_once', true),
            'key' => self::key([$title, $text, $url]),
        ];
    }

    /** Una de les icones que es poden triar; si no ho és, la d'alerta. */
    private static function icon(string $name): string
    {
        $name = trim($name);

        return isset(self::ICONS[$name]) ? $name : 'alert';
    }

    /** Adreça que es pot posar en un enllaç, o '' si no ho és. */
    public static function url(string $url): string
    {
        $url = trim($url);

        return $url !== '' && Html::safeUrl($url) ? $url : '';
    }

    /** Color en format #rrggbb, o el de reserva si no ho és. */
    private static function color(string $value, string $fallback): string
    {
        $value = trim($value);

        return preg_match('/^#[0-9a-f]{3,8}$/i', $value) === 1 ? $value : $fallback;
    }

    /**
     * Identificador curt del contingut d'un avís. Serveix perquè el navegador
     * recordi que ja l'ha vist i, alhora, perquè un missatge nou torni a sortir.
     * @param array<int,string> $parts
     */
    private static function key(array $parts): string
    {
        return substr(md5(implode('|', $parts)), 0, 8);
    }
}
