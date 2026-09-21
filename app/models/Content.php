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
        $categories = Db::all(
            'SELECT c.* FROM categories c WHERE c.active = 1 ORDER BY c.sort_order ASC, c.id ASC'
        );
        return self::withCourses($categories);
    }

    /**
     * Recorreguts de cada categoria, amb les voltes i en l'ordre indicat.
     * @return array<int,array<int,array>> indexat per categoria
     */
    public static function categoryCourses(?int $categoryId = null): array
    {
        $sql = 'SELECT cc.category_id, cc.course_id, cc.laps, cc.sort_order,
                       co.name, co.slug, co.distance_m, co.color
                FROM category_courses cc
                JOIN courses co ON co.id = cc.course_id';
        $params = [];
        if ($categoryId !== null) {
            $sql .= ' WHERE cc.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        $sql .= ' ORDER BY cc.category_id ASC, cc.sort_order ASC, cc.id ASC';
        $grouped = [];
        foreach (Db::all($sql, $params) as $row) {
            $grouped[(int) $row['category_id']][] = [
                'course_id' => (int) $row['course_id'],
                'laps' => max(1, (int) $row['laps']),
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
                'distance_m' => (int) $row['distance_m'],
                'color' => $row['color'],
            ];
        }
        return $grouped;
    }

    /**
     * Afegeix a cada categoria els seus recorreguts i la distància total.
     * Es manté course_name / course_slug amb el primer recorregut.
     */
    public static function withCourses(array $categories): array
    {
        $byCategory = self::categoryCourses();
        foreach ($categories as &$category) {
            $courses = $byCategory[(int) $category['id']] ?? [];
            $category['courses'] = $courses;
            $category['distance_total'] = array_sum(array_map(
                fn ($course) => $course['distance_m'] * $course['laps'],
                $courses
            ));
            $first = $courses[0] ?? null;
            $category['course_name'] = $first['name'] ?? null;
            $category['course_slug'] = $first['slug'] ?? null;
            $category['course_distance'] = $first['distance_m'] ?? null;
        }
        return $categories;
    }

    /** Desa els recorreguts d'una categoria (ids i voltes, en ordre). */
    public static function saveCategoryCourses(int $categoryId, array $courseIds, array $laps): void
    {
        Db::delete('category_courses', 'category_id = :cat', ['cat' => $categoryId]);
        $order = 0;
        $first = null;
        foreach (array_values($courseIds) as $index => $courseId) {
            $courseId = (int) $courseId;
            if ($courseId <= 0) {
                continue;
            }
            $first = $first ?? $courseId;
            Db::insert('category_courses', [
                'category_id' => $categoryId,
                'course_id' => $courseId,
                'laps' => max(1, min(99, (int) ($laps[$index] ?? 1))),
                'sort_order' => $order++,
            ]);
        }
        // El camp antic apunta al primer recorregut: així res del que ja hi havia es trenca.
        Db::update('categories', ['course_id' => $first], 'id = :id', ['id' => $categoryId]);
    }

    /** Text curt amb la composició del recorregut d'una categoria. */
    public static function lapsLabel(array $courses): string
    {
        $parts = [];
        foreach ($courses as $course) {
            $laps = (int) $course['laps'];
            $parts[] = $laps . ($laps === 1 ? ' volta' : ' voltes') . ' · ' . $course['name'];
        }
        return implode(' + ', $parts);
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

    /**
     * Anys de naixement d'una categoria: «2013–2014», o només «2020» quan
     * la categoria és d'un sol any. Cadena buida si no en té cap.
     *
     * Les claus s'hi poden dir «year_from»/«year_to» o portar el prefix
     * «category_», que és com arriben quan s'han consultat amb la inscripció.
     */
    public static function years(array $category, string $separator = '–'): string
    {
        $from = $category['year_from'] ?? $category['category_year_from'] ?? null;
        $to = $category['year_to'] ?? $category['category_year_to'] ?? null;
        if (empty($from) && empty($to)) {
            return '';
        }
        $from = (int) ($from ?: $to);
        $to = (int) ($to ?: $from);

        return $from === $to ? (string) $from : min($from, $to) . $separator . max($from, $to);
    }

    /** Nom del gènere d'una categoria («masculí», «femení») o cadena buida si és mixta. */
    public static function genderLabel(array $category, bool $feminine = false): string
    {
        $gender = (string) ($category['gender'] ?? $category['category_gender'] ?? 'mixt');

        return match ($gender) {
            'masculi' => $feminine ? 'Masculina' : 'masculí',
            'femeni' => $feminine ? 'Femenina' : 'femení',
            default => '',
        };
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
