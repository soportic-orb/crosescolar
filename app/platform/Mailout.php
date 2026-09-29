<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Html;
use Cros\Core\Mailer;
use Cros\Core\Settings;

/**
 * Enviaments de correu de la plataforma.
 *
 * Funciona igual que el dels cros —es prepara la llista de destinataris i
 * s'envia per tandes, perquè un allotjament compartit no aguanta centenars de
 * correus en una sola petició— però amb la gent de la plataforma: els clients,
 * les administradores de cada web o una llista feta a mà.
 *
 * El nom («mailout») no és cap caprici: «Mailing» ja és el dels cros i tenir
 * dues classes que es diguin igual en dos llocs diferents acaba costant una
 * tarda a qui les llegeixi d'aquí a un any.
 */
class Mailout
{
    /** A qui va el correu. */
    public const AUDIENCES = [
        'clients' => 'Les persones de contacte dels clients',
        'instances' => 'Les administradores de cada web',
        'list' => 'Una llista de correu',
        'manual' => 'Adreces escrites a mà',
    ];

    /** Quines instàncies compten. */
    public const SCOPES = [
        'active' => 'Només els webs en marxa',
        'all' => 'Tots, també els aturats i els que no s\'han estrenat',
    ];

    /** Quants correus s'envien de cop. */
    public static function batchSize(): int
    {
        return max(1, min(100, (int) Settings::get('mail_batch_size', '20')));
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM platform_mailings WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        return Db::all('SELECT * FROM platform_mailings ORDER BY id DESC LIMIT ' . max(1, $limit));
    }

