<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Auth;
use Cros\Core\Db;
use Cros\Core\Html;
use Cros\Core\Mailer;
use Cros\Core\View;

/**
 * Enviaments de correu a les persones inscrites.
 *
 * El correu s'envia per tandes: un allotjament compartit no aguanta centenars
 * d'enviaments en una sola petició, i així es pot reprendre si es talla.
 */
class Mailing
{
    /** A qui va el correu. */
    public const AUDIENCES = [
        'all' => 'Totes les persones inscrites',
        'category' => 'Només algunes categories',
        'manual' => 'Adreces escrites a mà',
    ];

    /** Quines inscripcions compten. */
    public const STATUSES = [
        'confirmed' => 'Només les inscripcions confirmades',
        'any' => 'Totes, també les pendents',
    ];

    /** Marcadors que es poden escriure al cos del correu. */
    public const PLACEHOLDERS = [
        '{{tutor}}' => 'Nom de la persona de contacte',
        '{{participants}}' => 'Noms dels participants d\'aquesta família',
        '{{dorsals}}' => 'Números de dorsal',
        '{{cursa}}' => 'Nom de la cursa',
        '{{data}}' => 'Data de la cursa',
    ];

    /** Quants correus s'envien de cop. */
    public static function batchSize(): int
    {
        return max(1, min(100, (int) setting('mail_batch_size', '20')));
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM mailings WHERE id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        return Db::all('SELECT * FROM mailings ORDER BY id DESC LIMIT ' . max(1, $limit));
    }

    /** Desa un esborrany i en torna l'identificador. */
    public static function save(int $id, array $data): int
    {
        $fields = [
            'subject' => mb_substr(trim((string) $data['subject']), 0, 190),
            'body' => self::tidy((string) $data['body']),
            'audience' => isset(self::AUDIENCES[$data['audience'] ?? '']) ? $data['audience'] : 'all',
            'categories' => implode(',', array_map('intval', (array) ($data['categories'] ?? []))),
            'reg_status' => isset(self::STATUSES[$data['reg_status'] ?? '']) ? $data['reg_status'] : 'confirmed',
            'manual_emails' => trim((string) ($data['manual_emails'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            Db::update('mailings', $fields, 'id = :id', ['id' => $id]);
            return $id;
        }
        $fields['status'] = 'draft';
        $fields['created_by'] = Auth::user()['id'] ?? null;
        $fields['created_at'] = date('Y-m-d H:i:s');

        return Db::insert('mailings', $fields);
    }

    /**
     * Qui rebrà el correu, ja sense adreces repetides: una família amb tres
     * participants rep un sol correu amb els tres noms.
     *
     * @return array<string,array{email:string,name:string,participants:string,bibs:string}>
     */
    public static function audience(array $mailing): array
    {
        if (($mailing['audience'] ?? 'all') === 'manual') {
            $list = [];
            foreach (preg_split('/[\s,;]+/', (string) ($mailing['manual_emails'] ?? '')) ?: [] as $email) {
                $email = mb_strtolower(trim($email));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $list[$email] = ['email' => $email, 'name' => '', 'participants' => '', 'bibs' => ''];
                }
            }
            return $list;
        }

        $where = ["r.tutor_email IS NOT NULL", "r.tutor_email <> ''"];
        $params = [];
        if (($mailing['reg_status'] ?? 'confirmed') === 'confirmed') {
            $where[] = "r.status = 'confirmed'";
        } else {
            $where[] = "r.status <> 'cancelled'";
        }
        $categories = self::categoryIds($mailing);
        if (($mailing['audience'] ?? 'all') === 'category') {
            if (!$categories) {
                return [];
            }
            $in = [];
            foreach ($categories as $index => $categoryId) {
                $in[] = ':cat' . $index;
                $params['cat' . $index] = $categoryId;
            }
            $where[] = 'r.category_id IN (' . implode(', ', $in) . ')';
        }

        $rows = Db::all(
            'SELECT r.first_name, r.last_name, r.bib_number, r.tutor_name, r.tutor_email
             FROM registrations r
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY r.tutor_email ASC, r.bib_number ASC',
            $params
        );

        $list = [];
        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) $row['tutor_email']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (!isset($list[$email])) {
                $list[$email] = [
                    'email' => $email,
                    'name' => (string) ($row['tutor_name'] ?? ''),
                    'participants' => [],
                    'bibs' => [],
                ];
            }
            $list[$email]['participants'][] = trim($row['first_name'] . ' ' . $row['last_name']);
            if (!empty($row['bib_number'])) {
                $list[$email]['bibs'][] = Bib::number($row);
            }
        }
        foreach ($list as $email => $person) {
            $list[$email]['participants'] = mb_substr(self::join($person['participants']), 0, 500);
            $list[$email]['bibs'] = mb_substr(implode(', ', $person['bibs']), 0, 190);
        }

        return $list;
    }

