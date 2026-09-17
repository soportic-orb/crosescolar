<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Db;

/** Lectura dels continguts públics del web. */
class Content
{
    /** Patrocinadors actius agrupats per tipus. */
    public static function sponsors(?string $tier = null): array
    {
        $sql = 'SELECT * FROM sponsors WHERE active = 1';
        $params = [];
        if ($tier !== null) {
            $sql .= ' AND tier = :tier';
            $params['tier'] = $tier;
        }
        $sql .= ' ORDER BY FIELD(tier, \'institucional\', \'principal\', \'collaborador\', \'mitjans\'), sort_order ASC, name ASC';
        try {
            return Db::all($sql, $params);
        } catch (\Throwable $e) {
            // FIELD() no existeix a tots els motors
            return Db::all(str_replace('FIELD(tier, \'institucional\', \'principal\', \'collaborador\', \'mitjans\'), ', 'tier, ', $sql), $params);
        }
    }

    /** Patrocinadors agrupats per tipus. */
    public static function sponsorsByTier(): array
    {
        $grouped = [];
        foreach (self::sponsors() as $sponsor) {
            $grouped[$sponsor['tier']][] = $sponsor;
        }
        return $grouped;
    }

    public static function courses(): array
    {
        return Db::all('SELECT * FROM courses WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public static function course(string $slug): ?array
    {
        return Db::one('SELECT * FROM courses WHERE slug = :slug AND active = 1', ['slug' => $slug]);
    }

    public static function categories(): array
    {
        return Db::all(
            'SELECT c.*, co.name AS course_name, co.slug AS course_slug, co.distance_m AS course_distance
             FROM categories c LEFT JOIN courses co ON co.id = c.course_id
             WHERE c.active = 1 ORDER BY c.sort_order ASC, c.id ASC'
        );
    }

    public static function prizes(): array
    {
        return Db::all('SELECT * FROM prizes WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public static function schedule(): array
    {
        return Db::all('SELECT * FROM schedule_items WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public static function infoBlocks(): array
    {
        return Db::all('SELECT * FROM info_blocks WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public static function faqs(): array
    {
        return Db::all('SELECT * FROM faqs WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    public static function gallery(int $limit = 24): array
    {
        return Db::all('SELECT * FROM gallery WHERE active = 1 ORDER BY sort_order ASC, id ASC LIMIT ' . max(1, $limit));
    }

    public static function documents(): array
    {
        return Db::all('SELECT * FROM documents WHERE active = 1 ORDER BY sort_order ASC, id ASC');
    }

    /** Identificador de Wikiloc extret de l'URL si cal. */
    public static function wikilocId(array $course): string
    {
        $id = trim((string) ($course['wikiloc_id'] ?? ''));
        if ($id !== '' && ctype_digit($id)) {
            return $id;
        }
        $url = (string) ($course['wikiloc_url'] ?? '');
        if (preg_match('/(\d{5,})/', $url, $m)) {
            return $m[1];
        }
        return '';
    }
}
