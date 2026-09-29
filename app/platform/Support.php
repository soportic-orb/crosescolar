<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Html;

/**
 * El servei de suport: els tiquets que obren els clients i les respostes que
 * els donem.
 *
 * Els tiquets viuen a la base de dades de la plataforma i no a la de cada
 * client. És a posta: qui els ha d'atendre els vol tots en una safata, amb el
 * que fa més estona que espera a dalt, i no anar-los a buscar web per web. El
 * preu és que el panell d'un client ha d'obrir la connexió de la plataforma
 * per escriure-hi —{@see self::run()}—, que és la mateixa via que el panell de
 * superadministració ja fa servir per mirar dins d'una instància, només que al
 * revés.
 */
class Support
{
    /** Per on passa un tiquet. */
    public const STATUSES = [
        'open' => 'Obert',
        'answered' => 'Respost',
        'waiting' => 'Esperant el client',
        'closed' => 'Tancat',
    ];

    /** Estats que encara demanen alguna cosa de nosaltres. */
    public const ACTIVE = ['open', 'answered', 'waiting'];

    public const PRIORITIES = [
        'low' => 'Baixa',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgent',
    ];

    /** Qui ha escrit un missatge. */
    public const SENDERS = ['client' => 'El client', 'support' => 'Suport'];

    // ----------------------------------------------------------- el pont

    /**
     * Hi ha servei de suport en aquesta instal·lació?
     *
     * Sense plataforma no n'hi ha: un cros instal·lat tot sol no té ningú a
     * qui escriure, i val més no ensenyar-li un apartat que no aniria enlloc.
     */
    public static function available(?string $root = null): bool
    {
        return Bridge::available($root);
    }

    /**
     * Executa una consulta contra la base de dades de la plataforma.
     * El pont és compartit amb el pagament d'activació: {@see Bridge}.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function run(callable $fn, ?string $root = null, bool $withSettings = false)
    {
        return Bridge::run($fn, $root, $withSettings);
    }

    // ------------------------------------------------------ departaments

    /** @return array<int,array<string,mixed>> */
    public static function departments(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE active = 1' : '';

        return Db::all('SELECT * FROM support_departments ' . $where . ' ORDER BY sort_order ASC, name ASC');
    }

    public static function department(int $id): ?array
    {
        return Db::one('SELECT * FROM support_departments WHERE id = :id', ['id' => $id]);
    }

