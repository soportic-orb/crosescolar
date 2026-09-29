<?php
declare(strict_types=1);

namespace Cros\Platform;

use Cros\Core\Settings;

/**
 * La plantilla dels correus de la plataforma: la capçalera i el peu que
 * emboliquen el que s'escriu a cada enviament.
 *
 * El marc —la taula que fa que el correu es vegi centrat i s'adapti al mòbil—
 * no s'edita. És a posta: el codi d'un correu que es vegi bé a l'Outlook, al
 * Gmail i a l'iPhone és d'una altra època i no s'assembla gens a una pàgina
 * web, i n'hi ha prou amb una etiqueta mal tancada perquè mig món el vegi
 * escapçat. El que sí que s'edita és el de dins: el que hi ha a dalt (el
 * logotip, el nom, un lema) i el que hi ha a baix (qui som, com donar-se de
 * baixa, l'adreça postal).
 */
class MailTemplate
{
    /** Marcadors que valen tant a la plantilla com al cos del correu. */
    public const PLACEHOLDERS = [
        '{{nom}}' => 'Nom del destinatari',
        '{{entitat}}' => 'Entitat o escola',
        '{{correu}}' => 'La seva adreça electrònica',
        '{{assumpte}}' => 'Assumpte del correu',
        '{{plataforma}}' => 'Nom del servei',
        '{{web}}' => 'Adreça del web',
        '{{any}}' => 'Any actual',
    ];

    /** La capçalera que hi ha ara, o la de partida si no se n'ha escrit cap. */
    public static function header(): string
    {
        $saved = trim((string) Settings::get('platform_mail_header', ''));

        return $saved !== '' ? $saved : self::defaultHeader();
    }

    public static function footer(): string
    {
        $saved = trim((string) Settings::get('platform_mail_footer', ''));

        return $saved !== '' ? $saved : self::defaultFooter();
    }

    /**
     * El correu sencer, a punt d'enviar.
     *
     * @param array{name?:string,entity?:string,email?:string} $recipient
     */
    public static function render(string $body, string $subject = '', array $recipient = []): string
    {
        $values = self::values($subject, $recipient);
        $header = strtr(self::header(), $values);
        $footer = strtr(self::footer(), $values);
        $content = strtr($body, $values);

        return self::frame($header, $content, $footer, $subject);
    }

    /**
     * @param array<string,mixed> $recipient
     * @return array<string,string>
     */
    public static function values(string $subject, array $recipient = []): array
    {
        $domain = Platform::domain();

        return [
            '{{nom}}' => e(trim((string) ($recipient['name'] ?? ''))),
            '{{entitat}}' => e(trim((string) ($recipient['entity'] ?? ''))),
            '{{correu}}' => e(trim((string) ($recipient['email'] ?? ''))),
            '{{assumpte}}' => e($subject),
            '{{plataforma}}' => e((string) Settings::get('site_name', 'EsportWeb')),
            '{{web}}' => e($domain),
            '{{any}}' => date('Y'),
        ];
    }

    /**
     * El marc de sempre. No s'edita: {@see la nota de dalt}.
     */
    private static function frame(string $header, string $body, string $footer, string $subject): string
    {
        $title = e($subject !== '' ? $subject : (string) Settings::get('site_name', 'EsportWeb'));

        return '<!doctype html>' . "\n"
            . '<html lang="ca"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . '</title></head>' . "\n"
            . '<body style="margin:0;padding:0;background:#f2f7ef;font-family:\'Segoe UI\',system-ui,Arial,sans-serif;color:#17261c">' . "\n"
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f7ef;padding:24px 12px">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(18,48,28,.08)">'
            . '<tr><td>' . $header . '</td></tr>'
            . '<tr><td style="padding:28px 28px 12px;font-size:15px;line-height:1.6">' . $body . '</td></tr>'
            . '<tr><td>' . $footer . '</td></tr>'
            . '</table></td></tr></table>' . "\n"
            . '</body></html>';
    }

    /** La capçalera de partida: el nom del servei sobre el color de la casa. */
    public static function defaultHeader(): string
    {
        $color = (string) Settings::get('color_primary', '#1f4f5f');
        $logo = trim((string) Settings::get('platform_logo', ''));
        $image = $logo !== ''
            ? '<img src="' . e(upload_url($logo)) . '" alt="" width="40" style="display:block;margin:0 0 10px;max-width:40px;height:auto">'
            : '';

        return '<div style="background:' . e($color) . ';padding:24px 28px;color:#ffffff">'
            . $image
            . '<div style="font-size:20px;font-weight:700">{{plataforma}}</div>'
            . '<div style="font-size:13px;opacity:.85">{{web}}</div>'
            . '</div>';
    }

    /** El peu de partida: qui som i com trobar-nos. */
    public static function defaultFooter(): string
    {
        $color = (string) Settings::get('color_primary', '#1f4f5f');
        $entity = trim((string) Settings::get('platform_legal_entity', ''));
        $who = $entity !== '' ? e($entity) : '{{plataforma}}';

        return '<div style="padding:18px 28px 26px;font-size:12px;line-height:1.6;color:#5a6b60;border-top:1px solid #e3eade">'
            . 'Rebeu aquest correu perquè teniu —o heu demanat— un web de cros a {{plataforma}}.<br>'
            . $who . ' · <a href="https://{{web}}" style="color:' . e($color) . '">{{web}}</a><br>'
            . '© {{any}} {{plataforma}}'
            . '</div>';
    }
}
