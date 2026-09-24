<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;

/** Sol·licituds de gent que vol el web del seu cros. */
class Request
{
    public const STATUSES = [
        'pending' => 'Pendent',
        'info' => 'Esperant resposta',
        'approved' => 'Aprovada',
        'rejected' => 'Rebutjada',
    ];

    /** Quantes sol·licituds pot enviar una mateixa adreça en un dia. */
    public const PER_DAY = 3;

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM instance_requests WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $status = 'pending'): array
    {
        $sql = 'SELECT * FROM instance_requests';
        $params = [];
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }

        return Db::all($sql . ' ORDER BY created_at DESC', $params);
    }

    public static function pending(): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM instance_requests WHERE status IN ('pending', 'info')", [], 0);
    }

    /**
     * Desa una sol·licitud nova i en torna la fitxa sencera.
     * @param array<string,mixed> $data tal com arriba del formulari, ja comprovat
     */
    public static function create(array $data): array
    {
        $language = (string) ($data['language'] ?? 'ca');
        $language = in_array($language, ['ca', 'es'], true) ? $language : 'ca';

        $id = Db::insert('instance_requests', [
            'code' => self::code(),
            'entity' => trim((string) $data['entity']),
            'nif' => trim((string) ($data['nif'] ?? '')) ?: null,
            'town' => trim((string) ($data['town'] ?? '')) ?: null,
            'website' => trim((string) ($data['website'] ?? '')) ?: null,
            'contact_name' => trim((string) $data['contact_name']),
            'contact_role' => trim((string) ($data['contact_role'] ?? '')) ?: null,
            'contact_email' => mb_strtolower(trim((string) $data['contact_email'])),
            'contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
            'slug' => mb_strtolower(trim((string) ($data['slug'] ?? ''))) ?: null,
            'language' => $language,
            'event_date' => !empty($data['event_date']) ? $data['event_date'] : null,
            'participants' => !empty($data['participants']) ? (int) $data['participants'] : null,
            'referral' => trim((string) ($data['referral'] ?? '')) ?: null,
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
            'status' => 'pending',
            'ip' => client_ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::find($id) ?? [];
    }

    /** Una adreça no pot omplir el formulari tantes vegades com vulgui. */
    public static function tooMany(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        $count = (int) Db::val(
            'SELECT COUNT(*) FROM instance_requests WHERE LOWER(contact_email) = :email AND created_at > :since',
            ['email' => $email, 'since' => date('Y-m-d H:i:s', time() - 86400)],
            0
        );

        return $count >= self::PER_DAY;
    }

    /**
     * Quantes n'hi ha de cada estat, per ensenyar-ho a les pestanyes.
     * @return array<string,int>
     */
    public static function counts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach (Db::all('SELECT status, COUNT(*) AS total FROM instance_requests GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }
        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /** Marca com a resolta una sol·licitud. */
    public static function decide(int $id, string $status, string $reason = '', ?int $instanceId = null, ?int $userId = null): void
    {
        if (!isset(self::STATUSES[$status])) {
            return;
        }
        Db::update('instance_requests', [
            'status' => $status,
            'reason' => trim($reason) ?: null,
            'instance_id' => $instanceId,
            'decided_at' => date('Y-m-d H:i:s'),
            'decided_by' => $userId,
        ], 'id = :id', ['id' => $id]);
    }

    /** Número que es diu a la família: #2026-014. */
    public static function code(): string
    {
        $year = date('Y');
        $count = (int) Db::val(
            'SELECT COUNT(*) FROM instance_requests WHERE code LIKE :like',
            ['like' => $year . '-%'],
            0
        );
        do {
            $count++;
            $code = $year . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        } while (Db::val('SELECT 1 FROM instance_requests WHERE code = :code', ['code' => $code]));

        return $code;
    }
}
