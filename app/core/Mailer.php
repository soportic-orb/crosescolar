<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Enviament de correu (funció mail() de PHP o SMTP autenticat).
 * No requereix cap llibreria externa.
 */
class Mailer
{
    /** Per què ha fallat l'últim enviament, o '' si ha anat bé. */
    private static string $lastError = '';

    /**
     * Com envia la plataforma, un cop llegit: una tanda d'enviaments no ha
     * d'obrir una connexió a la plataforma per a cada correu.
     *
     * @var array<string,mixed>|null|false  false vol dir que encara no s'ha mirat
     */
    private static $platformMail = false;

    /** Per què no ha sortit l'últim correu, per poder-ho dir a qui ho ha demanat. */
    public static function lastError(): string
    {
        return self::$lastError;
    }

    /**
     * Envia un correu HTML.
     * @param array{reply_to?:string,text?:string,attachments?:array<int,array{name:string,content:string,type:string}>,bcc?:string} $options
     */
    public static function send(string $to, string $subject, string $html, array $options = []): bool
    {
        self::$lastError = '';
        $transport = (string) setting('mail_transport', 'mail');
        $smtp = self::smtpConfig();
        $fromEmail = (string) setting('mail_from_email', '');
        $replyTo = (string) ($options['reply_to'] ?? '');
        if ($replyTo === '') {
            $replyTo = (string) setting('mail_reply_to', '');
        }
        // El web d'una cursa que és a la plataforma envia pel servidor de correu
        // d'ella, que és el que té el domini preparat perquè els correus arribin.
        // Surt amb el nom del web i l'adreça de la plataforma, i les respostes
        // van a qui organitza la cursa.
        if ($transport === 'platform') {
            $platform = self::platformMail();
            if ($platform === null) {
                $transport = 'mail';
            } else {
                $transport = $platform['transport'];
                $smtp = $platform['smtp'];
                if ($platform['from_email'] !== '') {
                    $fromEmail = $platform['from_email'];
                }
                if ($replyTo === '') {
                    $replyTo = (string) setting('contact_email', '');
                }
            }
        }
        if ($fromEmail === '') {
            $host = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
            $fromEmail = 'no-reply@' . preg_replace('/^www\./', '', (string) $host);
        }
        $fromName = trim((string) setting('mail_from_name', ''));
        if ($fromName === '') {
            $fromName = site_name('');
        }
        $text = $options['text'] ?? self::htmlToText($html);
        $boundary = 'b' . bin2hex(random_bytes(12));
        $mixedBoundary = 'm' . bin2hex(random_bytes(12));
        $attachments = $options['attachments'] ?? [];

        // L'identificador del missatge, amb el domini de qui envia: alguns
        // servidors (els de Microsoft, sobretot) desconfien d'un correu que
        // diu venir d'un domini i porta l'identificador d'un altre.
        $messageDomain = substr((string) strrchr($fromEmail, '@'), 1)
            ?: (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::address($fromEmail, $fromName),
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageDomain . '>',
        ];
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $alternative = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "\r\n"
            . "--{$boundary}--\r\n";

        if ($attachments) {
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';
            $body = "--{$mixedBoundary}\r\n"
                . "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n"
                . $alternative;
            foreach ($attachments as $attachment) {
                $body .= "--{$mixedBoundary}\r\n"
                    . 'Content-Type: ' . ($attachment['type'] ?? 'application/octet-stream') . '; name="' . $attachment['name'] . "\"\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . 'Content-Disposition: attachment; filename="' . $attachment['name'] . "\"\r\n\r\n"
                    . chunk_split(base64_encode($attachment['content'])) . "\r\n";
            }
            $body .= "--{$mixedBoundary}--\r\n";
        } else {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = $alternative;
        }


        try {
            if ($transport === 'log') {
                // Mode d'assaig: no surt res del servidor, però queda registrat
                // com si s'hagués enviat, per poder-ho provar tot sense molestar ningú.
                log_line('mail', 'Assaig (no s\'envia)', ['to' => $to, 'subject' => $subject, 'from' => $fromEmail]);
                self::logEmail($to, $subject, 'sent');
                return true;
            }
            // La còpia oculta va només al sobre (RCPT TO), mai a les capçaleres:
            // si hi anés, tothom veuria qui la rep. La funció mail() la treu ella
            // mateixa de les capçaleres, per això allà sí que s'hi posa.
            $sent = $transport === 'smtp'
                ? self::smtpSend($smtp, $fromEmail, $to, self::encodeHeader($subject, strlen('Subject: ')), $headers, $body, $options)
                : @mail($to, self::encodeName($subject), $body, implode("\r\n", array_merge(
                    $headers,
                    !empty($options['bcc']) ? ['Bcc: ' . $options['bcc']] : []
                )));
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            log_line('mail', 'Error enviant correu', ['to' => $to, 'from' => $fromEmail, 'error' => $e->getMessage()]);
            self::logEmail($to, $subject, 'error', $e->getMessage());
            return false;
        }

        self::logEmail($to, $subject, $sent ? 'sent' : 'error', $sent ? '' : 'L\'enviament ha fallat');
        if (!$sent) {
            self::$lastError = 'El servidor no ha acceptat el correu (' . $transport . ').';
            log_line('mail', 'Enviament fallit', ['to' => $to, 'subject' => $subject, 'transport' => $transport]);
        }
        return (bool) $sent;
    }

