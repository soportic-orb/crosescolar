<?php
declare(strict_types=1);

namespace Cros\Models;

use Cros\Core\Db;
use Cros\Core\Mailer;

/** Inscripcions a les curses. */
class Registration
{
    /** Opcions de gènere admeses als formularis. */
    public const GENDERS = ['femeni' => 'Femení', 'masculi' => 'Masculí'];

    /** Cursos de l'escola, en l'ordre en què es mostren als formularis. */
    public const COURSES = [
        'Infantil 1er',
        'Infantil 2on',
        'Infantil 3r',
        'Primària 1er',
        'Primària 2on',
        'Primària 3r',
        'Primària 4rt',
        'Primària 5è',
        'Primària 6è',
    ];

    public static function create(array $data): array
    {
        $id = self::insert([
            'code' => self::generateCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_year' => $data['birth_year'] !== '' ? (int) $data['birth_year'] : null,
            'gender' => $data['gender'] ?: null,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'school' => $data['school'] ?: null,
            'class_group' => $data['class_group'] ?: null,
            'tutor_name' => $data['tutor_name'] ?: null,
            'tutor_email' => $data['tutor_email'] ? mb_strtolower($data['tutor_email']) : null,
            'tutor_phone' => $data['tutor_phone'] ?: null,
            'shirt_size' => $data['shirt_size'] ?: null,
            'notes' => $data['notes'] ?: null,
            'status' => 'confirmed',
            'consent_data' => (int) ($data['consent_data'] ?? 0),
            'consent_image' => (int) ($data['consent_image'] ?? 0),
            'ip' => client_ip(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $registration = self::find($id) ?? [];
        self::notify($registration);
        return $registration;
    }

    /**
     * Desa una inscripció nova assignant-li dorsal i enllaç privat si no en porta.
     * Si dues inscripcions arriben alhora i es barallen pel mateix número, es
     * torna a provar amb el següent lliure.
     */
    public static function insert(array $data): int
    {
        $auto = empty($data['bib_number']);
        if (empty($data['token'])) {
            $data['token'] = random_token(16);
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            if ($auto) {
                $data['bib_number'] = self::nextBib();
            }
            try {
                return Db::insert('registrations', $data);
            } catch (\PDOException $e) {
                if (!$auto || !self::isDuplicateBib($e)) {
                    throw $e;
                }
            }
        }
        throw new \RuntimeException('No s\'ha pogut assignar cap número de dorsal lliure.');
    }

    /** Següent número de dorsal lliure (comença per 1). */
    public static function nextBib(): int
    {
        return (int) Db::val('SELECT COALESCE(MAX(bib_number), 0) FROM registrations', [], 0) + 1;
    }

    /** Hi ha cap altre participant amb aquest dorsal? */
    public static function bibTaken(int $bib, ?int $exceptId = null): bool
    {
        if ($bib <= 0) {
            return false;
        }
        $sql = 'SELECT 1 FROM registrations WHERE bib_number = :bib';
        $params = ['bib' => $bib];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }
        return (bool) Db::val($sql, $params);
    }

    private static function isDuplicateBib(\PDOException $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed')
            || $e->getCode() === '23000';
    }

    /** Assigna dorsal i codi d'accés a les inscripcions que no en tinguin. */
    public static function assignMissing(): int
    {
        $rows = Db::all('SELECT id, bib_number, token FROM registrations WHERE bib_number IS NULL OR token IS NULL ORDER BY created_at ASC, id ASC');
        $next = self::nextBib();
        $count = 0;
        foreach ($rows as $row) {
            $data = [];
            if ($row['bib_number'] === null) {
                $data['bib_number'] = $next++;
            }
            if (empty($row['token'])) {
                $data['token'] = random_token(16);
            }
            if ($data) {
                Db::update('registrations', $data, 'id = :id', ['id' => $row['id']]);
                $count++;
            }
        }
        return $count;
    }

    /** Inscripció a partir del codi d'accés privat que s'envia per correu. */
    public static function findByToken(string $token): ?array
    {
        if (strlen($token) < 16) {
            return null;
        }
        return Db::one(
            'SELECT r.*, c.name AS category_name FROM registrations r
             LEFT JOIN categories c ON c.id = r.category_id WHERE r.token = :token',
            ['token' => $token]
        );
    }

    /** Inscripció pel número de dorsal. */
    public static function findByBib(int $bib): ?array
    {
        return Db::one(
            'SELECT r.*, c.name AS category_name FROM registrations r
             LEFT JOIN categories c ON c.id = r.category_id WHERE r.bib_number = :bib',
            ['bib' => $bib]
        );
    }

    /** Totes les inscripcions fetes amb la mateixa adreça de contacte. */
    public static function forEmail(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return [];
        }
        return Db::all(
            'SELECT r.*, c.name AS category_name FROM registrations r
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE LOWER(r.tutor_email) = :email ORDER BY r.bib_number ASC, r.id ASC',
            ['email' => $email]
        );
    }

    /** Inscripció concreta d'una adreça de contacte (per a «Les meves inscripcions»). */
    public static function findForEmail(int $id, string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        return Db::one(
            'SELECT r.*, c.name AS category_name FROM registrations r
             LEFT JOIN categories c ON c.id = r.category_id
             WHERE r.id = :id AND LOWER(r.tutor_email) = :email',
            ['id' => $id, 'email' => $email]
        );
    }

    /** Camps que la família pot canviar des del web. */
    public const EDITABLE = [
        'first_name', 'last_name', 'birth_year', 'gender', 'school', 'class_group',
        'shirt_size', 'tutor_name', 'tutor_phone', 'notes', 'consent_image',
    ];

    /**
     * Desa els canvis que la família fa des de «Les meves inscripcions».
     * Només toca els camps de self::EDITABLE i només si la inscripció és seva.
     */
    public static function updateForEmail(int $id, string $email, array $data): ?array
    {
        $current = self::findForEmail($id, $email);
        if (!$current) {
            return null;
        }
        $changes = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'birth_year' => $data['birth_year'] !== '' ? (int) $data['birth_year'] : null,
            'gender' => $data['gender'] ?: null,
            'school' => $data['school'] ?: null,
            'class_group' => $data['class_group'] ?: null,
            'shirt_size' => $data['shirt_size'] ?: null,
            'tutor_name' => $data['tutor_name'] ?: null,
            'tutor_phone' => $data['tutor_phone'] ?: null,
            'notes' => $data['notes'] ?: null,
            'consent_image' => (int) ($data['consent_image'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        // Si canvia l'any de naixement, la categoria es torna a calcular.
        if ((int) $current['birth_year'] !== (int) $changes['birth_year'] && $changes['birth_year'] !== null) {
            $category = self::categoryForYear((int) $changes['birth_year']);
            $changes['category_id'] = $category['id'] ?? null;
        }
        Db::update('registrations', $changes, 'id = :id', ['id' => $id]);
        return self::find($id);
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT r.*, c.name AS category_name FROM registrations r
             LEFT JOIN categories c ON c.id = r.category_id WHERE r.id = :id',
            ['id' => $id]
        );
    }

    /** Categoria suggerida segons l'any de naixement. */
    public static function categoryForYear(int $year): ?array
    {
        $categories = Db::all(
            'SELECT * FROM categories WHERE active = 1 AND year_from IS NOT NULL AND year_to IS NOT NULL
             ORDER BY sort_order ASC'
        );
        foreach ($categories as $category) {
            $from = min((int) $category['year_from'], (int) $category['year_to']);
            $to = max((int) $category['year_from'], (int) $category['year_to']);
            if ($year >= $from && $year <= $to) {
                return $category;
            }
        }
        return null;
    }

    /** Envia la confirmació a la família i l'avís a l'organització. */
    private static function notify(array $registration): void
    {
        if (!$registration) {
            return;
        }
        $email = (string) ($registration['tutor_email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Mailer::sendTemplate($email, 'Inscripció confirmada — ' . setting('site_name', 'Cros Escolar La Granada'), 'registration-confirmation', [
                'registration' => $registration,
                'siblings' => count(self::forEmail($email)),
            ]);
        }
        $notify = (string) setting('mail_admin_notify', '');
        if (setting('registrations_notify', '1') === '1' && $notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            Mailer::sendTemplate($notify, 'Nova inscripció: ' . $registration['first_name'] . ' ' . $registration['last_name'], 'registration-admin', [
                'registration' => $registration,
            ]);
        }
    }

    public static function generateCode(): string
    {
        do {
            $code = 'IN' . date('y') . '-' . random_code(5);
        } while (Db::val('SELECT 1 FROM registrations WHERE code = :code', ['code' => $code]));
        return $code;
    }

    /** Files per exportar a CSV. */
    public static function exportRows(): array
    {
        return Db::all(
            'SELECT r.bib_number, r.code, r.first_name, r.last_name, r.birth_year, r.gender, c.name AS category,
                    r.school, r.class_group, r.tutor_name, r.tutor_email, r.tutor_phone, r.shirt_size,
                    r.notes, r.status, r.consent_data, r.consent_image, r.created_at
             FROM registrations r LEFT JOIN categories c ON c.id = r.category_id
             ORDER BY r.created_at ASC'
        );
    }
}
