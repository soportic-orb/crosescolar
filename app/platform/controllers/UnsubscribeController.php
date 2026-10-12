<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\View;
use Cros\Platform\Mail\Unsubscribe;
use Cros\Platform\Platform;

/**
 * La baixa dels enviaments, des de l'enllaç del correu.
 *
 * Obrir l'enllaç no dona de baixa ningú: només ensenya el botó. Els filtres de
 * correu (el de Microsoft, sobretot) obren tots els enllaços d'un missatge per
 * mirar si són de fiar, i si n'hi hagués prou amb obrir-lo, tothom qui té
 * Outlook es donaria de baixa sol. La baixa d'un sol clic dels programes de
 * correu (RFC 8058) arriba per POST i sí que es fa de seguida.
 *
 * Sense testimoni CSRF a posta: qui truca pot ser el servidor de Gmail, i el
 * que autoritza la baixa és la signatura de l'enllaç.
 */
class UnsubscribeController extends Controller
{
    public function show(array $params): void
    {
        $who = Unsubscribe::read((string) ($params['token'] ?? ''));
        $this->page($who, (string) $params['token'], $who !== null && Unsubscribe::has($who['email']));
    }

    public function confirm(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $who = Unsubscribe::read($token);
        if ($who !== null) {
            Unsubscribe::add($who['email'], Platform::validDomain((string) ($_SERVER['HTTP_HOST'] ?? '')), $who['mailing']);
            Platform::log('mailout.unsubscribe', 'mailout', $who['mailing'] ?: null, ['email' => $who['email']]);
        }
        // El programa de correu no mira la pàgina, només la resposta.
        if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {
            http_response_code($who !== null ? 200 : 400);
            header('Content-Type: text/plain; charset=utf-8');
            echo $who !== null ? 'OK' : 'Enllaç no vàlid';

            return;
        }
        $this->page($who, $token, $who !== null);
    }

    /** @param array{email:string,mailing:int}|null $who */
    private function page(?array $who, string $token, bool $done): void
    {
        if ($who === null) {
            http_response_code(404);
        }
        View::render('platform/unsubscribe', [
            'title' => 'Baixa dels enviaments',
            'noindex' => true,
            'who' => $who,
            'token' => $token,
            'done' => $done,
        ], 'layouts/platform');
    }
}
