<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Repàs de com va la preparació de la cursa.
 *
 * No és cap obligació: és la llista de coses que la gent oblida quan queden
 * quatre dies. Al panell surt a mesura que s'acosta el dia i desapareix quan
 * ja no hi ha res a fer.
 */
class Readiness
{
    /** A partir de quants dies abans de la cursa es comença a recordar. */
    public const WINDOW = 45;

    /**
     * Les comprovacions, en ordre d'urgència.
     *
     * @return array<int,array{key:string,label:string,ok:bool,hint:string,link:string}>
     */
    public static function checks(): array
    {
        $checks = [];
        $add = static function (string $key, string $label, bool $ok, string $hint, string $link) use (&$checks): void {
            $checks[] = ['key' => $key, 'label' => $label, 'ok' => $ok, 'hint' => $hint, 'link' => $link];
        };

        $date = (string) setting('event_date', '');
        $add('date', 'La data i l\'hora de la cursa', $date !== '' && (string) setting('event_time', '') !== '',
            'Sense data no hi ha compte enrere ni es pot tancar la inscripció a temps.',
            '/admin/configuracio/general');

        $add('place', 'El lloc de sortida i el mapa',
            (string) setting('event_place', '') !== '' && (string) setting('map_lat', '') !== '',
            'La gent de fora del poble ho busca al mapa el mateix matí.',
            '/admin/configuracio/general');

        $categories = (int) Db::val('SELECT COUNT(*) FROM categories WHERE active = 1', [], 0);
        $add('categories', 'Les categories', $categories > 0,
            'Sense categories actives no es pot inscriure ningú.',
            '/admin/contingut/categories');

        $courses = (int) Db::val('SELECT COUNT(*) FROM courses', [], 0);
        $add('courses', 'Els recorreguts', $courses > 0,
            'Cada categoria hauria de saber quantes voltes fa i quant són.',
            '/admin/contingut/recorreguts');

        $registrations = (int) Db::val("SELECT COUNT(*) FROM registrations WHERE status <> 'cancelled'", [], 0);
        $add('registrations', 'Hi ha gent inscrita', $registrations > 0,
            'Si el formulari fa dies que és obert i no arriba ningú, val la pena provar-lo.',
            '/admin/inscripcions');

        $add('published', 'El web és públic', !Settings::bool('coming_soon'),
            'Encara hi ha l\'avís de «aviat publicarem el web»: ningú no el pot veure.',
            '/admin/configuracio/coming_soon');

        $add('mail', 'Els correus surten de debò',
            (string) setting('mail_transport', 'mail') !== 'log',
            'L\'enviament està en mode d\'assaig: les confirmacions no arriben a ningú.',
            '/admin/configuracio/email');

        $closeAt = (string) setting('registrations_close_at', '');
        $add('close', 'La inscripció es tanca a temps',
            $closeAt === '' || $date === '' || strtotime($closeAt) <= strtotime($date),
            'El formulari es tanca després del dia de la cursa.',
            '/admin/configuracio/registrations');

        $add('bibs', 'La maqueta del dorsal', (string) setting('bib_template', '') !== '',
            'Sense maqueta els dorsals surten sobre un fons blanc, que també va bé.',
            '/admin/configuracio/bibs');

        return $checks;
    }

    /** Les que queden per fer. */
    public static function pending(): array
    {
        return array_values(array_filter(self::checks(), static fn (array $check): bool => !$check['ok']));
    }

    /** Dies que falten per a la cursa, o null si no hi ha data. */
    public static function daysLeft(): ?int
    {
        $date = (string) setting('event_date', '');
        if ($date === '' || strtotime($date) === false) {
            return null;
        }

        return (int) floor((strtotime($date) - strtotime('today')) / 86400);
    }

    /** Toca ensenyar el repàs? Només quan s'acosta i queda alguna cosa. */
    public static function due(): bool
    {
        $days = self::daysLeft();

        return $days !== null && $days >= 0 && $days <= self::WINDOW && self::pending() !== [];
    }
}
