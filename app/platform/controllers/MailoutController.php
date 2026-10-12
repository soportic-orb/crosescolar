<?php
declare(strict_types=1);

namespace Cros\Platform\Controllers;

use Cros\Core\Controller;
use Cros\Core\HttpException;
use Cros\Core\Settings;
use Cros\Core\View;
use Cros\Platform\Console;
use Cros\Platform\Instance;
use Cros\Platform\MailList;
use Cros\Platform\Mailout;
use Cros\Platform\Mail\Ai;
use Cros\Platform\Mail\Brand;
use Cros\Platform\Mail\Doc;
use Cros\Platform\Mail\Images;
use Cros\Platform\Mail\Renderer;
use Cros\Platform\Platform;
use Cros\Platform\Site;

/**
 * Els enviaments de correu de la plataforma: la llista, l'editor de blocs,
 * l'enviament i el seguiment, les plantilles base, les llistes de correu i la IA.
 *
 * L'editor parla amb el servidor en JSON (desar, imatges, previsualització,
 * versions, IA); la resta són formularis de sempre.
 */
class MailoutController extends Controller
{
    public function index(): void
    {
        Console::requireLogin();
        $filter = (string) ($_GET['filtre'] ?? 'tots');
        $filter = isset(Mailout::FILTERS[$filter]) ? $filter : 'tots';
        $search = trim((string) ($_GET['q'] ?? ''));
        View::render('platform/console/mail/index', [
            'styles' => ['css/mail-editor.css'],
            'title' => 'Enviaments',
            'rows' => Mailout::mails($filter, $search),
            'filter' => $filter,
            'search' => $search,
            'counts' => Mailout::counts(),
            'templates' => Mailout::templates(),
            'lists' => MailList::all(),
            'domains' => Platform::domains(),
            'cronStale' => Mailout::cronStale(),
        ], 'layouts/console');
    }

    /** Correu nou, a partir d'una plantilla base o en blanc. */
    public function store(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $templateId = (int) input('template_id', '0');
        $id = Mailout::create((string) input('subject'), $templateId > 0 ? $templateId : null, (string) input('domain'), Console::id(), $this->who());
        Console::log('mailout_create', 'mailout', $id, ['assumpte' => (string) input('subject')]);
        redirect('/enviaments/' . $id . '/editar');
    }

    public function templates(): void
    {
        Console::requireLogin();
        View::render('platform/console/mail/templates', [
            'styles' => ['css/mail-editor.css'],
            'title' => 'Plantilles base',
            'templates' => Mailout::templates(),
        ], 'layouts/console');
    }

    /** Plantilla base nova: en blanc, o a partir d'un correu (des de l'editor, en JSON). */
    public function storeTemplate(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $data = $this->json() + $_POST;
        $from = (int) ($data['from_id'] ?? 0);
        $id = Mailout::createTemplate((string) ($data['name'] ?? ''), $from > 0 ? $from : null, Console::id(), $this->who());
        Console::log('mailout_template', 'mailout', $id);
        if ($this->wantsJson()) {
            json_out(['ok' => true, 'id' => $id, 'url' => url('/enviaments/' . $id . '/editar'),
                'message' => 'Plantilla base «' . Mailout::find($id)['template_name'] . '» desada.']);
        }
        redirect('/enviaments/' . $id . '/editar');
    }

    /** Un correu: si encara s'escriu, l'editor; si ja ha sortit, el seguiment. */
    public function show(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        if (Mailout::editable($mailing)) {
            redirect('/enviaments/' . (int) $mailing['id'] . '/editar');
        }
        $id = (int) $mailing['id'];
        View::render('platform/console/mail/show', [
            'styles' => ['css/mail-editor.css'],
            'title' => $mailing['subject'] ?: 'Enviament',
            'mailing' => $mailing,
            'counts' => Mailout::recipientCounts($id),
            'recipients' => Mailout::recipients($id, isset($_GET['estat']) ? (string) $_GET['estat'] : null, 500),
            'list' => !empty($mailing['list_id']) ? MailList::find((int) $mailing['list_id']) : null,
            'timeline' => $this->timeline($mailing),
            'limit' => Mailout::hourlyLimit(Mailout::domain($mailing)),
            'batch' => Mailout::batchSize(),
            'cronStale' => Mailout::cronStale(),
        ], 'layouts/console');
    }