    /** Desa un esborrany i en torna l'identificador. */
    public static function save(int $id, array $data, ?int $userId = null): int
    {
        $audience = isset(self::AUDIENCES[$data['audience'] ?? '']) ? (string) $data['audience'] : 'clients';
        $fields = [
            'subject' => mb_substr(trim((string) ($data['subject'] ?? '')), 0, 190),
            'body' => self::tidy((string) ($data['body'] ?? '')),
            'audience' => $audience,
            'list_id' => $audience === 'list' && (int) ($data['list_id'] ?? 0) > 0 ? (int) $data['list_id'] : null,
            'instance_status' => isset(self::SCOPES[$data['instance_status'] ?? '']) ? (string) $data['instance_status'] : 'active',
            'manual_emails' => trim((string) ($data['manual_emails'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            Db::update('platform_mailings', $fields, 'id = :id', ['id' => $id]);

            return $id;
        }
        $fields['status'] = 'draft';
        $fields['created_by'] = $userId;
        $fields['created_at'] = date('Y-m-d H:i:s');

        return Db::insert('platform_mailings', $fields);
    }

    /**
     * Qui rebrà el correu, sense adreces repetides.
     *
     * @return array<string,array{email:string,name:string,entity:string}>
     */
    public static function audience(array $mailing): array
    {
        $audience = (string) ($mailing['audience'] ?? 'clients');
        $rows = [];
        if ($audience === 'clients') {
            $rows = Db::all(
                "SELECT contact_email AS email, contact_name AS name, name AS entity
                 FROM clients WHERE status = 'active' AND contact_email <> ''"
            );
        } elseif ($audience === 'instances') {
            $where = ($mailing['instance_status'] ?? 'active') === 'all'
                ? "status NOT IN ('cancelled', 'purged')"
                : "status = 'active'";
            $rows = Db::all(
                "SELECT admin_email AS email, '' AS name, site_name AS entity
                 FROM instances WHERE $where AND admin_email IS NOT NULL AND admin_email <> ''"
            );
        } elseif ($audience === 'list') {
            $rows = Db::all(
                'SELECT email, name, entity FROM mail_contacts WHERE list_id = :id AND active = 1',
                ['id' => (int) ($mailing['list_id'] ?? 0)]
            );
        } else {
            foreach (preg_split('/\R/', (string) ($mailing['manual_emails'] ?? '')) ?: [] as $line) {
                $contact = MailList::parse($line);
                if ($contact !== null) {
                    $rows[] = $contact;
                }
            }
        }

        $list = [];
        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($list[$email])) {
                continue;
            }
            $list[$email] = [
                'email' => $email,
                'name' => mb_substr(trim((string) ($row['name'] ?? '')), 0, 190),
                'entity' => mb_substr(trim((string) ($row['entity'] ?? '')), 0, 190),
            ];
        }
        ksort($list);

        return $list;
    }

    /**
     * Prepara la llista de destinataris i deixa l'enviament a punt de començar.
     * @return int quants n'hi ha de nous
     */
    public static function prepare(int $id): int
    {
        $mailing = self::find($id);
        if (!$mailing) {
            return 0;
        }
        Db::delete('platform_mailing_recipients', 'mailing_id = :id AND status = :s', ['id' => $id, 's' => 'pending']);
        $already = [];
        foreach (Db::all('SELECT email FROM platform_mailing_recipients WHERE mailing_id = :id', ['id' => $id]) as $row) {
            $already[(string) $row['email']] = true;
        }

        $count = 0;
        foreach (self::audience($mailing) as $person) {
            if (isset($already[$person['email']])) {
                continue; // ja l'ha rebut en una tanda anterior
            }
            Db::insert('platform_mailing_recipients', [
                'mailing_id' => $id,
                'email' => $person['email'],
                'name' => $person['name'] ?: null,
                'entity' => $person['entity'] ?: null,
                'status' => 'pending',
            ]);
            $count++;
        }

        $total = (int) Db::val('SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id', ['id' => $id], 0);
        $pending = (int) Db::val(
            'SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id AND status = :s',
            ['id' => $id, 's' => 'pending'],
            0
        );
        Db::update('platform_mailings', [
            'status' => $pending > 0 ? 'sending' : ($total > 0 ? 'sent' : 'draft'),
            'total' => $total,
            'started_at' => $mailing['started_at'] ?: date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        return $count;
    }

    /**
     * Envia una tanda.
     * @return array{sent:int,failed:int,pending:int,done:bool}
     */
    public static function sendBatch(int $id, ?int $size = null): array
    {
        $mailing = self::find($id);
        if (!$mailing || $mailing['status'] === 'draft') {
            return ['sent' => 0, 'failed' => 0, 'pending' => 0, 'done' => true];
        }
        $rows = Db::all(
            'SELECT * FROM platform_mailing_recipients WHERE mailing_id = :id AND status = :s ORDER BY id ASC LIMIT '
            . max(1, $size ?? self::batchSize()),
            ['id' => $id, 's' => 'pending']
        );

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $ok = false;
            $error = '';
            try {
                $ok = Mailer::send(
                    (string) $row['email'],
                    self::subjectFor($mailing, $row),
                    self::render($mailing, $row)
                );
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
            Db::update('platform_mailing_recipients', [
                'status' => $ok ? 'sent' : 'failed',
                'error' => $ok ? null : mb_substr($error !== '' ? $error : 'El servidor no ha pogut enviar el correu.', 0, 500),
                'sent_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $row['id']]);
            $ok ? $sent++ : $failed++;
        }

        $pending = (int) Db::val(
            'SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id AND status = :s',
            ['id' => $id, 's' => 'pending'],
            0
        );
        $done = $pending === 0;
        Db::update('platform_mailings', [
            'sent' => (int) Db::val('SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id AND status = :s', ['id' => $id, 's' => 'sent'], 0),
            'failed' => (int) Db::val('SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id AND status = :s', ['id' => $id, 's' => 'failed'], 0),
            'status' => $done ? 'sent' : 'sending',
            'finished_at' => $done ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        return ['sent' => $sent, 'failed' => $failed, 'pending' => $pending, 'done' => $done];
    }

    /** L'assumpte d'un destinatari concret: també hi valen els marcadors. */
    public static function subjectFor(array $mailing, array $recipient): string
    {
        $values = MailTemplate::values((string) $mailing['subject'], $recipient);
        // A l'assumpte no hi va HTML, de manera que els marcadors hi entren en text pla.
        $plain = array_map(static fn (string $v): string => html_entity_decode($v, ENT_QUOTES, 'UTF-8'), $values);

        return mb_substr(trim(strtr((string) $mailing['subject'], $plain)), 0, 190);
    }

    /** El correu d'un destinatari, amb la plantilla i els marcadors posats. */
    public static function render(array $mailing, array $recipient): string
    {
        return MailTemplate::render(
            (string) $mailing['body'],
            (string) $mailing['subject'],
            [
                'name' => (string) ($recipient['name'] ?? ''),
                'entity' => (string) ($recipient['entity'] ?? ''),
                'email' => (string) ($recipient['email'] ?? ''),
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function recipients(int $id, ?string $status = null, int $limit = 300): array
    {
        $sql = 'SELECT * FROM platform_mailing_recipients WHERE mailing_id = :id';
        $params = ['id' => $id];
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        return Db::all($sql . ' ORDER BY id ASC LIMIT ' . max(1, $limit), $params);
    }

    public static function delete(int $id): void
    {
        Db::delete('platform_mailing_recipients', 'mailing_id = :id', ['id' => $id]);
        Db::delete('platform_mailings', 'id = :id', ['id' => $id]);
    }

    /** El cos, net d'HTML que no hi pinta res i dels paràgrafs buits de l'editor. */
    public static function tidy(string $body): string
    {
        $clean = Html::clean($body);

        return trim(preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#iu', '', $clean) ?? $clean);
    }
}