    /** Desa un departament (nou si l'identificador és 0) i en torna l'id. */
    public static function saveDepartment(int $id, array $data): int
    {
        $fields = [
            'name' => mb_substr(trim((string) ($data['name'] ?? '')), 0, 120),
            'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 255) ?: null,
            'email' => self::email((string) ($data['email'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => !empty($data['active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            Db::update('support_departments', $fields, 'id = :id', ['id' => $id]);

            return $id;
        }
        $fields['created_at'] = date('Y-m-d H:i:s');

        return Db::insert('support_departments', $fields);
    }

    /**
     * Treu un departament.
     *
     * Els tiquets que hi havia no s'esborren: es queden sense departament, que
     * és millor que perdre'ls. Per deixar de fer-lo servir sense tocar res,
     * n'hi ha prou desactivant-lo.
     */
    public static function deleteDepartment(int $id): void
    {
        Db::q('UPDATE support_tickets SET department_id = NULL WHERE department_id = :id', ['id' => $id]);
        Db::delete('support_departments', 'id = :id', ['id' => $id]);
    }

    // ----------------------------------------------------------- tiquets

    /** De quin client és el web que fa la petició. */
    public static function context(string $slug): array
    {
        $instance = $slug === '' ? null
            : Db::one('SELECT id, client_id, slug, site_name FROM instances WHERE slug = :slug', ['slug' => $slug]);

        return [
            'instance_id' => $instance['id'] ?? null,
            'client_id' => $instance['client_id'] ?? null,
            'slug' => $instance['slug'] ?? ($slug !== '' ? $slug : null),
            'site_name' => $instance['site_name'] ?? null,
        ];
    }

    /**
     * Obre un tiquet amb el primer missatge, i en torna la fitxa.
     *
     * @param array<string,mixed> $data
     */
    public static function open(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $department = (int) ($data['department_id'] ?? 0);
        $id = Db::insert('support_tickets', [
            'reference' => self::reference(),
            'department_id' => $department > 0 ? $department : null,
            'instance_id' => $data['instance_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'slug' => $data['slug'] ?? null,
            'site_name' => $data['site_name'] ?? null,
            'subject' => mb_substr(trim((string) ($data['subject'] ?? '')), 0, 190),
            'status' => 'open',
            'priority' => isset(self::PRIORITIES[$data['priority'] ?? '']) ? (string) $data['priority'] : 'normal',
            'author_name' => mb_substr(trim((string) ($data['author_name'] ?? '')), 0, 150) ?: null,
            'author_email' => self::email((string) ($data['author_email'] ?? '')),
            'messages' => 0,
            'last_sender' => 'client',
            'last_message_at' => $now,
            'client_read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::write($id, 'client', (string) ($data['body'] ?? ''), [
            'author_name' => (string) ($data['author_name'] ?? ''),
            'author_email' => (string) ($data['author_email'] ?? ''),
        ]);

        return self::find($id) ?? [];
    }

    /**
     * Afegeix un missatge a un tiquet i li posa l'estat que toca.
     *
     * Qui escriu decideix per on va el tiquet: si contesta el client torna a
     * estar obert, i si contestem nosaltres queda respost i esperant-lo. Les
     * notes internes no el mouen: són per a nosaltres.
     *
     * @param array{author_name?:string,author_email?:string,internal?:bool} $options
     */
    public static function write(int $ticketId, string $sender, string $body, array $options = []): int
    {
        $sender = isset(self::SENDERS[$sender]) ? $sender : 'client';
        $internal = $sender === 'support' && !empty($options['internal']);
        $now = date('Y-m-d H:i:s');
        $id = Db::insert('support_messages', [
            'ticket_id' => $ticketId,
            'sender' => $sender,
            'author_name' => mb_substr(trim((string) ($options['author_name'] ?? '')), 0, 150) ?: null,
            'author_email' => mb_substr(trim((string) ($options['author_email'] ?? '')), 0, 190) ?: null,
            'body' => self::tidy($body),
            'internal' => $internal ? 1 : 0,
            'ip' => client_ip(),
            'created_at' => $now,
        ]);
        if ($internal) {
            Db::update('support_tickets', ['updated_at' => $now], 'id = :id', ['id' => $ticketId]);

            return $id;
        }

        $fields = [
            'messages' => (int) Db::val(
                'SELECT COUNT(*) FROM support_messages WHERE ticket_id = :id AND internal = 0',
                ['id' => $ticketId],
                0
            ),
            'last_sender' => $sender,
            'last_message_at' => $now,
            'status' => $sender === 'client' ? 'open' : 'answered',
            'closed_at' => null,
            'updated_at' => $now,
        ];
        if ($sender === 'client') {
            // El client acaba d'escriure: per força ha llegit el que hi havia.
            $fields['client_read_at'] = $now;
        }
        Db::update('support_tickets', $fields, 'id = :id', ['id' => $ticketId]);

        return $id;
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM support_tickets WHERE id = :id', ['id' => $id]);
    }

    /** El tiquet d'un web concret: ningú no ha de poder llegir el d'un altre. */
    public static function findFor(int $id, string $slug): ?array
    {
        $ticket = self::find($id);

        return $ticket && (string) ($ticket['slug'] ?? '') === $slug ? $ticket : null;
    }

    /**
     * Els missatges d'un tiquet.
     * @param bool $withInternal les notes internes, que el client no veu mai
     * @return array<int,array<string,mixed>>
     */
    public static function messages(int $ticketId, bool $withInternal = false): array
    {
        $where = $withInternal ? '' : ' AND internal = 0';

        return Db::all(
            'SELECT * FROM support_messages WHERE ticket_id = :id' . $where . ' ORDER BY id ASC',
            ['id' => $ticketId]
        );
    }

    /**
     * Els tiquets d'un web, els que esperen resposta primer.
     * @return array<int,array<string,mixed>>
     */
    public static function forSlug(string $slug, int $limit = 100): array
    {
        return Db::all(
            "SELECT * FROM support_tickets WHERE slug = :slug
             ORDER BY (status = 'closed') ASC, last_message_at DESC LIMIT " . max(1, $limit),
            ['slug' => $slug]
        );
    }

    /**
     * La safata del suport.
     *
     * @param array{status?:string,department?:int,search?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public static function inbox(array $filters = [], int $limit = 200): array
    {
        $where = [];
        $params = [];
        $status = (string) ($filters['status'] ?? 'active');
        if ($status === 'active') {
            $where[] = "status <> 'closed'";
        } elseif (isset(self::STATUSES[$status])) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ((int) ($filters['department'] ?? 0) > 0) {
            $where[] = 'department_id = :department';
            $params['department'] = (int) $filters['department'];
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(subject LIKE :q OR reference LIKE :q OR author_email LIKE :q OR site_name LIKE :q OR slug LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return Db::all(
            'SELECT * FROM support_tickets'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'), last_message_at DESC"
            . ' LIMIT ' . max(1, $limit),
            $params
        );
    }

    /**
     * Quants n'hi ha de cada estat.
     * @return array<string,int>
     */
    public static function counts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach (Db::all('SELECT status, COUNT(*) AS n FROM support_tickets GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        $counts['active'] = $counts['open'] + $counts['answered'] + $counts['waiting'];

        return $counts;
    }

    /** Quants tiquets esperen que els contestem nosaltres. */
    public static function pending(): int
    {
        try {
            return (int) Db::val("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'", [], 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Quantes respostes té el client sense llegir. */
    public static function unreadFor(string $slug): int
    {
        try {
            return (int) Db::val(
                "SELECT COUNT(*) FROM support_tickets
                 WHERE slug = :slug AND last_sender = 'support'
                   AND (client_read_at IS NULL OR client_read_at < last_message_at)",
                ['slug' => $slug],
                0
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Quantes respostes té pendents el web que s'està servint, per al distintiu
     * del menú.
     *
     * Això es demana a cada pàgina del panell d'un client, així que es guarda
     * per a la petició i qualsevol entrebanc amb la plataforma es queda en un
     * zero: el panell del client no s'ha d'espatllar perquè la nostra base de
     * dades no respongui.
     */
    public static function badge(string $slug, ?string $root = null): int
    {
        static $memo = [];
        if ($slug === '' || !self::available($root)) {
            return 0;
        }
        if (array_key_exists($slug, $memo)) {
            return $memo[$slug];
        }
        try {
            return $memo[$slug] = self::run(static fn (): int => self::unreadFor($slug), $root);
        } catch (\Throwable $e) {
            return $memo[$slug] = 0;
        }
    }

    /** Apunta que el client ha obert el tiquet. */
    public static function markRead(int $id): void
    {
        Db::update('support_tickets', ['client_read_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    /** Canvia l'estat, el departament o la prioritat. */
    public static function update(int $id, array $fields): void
    {
        $data = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($fields['status']) && isset(self::STATUSES[$fields['status']])) {
            $data['status'] = (string) $fields['status'];
            $data['closed_at'] = $data['status'] === 'closed' ? date('Y-m-d H:i:s') : null;
        }
        if (array_key_exists('department_id', $fields)) {
            $data['department_id'] = (int) $fields['department_id'] > 0 ? (int) $fields['department_id'] : null;
        }
        if (isset($fields['priority']) && isset(self::PRIORITIES[$fields['priority']])) {
            $data['priority'] = (string) $fields['priority'];
        }
        Db::update('support_tickets', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Db::delete('support_messages', 'ticket_id = :id', ['id' => $id]);
        Db::delete('support_tickets', 'id = :id', ['id' => $id]);
    }

    /** El número que es diu al client: S-2026-014. */
    public static function reference(): string
    {
        $year = date('Y');
        $count = (int) Db::val(
            'SELECT COUNT(*) FROM support_tickets WHERE reference LIKE :like',
            ['like' => 'S-' . $year . '-%'],
            0
        );
        do {
            $count++;
            $reference = 'S-' . $year . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
        } while (Db::val('SELECT 1 FROM support_tickets WHERE reference = :r', ['r' => $reference]));

        return $reference;
    }

    /** El cos d'un missatge, net d'HTML que no hi pinta res. */
    public static function tidy(string $body): string
    {
        $clean = Html::clean(trim($body));

        return preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#iu', '', $clean) ?? $clean;
    }

    private static function email(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 190) : '';
    }
}
