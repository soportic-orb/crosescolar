<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Db;

/**
 * Els tipus d'inscripció: què val inscriure's a la cursa.
 *
 * Un cros en pot tenir un de sol («Inscripció, 5 €») o uns quants: sòcies i
 * no sòcies, germans, inscripció amb samarreta… Si no n'hi ha cap actiu, la
 * inscripció és gratuïta i el formulari no en demana cap.
 */
class FeeType
{
    /** @return array<int,array<string,mixed>> */
    public static function all(bool $onlyActive = true): array
    {
        return Db::all(
            'SELECT * FROM fee_types' . ($onlyActive ? ' WHERE active = 1' : '')
            . ' ORDER BY sort_order ASC, id ASC'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM fee_types WHERE id = :id', ['id' => $id]);
    }

    /** Un de triable, o null si aquell ja no hi és o està desactivat. */
    public static function choosable(int $id): ?array
    {
        $fee = self::find($id);

        return $fee && (int) $fee['active'] === 1 ? $fee : null;
    }

    /** Es cobra la inscripció? Només si hi ha algun tipus actiu amb preu. */
    public static function charging(): bool
    {
        if (!\Cros\Core\Settings::bool('registrations_payment')) {
            return false;
        }
        foreach (self::all() as $fee) {
            if ((int) $fee['price_cents'] > 0) {
                return true;
            }
        }

        return false;
    }

    /** Quants n'hi ha triables. */
    public static function count(): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM fee_types WHERE active = 1', [], 0);
    }
}
