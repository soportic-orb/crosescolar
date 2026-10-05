<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;

/**
 * Els missatges del formulari de contacte de les pàgines públiques.
 *
 * Arriben de qualsevol dels dominis de la plataforma i es llegeixen tots al
 * mateix lloc, a l'apartat «Contacte» del Panell de Superadministració. Cada
 * missatge recorda de quin domini ha vingut, perquè no és el mateix qui escriu
 * des de crosescolar.cat que des d'esportweb.cat.
 */
final class Contact
{
    /** Com està cada missatge. */
    public const STATUSES = [
        'new' => 'Per llegir',
        'read' => 'Llegit',
        'answered' => 'Respost',
        'archived' => 'Arxivat',
    ];

    /** Quants missatges pot enviar una mateixa adreça IP en una hora. */
    public const PER_HOUR = 5;

    /**
     * Desa un missatge.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function create(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $id = Db::insert('platform_contacts', [
            'domain' => trim((string) ($data['domain'] ?? '')) ?: null,
            'name' => mb_substr(trim((string) $data['name']), 0, 150),
            'entity' => mb_substr(trim((string) ($data['entity'] ?? '')), 0, 190) ?: null,
            'email' => mb_strtolower(mb_substr(trim((string) $data['email']), 0, 190)),
            'phone' => mb_substr(trim((string) ($data['phone'] ?? '')), 0, 40) ?: null,
            'message' => mb_substr(trim((string) $data['message']), 0, 5000),
            'privacy_at' => $now,
            'news' => !empty($data['news']) ? 1 : 0,
            'status' => 'new',
            'ip' => mb_substr((string) ($data['ip'] ?? ''), 0, 45) ?: null,
            'created_at' => $now,
        ]);

        return (array) self::find($id);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM platform_contacts WHERE id = :id', ['id' => $id]);
    }

    /**
     * Els missatges, del més nou al més vell.
     *
     * Sense filtre surten tots menys els arxivats: la safata és el que encara
     * demana feina. Els arxivats es miren a part.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(string $status = '', string $search = ''): array
    {
        $where = [];
        $params = [];
        if ($status !== '' && isset(self::STATUSES[$status])) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        } else {
            $where[] = "status <> 'archived'";
        }
        $search = trim($search);
        if ($search !== '') {
            $where[] = '(name LIKE :q1 OR entity LIKE :q2 OR email LIKE :q3 OR message LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }

        return Db::all(
            'SELECT * FROM platform_contacts WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500',
            $params
        );
    }

    /** Quants n'hi ha per llegir. Per a la xifra del menú del panell. */
    public static function unread(): int
    {
        try {
            return (int) Db::val("SELECT COUNT(*) FROM platform_contacts WHERE status = 'new'", [], 0);
        } catch (\Throwable $e) {
            return 0; // abans de la migració, la taula encara no hi és
        }
    }

    /** Canvia l'estat d'un missatge. Obrir-lo el marca com a llegit. */
    public static function setStatus(int $id, string $status): bool
    {
        if (!isset(self::STATUSES[$status]) || !self::find($id)) {
            return false;
        }
        $fields = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        if ($status !== 'new') {
            $fields['read_at'] = date('Y-m-d H:i:s');
        } else {
            $fields['read_at'] = null;
        }
        Db::update('platform_contacts', $fields, 'id = :id', ['id' => $id]);

        return true;
    }

    /** Marca com a llegit un missatge que encara no ho era. */
    public static function open(int $id): void
    {
        Db::update('platform_contacts',
            ['status' => 'read', 'read_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            "id = :id AND status = 'new'", ['id' => $id]);
    }

    public static function delete(int $id): bool
    {
        if (!self::find($id)) {
            return false;
        }
        Db::q('DELETE FROM platform_contacts WHERE id = :id', ['id' => $id]);

        return true;
    }

    /**
     * Massa missatges seguits des de la mateixa adreça?
     *
     * Un robot que hagi passat el captcha una vegada no ha de poder omplir la
     * safata. Cinc per hora són més que els que escriurà mai una persona.
     */
    public static function tooMany(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }
        $count = (int) Db::val(
            'SELECT COUNT(*) FROM platform_contacts WHERE ip = :ip AND created_at > :since',
            ['ip' => $ip, 'since' => date('Y-m-d H:i:s', time() - 3600)],
            0
        );

        return $count >= self::PER_HOUR;
    }
}
