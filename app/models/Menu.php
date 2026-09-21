<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Controllers\AccountController;
use Cros\Controllers\TicketsController;
use Cros\Core\Db;
use Cros\Core\Settings;

/**
 * Menú del web públic: quins apartats s'hi veuen i en quin ordre.
 *
 * Els apartats són els del programa (no se'n poden inventar de nous), però
 * l'ordre, el nom i si es veuen o no es configuren des del panell. Si una
 * versió nova n'afegeix un, hi apareix al final sense haver de tocar res.
 */
class Menu
{
    /**
     * Apartats que pot tenir el menú, en l'ordre de sortida.
     *
     * «shown» diu si l'apartat existeix ara mateix en aquesta instal·lació:
     * de poc serviria oferir «Resultats» si encara no s'han publicat.
     *
     * @return array<int,array{key:string,url:string,label:string,shown:bool,note:string}>
     */
    public static function available(): array
    {
        return [
            ['key' => 'home', 'url' => '/', 'label' => 'Inici', 'shown' => true, 'note' => ''],
            ['key' => 'results', 'url' => '/resultats', 'label' => 'Resultats',
                'shown' => Settings::bool('results_published'),
                'note' => 'Només mentre els resultats estiguin publicats.'],
            ['key' => 'courses', 'url' => '/recorreguts', 'label' => 'Recorreguts', 'shown' => true, 'note' => ''],
            ['key' => 'categories', 'url' => '/categories-i-premis', 'label' => 'Categories i premis', 'shown' => true, 'note' => ''],
            ['key' => 'registration', 'url' => '/inscripcio', 'label' => 'Inscripció', 'shown' => true,
                'note' => 'El botó «Inscriu-te!» del menú ja hi porta.'],
            ['key' => 'rules', 'url' => '/reglament', 'label' => 'Reglament', 'shown' => true, 'note' => ''],
            ['key' => 'tickets', 'url' => '/punt-de-recarrega', 'label' => 'Punt de recàrrega', 'shown' => true, 'note' => ''],
            ['key' => 'account', 'url' => '/les-meves-inscripcions', 'label' => 'Les meves inscripcions',
                'shown' => AccountController::enabled(),
                'note' => 'Només si «Les meves inscripcions» està actiu.'],
            ['key' => 'my_tickets', 'url' => '/els-meus-tiquets', 'label' => 'Els meus tiquets',
                'shown' => TicketsController::saleMode(),
                'note' => 'Només amb la venda en línia activada.'],
            ['key' => 'faqs', 'url' => '/preguntes-frequents', 'label' => 'Preguntes freqüents', 'shown' => true, 'note' => ''],
            ['key' => 'contact', 'url' => '/contacte', 'label' => 'Contacte', 'shown' => true, 'note' => ''],
        ];
    }

    /** Apartats que surten al menú per defecte, si mai no s'ha tocat res. */
    private const DEFAULT_VISIBLE = ['home', 'results', 'courses', 'categories', 'account'];

    /**
     * Tots els apartats amb el que s'hi ha configurat, per al panell.
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        $saved = [];
        foreach (Db::all('SELECT * FROM menu_items') as $row) {
            $saved[(string) $row['item_key']] = $row;
        }

        $items = [];
        foreach (self::available() as $index => $item) {
            $row = $saved[$item['key']] ?? null;
            $items[] = $item + [
                'label_custom' => (string) ($row['label'] ?? ''),
                'title' => trim((string) ($row['label'] ?? '')) !== '' ? (string) $row['label'] : $item['label'],
                'active' => $row === null
                    ? in_array($item['key'], self::DEFAULT_VISIBLE, true)
                    : (int) $row['active'] === 1,
                'sort_order' => $row === null ? ($index + 1) * 10 : (int) $row['sort_order'],
                'position' => $index,
            ];
        }

        usort($items, static fn (array $a, array $b): int => [$a['sort_order'], $a['position']] <=> [$b['sort_order'], $b['position']]);

        return $items;
    }

    /**
     * El menú tal com surt al web: només els apartats visibles i disponibles.
     * @return array<int,array{url:string,title:string}>
     */
    public static function visible(): array
    {
        $menu = [];
        foreach (self::all() as $item) {
            if ($item['active'] && $item['shown']) {
                $menu[] = ['url' => $item['url'], 'title' => $item['title']];
            }
        }

        return $menu;
    }

    /** Desa la visibilitat i el nom de cada apartat. */
    public static function save(array $active, array $labels): void
    {
        $order = 0;
        foreach (self::all() as $item) {
            $key = $item['key'];
            $label = trim((string) ($labels[$key] ?? ''));
            self::store($key, [
                'label' => $label !== '' && $label !== $item['label'] ? mb_substr($label, 0, 120) : null,
                'active' => in_array($key, $active, true) ? 1 : 0,
                'sort_order' => ++$order * 10,
            ]);
        }
    }

    /** Puja o baixa un apartat dins del menú. */
    public static function move(string $key, int $direction): void
    {
        $items = self::all();
        $index = null;
        foreach ($items as $position => $item) {
            if ($item['key'] === $key) {
                $index = $position;
                break;
            }
        }
        $target = $index === null ? null : $index + ($direction < 0 ? -1 : 1);
        if ($index === null || $target === null || !isset($items[$target])) {
            return;
        }
        [$items[$index], $items[$target]] = [$items[$target], $items[$index]];

        $order = 0;
        foreach ($items as $item) {
            self::store($item['key'], [
                'label' => $item['label_custom'] !== '' ? $item['label_custom'] : null,
                'active' => $item['active'] ? 1 : 0,
                'sort_order' => ++$order * 10,
            ]);
        }
    }

    private static function store(string $key, array $values): void
    {
        $exists = Db::one('SELECT id FROM menu_items WHERE item_key = :k', ['k' => $key]);
        if ($exists) {
            Db::update('menu_items', $values, 'id = :id', ['id' => $exists['id']]);
            return;
        }
        Db::insert('menu_items', $values + ['item_key' => $key]);
    }
}
