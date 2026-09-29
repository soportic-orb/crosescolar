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

    /**
     * Icones d'esports i activitats, per triar-ne una al lloc del logotip.
     *
     * Van a part de les de la interfície perquè són una altra cosa: aquestes
     * les tria qui organitza la cursa i li han de dir alguna cosa —una
     * bicicleta, una braçada, un patí—, mentre que les altres són per als
     * botons i els menús del panell.
     *
     * Les que no porten dibuix propi (`path` a null) reaprofiten la de la
     * interfície que ja hi ha amb el mateix nom.
     *
     * @var array<string,array{label:string,path:string|null}>
     */
    private const SPORTS = [
        'run' => ['label' => 'Cursa a peu', 'path' => null],
        'sprint' => ['label' => 'Atletisme', 'path' => '<path d="M14.007 5a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M7 17l5 1l.75 -1.5" /><path d="M18 21v-4l-4 -3l1 -6" /><path d="M10 12v-3l5 -1l3 3l3 1" /><path d="M10 5h-4" /><path d="M6 10h-4" />'],
        'trekking' => ['label' => 'Excursionisme', 'path' => '<path d="M11 4a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M7 21l2 -4" /><path d="M13 21v-4l-3 -3l1 -6l3 4l3 2" /><path d="M10 14l-1.827 -1.218a2 2 0 0 1 -.831 -2.15l.28 -1.117a2 2 0 0 1 1.939 -1.515h1.439l4 1l3 -2" /><path d="M17 12v9" /><path d="M16 20h2" />'],
        'walk' => ['label' => 'Caminada', 'path' => '<path d="M12 4a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M7 21l3 -4" /><path d="M16 21l-2 -4l-3 -3l1 -6" /><path d="M6 12l2 -3l4 -1l3 3l3 1" />'],
        'cycling' => ['label' => 'Ciclisme', 'path' => '<path d="M2 18a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M16 18a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /><path d="M12 19v-4l-3 -3l5 -4l2 3h3" /><path d="M13.007 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />'],
        'swimming' => ['label' => 'Natació', 'path' => '<path d="M15 9a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M6 11l4 -2l3.5 3l-1.5 2" /><path d="M3 16.75a2.4 2.4 0 0 0 1 .25a2.4 2.4 0 0 0 2 -1a2.4 2.4 0 0 1 2 -1a2.4 2.4 0 0 1 2 1a2.4 2.4 0 0 0 2 1a2.4 2.4 0 0 0 2 -1a2.4 2.4 0 0 1 2 -1a2.4 2.4 0 0 1 2 1a2.4 2.4 0 0 0 2 1a2.4 2.4 0 0 0 1 -.25" />'],
        'waterpolo' => ['label' => 'Waterpolo', 'path' => '<path d="M5 8l3 4l5 1l7 -1" /><path d="M3 18.75a2.4 2.4 0 0 0 1 .25a2.4 2.4 0 0 0 2 -1a2.4 2.4 0 0 1 2 -1a2.4 2.4 0 0 1 2 1a2.4 2.4 0 0 0 2 1a2.4 2.4 0 0 0 2 -1a2.4 2.4 0 0 1 2 -1a2.4 2.4 0 0 1 2 1a2.4 2.4 0 0 0 2 1a2.4 2.4 0 0 0 1 -.25" /><path d="M12 16l1 -3" /><path d="M11.007 9a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5.007 3.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0" />'],
        'diving' => ['label' => 'Submarinisme', 'path' => '<path d="M19 12a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M2 2l3 3l1.5 4l3.5 2l6 2l1 4l2.5 3" /><path d="M11 8l4.5 1.5" />'],
        'football' => ['label' => 'Futbol', 'path' => '<path d="M3 17l5 1l.75 -1.5" /><path d="M14 21v-4l-4 -3l1 -6" /><path d="M6 12v-3l5 -1l3 3l3 1" /><path d="M18.007 19.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0" /><path d="M10.007 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />'],
        'soccer-ball' => ['label' => 'Pilota de futbol', 'path' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 7l4.76 3.45l-1.76 5.55h-6l-1.76 -5.55l4.76 -3.45" /><path d="M12 7v-4m3 13l2.5 3m-.74 -8.55l3.74 -1.45m-11.44 7.05l-2.56 2.95m.74 -8.55l-3.74 -1.45" />'],
        'basketball' => ['label' => 'Bàsquet', 'path' => '<path d="M9.007 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M5 21l3 -3l.75 -1.5" /><path d="M14 21v-4l-4 -3l.5 -6" /><path d="M5 12l1 -3l4.5 -1l3.5 3l4 -.5" /><path d="M18.007 15.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0" />'],
        'handball' => ['label' => 'Handbol', 'path' => '<path d="M13 21l3.5 -2l-4.5 -4l2 -4.5" /><path d="M5 7l4 3l5 .5l4 2.5l2.5 3" /><path d="M4 20l5 -1l1.5 -2" /><path d="M13.007 8a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M6.007 3.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0" />'],
        'volleyball' => ['label' => 'Voleibol', 'path' => '<path d="M11.007 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M19.007 9.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0" /><path d="M2 16l5 1l.5 -2.5" /><path d="M11.5 21l2.5 -5.5l-5.5 -3.5l3.5 -4l3 4l4 2" />'],
        'rugby' => ['label' => 'Rugbi', 'path' => '<path d="M14 15h-4v6h4v-6" /><path d="M12 15v-4" /><path d="M8 21h8" /><path d="M19 3v8h-14v-8" />'],
        'american-football' => ['label' => 'Futbol americà', 'path' => '<path d="M15 9l-6 6" /><path d="M10 12l2 2" /><path d="M12 10l2 2" /><path d="M8 21a5 5 0 0 0 -5 -5" /><path d="M16 3c-7.18 0 -13 5.82 -13 13a5 5 0 0 0 5 5c7.18 0 13 -5.82 13 -13a5 5 0 0 0 -5 -5" /><path d="M16 3a5 5 0 0 0 5 5" />'],
        'cricket' => ['label' => 'Criquet', 'path' => '<path d="M11.105 18.79l-1 .992a4.159 4.159 0 0 1 -6.038 -5.715l.157 -.166l8.282 -8.401l1.5 1.5l3.45 -3.391a2.08 2.08 0 0 1 3.057 2.815l-.116 .126l-3.391 3.45l1.5 1.5l-3.668 3.617" /><path d="M10.5 7.5l6 6" /><path d="M11 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />'],
        'table-tennis' => ['label' => 'Tennis taula', 'path' => '<path d="M12.718 20.713a7.64 7.64 0 0 1 -7.48 -12.755l.72 -.72a7.643 7.643 0 0 1 9.105 -1.283l2.387 -2.345a2.08 2.08 0 0 1 3.057 2.815l-.116 .126l-2.346 2.387a7.644 7.644 0 0 1 -1.052 8.864" /><path d="M11 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" /><path d="M9.3 5.3l9.4 9.4" />'],
        'gymnastics' => ['label' => 'Gimnàstica', 'path' => '<path d="M7 7a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M13 21l1 -9l7 -6" /><path d="M3 11h6l5 1" /><path d="M11.5 8.5l4.5 -3.5" />'],
        'acrobatics' => ['label' => 'Acrobàcies', 'path' => '<path d="M13.207 3l-6.735 2.462a1 1 0 0 0 -.364 1.646l1.892 1.892" /><path d="M10.5 8.25l1.5 -.25h3.174a2 2 0 0 1 1.411 .583l1.422 1.417" /><path d="M8 9c0 4.5 1.781 5.14 3 5.5" /><path d="M13.007 21h-1a1 1 0 0 1 -1 -1l-.007 -5.5" /><path d="M12.007 14a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />'],
        'yoga' => ['label' => 'Ioga', 'path' => '<path d="M4 20h4l1.5 -3" /><path d="M17 20l-1 -5h-5l1 -7" /><path d="M4 10l4 -1l4 -1l4 1.5l4 1.5" /><path d="M10.007 5a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />'],
        'roller-skating' => ['label' => 'Patinatge', 'path' => '<path d="M5.905 5h3.418a1 1 0 0 1 .928 .629l1.143 2.856a3 3 0 0 0 2.207 1.83l4.717 .926a2.084 2.084 0 0 1 1.682 2.045v.714a1 1 0 0 1 -1 1h-13.895a1 1 0 0 1 -1 -1.1l.8 -8a1 1 0 0 1 1 -.9" /><path d="M6 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" /><path d="M14 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />'],
        'ice-skating' => ['label' => 'Patinatge sobre gel', 'path' => '<path d="M5.905 5h3.418a1 1 0 0 1 .928 .629l1.143 2.856a3 3 0 0 0 2.207 1.83l4.717 .926a2.084 2.084 0 0 1 1.682 2.045v.714a1 1 0 0 1 -1 1h-13.895a1 1 0 0 1 -1 -1.1l.8 -8a1 1 0 0 1 1 -.9" /><path d="M3 19h17a1 1 0 0 0 1 -1" /><path d="M9 15v4" /><path d="M15 15v4" />'],
        'skateboarding' => ['label' => 'Monopatí', 'path' => '<path d="M5.5 15h3.5l.75 -1.5" /><path d="M14 19v-5l-2.5 -3l2.5 -4" /><path d="M8 8l3 -1h4l1 3h3" /><path d="M17.5 21a.5 .5 0 1 0 0 -1a.5 .5 0 0 0 0 1" /><path d="M3 18c0 .552 .895 1 2 1h14c1.105 0 2 -.448 2 -1" /><path d="M6.5 21a.5 .5 0 1 0 0 -1a.5 .5 0 0 0 0 1" /><path d="M14.007 4a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />'],
        'snowboarding' => ['label' => 'Surf de neu', 'path' => '<path d="M15 3a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M7 19l4 -2.5l-.5 -1.5" /><path d="M16 21l-1 -6l-4.5 -3l3.5 -6" /><path d="M7 9l1.5 -3h5.5l2 4l3 1" /><path d="M3 17c.399 1.154 .899 1.805 1.5 1.951c6 1.464 10.772 2.262 13.5 2.927c1.333 .325 2.333 0 3 -.976" />'],
        'rally' => ['label' => 'Automobilisme', 'path' => '<path d="M5 17a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M16 17a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M5 9l2 -4h7.438a2 2 0 0 1 1.94 1.515l.622 2.485h3a2 2 0 0 1 2 2v3" /><path d="M10 9v-4" /><path d="M2 7v4" /><path d="M22.001 14.001a4.992 4.992 0 0 0 -4.001 -2.001a4.992 4.992 0 0 0 -4 2h-3a4.998 4.998 0 0 0 -8.003 .003" /><path d="M5 12v-3h13" />'],
        'helmet' => ['label' => 'Casc', 'path' => '<path d="M12 4a9 9 0 0 1 5.656 16h-11.312a9 9 0 0 1 5.656 -16" /><path d="M20 9h-8.8a1 1 0 0 0 -.968 1.246c.507 2 1.596 3.418 3.268 4.254c2 1 4.333 1.5 7 1.5" />'],
    ];

    /** Retorna el codi SVG d'una icona. */
    public static function svg(string $name, string $class = 'icon', int $size = 24): string
    {
        $path = self::PATHS[$name] ?? null;
        if ($path === null && isset(self::SPORTS[$name])) {
            $path = self::SPORTS[$name]['path'] ?? self::PATHS['info'];
        }
        $path = $path ?? self::PATHS['info'];
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
        return isset(self::PATHS[$name]) || isset(self::SPORTS[$name]);
    }

    /**
     * Les icones d'esport que es poden triar, amb el seu nom.
     * @return array<string,string>
     */
    public static function sports(): array
    {
        $list = [];
        foreach (self::SPORTS as $key => $sport) {
            $list[$key] = $sport['label'];
        }

        return $list;
    }

    /** És una icona d'esport? */
    public static function isSport(string $name): bool
    {
        return isset(self::SPORTS[$name]);
    }
}
