<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Platform\Mail\Brand;
use Cros\Platform\Mail\Doc;
use Cros\Platform\Mail\Quota;
use Cros\Platform\Mail\Renderer;
use Cros\Platform\Mail\RichText;
use Cros\Platform\Mail\Unsubscribe;

/**
 * Enviaments de correu de la plataforma.
 *
 * El correu es fa amb l'editor de blocs (Mail\Doc), cada «Desa» en guarda una
 * versió, i s'envia ara o el dia i l'hora que es programa. Qui l'envia de debò
 * és el cron (tools/platform.php enviaments, cada cinc minuts): prepara la
 * llista de destinataris quan arriba l'hora i l'envia per tandes, sense passar
 * dels correus per hora que admet el servidor de cada domini.
 *
 * Cada enviament surt d'un domini —el seu servidor de correu, el seu remitent,
 * els seus colors— i, si no es diu el contrari, només va a la gent d'aquell
 * domini. Qui s'ha donat de baixa no en rep cap més.
 *
 * El nom («mailout») no és cap caprici: «Mailing» ja és el dels cros i tenir
 * dues classes que es diguin igual en dos llocs diferents acaba costant una
 * tarda a qui les llegeixi d'aquí a un any.
 */
class Mailout
{
    public const MAIL = 'mail';

    public const TEMPLATE = 'template';

    /** Els estats d'un enviament. */
    public const STATUSES = [
        'draft' => 'Esborrany',
        'scheduled' => 'Programat',
        'sending' => 'S\'està enviant',
        'paused' => 'En pausa',
        'sent' => 'Enviat',
        'cancelled' => 'Aturat',
    ];

    /** Les pestanyes de la llista. */
    public const FILTERS = [
        'tots' => 'Tots',
        'esborranys' => 'Esborranys',
        'programats' => 'Programats',
        'enviant' => 'S\'estan enviant',
        'enviats' => 'Enviats',
        'arxivats' => 'Arxivats',
    ];

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

    /** Una tanda que no envia res i en falla tantes vol dir que el servidor no va. */
    public const PAUSE_AFTER = 3;

    /** El que pot durar com a molt una passada del cron, en segons. */
    public const TICK_SECONDS = 50;

    /** Quants correus s'envien de cop. */
    public static function batchSize(): int
    {
        return max(1, min(100, (int) Settings::get('mail_batch_size', '20')));
    }

    /** Quants correus per hora pot enviar un domini (0: sense límit). */
    public static function hourlyLimit(string $domain): int
    {
        return Quota::limit($domain);
    }

    /**
     * Els que ha enviat un domini l'última hora: els del panell i els dels
     * webs dels clients, que surten tots pel mateix servidor (Mail\Quota).
     */
    public static function sentLastHour(string $domain): int
    {
        return Quota::used($domain);
    }

    public static function find(int $id, ?string $kind = null): ?array
    {
        $row = Db::one('SELECT * FROM platform_mailings WHERE id = :id', ['id' => $id]);
        if ($row && $kind !== null && self::kind($row) !== $kind) {
            return null;
        }

        return $row;
    }

    public static function kind(array $mailing): string
    {
        return ($mailing['kind'] ?? '') === self::TEMPLATE ? self::TEMPLATE : self::MAIL;
    }

    /** El domini des d'on surt (els enviaments d'abans, del principal). */
    public static function domain(array $mailing): string
    {
        return Platform::validDomain((string) ($mailing['domain'] ?? ''));
    }

    /** Es pot tocar el contingut? Un cop ha començat a sortir, ja no. */
    public static function editable(array $mailing): bool
    {
        return self::kind($mailing) === self::TEMPLATE || in_array($mailing['status'], ['draft', 'scheduled'], true);
    }

    /** @return array<int,array<string,mixed>> */
    public static function mails(string $filter = 'tots', string $search = '', int $limit = 200): array
    {
        $where = ["kind = 'mail'"];
        $params = [];
        $where[] = match ($filter) {
            'esborranys' => "status = 'draft' AND archived_at IS NULL",
            'programats' => "status = 'scheduled' AND archived_at IS NULL",
            'enviant' => "status IN ('sending', 'paused') AND archived_at IS NULL",
            'enviats' => "status IN ('sent', 'cancelled') AND archived_at IS NULL",
            'arxivats' => 'archived_at IS NOT NULL',
            default => 'archived_at IS NULL',
        };
        if (trim($search) !== '') {
            $where[] = '(subject LIKE :q OR preheader LIKE :q)';
            $params['q'] = '%' . trim($search) . '%';
        }

        return Db::all(
            'SELECT * FROM platform_mailings WHERE ' . implode(' AND ', $where)
            . " ORDER BY CASE status WHEN 'sending' THEN 0 WHEN 'paused' THEN 1 WHEN 'scheduled' THEN 2 ELSE 3 END,"
            . ' COALESCE(scheduled_at, started_at, updated_at, created_at) DESC, id DESC LIMIT ' . max(1, $limit),
            $params
        );
    }

