<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Db;
use Cros\Core\Mailer;
use Cros\Core\Settings;
use Cros\Core\Tenancy;

/**
 * Vigilància de les instàncies.
 *
 * Passa per cada web de client i mira que la seva base de dades respongui i,
 * si es demana, que la pàgina s'obri. Quan una instància cau —o quan torna—
 * s'envia un avís, una sola vegada: ningú no vol el mateix correu cada quart.
 */
class Health
{
    /** Estats possibles. */
    public const OK = 'ok';
    public const ERROR = 'error';

    /**
     * Mira una instància i diu com està.
     *
     * @return array{ok:bool,error:string}
     */
    public static function check(array $instance, ?string $root = null, bool $web = true, string $proxy = ''): array
    {
        $root = $root ?? CROS_ROOT;
        $dir = Tenancy::dir($root, (string) $instance['slug']);
        if ($dir === '' || !is_file($dir . '/config.php')) {
            return ['ok' => false, 'error' => 'No hi ha la carpeta de la instància.'];
        }
        if (is_file($dir . '/' . Tenancy::SUSPENDED)) {
            return ['ok' => false, 'error' => 'El web està aturat.'];
        }

        $config = require $dir . '/config.php';
        $platform = Db::connection();
        try {
            $pdo = Db::connect((array) ($config['db'] ?? []) + ['charset' => 'utf8mb4', 'timeout' => 8]);
            $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
            $pdo = null;
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Base de dades: ' . mb_substr($e->getMessage(), 0, 180)];
        } finally {
            Db::setConnection($platform);
        }

        if ($web) {
            $problem = self::web(
                (string) ($config['base_url'] ?? Instance::url($instance, $root)),
                $proxy
            );
            if ($problem !== '') {
                return ['ok' => false, 'error' => $problem];
            }
        }

        return ['ok' => true, 'error' => ''];
    }

    /** Obre la pàgina del cros i mira que contesti. */
    private static function web(string $url, string $proxy = ''): string
    {
        if (!function_exists('curl_init')) {
            return '';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'CrosEscolar/' . app_version() . ' (vigilància)',
            // Els webs dels clients són els d'aquest mateix servidor: no s'hi
            // va a buscar cap intermediari encara que n'hi hagi un a l'entorn.
            CURLOPT_PROXY => $proxy,
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = (string) curl_error($ch);
        curl_close($ch);

        if ($status === 0) {
            return 'El web no respon' . ($error !== '' ? ': ' . mb_substr($error, 0, 120) : '.');
        }
        if ($status >= 500) {
            return 'El web contesta amb un error ' . $status . '.';
        }

        return '';
    }

    /**
     * Repassa totes les instàncies en marxa i avisa dels canvis.
     *
     * @return array{checked:int,failing:array<int,string>,recovered:array<int,string>,broke:array<int,string>}
     */
    public static function run(?string $root = null, ?bool $web = null): array
    {
        $root = $root ?? CROS_ROOT;
        // Ho mana el panell; si no s'hi ha tocat mai, el fitxer de la plataforma.
        $settings = (array) (Tenancy::settings($root)['monitor'] ?? []);
        $web = $web ?? Settings::bool('platform_monitor_web', (bool) ($settings['web'] ?? true));
        $proxy = (string) ($settings['proxy'] ?? '');

        $report = ['checked' => 0, 'failing' => [], 'recovered' => [], 'broke' => []];
        foreach (Instance::all() as $instance) {
            if (!in_array((string) $instance['status'], ['new', 'active'], true)) {
                continue;
            }
            $report['checked']++;
            $result = self::check($instance, $root, $web, $proxy);
            $was = (string) ($instance['health'] ?? '');
            $now = $result['ok'] ? self::OK : self::ERROR;

            Instance::update((int) $instance['id'], [
                'health' => $now,
                'health_error' => $result['error'] !== '' ? mb_substr($result['error'], 0, 255) : null,
                'health_checked_at' => date('Y-m-d H:i:s'),
                'health_since' => $was === $now && !empty($instance['health_since'])
                    ? $instance['health_since']
                    : date('Y-m-d H:i:s'),
            ]);

            if (!$result['ok']) {
                $report['failing'][] = (string) $instance['slug'];
            }
            // Només s'avisa quan canvia: ni silenci quan cau, ni un correu cada volta.
            if ($now === self::ERROR && $was !== self::ERROR) {
                $report['broke'][] = (string) $instance['slug'];
                self::notify($instance, $result['error'], false, $root);
            } elseif ($now === self::OK && $was === self::ERROR) {
                $report['recovered'][] = (string) $instance['slug'];
                self::notify($instance, '', true, $root);
            }
        }

        return $report;
    }

    /** Avisa la superadministració que una instància ha caigut o ha tornat. */
    private static function notify(array $instance, string $error, bool $recovered, string $root): void
    {
        $to = Platform::notifyEmail($root);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $subject = ($recovered ? 'Torna a funcionar: ' : 'No respon: ') . $instance['slug'];
        Platform::log($recovered ? 'health_ok' : 'health_error', 'instance', (int) $instance['id'], [
            'slug' => $instance['slug'],
            'error' => $error,
        ]);
        log_line('platform', $subject, ['slug' => $instance['slug'], 'error' => $error]);
        Mailer::sendTemplate($to, $subject, 'instance-health', [
            'instance' => $instance,
            'error' => $error,
            'recovered' => $recovered,
            'url' => Instance::url($instance, $root),
        ]);
    }
}