    /** @return array<int,int> */
    public static function categoryIds(array $mailing): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string) ($mailing['categories'] ?? ''))));

        return array_values(array_unique($ids));
    }

    /**
     * Prepara la llista de destinataris i deixa l'enviament a punt de començar.
     * @return int quants en són
     */
    public static function prepare(int $id): int
    {
        $mailing = self::find($id);
        if (!$mailing) {
            return 0;
        }
        Db::delete('mailing_recipients', 'mailing_id = :id AND status = :status', ['id' => $id, 'status' => 'pending']);
        $already = [];
        foreach (Db::all('SELECT email FROM mailing_recipients WHERE mailing_id = :id', ['id' => $id]) as $row) {
            $already[(string) $row['email']] = true;
        }

        $count = 0;
        foreach (self::audience($mailing) as $person) {
            if (isset($already[$person['email']])) {
                continue; // ja se li ha enviat en una tanda anterior
            }
            Db::insert('mailing_recipients', [
                'mailing_id' => $id,
                'email' => $person['email'],
                'name' => mb_substr((string) $person['name'], 0, 190),
                'participants' => (string) $person['participants'],
                'bibs' => (string) $person['bibs'],
                'status' => 'pending',
            ]);
            $count++;
        }

        $total = (int) Db::val('SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id', ['id' => $id], 0);
        $pending = (int) Db::val(
            'SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id AND status = :status',
            ['id' => $id, 'status' => 'pending'],
            0
        );
        // Si no hi ha ningú nou a qui escriure, l'enviament es queda com estava.
        Db::update('mailings', [
            'status' => $pending > 0 ? 'sending' : ($total > 0 ? 'sent' : 'draft'),
            'total' => $total,
            'started_at' => $mailing['started_at'] ?: date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        return $count;
    }

    /**
     * Envia una tanda de correus.
     * @return array{sent:int,failed:int,pending:int,done:bool}
     */
    public static function sendBatch(int $id, ?int $size = null): array
    {
        $mailing = self::find($id);
        if (!$mailing || $mailing['status'] === 'draft') {
            return ['sent' => 0, 'failed' => 0, 'pending' => 0, 'done' => true];
        }
        $size = $size ?? self::batchSize();
        $rows = Db::all(
            'SELECT * FROM mailing_recipients WHERE mailing_id = :id AND status = :status ORDER BY id ASC LIMIT ' . max(1, $size),
            ['id' => $id, 'status' => 'pending']
        );

        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $ok = false;
            $error = '';
            try {
                $ok = Mailer::send(
                    (string) $row['email'],
                    (string) $mailing['subject'],
                    self::render($mailing, $row)
                );
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
            Db::update('mailing_recipients', [
                'status' => $ok ? 'sent' : 'failed',
                'error' => $ok ? null : mb_substr($error !== '' ? $error : 'El servidor no ha pogut enviar el correu.', 0, 500),
                'sent_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $row['id']]);
            $ok ? $sent++ : $failed++;
        }

        $pending = (int) Db::val(
            'SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id AND status = :status',
            ['id' => $id, 'status' => 'pending'],
            0
        );
        $done = $pending === 0;
        Db::update('mailings', [
            'sent' => (int) Db::val('SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id AND status = :s', ['id' => $id, 's' => 'sent'], 0),
            'failed' => (int) Db::val('SELECT COUNT(*) FROM mailing_recipients WHERE mailing_id = :id AND status = :s', ['id' => $id, 's' => 'failed'], 0),
            'status' => $done ? 'sent' : 'sending',
            'finished_at' => $done ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);

        if ($done) {
            Auth::logActivity('mailing_done', 'mailing', $id, ['enviats' => $sent, 'errors' => $failed]);
        }

        return ['sent' => $sent, 'failed' => $failed, 'pending' => $pending, 'done' => $done];
    }

    /** El correu d'una persona concreta, amb els marcadors substituïts. */
    public static function render(array $mailing, array $recipient): string
    {
        $body = self::personalize((string) $mailing['body'], $recipient);

        return View::make('emails/mailing', [
            'body' => $body,
            'subject' => (string) $mailing['subject'],
        ], 'emails/layout');
    }

    /** Substitueix els marcadors del cos pel que toca a cada destinatari. */
    public static function personalize(string $body, array $recipient): string
    {
        $participants = trim((string) ($recipient['participants'] ?? ''));

        return strtr($body, [
            '{{tutor}}' => e(trim((string) ($recipient['name'] ?? ''))),
            '{{participants}}' => e($participants),
            '{{dorsals}}' => e(trim((string) ($recipient['bibs'] ?? ''))),
            '{{cursa}}' => e((string) setting('site_name', '')),
            '{{data}}' => e(ca_date((string) setting('event_date', ''), true)),
        ]);
    }

    /** Destinataris d'un enviament, per mostrar-ne l'estat. */
    public static function recipients(int $id, ?string $status = null, int $limit = 200): array
    {
        $sql = 'SELECT * FROM mailing_recipients WHERE mailing_id = :id';
        $params = ['id' => $id];
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        return Db::all($sql . ' ORDER BY id ASC LIMIT ' . max(1, $limit), $params);
    }

    public static function delete(int $id): void
    {
        Db::delete('mailing_recipients', 'mailing_id = :id', ['id' => $id]);
        Db::delete('mailings', 'id = :id', ['id' => $id]);
        Auth::logActivity('mailing_delete', 'mailing', $id, []);
    }

    /**
     * Neteja el cos del correu: treu l'HTML que no és segur i els paràgrafs
     * buits que deixa l'editor visual en reordenar llistes i títols.
     */
    public static function tidy(string $body): string
    {
        $clean = Html::clean($body);
        $clean = preg_replace('#<p>(\s|&nbsp;|<br\s*/?>)*</p>#iu', '', $clean) ?? $clean;

        return trim($clean);
    }

    /** Llista de noms en llenguatge natural: «Laia, Pau i Roc». */
    private static function join(array $names): string
    {
        $names = array_values(array_unique(array_filter($names)));
        if (count($names) <= 1) {
            return $names[0] ?? '';
        }
        $last = array_pop($names);

        return implode(', ', $names) . ' i ' . $last;
    }
}
