<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Auth;
use Cros\Core\Db;

/** Tiquets individuals del punt de recàrrega. */
class Ticket
{
    public static function findByCode(string $code): ?array
    {
        return Db::one(
            'SELECT t.*, o.code AS order_code, o.name AS buyer_name, o.email AS buyer_email, o.status AS order_status,
                    tt.name AS type_name
             FROM tickets t
             JOIN orders o ON o.id = t.order_id
             LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
             WHERE t.code = :code',
            ['code' => mb_strtoupper(trim($code))]
        );
    }

    /**
     * Valida un tiquet a l'entrada del punt de recàrrega.
     * @return array{status:string,message:string,ticket:?array}
     */
    public static function validate(string $code, bool $markUsed = true): array
    {
        $ticket = self::findByCode($code);
        if (!$ticket) {
            return ['status' => 'error', 'message' => 'Aquest codi no existeix.', 'ticket' => null];
        }
        if ($ticket['order_status'] !== 'paid') {
            return ['status' => 'error', 'message' => 'La comanda d\'aquest tiquet no consta com a pagada.', 'ticket' => $ticket];
        }
        if ($ticket['status'] === 'void') {
            return ['status' => 'error', 'message' => 'Aquest tiquet està anul·lat.', 'ticket' => $ticket];
        }
        if ($ticket['status'] === 'used') {
            return [
                'status' => 'warning',
                'message' => 'Aquest tiquet ja es va validar el ' . dt($ticket['used_at']) . '.',
                'ticket' => $ticket,
            ];
        }
        if ($markUsed) {
            Db::update('tickets', [
                'status' => 'used',
                'used_at' => date('Y-m-d H:i:s'),
                'used_by' => Auth::id() ?: null,
            ], 'id = :id', ['id' => $ticket['id']]);
            $ticket['status'] = 'used';
            $ticket['used_at'] = date('Y-m-d H:i:s');
            Auth::logActivity('ticket_validate', 'ticket', (int) $ticket['id'], ['code' => $ticket['code']]);
        }
        return ['status' => 'ok', 'message' => 'Tiquet vàlid: ' . ($ticket['type_name'] ?? 'Tiquet'), 'ticket' => $ticket];
    }

    /** Torna a activar un tiquet ja validat. */
    public static function reset(int $id): void
    {
        Db::update('tickets', ['status' => 'valid', 'used_at' => null, 'used_by' => null], 'id = :id', ['id' => $id]);
    }

    /** Contingut del codi QR d'un tiquet. */
    public static function qrPayload(array $ticket): string
    {
        return url('/validar/' . $ticket['code']);
    }
}