    /** Envia una plantilla de correu de la carpeta views/emails. */
    public static function sendTemplate(string $to, string $subject, string $template, array $data = [], array $options = []): bool
    {
        $html = View::make('emails/' . $template, array_merge($data, ['subject' => $subject]), 'emails/layout');
        return self::send($to, $subject, $html, $options);
    }

    private static function logEmail(string $to, string $subject, string $status, string $error = ''): void
    {
        try {
            Db::insert('email_log', [
                'recipient' => mb_substr($to, 0, 190),
                'subject' => mb_substr($subject, 0, 190),
                'status' => $status,
                'error' => $error !== '' ? mb_substr($error, 0, 500) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // ignora
        }
    }

    /**
     * Una adreça amb nom per a les capçaleres (From, To…), ben escrita.
     *
     * Un nom amb accents va codificat; un nom sense accents però amb signes
     * com la coma o els parèntesis va entre cometes. Sense cometes, «Cros
     * Escolar, La Granada <hola@…>» semblarien dues adreces, i els servidors de
     * Microsoft rebutgen el correu (550 5.7.512) mentre que d'altres el deixen
     * passar.
     */
    public static function address(string $email, string $name = ''): string
    {
        $name = trim((string) preg_replace('/[\r\n\t]+/', ' ', $name));
        if ($name === '') {
            return $email;
        }
        if (preg_match('/[\x80-\xFF]/', $name)) {
            $phrase = self::encodeHeader($name, strlen('From: '));
        } elseif (preg_match('/^[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]+$/', $name)) {
            $phrase = $name;
        } else {
            $phrase = '"' . addcslashes($name, '"\\') . '"';
        }

        return $phrase . ' <' . $email . '>';
    }

    /**
     * Un text per a una capçalera: tal qual si és ASCII i, si no, codificat i
     * partit en trossos de la mida que mana la norma (RFC 2047), que un
     * assumpte llarg en un sol bloc també fa desconfiar alguns servidors.
     *
     * @param int $prefix quants caràcters porta la línia abans del text («Subject: »)
     */
    public static function encodeHeader(string $value, int $prefix = 0): string
    {
        $value = trim((string) preg_replace('/[\r\n]+/', ' ', $value));
        if (!preg_match('/[\x80-\xFF]/', $value)) {
            return $value;
        }

        // Tot el text codificat, a trossos de com a molt 75 caràcters per
        // tros (i la primera línia comptant el que hi ha al davant), sense
        // partir mai una lletra de més d'un byte. Codificar-ho tot, i no només
        // les paraules amb accents, evita que una coma quedi fora i trenqui la
        // capçalera.
        $words = [];
        $chunk = '';
        $room = max(3, intdiv(75 - 12 - $prefix, 4) * 3);
        foreach (mb_str_split($value, 1, 'UTF-8') as $char) {
            if ($chunk !== '' && strlen($chunk . $char) > $room) {
                $words[] = $chunk;
                $chunk = '';
                $room = 45; // les línies de continuació: «␠=?UTF-8?B?» + 60 + «?=»
            }
            $chunk .= $char;
        }
        if ($chunk !== '') {
            $words[] = $chunk;
        }

        return implode("\r\n ", array_map(static fn (string $w): string => '=?UTF-8?B?' . base64_encode($w) . '?=', $words));
    }

    public static function encodeName(string $value): string
    {
        return preg_match('/[\x80-\xFF]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    public static function htmlToText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</(p|div|tr|h1|h2|h3|li)>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * Les dades del servidor SMTP de la configuració que hi ha activa.
     *
     * @return array{host:string,port:int,user:string,pass:string,secure:string}
     */
    private static function smtpConfig(): array
    {
        return [
            'host' => (string) setting('smtp_host', ''),
            'port' => (int) setting('smtp_port', 587),
            'user' => (string) setting('smtp_user', ''),
            'pass' => (string) setting('smtp_pass', ''),
            'secure' => (string) setting('smtp_secure', 'tls'), // tls | ssl | none
        ];
    }

    /**
     * Com envia la plataforma, vist des del web d'una cursa. Torna null si
     * aquest web no és en cap plataforma o no s'hi pot arribar.
     *
     * @return array{transport:string,from_email:string,smtp:array{host:string,port:int,user:string,pass:string,secure:string}}|null
     */
    private static function platformMail(): ?array
    {
        if (self::$platformMail !== false) {
            return self::$platformMail;
        }

        return self::$platformMail = self::readPlatformMail();
    }

    /** @return array{transport:string,from_email:string,smtp:array{host:string,port:int,user:string,pass:string,secure:string}}|null */
    private static function readPlatformMail(): ?array
    {
        if (!class_exists(\Cros\Platform\Bridge::class) || !\Cros\Platform\Bridge::available()) {
            return null;
        }
        try {
            $mail = \Cros\Platform\Bridge::run(static fn (): array => [
                'transport' => (string) setting('mail_transport', 'mail'),
                'from_email' => (string) setting('mail_from_email', ''),
                'smtp' => self::smtpConfig(),
            ], null, true);
        } catch (\Throwable $e) {
            log_line('mail', 'No s\'ha pogut llegir el correu de la plataforma', ['error' => $e->getMessage()]);

            return null;
        }
        // La plataforma no en pot tenir cap altre, però per si de cas: que no
        // es quedi donant voltes.
        if (!in_array($mail['transport'], ['mail', 'smtp', 'log'], true)) {
            $mail['transport'] = 'mail';
        }

        return $mail;
    }

    /**
     * Enviament per SMTP amb autenticació.
     *
     * @param array{host:string,port:int,user:string,pass:string,secure:string} $smtp
     */
    private static function smtpSend(array $smtp, string $from, string $to, string $subject, array $headers, string $body, array $options = []): bool
    {
        $host = $smtp['host'];
        $port = $smtp['port'] > 0 ? $smtp['port'] : 587;
        $user = $smtp['user'];
        $pass = $smtp['pass'];
        $secure = $smtp['secure'];
        if ($host === '') {
            throw new \RuntimeException('No s\'ha configurat cap servidor SMTP.');
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['SNI_enabled' => true]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new \RuntimeException('No s\'ha pogut connectar amb el servidor SMTP: ' . $errstr);
        }
        stream_set_timeout($socket, 20);

        $read = function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $command = function (string $cmd, array $expect) use ($socket, $read): string {
            if ($cmd !== '') {
                fwrite($socket, $cmd . "\r\n");
            }
            $response = $read();
            $code = (int) substr(trim($response), 0, 3);
            if (!in_array($code, $expect, true)) {
                throw new \RuntimeException('SMTP: resposta inesperada (' . trim($response) . ')');
            }
            return $response;
        };

        try {
            $command('', [220]);
            $hostname = (string) (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
            $command('EHLO ' . $hostname, [250]);
            if ($secure === 'tls') {
                $command('STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                    throw new \RuntimeException('No s\'ha pogut iniciar el xifratge TLS.');
                }
                $command('EHLO ' . $hostname, [250]);
            }
            if ($user !== '') {
                $command('AUTH LOGIN', [334]);
                $command(base64_encode($user), [334]);
                $command(base64_encode($pass), [235]);
            }
            $command('MAIL FROM:<' . $from . '>', [250]);
            foreach (array_filter(array_merge([$to], array_filter([$options['bcc'] ?? '']))) as $recipient) {
                $command('RCPT TO:<' . trim($recipient) . '>', [250, 251]);
            }
            $command('DATA', [354]);
            $message = 'To: ' . $to . "\r\n" . 'Subject: ' . $subject . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $message = preg_replace('/^\./m', '..', $message) ?? $message;
            fwrite($socket, $message . "\r\n.\r\n");
            $command('', [250]);
            $command('QUIT', [221, 250]);
        } finally {
            @fclose($socket);
        }
        return true;
    }
}