    /** @return array<string,int> quants n'hi ha a cada pestanya */
    public static function counts(): array
    {
        $out = array_fill_keys(array_keys(self::FILTERS), 0);
        foreach (Db::all("SELECT status, archived_at IS NOT NULL AS archived, COUNT(*) AS n FROM platform_mailings WHERE kind = 'mail' GROUP BY status, archived_at IS NOT NULL") as $row) {
            $n = (int) $row['n'];
            if ((int) $row['archived']) {
                $out['arxivats'] += $n;
                continue;
            }
            $out['tots'] += $n;
            $tab = match ((string) $row['status']) {
                'draft' => 'esborranys',
                'scheduled' => 'programats',
                'sending', 'paused' => 'enviant',
                default => 'enviats',
            };
            $out[$tab] += $n;
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 50): array
    {
        return self::mails('tots', '', $limit);
    }

    // ------------------------------------------------------------------
    // Crear, desar i versions
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> les plantilles base */
    public static function templates(): array
    {
        self::ensureTemplates();

        return Db::all("SELECT * FROM platform_mailings WHERE kind = 'template' ORDER BY template_name, id");
    }

    /** Correu nou a partir d'una plantilla base (en fa una còpia) o en blanc. */
    public static function create(string $subject, ?int $templateId, string $domain, ?int $userId = null, string $userName = ''): int
    {
        $template = $templateId ? self::find($templateId, self::TEMPLATE) : null;

        return self::insert([
            'kind' => self::MAIL,
            'subject' => self::subject($subject !== '' ? $subject : (string) ($template['subject'] ?? '')),
            'preheader' => $template['preheader'] ?? null,
            'doc' => Doc::encode($template ? self::doc($template) : self::blankDoc()),
            'domain' => Platform::validDomain($domain),
            'template_id' => $template['id'] ?? null,
            'template_name' => $template['template_name'] ?? null,
        ], $userId, $userName);
    }

    /** Plantilla base nova: en blanc o a partir d'un correu o d'una altra plantilla. */
    public static function createTemplate(string $name, ?int $fromId, ?int $userId = null, string $userName = ''): int
    {
        $from = $fromId ? self::find($fromId) : null;

        return self::insert([
            'kind' => self::TEMPLATE,
            'template_name' => self::name($name, 'Plantilla sense nom'),
            'subject' => self::subject((string) ($from['subject'] ?? '')),
            'preheader' => $from['preheader'] ?? null,
            'doc' => Doc::encode($from ? self::doc($from) : self::blankDoc()),
        ], $userId, $userName);
    }

    public static function duplicate(int $id, ?int $userId = null, string $userName = ''): int
    {
        $source = self::find($id);
        if (!$source) {
            return 0;
        }
        $template = self::kind($source) === self::TEMPLATE;

        return self::insert([
            'kind' => self::kind($source),
            'subject' => self::subject($template ? (string) $source['subject'] : mb_substr((string) $source['subject'] . ' (còpia)', 0, 190)),
            'preheader' => $source['preheader'],
            'doc' => Doc::encode(self::doc($source)),
            'domain' => $source['domain'],
            'all_domains' => (int) ($source['all_domains'] ?? 0),
            'audience' => $source['audience'],
            'list_id' => $source['list_id'],
            'instance_status' => $source['instance_status'],
            'manual_emails' => $source['manual_emails'],
            'template_id' => $source['template_id'],
            'template_name' => $template ? mb_substr((string) $source['template_name'] . ' (còpia)', 0, 190) : $source['template_name'],
        ], $userId, $userName);
    }

    /**
     * Desa des de l'editor. Si algú hi ha desat una versió nova mentrestant
     * (i no es força), no es desa: torna 'conflict'. Un enviament que ja ha
     * començat a sortir no es pot canviar: torna 'locked'.
     *
     * @param array{subject?:string,preheader?:string,doc?:mixed,domain?:string,template_name?:string} $data
     * @return array{result:string,mailing:array<string,mixed>}
     */
    public static function save(int $id, array $data, int $baseVersion = 0, bool $force = false, ?int $userId = null, string $userName = ''): array
    {
        $mailing = self::find($id);
        if (!$mailing) {
            throw new \RuntimeException('Aquest correu ja no existeix.');
        }
        if (!self::editable($mailing)) {
            return ['result' => 'locked', 'mailing' => $mailing];
        }
        if (!$force && $baseVersion > 0 && $baseVersion < (int) $mailing['version']) {
            return ['result' => 'conflict', 'mailing' => $mailing];
        }

        $fields = [];
        if (array_key_exists('subject', $data)) {
            $fields['subject'] = self::subject((string) $data['subject']);
        }
        if (array_key_exists('preheader', $data)) {
            $fields['preheader'] = Doc::plain($data['preheader'], 255) ?: null;
        }
        if (array_key_exists('doc', $data)) {
            $fields['doc'] = Doc::encode(Doc::clean($data['doc']));
        }
        if (array_key_exists('domain', $data) && self::kind($mailing) === self::MAIL) {
            $fields['domain'] = Platform::validDomain((string) $data['domain']);
        }
        if (array_key_exists('template_name', $data) && self::kind($mailing) === self::TEMPLATE) {
            $fields['template_name'] = self::name((string) $data['template_name'], 'Plantilla sense nom');
        }

        $changed = false;
        foreach ($fields as $key => $value) {
            if ((string) ($mailing[$key] ?? '') !== (string) ($value ?? '')) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            return ['result' => 'unchanged', 'mailing' => $mailing];
        }

        Db::transaction(static function () use ($id, $mailing, $fields, $userId, $userName): void {
            Db::update('platform_mailings', $fields + [
                'version' => (int) $mailing['version'] + 1,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);
            self::storeVersion((array) self::find($id), $userId, $userName);
        });

        return ['result' => 'saved', 'mailing' => (array) self::find($id)];
    }

    /** Torna a una versió anterior (es desa com una versió nova). */
    public static function restore(int $id, int $versionId, ?int $userId = null, string $userName = ''): array
    {
        $version = self::version($id, $versionId);
        if (!$version) {
            throw new \RuntimeException('Aquesta versió no existeix.');
        }

        return self::save($id, [
            'subject' => (string) $version['subject'],
            'preheader' => (string) $version['preheader'],
            'doc' => Doc::decode((string) $version['doc']),
        ], 0, true, $userId, $userName);
    }

    /** @return array<int,array<string,mixed>> */
    public static function versions(int $id): array
    {
        return Db::all(
            'SELECT id, version, subject, saved_by_name, created_at FROM platform_mailing_versions WHERE mailing_id = :id ORDER BY version DESC LIMIT 100',
            ['id' => $id]
        );
    }

    public static function version(int $id, int $versionId): ?array
    {
        return Db::one('SELECT * FROM platform_mailing_versions WHERE id = :v AND mailing_id = :id', ['v' => $versionId, 'id' => $id]);
    }

    // ------------------------------------------------------------------
    // Destinataris, programació i estats
    // ------------------------------------------------------------------

    /**
     * A qui va i quan. Es desa des del diàleg «Envia o programa».
     *
     * @param array{audience?:string,list_id?:int|string,instance_status?:string,manual_emails?:string,all_domains?:mixed,domain?:string} $data
     */
    public static function setDelivery(int $id, array $data): void
    {
        $mailing = self::find($id, self::MAIL);
        if (!$mailing || !self::editable($mailing)) {
            throw new \RuntimeException('Aquest enviament ja ha començat: no se\'n poden canviar els destinataris.');
        }
        Db::update('platform_mailings', self::deliveryFields($data, $mailing) + ['updated_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed> */
    public static function deliveryFields(array $data, array $mailing = []): array
    {
        $audience = isset(self::AUDIENCES[$data['audience'] ?? '']) ? (string) $data['audience'] : (string) ($mailing['audience'] ?? 'clients');

        return [
            'audience' => $audience,
            'list_id' => $audience === 'list' && (int) ($data['list_id'] ?? 0) > 0 ? (int) $data['list_id'] : null,
            'instance_status' => isset(self::SCOPES[$data['instance_status'] ?? '']) ? (string) $data['instance_status'] : 'active',
            'manual_emails' => mb_substr(trim((string) ($data['manual_emails'] ?? '')), 0, 60000),
            'all_domains' => !empty($data['all_domains']) ? 1 : 0,
            'domain' => Platform::validDomain((string) ($data['domain'] ?? $mailing['domain'] ?? '')),
        ];
    }

    /** El programa per a un dia i una hora (ha de ser més endavant). */
    public static function schedule(int $id, \DateTimeInterface $at): void
    {
        $mailing = self::find($id, self::MAIL);
        if (!$mailing || !in_array($mailing['status'], ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('Només es pot programar un esborrany.');
        }
        if ($at->getTimestamp() <= time()) {
            throw new \RuntimeException('L\'hora programada ja ha passat: trieu-ne una de més endavant o envieu-lo ara.');
        }
        self::check($mailing);
        Db::update('platform_mailings', [
            'status' => 'scheduled',
            'scheduled_at' => $at->format('Y-m-d H:i:s'),
            'archived_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /** Torna un programat a esborrany. */
    public static function unschedule(int $id): void
    {
        Db::update('platform_mailings', ['status' => 'draft', 'scheduled_at' => null, 'updated_at' => date('Y-m-d H:i:s')],
            "id = :id AND status = 'scheduled'", ['id' => $id]);
    }

    /**
     * Comença a enviar-lo ara: fa la llista de destinataris (la que hi ha en
     * aquest moment, que és la que val encara que després se n'afegeixin) i el
     * deixa perquè en surtin les tandes.
     *
     * @return int quants destinataris té
     */
    public static function start(int $id): int
    {
        $mailing = self::find($id, self::MAIL);
        if (!$mailing || !in_array($mailing['status'], ['draft', 'scheduled'], true)) {
            throw new \RuntimeException('Aquest enviament ja ha començat.');
        }
        self::check($mailing);
        $count = 0;
        Db::transaction(static function () use ($id, $mailing, &$count): void {
            Db::delete('platform_mailing_recipients', 'mailing_id = :id', ['id' => $id]);
            foreach (self::audience($mailing) as $person) {
                Db::insert('platform_mailing_recipients', [
                    'mailing_id' => $id,
                    'email' => $person['email'],
                    'name' => $person['name'] ?: null,
                    'entity' => $person['entity'] ?: null,
                    'status' => 'pending',
                ]);
                $count++;
            }
            Db::update('platform_mailings', [
                'status' => $count > 0 ? 'sending' : 'sent',
                'total' => $count,
                'sent' => 0,
                'failed' => 0,
                'paused_reason' => null,
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => $count > 0 ? null : date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);
        });
        Platform::log('mailout.start', 'mailout', $id, ['recipients' => $count, 'domain' => self::domain($mailing)]);

        return $count;
    }

    /** Ho comprova abans d'enviar o de programar: que tingui assumpte i algú a qui anar. */
    public static function check(array $mailing): void
    {
        if (trim((string) $mailing['subject']) === '') {
            throw new \RuntimeException('Falta l\'assumpte del correu.');
        }
        if (self::audienceCount($mailing) === 0) {
            throw new \RuntimeException('No hi ha cap destinatari: reviseu a qui va i el domini.');
        }
    }

    public static function pause(int $id, string $reason = ''): void
    {
        Db::update('platform_mailings', [
            'status' => 'paused',
            'paused_reason' => $reason !== '' ? mb_substr($reason, 0, 500) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], "id = :id AND status = 'sending'", ['id' => $id]);
    }

    public static function resume(int $id): void
    {
        Db::update('platform_mailings', ['status' => 'sending', 'paused_reason' => null, 'updated_at' => date('Y-m-d H:i:s')],
            "id = :id AND status = 'paused'", ['id' => $id]);
    }

    /** L'atura del tot: els que encara no l'han rebut ja no el rebran. */
    public static function stop(int $id): void
    {
        $mailing = self::find($id, self::MAIL);
        if (!$mailing || !in_array($mailing['status'], ['sending', 'paused'], true)) {
            return;
        }
        Db::update('platform_mailing_recipients', ['status' => 'skipped'], "mailing_id = :id AND status = 'pending'", ['id' => $id]);
        Db::update('platform_mailings', ['status' => 'cancelled', 'finished_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            'id = :id', ['id' => $id]);
        Platform::log('mailout.stop', 'mailout', $id);
    }

    /** Torna a provar els que han fallat. @return int quants */
    public static function retryFailed(int $id): int
    {
        $mailing = self::find($id, self::MAIL);
        if (!$mailing || !in_array($mailing['status'], ['sent', 'sending', 'paused'], true)) {
            return 0;
        }
        $n = Db::update('platform_mailing_recipients', ['status' => 'pending', 'error' => null, 'sent_at' => null],
            "mailing_id = :id AND status = 'failed'", ['id' => $id]);
        if ($n > 0) {
            Db::update('platform_mailings', ['status' => 'sending', 'paused_reason' => null, 'finished_at' => null, 'updated_at' => date('Y-m-d H:i:s')],
                'id = :id', ['id' => $id]);
            self::recount($id);
        }

        return $n;
    }

    public static function archive(int $id, bool $archived = true): void
    {
        Db::update('platform_mailings', ['archived_at' => $archived ? date('Y-m-d H:i:s') : null],
            "id = :id AND status NOT IN ('scheduled', 'sending', 'paused')", ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Db::transaction(static function () use ($id): void {
            Db::delete('platform_mailing_recipients', 'mailing_id = :id', ['id' => $id]);
            Db::delete('platform_mailing_versions', 'mailing_id = :id', ['id' => $id]);
            Db::delete('platform_mailings', 'id = :id', ['id' => $id]);
        });
    }

    /**
     * Qui rebrà el correu, sense adreces repetides ni les de qui s'ha donat de baixa.
     *
     * Les audiències de la plataforma (clients i webs) es queden amb les del
     * domini des d'on surt, si no s'ha marcat que vagi a tots. Les llistes i
     * les adreces escrites a mà no: les ha triat qui envia.
     *
     * @return array<string,array{email:string,name:string,entity:string}>
     */
    public static function audience(array $mailing, bool $withoutUnsubscribed = true): array
    {
        $audience = (string) ($mailing['audience'] ?? 'clients');
        $domain = self::domain($mailing);
        $everywhere = !empty($mailing['all_domains']);
        $instanceDomain = 'COALESCE(NULLIF(i.domain, \'\'), :main) = :domain';
        $params = $everywhere ? [] : ['main' => Platform::domain(), 'domain' => $domain];
        $rows = [];
        if ($audience === 'clients') {
            $rows = Db::all(
                "SELECT c.contact_email AS email, c.contact_name AS name, c.name AS entity
                 FROM clients c WHERE c.status = 'active' AND c.contact_email <> ''"
                . ($everywhere ? '' : " AND EXISTS (SELECT 1 FROM instances i WHERE i.client_id = c.id
                   AND i.status NOT IN ('cancelled', 'purged') AND $instanceDomain)"),
                $params
            );
        } elseif ($audience === 'instances') {
            $where = ($mailing['instance_status'] ?? 'active') === 'all'
                ? "i.status NOT IN ('cancelled', 'purged')"
                : "i.status = 'active'";
            $rows = Db::all(
                "SELECT i.admin_email AS email, '' AS name, i.site_name AS entity
                 FROM instances i WHERE $where AND i.admin_email IS NOT NULL AND i.admin_email <> ''"
                . ($everywhere ? '' : " AND $instanceDomain"),
                $params
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

        $gone = $withoutUnsubscribed ? Unsubscribe::all() : [];
        $list = [];
        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($list[$email]) || isset($gone[$email])) {
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

    public static function audienceCount(array $mailing): int
    {
        return count(self::audience($mailing));
    }

    /**
     * Quants n'hi ha, i quants n'han quedat fora perquè s'havien donat de baixa.
     *
     * @return array{count:int,unsubscribed:int}
     */
    public static function audienceStats(array $mailing): array
    {
        $all = self::audience($mailing, false);
        $gone = Unsubscribe::all();
        $out = count(array_intersect_key($all, $gone));

        return ['count' => count($all) - $out, 'unsubscribed' => $out];
    }

    // ------------------------------------------------------------------
    // L'enviament
    // ------------------------------------------------------------------

    /**
     * Envia una tanda, des del domini de l'enviament i sense passar del seu
     * límit per hora. Si tota la tanda falla, el posa en pausa amb l'error:
     * vol dir que el servidor de correu no va, i continuar només faria que
     * fallessin tots.
     *
     * @return array{sent:int,failed:int,pending:int,done:bool,waiting:bool}
     */
    public static function sendBatch(int $id, ?int $size = null): array
    {
        if (self::$lock !== null) {
            return self::batch($id, $size);
        }
        $lock = self::lock();
        if ($lock === null) {
            // El cron hi és a mig: ja l'enviarà ell.
            return ['sent' => 0, 'failed' => 0, 'pending' => self::pending($id), 'done' => false, 'waiting' => true];
        }
        try {
            return self::batch($id, $size);
        } finally {
            self::unlock();
        }
    }

    /** @return array{sent:int,failed:int,pending:int,done:bool,waiting:bool} */
    private static function batch(int $id, ?int $size): array
    {
        $mailing = self::find($id, self::MAIL);
        if (!$mailing || $mailing['status'] !== 'sending') {
            return ['sent' => 0, 'failed' => 0, 'pending' => self::pending($id), 'done' => true, 'waiting' => false];
        }
        $domain = self::domain($mailing);
        $size = max(1, $size ?? self::batchSize());
        $rows = Db::all(
            "SELECT * FROM platform_mailing_recipients WHERE mailing_id = :id AND status = 'pending' ORDER BY id ASC LIMIT " . $size,
            ['id' => $id]
        );
        // Els correus per hora del domini són de tothom qui hi envia: s'agafa
        // lloc abans d'enviar, i si no n'hi ha, s'espera.
        $taken = Quota::take($domain, 'panell', count($rows));
        if ($rows && $taken === 0) {
            return ['sent' => 0, 'failed' => 0, 'pending' => self::pending($id), 'done' => false, 'waiting' => true];
        }
        $rows = array_slice($rows, 0, $taken);

        $sent = 0;
        $failed = 0;
        $lastError = '';
        $parts = self::parts($mailing);
        Site::during($domain, static function () use ($rows, $mailing, $parts, &$sent, &$failed, &$lastError): void {
            foreach ($rows as $row) {
                // Una altra passada (el cron i el botó alhora) se l'ha quedat: no s'envia dos cops.
                if (Db::update('platform_mailing_recipients', ['status' => 'sending'], "id = :id AND status = 'pending'", ['id' => (int) $row['id']]) === 0) {
                    continue;
                }
                $ok = false;
                $error = '';
                try {
                    $mail = self::personalize($mailing, $parts, $row);
                    $ok = Mailer::send((string) $row['email'], $mail['subject'], $mail['html'], [
                        'text' => $mail['text'],
                        'headers' => $mail['headers'],
                    ]);
                    $error = $ok ? '' : Mailer::lastError();
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
                $error = $error !== '' ? $error : 'El servidor no ha pogut enviar el correu.';
                Db::update('platform_mailing_recipients', [
                    'status' => $ok ? 'sent' : 'failed',
                    'error' => $ok ? null : mb_substr($error, 0, 500),
                    'sent_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => (int) $row['id']]);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                    $lastError = $error;
                }
            }
        });
        // El lloc que s'havia agafat i no s'ha fet servir (una altra passada
        // se n'havia quedat algun) es torna.
        Quota::giveBack($domain, 'panell', $taken - $sent - $failed);

        $pending = self::pending($id);
        $done = $pending === 0;
        self::recount($id);
        if ($done) {
            Db::update('platform_mailings', ['status' => 'sent', 'finished_at' => date('Y-m-d H:i:s')], "id = :id AND status = 'sending'", ['id' => $id]);
        } elseif ($sent === 0 && $failed >= self::PAUSE_AFTER) {
            self::pause($id, 'S\'ha aturat sol perquè no n\'ha pogut enviar cap: ' . $lastError);
            Platform::log('mailout.paused', 'mailout', $id, ['error' => mb_substr($lastError, 0, 300)]);
        }

        return ['sent' => $sent, 'failed' => $failed, 'pending' => $pending, 'done' => $done, 'waiting' => false];
    }

    /**
     * La feina del cron: comença els programats que ja toquen i envia tandes
     * dels que s'estan enviant fins que s'acaba el temps o el límit per hora.
     *
     * @return array{started:int,sent:int,failed:int,waiting:int,paused:int}
     */
    public static function tick(int $seconds = self::TICK_SECONDS): array
    {
        $out = ['started' => 0, 'sent' => 0, 'failed' => 0, 'waiting' => 0, 'paused' => 0];
        Settings::set('platform_mailout_tick', date('Y-m-d H:i:s'));
        if (self::lock() === null) {
            return $out; // una altra passada encara és en marxa
        }
        try {
            // Els que es van quedar a mig enviar (el procés es va tallar): no se
            // sap si van sortir, i es deixen com a fallits perquè es vegi.
            Db::update('platform_mailing_recipients', [
                'status' => 'failed',
                'error' => 'L\'enviament es va interrompre i no se sap si va arribar.',
                'sent_at' => date('Y-m-d H:i:s'),
            ], "status = 'sending'");
            $due = Db::all(
                "SELECT id FROM platform_mailings WHERE kind = 'mail' AND status = 'scheduled' AND scheduled_at <= :now ORDER BY scheduled_at, id",
                ['now' => date('Y-m-d H:i:s')]
            );
            foreach ($due as $row) {
                try {
                    self::start((int) $row['id']);
                    $out['started']++;
                } catch (\Throwable $e) {
                    // Sense destinataris o sense assumpte: no es pot enviar, i es diu per què.
                    Db::update('platform_mailings', ['status' => 'draft', 'paused_reason' => mb_substr('No s\'ha pogut enviar a l\'hora programada: ' . $e->getMessage(), 0, 500)],
                        'id = :id', ['id' => (int) $row['id']]);
                }
            }

            $until = microtime(true) + max(1, $seconds);
            $blocked = [];
            while (microtime(true) < $until) {
                $ids = array_map('intval', array_column(Db::all(
                    "SELECT id FROM platform_mailings WHERE kind = 'mail' AND status = 'sending' ORDER BY started_at, id"
                ), 'id'));
                $ids = array_values(array_diff($ids, $blocked));
                if (!$ids) {
                    break;
                }
                foreach ($ids as $id) {
                    $r = self::batch($id, null);
                    $out['sent'] += $r['sent'];
                    $out['failed'] += $r['failed'];
                    if ($r['waiting']) {
                        $blocked[] = $id;
                        $out['waiting']++;
                    } elseif (($r['sent'] + $r['failed']) === 0 && !$r['done']) {
                        $blocked[] = $id; // en pausa o sense res per fer
                        $out['paused']++;
                    }
                    if (microtime(true) >= $until) {
                        break;
                    }
                }
            }
        } finally {
            self::unlock();
        }

        return $out;
    }

    /** L'última passada del cron, o '' si no n'hi ha hagut cap. */
    public static function lastTick(): string
    {
        return (string) Settings::get('platform_mailout_tick', '');
    }

    /** L'ordre que posa el cron en aquest servidor (l'ha de fer root). */
    public static function cronCommand(): string
    {
        return 'sudo bash ' . CROS_ROOT . '/tools/instalar-cron.sh';
    }

    /**
     * Cal avisar que el cron no passa? Només si hi ha feina esperant-lo: un
     * programat o un que s'està enviant.
     */
    public static function cronStale(): bool
    {
        try {
            $waiting = (int) Db::val("SELECT COUNT(*) FROM platform_mailings WHERE kind = 'mail' AND status IN ('scheduled', 'sending')", [], 0);
        } catch (\Throwable $e) {
            return false; // encara sense la migració
        }
        if ($waiting === 0) {
            return false;
        }
        $last = strtotime(self::lastTick()) ?: 0;

        return $last < time() - 20 * 60;
    }

    /**
     * Envia una prova a una adreça, amb les dades d'una persona d'exemple.
     */
    public static function sendTest(array $mailing, string $to): bool
    {
        $domain = self::domain($mailing);
        $parts = self::parts($mailing);
        $mail = self::personalize($mailing, $parts, ['email' => $to, 'name' => 'Nom de prova', 'entity' => 'Entitat de prova']);

        return Site::during($domain, static fn (): bool => Mailer::send($to, '[Prova] ' . $mail['subject'], $mail['html'], ['text' => $mail['text']]));
    }

    // ------------------------------------------------------------------
    // El correu
    // ------------------------------------------------------------------

    /** El document d'un enviament. Els d'abans de l'editor passen a un bloc de text. */
    public static function doc(array $mailing): array
    {
        $doc = Doc::decode((string) ($mailing['doc'] ?? ''));
        if ($doc['blocks'] || trim((string) ($mailing['doc'] ?? '')) !== '') {
            return $doc;
        }
        $body = RichText::clean((string) ($mailing['body'] ?? ''));

        return $body !== ''
            ? Doc::make(['header', ['text', ['html' => $body]], 'footer'])
            : self::blankDoc();
    }

    /**
     * El correu muntat, amb els camps personals encara per posar.
     *
     * @return array{html:string,text:string,brand:array<string,string>}
     */
    public static function parts(array $mailing): array
    {
        $domain = self::domain($mailing);
        $doc = self::doc($mailing);
        $brand = Brand::of($domain);
        $renderer = new Renderer(Brand::images($doc, $domain), $brand);

        return [
            'html' => $renderer->html($doc, (string) $mailing['subject'], (string) ($mailing['preheader'] ?? '')),
            'text' => $renderer->text($doc),
            'brand' => $brand,
        ];
    }

    /**
     * El correu d'un destinatari: els camps posats i l'enllaç de baixa seu.
     *
     * @param array{html:string,text:string,brand:array<string,string>} $parts
     * @return array{subject:string,html:string,text:string,headers:array<string,string>}
     */
    public static function personalize(array $mailing, array $parts, array $recipient): array
    {
        $email = (string) ($recipient['email'] ?? '');
        $unsubscribe = Unsubscribe::url($email, $parts['brand']['domain'], (int) ($mailing['id'] ?? 0));
        $values = self::values($parts['brand'], $recipient) + ['{{baixa}}' => $unsubscribe];
        $escaped = array_map(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $values);

        return [
            // A l'assumpte, cap salt de línia (un nom mal desat no pot afegir capçaleres).
            'subject' => mb_substr(trim((string) preg_replace('/[\r\n\t]+/', ' ', strtr((string) $mailing['subject'], $values))), 0, 190),
            'html' => strtr($parts['html'], $escaped),
            'text' => strtr($parts['text'], $values),
            'headers' => [
                'List-Unsubscribe' => '<' . $unsubscribe . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        ];
    }

    /**
     * Els camps personals d'un destinatari, en text pla.
     *
     * @param array<string,string> $brand
     * @return array<string,string>
     */
    public static function values(array $brand, array $recipient = []): array
    {
        return [
            '{{nom}}' => trim((string) ($recipient['name'] ?? '')),
            '{{entitat}}' => trim((string) ($recipient['entity'] ?? '')),
            '{{correu}}' => trim((string) ($recipient['email'] ?? '')),
            '{{web}}' => (string) $brand['domain'],
            '{{plataforma}}' => (string) $brand['name'],
            '{{any}}' => date('Y'),
        ];
    }

    /** L'assumpte d'un destinatari concret: també hi valen els camps. */
    public static function subjectFor(array $mailing, array $recipient): string
    {
        return self::personalize($mailing, self::parts($mailing), $recipient)['subject'];
    }

    /** El correu d'un destinatari, en HTML. */
    public static function render(array $mailing, array $recipient): string
    {
        return self::personalize($mailing, self::parts($mailing), $recipient)['html'];
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

    /** @return array<string,int> quants n'hi ha de cada estat */
    public static function recipientCounts(int $id): array
    {
        $out = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach (Db::all('SELECT status, COUNT(*) AS n FROM platform_mailing_recipients WHERE mailing_id = :id GROUP BY status', ['id' => $id]) as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Plantilles base
    // ------------------------------------------------------------------

    /** La primera vegada que s'obren els enviaments es creen les plantilles base. */
    public static function ensureTemplates(): void
    {
        if ((int) Db::val("SELECT COUNT(*) FROM platform_mailings WHERE kind = 'template'", [], 0) > 0) {
            return;
        }
        foreach (self::starterTemplates() as [$name, $subject, $preheader, $doc]) {
            self::insert([
                'kind' => self::TEMPLATE,
                'template_name' => $name,
                'subject' => $subject,
                'preheader' => $preheader,
                'doc' => Doc::encode($doc),
            ], null, 'Sistema');
        }
    }

    public static function blankDoc(): array
    {
        return Doc::make([
            'header',
            ['heading', ['text' => 'Títol del correu']],
            ['text', ['html' => '<p>Hola, {{nom}}:</p><p>Escriviu aquí el text del correu.</p>']],
            'footer',
        ]);
    }

    /**
     * Les plantilles amb què es comença. No porten cap color ni cap nom
     * escrit: agafen els del domini que envia.
     *
     * @return array<int,array{0:string,1:string,2:string,3:array}>
     */
    public static function starterTemplates(): array
    {
        $sub = static fn (string $t): array => ['heading', ['text' => $t, 'level' => 2, 'size' => 18, 'pt' => 22, 'pb' => 6]];

        return [
            ['Comunicat', 'Un comunicat de {{plataforma}}', 'El que cal que sapigueu, en una línia', Doc::make([
                'header',
                ['heading', ['text' => 'Títol del comunicat', 'pt' => 20, 'pb' => 10]],
                ['text', ['html' => '<p>Hola, {{nom}}:</p><p>Primer paràgraf: què passa i per què us afecta.</p><p>Segon paràgraf amb el detall.</p>']],
                ['button', ['text' => 'Entreu al vostre web', 'href' => 'https://{{web}}']],
                ['text', ['html' => '<p>Si teniu cap dubte, responeu aquest correu i us ajudarem.</p>', 'pt' => 14]],
                'footer',
            ])],
            ['Novetats', 'Novetats de {{plataforma}}', 'El que hi ha de nou aquest mes', Doc::make([
                'header',
                ['heading', ['text' => 'Novetats de {{plataforma}}', 'pt' => 20, 'pb' => 8]],
                ['text', ['html' => '<p>Hola, {{nom}}:</p><p>Aquest mes hem afegit coses noves al vostre web. Us les expliquem en dos minuts.</p>']],
                ['news', ['kicker' => 'Novetat', 'title' => 'Una novetat de la plataforma']],
                ['news', ['kicker' => 'Millora', 'title' => 'Una altra millora', 'btnText' => 'Més informació ›']],
                $sub('Curses que ja s\'hi han apuntat'),
                ['race', []],
                'footer',
            ])],
            ['Recordatori', 'Recordatori: {{entitat}}', 'Un recordatori abans de la cursa', Doc::make([
                'header',
                ['heading', ['text' => 'Ho teniu tot a punt?', 'pt' => 20, 'pb' => 10]],
                ['text', ['html' => '<p>Hola, {{nom}}:</p><p>Falten pocs dies. Abans de la cursa, repasseu això:</p>']],
                ['box', ['html' => '<ul><li>Les inscripcions obertes i el límit de places.</li><li>Els horaris i el recorregut publicats.</li><li>Els dorsals preparats per imprimir.</li></ul>']],
                ['button', ['text' => 'Entreu al vostre web', 'href' => 'https://{{web}}']],
                'footer',
            ])],
        ];
    }

    // ------------------------------------------------------------------

    private static function insert(array $fields, ?int $userId, string $userName): int
    {
        return (int) Db::transaction(static function () use ($fields, $userId, $userName): int {
            $id = Db::insert('platform_mailings', $fields + [
                'kind' => self::MAIL,
                'subject' => '',
                'audience' => 'clients',
                'instance_status' => 'active',
                'status' => 'draft',
                'version' => 1,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            self::storeVersion((array) self::find($id), $userId, $userName);

            return $id;
        });
    }

    private static function storeVersion(array $mailing, ?int $userId, string $userName): void
    {
        Db::insert('platform_mailing_versions', [
            'mailing_id' => (int) $mailing['id'],
            'version' => (int) $mailing['version'],
            'subject' => (string) $mailing['subject'],
            'preheader' => $mailing['preheader'],
            'doc' => $mailing['doc'],
            'saved_by' => $userId,
            'saved_by_name' => $userName !== '' ? mb_substr($userName, 0, 150) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function pending(int $id): int
    {
        return (int) Db::val(
            "SELECT COUNT(*) FROM platform_mailing_recipients WHERE mailing_id = :id AND status IN ('pending', 'sending')",
            ['id' => $id],
            0
        );
    }

    private static function recount(int $id): void
    {
        $c = self::recipientCounts($id);
        Db::update('platform_mailings', [
            'sent' => $c['sent'],
            'failed' => $c['failed'],
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    /** @var resource|null el bloqueig, mentre se'n té */
    private static $lock = null;

    /**
     * Un sol enviament alhora: si una passada del cron s'allarga, la següent
     * no l'encavalca, i el botó «Envia una tanda» no envia el que ja envia el cron.
     *
     * @return resource|null
     */
    private static function lock()
    {
        $dir = CROS_STORAGE;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fp = @fopen($dir . '/enviaments.lock', 'c');
        if ($fp === false) {
            return null;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);

            return null;
        }

        return self::$lock = $fp;
    }

    private static function unlock(): void
    {
        if (self::$lock !== null) {
            flock(self::$lock, LOCK_UN);
            fclose(self::$lock);
            self::$lock = null;
        }
    }

    private static function subject(string $subject): string
    {
        return mb_substr(Doc::plain($subject, 190), 0, 190);
    }

    private static function name(string $name, string $fallback): string
    {
        $name = Doc::plain($name, 190);

        return $name !== '' ? $name : $fallback;
    }
}
