<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;

/** Els clients: l'entitat que organitza un cros i qui n'és el contacte. */
class Client
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM clients WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT c.*, COUNT(i.id) AS instances
                        FROM clients c LEFT JOIN instances i ON i.client_id = c.id
                        GROUP BY c.id ORDER BY c.name ASC');
    }

    /** Client amb aquesta adreça de contacte, si ja el tenim. */
    public static function byEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));

        return $email === '' ? null : Db::one('SELECT * FROM clients WHERE LOWER(contact_email) = :email', ['email' => $email]);
    }

    public static function create(array $data): int
    {
        return Db::insert('clients', [
            'name' => trim((string) $data['name']),
            'nif' => trim((string) ($data['nif'] ?? '')) ?: null,
            'town' => trim((string) ($data['town'] ?? '')) ?: null,
            'website' => trim((string) ($data['website'] ?? '')) ?: null,
            'contact_name' => trim((string) $data['contact_name']),
            'contact_role' => trim((string) ($data['contact_role'] ?? '')) ?: null,
            'contact_email' => mb_strtolower(trim((string) $data['contact_email'])),
            'contact_phone' => trim((string) ($data['contact_phone'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function update(int $id, array $fields): void
    {
        $fields['updated_at'] = date('Y-m-d H:i:s');
        Db::update('clients', $fields, 'id = :id', ['id' => $id]);
    }

    /**
     * El client d'una sol·licitud: si ja hi és amb la mateixa adreça, es fa
     * servir; si no, se'n crea un de nou amb les dades que hi ha posat.
     */
    public static function fromRequest(array $request): int
    {
        $existing = self::byEmail((string) $request['contact_email']);
        if ($existing) {
            return (int) $existing['id'];
        }

        return self::create([
            'name' => $request['entity'],
            'nif' => $request['nif'] ?? '',
            'town' => $request['town'] ?? '',
            'website' => $request['website'] ?? '',
            'contact_name' => $request['contact_name'],
            'contact_role' => $request['contact_role'] ?? '',
            'contact_email' => $request['contact_email'],
            'contact_phone' => $request['contact_phone'] ?? '',
        ]);
    }
}