    public function edit(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        if (!Mailout::editable($mailing)) {
            redirect('/enviaments/' . (int) $mailing['id']);
        }
        $doc = Mailout::doc($mailing);
        $brands = [];
        foreach (Platform::domains() as $domain) {
            $brands[$domain] = Brand::of($domain) + ['sender' => $this->sender($domain), 'limit' => Mailout::hourlyLimit($domain)];
        }
        $images = Brand::images($doc, Mailout::domain($mailing));
        unset($images['brand:logo']);
        View::render('platform/console/mail/editor', [
            'styles' => ['css/mail-editor.css'],
            'scripts' => ['js/mail-editor.js'],
            'title' => Mailout::kind($mailing) === Mailout::TEMPLATE ? 'Plantilla base' : 'Editor del correu',
            'mailing' => $mailing,
            'doc' => $doc,
            'images' => $images,
            'brands' => $brands,
            'versions' => count(Mailout::versions((int) $mailing['id'])),
            'lists' => MailList::all(),
            'me' => Console::user() ?? [],
            'batch' => Mailout::batchSize(),
            'ai' => [
                'show' => Ai::enabled(),
                'text' => Ai::textProviders(),
                'image' => Ai::imageReady(),
                'provider' => Ai::defaultTextProvider(),
                'imageModel' => Ai::openaiImageModel(),
            ],
        ], 'layouts/console');
    }

    /** Desa des de l'editor (JSON). Si algú hi ha desat mentrestant, 409. */
    public function save(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $data = $this->json();
        $fields = array_intersect_key($data, array_flip(['subject', 'preheader', 'doc', 'domain', 'template_name']));
        $r = Mailout::save((int) $mailing['id'], $fields, (int) ($data['base_version'] ?? 0), !empty($data['force']), Console::id(), $this->who());
        if ($r['result'] === 'locked') {
            json_out(['ok' => false, 'message' => 'Aquest correu ja ha començat a sortir i no es pot canviar. Dupliqueu-lo per fer-ne un altre.'], 423);
        }
        if ($r['result'] === 'conflict') {
            $last = Mailout::versions((int) $mailing['id'])[0] ?? [];
            json_out(['ok' => false, 'conflict' => true, 'message' => 'Mentrestant, ' . ($last['saved_by_name'] ?? 'algú') . ' ha desat la versió '
                . (int) $r['mailing']['version'] . ' (' . dt((string) ($last['created_at'] ?? '')) . '). Si deseu, la vostra la substituirà.'], 409);
        }
        json_out($this->state($r['mailing']) + ['ok' => true, 'result' => $r['result']]);
    }

    /** Com queda el correu que hi ha a l'editor ara mateix, encara que no s'hagi desat. */
    public function preview(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $data = $this->json();
        $doc = Doc::clean($data['doc'] ?? []);
        $mailing = [
            'id' => 0,
            'subject' => Doc::plain($data['subject'] ?? '', 190),
            'preheader' => Doc::plain($data['preheader'] ?? '', 255),
            'doc' => Doc::encode($doc),
            'domain' => Platform::validDomain((string) ($data['domain'] ?? '')),
        ];
        $this->html(Mailout::personalize($mailing, Mailout::parts($mailing), $this->sample())['html']);
    }

    /** El correu desat, tal com surt (per al seguiment i les miniatures). */
    public function rendered(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        $this->html(Mailout::personalize($mailing, Mailout::parts($mailing), $this->sample())['html']);
    }

