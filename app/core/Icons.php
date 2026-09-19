<?php
declare(strict_types=1);

namespace Cros\Core;

/** Icones SVG en línia (traç, hereten el color del text). */
class Icons
{
    private const PATHS = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'map' => '<path d="M9 3 3 5.5v15L9 18l6 3 6-2.5v-15L15 6 9 3z"/><path d="M9 3v15M15 6v15"/>',
        'ticket' => '<path d="M3 9a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M12 7v2M12 13v2M12 17v0"/>',
        'trophy' => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 5H5v2a3 3 0 0 0 3 3M16 5h3v2a3 3 0 0 1-3 3"/><path d="M12 13v4M9 20h6M10 17h4"/>',
        'medal' => '<circle cx="12" cy="15" r="5"/><path d="m8 3 2 6M16 3l-2 6M12 13v4M10.5 15h3"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2A3.2 3.2 0 0 1 16 11M17 14.8c2.4.5 4 2.4 4 5.2"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-9.2A4 4 0 0 1 12 8a4 4 0 0 1 7 2.8C19 15.6 12 20 12 20z"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.5"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.8-.9 1.4v.3M12 16.5v.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-4 4 3 3-2 4 4"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.9 1.5-1.9-.3-1 .4-2.1 1.5-2.1H18a3 3 0 0 0 3-3c0-5.5-4-11-9-11z"/><circle cx="8" cy="11" r="1"/><circle cx="12" cy="8" r="1"/><circle cx="16" cy="11" r="1"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.8 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.8-3.8-9S9.5 5.6 12 3z"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>',
        'card' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'star' => '<path d="m12 4 2.4 5 5.6.8-4 3.9 1 5.5-5-2.7-5 2.7 1-5.5-4-3.9 5.6-.8z"/>',
        'run' => '<circle cx="16" cy="5" r="2"/><path d="M7 17l5 1l.75-1.5"/><path d="M18 21v-4l-4-3l1-6"/><path d="M10 12v-3l5-1l3 3l3 1"/><path d="M10 5h-4"/><path d="M6 10h-4"/>',
        'coffee' => '<path d="M4 8h13v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M17 9.5h1.5a2.5 2.5 0 0 1 0 5H17"/><path d="M7 3.5v2M11 3.5v2"/>',
        'food' => '<path d="M5 3v7a2.5 2.5 0 0 0 5 0V3M7.5 10v11"/><path d="M17 3c-1.5 1.5-2 3.5-2 5.5s.7 3.5 2 3.5 2-1.5 2-3.5S18.5 4.5 17 3zM17 12v9"/>',
        'parking' => '<rect x="3.5" y="3.5" width="17" height="17" rx="3"/><path d="M9.5 17V7h3.2a2.9 2.9 0 0 1 0 5.8H9.5"/>',
        'bus' => '<rect x="4" y="4" width="16" height="12" rx="2"/><path d="M4 10h16M7.5 20v-2M16.5 20v-2"/><circle cx="8" cy="16" r="1"/><circle cx="16" cy="16" r="1"/>',
        'water' => '<path d="M12 3s6 6.4 6 10.4A6 6 0 0 1 6 13.4C6 9.4 12 3 12 3z"/>',
        'shirt' => '<path d="M8 3 4 5.5 6 10l2-1v12h8V9l2 1 2-4.5L16 3l-2 2h-4z"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'edit' => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="m14.5 5.5 4 4"/>',
        'trash' => '<path d="M4 7h16M10 7V4.5h4V7M6 7l1 13h10l1-13"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'external' => '<path d="M14 4h6v6M20 4l-8 8"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'download' => '<path d="M12 4v11M8 11l4 4 4-4M4 19h16"/>',
        'qr' => '<rect x="3.5" y="3.5" width="6" height="6"/><rect x="14.5" y="3.5" width="6" height="6"/><rect x="3.5" y="14.5" width="6" height="6"/><path d="M14.5 14.5h3v3h-3zM20.5 14.5v3M17.5 20.5h3"/>',
        'euro' => '<path d="M17 6.5A6 6 0 0 0 7.5 12a6 6 0 0 0 9.5 5.5M5 10.5h7M5 13.5h7"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 17v-5M12.5 17V8M17 17v-7"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M21 12h-2.5M5.5 12H3M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8M18.4 18.4l-1.8-1.8M7.4 7.4 5.6 5.6"/>',
        'logout' => '<path d="M15 6V4a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-2"/><path d="M10 12h11M17 8l4 4-4 4"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m16 16 4.5 4.5"/>',
        'upload' => '<path d="M12 19V8M8 12l4-4 4 4M4 20h16"/>',
        'eye' => '<path d="M2.5 12S6 6 12 6s9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6z"/><circle cx="12" cy="12" r="2.5"/>',
        'alert' => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17v.5"/>',
        'gift' => '<rect x="3.5" y="8" width="17" height="12" rx="2"/><path d="M3.5 12.5h17M12 8v12"/><path d="M12 8S10.5 4 8.5 4a2 2 0 0 0 0 4M12 8s1.5-4 3.5-4a2 2 0 0 1 0 4"/>',
        'flag' => '<path d="M6 21V4M6 4h11l-2 3.5L17 11H6"/>',
        'vine' => '<path d="M12 3v7"/><circle cx="9" cy="12" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="12" cy="16" r="2"/><circle cx="12" cy="20" r="1.5"/><path d="M12 5c2 0 3-1 3-2"/>',
        // Carràs de raïm i fulla de parra: els motius de la vinya del Penedès.
        'grape' => '<path d="M13 3a14.5 14.5 0 0 0-1 6"/><path d="M12 8.9s-2.77.52-4.1-.8s-.8-4-.8-4s2.57-.53 3.88.8s1.02 4 1.02 4"/><circle cx="12" cy="19" r="2"/><circle cx="14" cy="15" r="2"/><circle cx="10" cy="15" r="2"/><circle cx="12" cy="11" r="2"/><circle cx="16" cy="11" r="2"/><circle cx="8" cy="11" r="2"/>',
        'leaf' => '<path d="M12 21v-4"/><path d="M12 3.5 13.5 8l3.2-1.8 2.8 2.6-3.3 3 2.6 1.7-1.6 3.6-4.2-.6L12 18l-1-1.5-4.2.6L5.2 13.5l2.6-1.7-3.3-3 2.8-2.6L10.5 8z"/><path d="M12 17V8M12 12l3.5-2.5M12 12 8.5 9.5"/>',
        'mountain' => '<path d="m3 19 6-10 4 6 2-3 6 7z"/>',
        'phone' => '<path d="M7 3.5h3l1.5 4-2 1.5a12 12 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2C11 18.5 5.5 13 4.7 5.7A2 2 0 0 1 7 3.5z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17" cy="7" r="1"/>',
        'facebook' => '<path d="M14 21v-8h2.7l.4-3H14V8.2c0-.9.3-1.5 1.6-1.5H17V4.1A20 20 0 0 0 14.8 4C12.6 4 11 5.3 11 7.9V10H8.5v3H11v8z"/>',
        'whatsapp' => '<path d="M4 20l1.2-3.6A7.8 7.8 0 1 1 8 19.2z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5.6 0 1-.5 1-1l-1.3-.8-1 .8a5 5 0 0 1-2.2-2.2l.8-1L11 9.5c-.5 0-1 .4-1 1"/>',
        'location' => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'kids' => '<circle cx="12" cy="7" r="3"/><path d="M6 21v-4l-2-3 3-2h10l3 2-2 3v4"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>',
    ];

    /** Retorna el codi SVG d'una icona. */
    public static function svg(string $name, string $class = 'icon', int $size = 24): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['info'];
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $path . '</svg>';
    }

    /** Llista de noms disponibles (per als selectors del panell). */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }

    public static function exists(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }
}
