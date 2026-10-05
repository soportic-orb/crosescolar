<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Settings;
use RuntimeException;

/**
 * El registre lliure: qui vol un web se'l fa ell mateix.
 *
 * Abans calia demanar-lo i esperar que algú digués que sí. Ara no: s'omple un
 * formulari curt i el web queda fet al moment, amb el seu panell i tot. No hi
 * ha res a perdre-hi, perquè el web neix amagat i no es paga fins al dia que
 * es vol fer públic.
 *
 * Qui fa de porta és el correu. L'única manera d'entrar al panell acabat de
 * crear és l'enllaç que s'hi envia, de manera que prémer-lo és alhora entrar
 * i demostrar que l'adreça és de qui l'ha escrita.
 */
final class Signup
{
    /** Quantes altes pot fer una mateixa adreça en un dia. */
    private const PER_DAY = 3;

    /** I quantes en pot fer una mateixa connexió. */
    private const PER_DAY_IP = 5;

    /** Es poden fer altes ara mateix? */
    public static function open(): bool
    {
        return Settings::bool('platform_requests_open', true);
    }

    /**
     * Mira les dades del formulari i torna els errors que hi hagi.
     *
     * @param array<string,string> $data
     * @return array<string,string>
     */
    public static function check(array $data): array
    {
        $errors = [];
        if (trim($data['site_name'] ?? '') === '') {
            $errors['site_name'] = 'Digueu com es diu.';
        } elseif (mb_strlen($data['site_name']) > 190) {
            $errors['site_name'] = 'El nom és massa llarg.';
        }
        if (trim($data['admin_name'] ?? '') === '') {
            $errors['admin_name'] = 'Digueu-nos el vostre nom.';
        }
        $email = mb_strtolower(trim($data['admin_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['admin_email'] = 'Cal una adreça de correu que funcioni: hi enviarem l\'accés.';
        }
        $problem = Instance::slugProblem((string) ($data['slug'] ?? ''));
        if ($problem !== '') {
            $errors['slug'] = $problem;
        }
        if (!isset(Client::KINDS[(string) ($data['client_kind'] ?? '')])) {
            $errors['client_kind'] = 'Digueu-nos si sou una entitat o un particular.';
        }
        if (empty($data['consent'])) {
            $errors['consent'] = 'Cal acceptar les condicions per continuar.';
        }

        return $errors;
    }

    /** Aquesta adreça (o aquesta connexió) ja n'ha fet prou per avui? */
    public static function tooMany(string $email): bool
    {
        $since = date('Y-m-d H:i:s', time() - 86400);
        $perEmail = (int) Db::val(
            "SELECT COUNT(*) FROM instances WHERE source = 'signup' AND LOWER(admin_email) = :email AND created_at > :since",
            ['email' => mb_strtolower(trim($email)), 'since' => $since],
            0
        );
        if ($perEmail >= self::PER_DAY) {
            return true;
        }
        $perIp = (int) Db::val(
            "SELECT COUNT(*) FROM platform_activity WHERE action = 'signup' AND ip = :ip AND created_at > :since",
            ['ip' => client_ip(), 'since' => $since],
            0
        );

        return $perIp >= self::PER_DAY_IP;
    }

    /**
     * Crea el web i n'envia l'accés. Torna l'adreça i el subdomini.
     *
     * @param array<string,string> $data
     * @return array{slug:string,url:string,email:string,instance_id:int}
     */
    public static function create(array $data, ?string $root = null): array
    {
        $root = $root ?? CROS_ROOT;
        $slug = mb_strtolower(trim((string) $data['slug']));
        $domain = Platform::validDomain((string) ($data['domain'] ?? ''), $root);
        $email = mb_strtolower(trim((string) $data['admin_email']));

        $client = Client::byEmail($email);
        $clientId = $client
            ? (int) $client['id']
            : Client::create([
                'name' => (string) ($data['entity'] ?? $data['site_name']),
                'kind' => (string) ($data['client_kind'] ?? 'company'),
                'town' => (string) ($data['town'] ?? ''),
                'contact_name' => (string) $data['admin_name'],
                'contact_email' => $email,
                'notes' => 'Alta feta des del web, sense passar pel panell.',
            ]);

        // Muntar un web sencer (base de dades, carpetes i instal·lació) pot
        // trigar més que una pàgina normal.
        @set_time_limit(300);
        $result = Provisioner::create([
            'slug' => $slug,
            'domain' => $domain,
            'site_name' => (string) $data['site_name'],
            'town' => (string) ($data['town'] ?? ''),
            'language' => 'ca',
            'event_date' => (string) ($data['event_date'] ?? ''),
            'admin_name' => (string) $data['admin_name'],
            'admin_email' => $email,
            'client_id' => $clientId,
            'listed' => true,
            'source' => 'signup',
        ], $root);

        Platform::log('signup', 'instance', (int) $result['instance_id'], [
            'slug' => $slug,
            'domini' => $domain,
            'correu' => $email,
        ]);

        return [
            'slug' => $slug,
            'url' => Platform::url($slug, $root, $domain),
            'email' => $email,
            'instance_id' => (int) $result['instance_id'],
        ];
    }

    /**
     * Apunta que una adreça ha quedat validada.
     * Ho demana el web del client quan algú fa servir l'enllaç d'estrena.
     */
    public static function verify(string $slug): bool
    {
        $instance = Instance::bySlug($slug);
        if (!$instance || !empty($instance['verified_at'])) {
            return false;
        }
        Instance::update((int) $instance['id'], ['verified_at' => date('Y-m-d H:i:s')]);
        Platform::log('signup_verified', 'instance', (int) $instance['id'], ['slug' => $slug]);

        return true;
    }

    /** Un subdomini a partir del nom que hagin escrit. */
    public static function suggest(string $name): string
    {
        return mb_substr((string) preg_replace('/[^a-z0-9]+/', '', self::plain($name)), 0, 30);
    }

    /** En minúscules i sense accents, que és com van les adreces. */
    private static function plain(string $text): string
    {
        return strtr(mb_strtolower(trim($text)), [
            'à' => 'a', 'á' => 'a', 'ä' => 'a', 'â' => 'a', 'è' => 'e', 'é' => 'e', 'ë' => 'e',
            'ê' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ò' => 'o', 'ó' => 'o',
            'ö' => 'o', 'ô' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ç' => 'c',
            'ñ' => 'n', '·' => '',
        ]);
    }

    /**
     * Una adreça lliure que s'assembli a la que es volia.
     *
     * Es fa servir quan la que s'ha escrit ja és d'un altre: primer amb el
     * número darrere (-2, -3…) i, si totes són agafades, amb l'any. Torna ''
     * si no se'n troba cap, cosa que vol dir que la base no val.
     */
    public static function alternative(string $slug): string
    {
        // El que no sigui lletra o número fa de guió, com en una adreça bona.
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', self::plain($slug)), '-');
        if ($base === '') {
            return '';
        }
        // Si només fallava la manera d'escriure-la, ja n'hi ha prou d'arreglar-la.
        if ($base !== mb_strtolower(trim($slug)) && Instance::slugProblem($base) === '') {
            return $base;
        }
        $tails = [];
        for ($n = 2; $n <= 9; $n++) {
            $tails[] = '-' . $n;
        }
        $tails[] = '-' . date('Y');
        foreach ($tails as $tail) {
            $candidate = rtrim(mb_substr($base, 0, 30 - strlen($tail)), '-') . $tail;
            if (Instance::slugProblem($candidate) === '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Què passa amb una adreça: si es pot fer servir i, si no, per què i quina
     * altra s'hi podria posar. És el que consulta el formulari mentre s'escriu.
     *
     * @return array{slug:string,ok:bool,message:string,alternative:string}
     */
    public static function availability(string $slug): array
    {
        $slug = mb_strtolower(trim($slug));
        $problem = Instance::slugProblem($slug);

        return [
            'slug' => $slug,
            'ok' => $problem === '',
            'message' => $problem,
            'alternative' => $problem === '' ? '' : self::alternative($slug),
        ];
    }
}