    public function upload(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            json_out(['ok' => false, 'message' => (int) ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE
                ? 'La imatge és massa gran per a aquest servidor.' : 'No s\'ha rebut cap imatge.'], 422);
        }
        try {
            $img = Images::store((string) $file['tmp_name'], (string) ($file['name'] ?? 'imatge'), Console::id());
        } catch (\RuntimeException $e) {
            json_out(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        json_out(['ok' => true, 'src' => $img['src'], 'url' => $this->imageUrl($img['src']), 'width' => $img['width'], 'height' => $img['height']]);
    }

    public function versions(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        $id = (int) $mailing['id'];
        json_out(['ok' => true, 'versions' => array_map(static fn (array $v): array => [
            'id' => (int) $v['id'],
            'version' => (int) $v['version'],
            'subject' => (string) $v['subject'],
            'when' => dt((string) $v['created_at']),
            'by' => (string) ($v['saved_by_name'] ?: '—'),
            'current' => (int) $v['version'] === (int) $mailing['version'],
            'preview' => url('/enviaments/' . $id . '/versions/' . (int) $v['id']),
            'restore' => url('/enviaments/' . $id . '/versions/' . (int) $v['id'] . '/recupera'),
        ], Mailout::versions($id))]);
    }

    public function versionPreview(array $params): void
    {
        Console::requireLogin();
        $mailing = $this->mailing((int) $params['id']);
        $version = Mailout::version((int) $mailing['id'], (int) $params['v']);
        if (!$version) {
            throw new HttpException(404, 'Aquesta versió no existeix.');
        }
        $old = ['subject' => $version['subject'], 'preheader' => $version['preheader'], 'doc' => $version['doc']] + $mailing;
        $this->html(Mailout::personalize($old, Mailout::parts($old), $this->sample())['html']);
    }

    public function restore(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        try {
            $r = Mailout::restore((int) $mailing['id'], (int) $params['v'], Console::id(), $this->who());
            flash($r['result'] === 'locked' ? 'error' : 'success', $r['result'] === 'locked'
                ? 'Aquest correu ja ha sortit i no es pot canviar.'
                : 'Versió recuperada: ara és la ' . (int) $r['mailing']['version'] . '.');
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/enviaments/' . (int) $mailing['id'] . '/editar');
    }

    /** Una prova a l'adreça de qui hi treballa (o a la que digui). */
    public function test(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $data = $this->json() + $_POST;
        $to = trim((string) ($data['email'] ?? '')) ?: (string) (Console::user()['email'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->reply(false, 'Indiqueu una adreça vàlida per a la prova.', (int) $mailing['id']);
        }
        $ok = Mailout::sendTest($mailing, $to);
        if ($ok) {
            Console::log('mailout_test', 'mailout', (int) $mailing['id'], ['a' => $to]);
        }
        $this->reply($ok, $ok
            ? 'Prova enviada a ' . $to . ' ' . self::from(Mailout::domain($mailing)) . '.'
            : 'No s\'ha pogut enviar la prova: ' . (\Cros\Core\Mailer::lastError() ?: 'reviseu el servidor de correu del domini') . '.', (int) $mailing['id']);
    }

    /** Quanta gent el rebria amb aquests destinataris (el diàleg «Envia o programa»). */
    public function audience(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $probe = Mailout::deliveryFields($this->json(), $mailing) + $mailing;
        $stats = Mailout::audienceStats($probe);
        $sample = array_slice(array_values(Mailout::audience($probe)), 0, 6);
        json_out(['ok' => true, 'count' => $stats['count'], 'unsubscribed' => $stats['unsubscribed'],
            'sample' => array_map(static fn (array $p): string => $p['name'] !== '' ? $p['name'] . ' <' . $p['email'] . '>' : $p['email'], $sample)]);
    }

    /** Envia ara o el programa (JSON, des de l'editor). */
    public function send(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $id = (int) $mailing['id'];
        $data = $this->json();
        try {
            if (Mailout::kind($mailing) !== Mailout::MAIL) {
                throw new \RuntimeException('Una plantilla base no s\'envia: feu-ne un correu.');
            }
            Mailout::setDelivery($id, $data);
            if (($data['when'] ?? 'now') === 'later') {
                $at = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim((string) ($data['date'] ?? '')) . ' ' . trim((string) ($data['time'] ?? '')));
                if (!$at) {
                    throw new \RuntimeException('Trieu el dia i l\'hora de l\'enviament.');
                }
                Mailout::schedule($id, $at);
                Console::log('mailout_schedule', 'mailout', $id, ['quan' => $at->format('Y-m-d H:i')]);
                flash('success', 'Programat per al ' . dt($at->format('Y-m-d H:i:s')) . '. Sortirà sol: no cal tenir el panell obert.');
                json_out(['ok' => true, 'url' => url('/enviaments/' . $id . '/editar')]);
            }
            $count = Mailout::start($id);
            // La primera tanda surt de seguida; la resta, amb el cron.
            $first = Mailout::sendBatch($id);
            $people = $count . ' ' . ($count === 1 ? 'destinatari' : 'destinataris');
            flash('success', $first['done']
                ? 'Enviat: ' . $people . ($first['failed'] ? ', ' . $first['failed'] . ' amb error.' : '.')
                : 'En marxa: ' . $people . '. La resta de tandes surten soles cada cinc minuts.');
            json_out(['ok' => true, 'url' => url('/enviaments/' . $id)]);
        } catch (\RuntimeException $e) {
            json_out(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** Els botons del seguiment i de la llista. */
    public function action(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $mailing = $this->mailing((int) $params['id']);
        $id = (int) $mailing['id'];
        $back = '/enviaments/' . $id;
        switch ((string) input('action')) {
            case 'unschedule':
                Mailout::unschedule($id);
                flash('success', 'Desprogramat: torna a ser un esborrany.');
                $back .= '/editar';
                break;
            case 'pause':
                Mailout::pause($id, 'En pausa des del panell.');
                flash('success', 'En pausa. Els que falten no sortiran fins que el repreneu.');
                break;
            case 'resume':
                Mailout::resume($id);
                flash('success', 'Reprès: el cron enviarà la resta.');
                break;
            case 'stop':
                Mailout::stop($id);
                flash('success', 'Aturat. Els que no l\'havien rebut ja no el rebran.');
                break;
            case 'retry':
                $n = Mailout::retryFailed($id);
                flash($n > 0 ? 'success' : 'info', $n > 0 ? 'Es tornaran a provar ' . $n . ' adreces.' : 'No n\'hi ha cap per tornar a provar.');
                break;
            case 'batch':
                $r = Mailout::sendBatch($id);
                flash($r['waiting'] ? 'info' : 'success', $r['waiting']
                    ? 'Ara no se\'n pot enviar cap més: s\'ha arribat al límit per hora del domini (o el cron hi és a mig).'
                    : 'Tanda enviada: ' . $r['sent'] . ' enviats' . ($r['failed'] ? ', ' . $r['failed'] . ' amb error' : '') . '. En queden ' . $r['pending'] . '.');
                break;
            case 'archive':
                Mailout::archive($id, true);
                flash('success', 'Arxivat.');
                $back = '/enviaments';
                break;
            case 'unarchive':
                Mailout::archive($id, false);
                flash('success', 'Desarxivat.');
                break;
            case 'duplicate':
                $copy = Mailout::duplicate($id, Console::id(), $this->who());
                Console::log('mailout_duplicate', 'mailout', $copy, ['de' => $id]);
                flash('success', 'Còpia feta: és un esborrany nou.');
                $back = '/enviaments/' . $copy . '/editar';
                break;
            case 'delete':
                if (in_array($mailing['status'], ['sending', 'paused'], true)) {
                    flash('error', 'Atureu-lo abans d\'esborrar-lo.');
                    break;
                }
                Mailout::delete($id);
                Console::log('mailout_delete', 'mailout', $id, ['assumpte' => $mailing['subject']]);
                flash('success', Mailout::kind($mailing) === Mailout::TEMPLATE ? 'Plantilla esborrada.' : 'Enviament esborrat.');
                $back = Mailout::kind($mailing) === Mailout::TEMPLATE ? '/enviaments/plantilles' : '/enviaments';
                break;
            default:
                flash('error', 'No sé què voleu fer.');
        }
        redirect($back);
    }

    // ------------------------------------------------------------ el bloc «Cursa»

    /** Els webs del directori, per triar-ne un (JSON). */
    public function races(): void
    {
        Console::requireLogin();
        $q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
        $items = [];
        foreach (Instance::directory() as $row) {
            if ($q !== '' && !str_contains(mb_strtolower($row['site_name'] . ' ' . $row['town'] . ' ' . $row['slug']), $q)) {
                continue;
            }
            $items[] = ['id' => (string) $row['slug'], 'title' => (string) $row['site_name'],
                'sub' => trim(($row['event_date'] ? ca_date((string) $row['event_date'], true) : 'Sense data') . ' · ' . ($row['town'] ?: Instance::host($row)))];
            if (count($items) >= 40) {
                break;
            }
        }
        json_out(['ok' => true, 'items' => $items]);
    }

    /** Les dades d'un web per al bloc (JSON). */
    public function race(array $params): void
    {
        Console::requireLogin();
        $row = null;
        foreach (Instance::directory() as $candidate) {
            if ($candidate['slug'] === (string) $params['slug']) {
                $row = $candidate;
                break;
            }
        }
        if (!$row) {
            json_out(['ok' => false, 'message' => 'Aquest web ja no surt al directori.'], 404);
        }
        $instance = Instance::bySlug((string) $row['slug']);
        $hero = Instance::heroUrl($row);
        json_out(['ok' => true, 'data' => [
            'refId' => (string) ($instance['id'] ?? ''),
            'title' => (string) $row['site_name'],
            'when' => $row['event_date'] ? ucfirst(ca_date((string) $row['event_date'], true)) : '',
            'town' => (string) ($row['town'] ?? ''),
            'src' => str_starts_with($hero, 'https://') ? $hero : '',
            'href' => Instance::url($row),
            'btnText' => 'Visiteu el web ›',
        ]]);
    }

    // ------------------------------------------------------------ la IA

    public function aiText(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $data = $this->json();
        try {
            $r = Ai::text((string) ($data['provider'] ?? Ai::defaultTextProvider()), (string) ($data['prompt'] ?? ''), [
                'format' => (string) ($data['format'] ?? ''),
                'tone' => (string) ($data['tone'] ?? ''),
                'length' => (string) ($data['length'] ?? ''),
                'context' => (string) ($data['context'] ?? ''),
                'current' => (string) ($data['current'] ?? ''),
                'title' => (string) ($data['title'] ?? ''),
                'brand' => Brand::of((string) ($data['domain'] ?? '')),
            ]);
            Console::log('mailout_ai_text', 'mailout', null, ['servei' => $r['provider'], 'format' => (string) ($data['format'] ?? '')]);
            json_out(['ok' => true] + $r);
        } catch (\RuntimeException $e) {
            json_out(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function aiImage(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        try {
            $refs = [];
            $files = $_FILES['refs'] ?? null;
            if (is_array($files) && is_array($files['tmp_name'] ?? null)) {
                foreach ($files['tmp_name'] as $i => $tmp) {
                    if ((int) ($files['error'][$i] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file((string) $tmp)) {
                        $refs[] = Ai::prepareRef((string) file_get_contents((string) $tmp), (string) ($files['name'][$i] ?? 'referencia'));
                    }
                }
            }
            foreach ((array) ($_POST['ref_srcs'] ?? []) as $src) {
                $path = Images::file((string) $src);
                if ($path !== '') {
                    $refs[] = Ai::prepareRef((string) file_get_contents($path), basename($path));
                }
            }
            if (count($refs) > Ai::MAX_REFS) {
                throw new \RuntimeException('Com a molt ' . Ai::MAX_REFS . ' imatges de referència.');
            }
            $img = Ai::image((string) input('prompt'), (string) input('shape', 'landscape'), (string) input('style', 'photo'),
                (string) input('no_text', '1') === '1', $refs, (string) input('keep_details', '0') === '1', Brand::of((string) input('domain')), Console::id());
            Console::log('mailout_ai_image', 'mailout', null, ['referencies' => count($refs)]);
            json_out(['ok' => true, 'src' => $img['src'], 'url' => $this->imageUrl($img['src'])]);
        } catch (\RuntimeException $e) {
            json_out(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ------------------------------------------------------- les llistes

    public function lists(): void
    {
        Console::requireLogin();
        View::render('platform/console/mail/lists', [
            'title' => 'Llistes de correu',
            'lists' => MailList::all(),
            'edit' => isset($_GET['editar']) ? MailList::find((int) $_GET['editar']) : null,
        ], 'layouts/console');
    }

    public function saveList(): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $name = trim((string) input('name'));
        if ($name === '') {
            flash('error', 'La llista necessita un nom.');
            $this->back('/enviaments/llistes');
        }
        $id = MailList::save((int) input('id', '0'), $name, (string) input('description'));
        Console::log('maillist_save', 'maillist', $id, ['nom' => $name]);
        flash('success', 'Llista desada.');
        redirect('/enviaments/llistes/' . $id);
    }

    public function listShow(array $params): void
    {
        Console::requireLogin();
        $list = MailList::find((int) $params['id']);
        if (!$list) {
            throw new HttpException(404, 'Aquesta llista no existeix.');
        }
        View::render('platform/console/mail/list', [
            'title' => (string) $list['name'],
            'list' => $list,
            'contacts' => MailList::contacts((int) $list['id']),
        ], 'layouts/console');
    }

    /** Afegir adreces enganxant-les, una per línia. */
    public function addContacts(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $list = MailList::find((int) $params['id']);
        if (!$list) {
            throw new HttpException(404, 'Aquesta llista no existeix.');
        }
        $result = MailList::addContacts((int) $list['id'], (string) ($_POST['contacts'] ?? ''));
        $parts = [];
        if ($result['added'] > 0) {
            $parts[] = $result['added'] . ($result['added'] === 1 ? ' adreça nova' : ' adreces noves');
        }
        if ($result['updated'] > 0) {
            $parts[] = $result['updated'] . ' completades';
        }
        if ($result['skipped']) {
            $parts[] = count($result['skipped']) . ' sense adreça vàlida ('
                . implode('; ', array_slice($result['skipped'], 0, 3)) . ')';
        }
        Console::log('maillist_contacts', 'maillist', (int) $list['id'], $result);
        flash($result['added'] + $result['updated'] > 0 ? 'success' : 'error',
            $parts ? ucfirst(implode(', ', $parts)) . '.' : 'No s\'hi ha afegit res.');
        $this->back('/enviaments/llistes/' . (int) $list['id']);
    }

    public function contactAction(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $id = (int) $params['id'];
        $listId = (int) input('list_id', '0');
        if ((string) input('action') === 'remove') {
            MailList::removeContact($id);
            flash('success', 'Adreça treta de la llista.');
        } else {
            MailList::toggleContact($id);
            flash('success', 'Adreça canviada.');
        }
        $this->back('/enviaments/llistes/' . $listId);
    }

    public function deleteList(array $params): void
    {
        Console::requireLogin();
        $this->checkCsrf();
        $list = MailList::find((int) $params['id']);
        if (!$list) {
            throw new HttpException(404, 'Aquesta llista no existeix.');
        }
        MailList::delete((int) $list['id']);
        Console::log('maillist_delete', 'maillist', (int) $list['id'], ['nom' => $list['name']]);
        flash('success', 'Llista esborrada.');
        redirect('/enviaments/llistes');
    }

    // ------------------------------------------------------------ ajuda

    private function mailing(int $id): array
    {
        $mailing = Mailout::find($id);
        if (!$mailing) {
            throw new HttpException(404, 'Aquest enviament no existeix.');
        }

        return $mailing;
    }

    /** El cos JSON de la petició. @return array<string,mixed> */
    private function json(): array
    {
        static $data = null;
        if ($data === null) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** Resposta a un botó que pot venir de l'editor (JSON) o d'un formulari. */
    private function reply(bool $ok, string $message, int $id): void
    {
        if ($this->wantsJson()) {
            json_out(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }
        flash($ok ? 'success' : 'error', $message);
        redirect('/enviaments/' . $id);
    }

    private function html(string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        // A l'editor es veu dins d'un iframe sense permisos: cap script del correu no hi corre.
        header("Content-Security-Policy: script-src 'none'");
        echo $html;
        exit;
    }

    /** Qui desa, per a l'historial de versions. */
    /** «des de crosescolar.cat», «des d'esportweb.cat». */
    public static function from(string $domain): string
    {
        return (preg_match('/^[aeiouh]/i', $domain) ? 'des d\'' : 'des de ') . $domain;
    }

    private function who(): string
    {
        $user = Console::user() ?? [];

        return trim((string) ($user['name'] ?? '')) ?: (string) ($user['email'] ?? '');
    }

    /** Com surt el remitent d'un domini: «Nom <adreça>». */
    private function sender(string $domain): string
    {
        return Site::during($domain, static function () use ($domain): string {
            $email = trim((string) Settings::get('mail_from_email', '')) ?: 'no-reply@' . $domain;
            $name = trim((string) Settings::get('mail_from_name', '')) ?: Site::value($domain, 'site_name', 'EsportWeb');

            return $name . ' <' . $email . '>';
        });
    }

    /** Les dades d'exemple per a la previsualització: qui hi treballa. */
    private function sample(): array
    {
        $user = Console::user() ?? [];

        return ['name' => (string) ($user['name'] ?? 'Anna Duran'), 'entity' => 'AFA Escola Pompeu Fabra', 'email' => (string) ($user['email'] ?? 'anna@example.cat')];
    }

    private function imageUrl(string $src): string
    {
        return Images::urls([$src], rtrim(base_url(), '/'))[$src] ?? '';
    }

    /** El que l'editor ha de saber després de desar. @return array<string,mixed> */
    private function state(array $mailing): array
    {
        return [
            'version' => (int) $mailing['version'],
            'status' => (string) $mailing['status'],
            'saved' => 'Desat ' . dt((string) ($mailing['updated_at'] ?: $mailing['created_at'])),
        ];
    }

    /**
     * Com ha anat: quan es va programar, quan va començar, les tandes (per
     * minuts) i quan es va acabar, aturar o posar en pausa.
     *
     * @return array<int,array{at:string,text:string}>
     */
    private function timeline(array $mailing): array
    {
        $id = (int) $mailing['id'];
        $out = [];
        $labels = [
            'mailout_test' => 'Prova enviada',
            'mailout_schedule' => 'Programat',
            'mailout.start' => 'Comença',
            'mailout.paused' => 'En pausa: no en sortia cap',
            'mailout.stop' => 'Aturat',
            'mailout.unsubscribe' => 'Una persona s\'ha donat de baixa',
        ];
        foreach (\Cros\Core\Db::all(
            "SELECT action, context, created_at FROM platform_activity WHERE subject = 'mailout' AND subject_id = :id ORDER BY id",
            ['id' => $id]
        ) as $row) {
            if (!isset($labels[$row['action']])) {
                continue;
            }
            $ctx = json_decode((string) $row['context'], true) ?: [];
            $text = $labels[$row['action']];
            if ($row['action'] === 'mailout.start') {
                $text .= ': ' . (int) ($ctx['recipients'] ?? 0) . ' destinataris, ' . self::from((string) ($ctx['domain'] ?? Mailout::domain($mailing)));
            } elseif ($row['action'] === 'mailout_test' && !empty($ctx['a'])) {
                $text .= ' a ' . $ctx['a'];
            }
            $out[] = ['at' => (string) $row['created_at'], 'text' => $text];
        }
        foreach (\Cros\Core\Db::all(
            "SELECT MAX(sent_at) AS at, SUM(status = 'sent') AS ok, SUM(status = 'failed') AS ko
             FROM platform_mailing_recipients WHERE mailing_id = :id AND sent_at IS NOT NULL
             GROUP BY DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i:00') ORDER BY at LIMIT 200",
            ['id' => $id]
        ) as $row) {
            $out[] = ['at' => (string) $row['at'], 'text' => 'Tanda: ' . (int) $row['ok'] . ' enviats' . ((int) $row['ko'] ? ' · ' . (int) $row['ko'] . ' amb error' : '')];
        }
        if ($mailing['status'] === 'sent' && $mailing['finished_at']) {
            $out[] = ['at' => (string) $mailing['finished_at'], 'text' => 'Acabat'];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $out;
    }
}
