<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;

/**
 * Llistes de correu de la plataforma.
 *
 * Serveixen per escriure a gent que no és clienta de res: l'associació de
 * mestres d'una comarca, els contactes d'una fira, les escoles a qui es vol
 * ensenyar el servei. Els clients i les instàncies ja se saben d'on són i no
 * cal apuntar-los enlloc.
 */
class MailList
{
    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        return Db::all(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM mail_contacts c WHERE c.list_id = l.id) AS contacts,
                    (SELECT COUNT(*) FROM mail_contacts c WHERE c.list_id = l.id AND c.active = 1) AS active_contacts
             FROM mail_lists l ORDER BY l.name ASC'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM mail_lists WHERE id = :id', ['id' => $id]);
    }

    public static function save(int $id, string $name, string $description = ''): int
    {
        $fields = [
            'name' => mb_substr(trim($name), 0, 150),
            'description' => mb_substr(trim($description), 0, 255) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            Db::update('mail_lists', $fields, 'id = :id', ['id' => $id]);

            return $id;
        }
        $fields['created_at'] = date('Y-m-d H:i:s');

        return Db::insert('mail_lists', $fields);
    }

    public static function delete(int $id): void
    {
        Db::delete('mail_contacts', 'list_id = :id', ['id' => $id]);
        Db::delete('mail_lists', 'id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function contacts(int $listId, bool $onlyActive = false): array
    {
        return Db::all(
            'SELECT * FROM mail_contacts WHERE list_id = :id' . ($onlyActive ? ' AND active = 1' : '')
            . ' ORDER BY (entity IS NULL OR entity = \'\') ASC, entity ASC, name ASC, email ASC',
            ['id' => $listId]
        );
    }

    /**
     * Afegeix contactes a una llista a partir del que s'ha enganxat al
     * formulari, una adreça per línia.
     *
     * S'accepten les tres maneres com la gent té les adreces apuntades:
     * l'adreça sola, la forma «Nom Cognom <adreça>» que surt del correu, i
     * columnes separades per comes, punts i comes o tabuladors (el que dona un
     * full de càlcul). De les columnes, la que sigui una adreça és l'adreça, i
     * les altres dues són el nom i l'entitat, en aquest ordre.
     *
     * @return array{added:int,updated:int,skipped:array<int,string>}
     */
    public static function addContacts(int $listId, string $raw): array
    {
        $added = 0;
        $updated = 0;
        $skipped = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $contact = self::parse($line);
            if ($contact === null) {
                $skipped[] = mb_substr($line, 0, 80);
                continue;
            }
            $existing = Db::one(
                'SELECT id, name, entity FROM mail_contacts WHERE list_id = :list AND email = :email',
                ['list' => $listId, 'email' => $contact['email']]
            );
            if ($existing) {
                // Una adreça repetida no es duplica: s'aprofita per completar
                // el que hi falti, que sol ser el nom.
                $fields = [];
                if ($contact['name'] !== '' && trim((string) $existing['name']) === '') {
                    $fields['name'] = $contact['name'];
                }
                if ($contact['entity'] !== '' && trim((string) $existing['entity']) === '') {
                    $fields['entity'] = $contact['entity'];
                }
                if ($fields) {
                    Db::update('mail_contacts', $fields, 'id = :id', ['id' => (int) $existing['id']]);
                    $updated++;
                }
                continue;
            }
            Db::insert('mail_contacts', [
                'list_id' => $listId,
                'email' => $contact['email'],
                'name' => $contact['name'] ?: null,
                'entity' => $contact['entity'] ?: null,
                'active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $added++;
        }

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * Una línia enganxada, convertida en contacte.
     * @return array{email:string,name:string,entity:string}|null
     */
    public static function parse(string $line): ?array
    {
        $line = trim($line);
        if (preg_match('/^(.*?)<\s*([^>]+?)\s*>$/u', $line, $m)) {
            $email = self::email($m[2]);

            return $email === '' ? null
                : ['email' => $email, 'name' => self::clean($m[1]), 'entity' => ''];
        }

        $parts = array_map('trim', preg_split('/\s*[;,\t]\s*/', $line) ?: []);
        $email = '';
        $rest = [];
        foreach ($parts as $part) {
            if ($email === '' && self::email($part) !== '') {
                $email = self::email($part);
                continue;
            }
            if ($part !== '') {
                $rest[] = $part;
            }
        }
        if ($email === '') {
            return null;
        }

        return [
            'email' => $email,
            'name' => self::clean($rest[0] ?? ''),
            'entity' => self::clean($rest[1] ?? '', 190),
        ];
    }

    public static function removeContact(int $id): void
    {
        Db::delete('mail_contacts', 'id = :id', ['id' => $id]);
    }

    /** Dona de baixa un contacte sense esborrar-lo, o el torna a donar d'alta. */
    public static function toggleContact(int $id): void
    {
        Db::q('UPDATE mail_contacts SET active = 1 - active WHERE id = :id', ['id' => $id]);
    }

    private static function email(string $value): string
    {
        $value = mb_strtolower(trim($value, " \t\n\r\0\x0B<>\"'"));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? mb_substr($value, 0, 190) : '';
    }

    private static function clean(string $value, int $length = 150): string
    {
        return mb_substr(trim($value, " \t\n\r\"'"), 0, $length);
    }
}
