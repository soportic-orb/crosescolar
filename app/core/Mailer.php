<?php
declare(strict_types=1);

namespace Cros\Core;

/**
 * Enviament de correu (funció mail() de PHP o SMTP autenticat).
 * No requereix cap llibreria externa.
 */
class Mailer
{
    /**
     * Envia un correu HTML.
     * @param array{reply_to?:string,text?:string,attachments?:array<int,array{name:string,content:string,type:string}>,bcc?:string} $options
     */
    public static function send(string $to, string $subject, string $html, array $options = []): bool
    {
        $fromEmail = (string) setting('mail_from_email', '');
        if ($fromEmail === '') {
            $host = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
            $fromEmail = 'no-reply@' . preg_replace('/^www\./', '', (string) $host);
        }
        $fromName = (string) setting('mail_from_name', setting('site_name', 'Cros Escolar La Granada'));
        $text = $options['text'] ?? self::htmlToText($html);
        $boundary = 'b' . bin2hex(random_bytes(12));
        $mixedBoundary = 'm' . bin2hex(random_bytes(12));
        $attachments = $options['attachments'] ?? [];

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::encodeName($fromName) . ' <' . $fromEmail . '>',
            'Date: ' . date('r'),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost') . '>',
        ];
        if (!empty($options['reply_to'])) {
            $headers[] = 'Reply-To: ' . $options['reply_to'];
        } elseif ($replyTo = (string) setting('mail_reply_to', '')) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
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

        $encodedSubject = self::encodeName($subject);
        $transport = (string) setting('mail_transport', 'mail');

        try {
            $sent = $transport === 'smtp'
                ? self::smtpSend($fromEmail, $to, $encodedSubject, $headers, $body, $options)
                : @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
        } catch (\Throwable $e) {
            log_line('mail', 'Error enviant correu', ['to' => $to, 'error' => $e->getMessage()]);
            self::logEmail($to, $subject, 'error', $e->getMessage());
            return false;
        }

        self::logEmail($to, $subject, $sent ? 'sent' : 'error', $sent ? '' : 'L\'enviament ha fallat');
        if (!$sent) {
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

    /** Enviament per SMTP amb autenticació. */
    private static function smtpSend(string $from, string $to, string $subject, array $headers, string $body, array $options = []): bool
    {
        $host = (string) setting('smtp_host', '');
        $port = (int) setting('smtp_port', 587);
        $user = (string) setting('smtp_user', '');
        $pass = (string) setting('smtp_pass', '');
        $secure = (string) setting('smtp_secure', 'tls'); // tls | ssl | none
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
