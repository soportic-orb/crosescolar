<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Db;

/** Tipus de tiquet del punt de recàrrega. */
class TicketType
{
    /** Minuts que es reserven les unitats d'una comanda pendent de pagament. */
    public const RESERVE_MINUTES = 30;

    public static function all(bool $onlyActive = true): array
    {
        $sql = 'SELECT * FROM ticket_types' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
        $types = Db::all($sql);
        foreach ($types as &$type) {
            $type['sold'] = self::sold((int) $type['id']);
            $type['reserved'] = self::reserved((int) $type['id']);
            $type['available'] = self::available($type);
        }
        return $types;
    }

    public static function find(int $id): ?array
    {
        $type = Db::one('SELECT * FROM ticket_types WHERE id = :id', ['id' => $id]);
        if ($type) {
            $type['sold'] = self::sold($id);
            $type['reserved'] = self::reserved($id);
            $type['available'] = self::available($type);
        }
        return $type;
    }

    /** Unitats pagades. */
    public static function sold(int $id): int
    {
        return (int) Db::val(
            'SELECT COALESCE(SUM(oi.qty), 0) FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.ticket_type_id = :id AND o.status = \'paid\'',
            ['id' => $id],
            0
        );
    }

    /** Unitats en comandes pendents recents. */
    public static function reserved(int $id): int
    {
        return (int) Db::val(
            'SELECT COALESCE(SUM(oi.qty), 0) FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.ticket_type_id = :id AND o.status = \'pending\' AND o.created_at > :since',
            ['id' => $id, 'since' => date('Y-m-d H:i:s', time() - self::RESERVE_MINUTES * 60)],
            0
        );
    }

    /** Unitats disponibles (null = sense límit). */
    public static function available(array $type): ?int
    {
        if ($type['stock'] === null || $type['stock'] === '') {
            return null;
        }
        $available = (int) $type['stock'] - (int) ($type['sold'] ?? self::sold((int) $type['id'])) - (int) ($type['reserved'] ?? self::reserved((int) $type['id']));
        return max(0, $available);
    }

    /** Hi ha existències per a la quantitat demanada? */
    public static function hasStock(array $type, int $qty): bool
    {
        $available = self::available($type);
        return $available === null || $available >= $qty;
    }
}
