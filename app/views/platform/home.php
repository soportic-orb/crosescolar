<?php
/**
 * Portada de la plataforma: els webs que ja hi són i el formulari per crear-ne un.
 * @var array<int,array<string,mixed>> $instances
 * @var array<string,string> $errors
 */
use Cros\Core\Icons;
use Cros\Platform\Platform;
use Cros\Platform\Site;

$domain = Site::current();
$domains = Platform::domains();
$crida = (string) setting('platform_cta_label', 'Crea el web de la teva cursa');
$llistat = (string) setting('platform_nav_directory', 'Curses');
$exemple = (string) setting('platform_slug_example', 'lavostracursa');
$old = static fn (string $key, string $default = ''): string => (string) old($key, $default);
// La imatge del banner i el vel que hi va a sobre perquè el text es llegeixi.
$heroImage = (string) setting('platform_hero_image', '');
$heroOverlay = max(0, min(95, (int) setting('platform_hero_overlay', '72'))) / 100;
?>
<section class="platform-hero<?= $heroImage !== '' ? ' platform-hero--image' : '' ?>"
  <?php if ($heroImage !== ''): ?>style="--hero-image:url('<?= e(upload_url($heroImage)) ?>');--hero-overlay:<?= $heroOverlay ?>"<?php endif; ?>>
  <div class="container">
    <?php if ($eyebrow = trim((string) setting('platform_hero_eyebrow', 'Per a escoles, AFA i clubs'))): ?>
      <span class="eyebrow"><?= e($eyebrow) ?></span>
    <?php endif; ?>
    <h1><?= e(setting('platform_tagline', 'El web de la vostra cursa, a punt en una hora.')) ?></h1>
    <?php if ($lead = trim((string) setting('platform_hero_lead', 'Inscripcions en línia, dorsals en PDF, resultats per categories i correus a les famílies. Tot amb la vostra imatge i a la vostra adreça.'))): ?>
      <p class="lead"><?= e($lead) ?></p>
    <?php endif; ?>
    <?php if ($intro = (string) setting('platform_intro', '')): ?>
      <div class="lead" style="color:rgba(255,255,255,.85)"><?= \Cros\Core\Html::clean($intro) ?></div>
    <?php endif; ?>
    <div class="flex" style="margin-top:1.6rem">
      <a class="btn btn--accent" href="#formulari"><?= Icons::svg('plus', 'icon', 18) ?> <?= e($crida) ?></a>
      <a class="btn btn--light" href="#cros">Veure <?= e(mb_strtolower($llistat)) ?></a>
    </div>
    <p class="platform-hero__address">La vostra adreça serà <strong><?= e($exemple) ?><span>.<?= e($domain) ?></span></strong></p>
  </div>
</section>

<?php if (\Cros\Core\Settings::bool('platform_directory', true)): ?>
<section class="section" id="cros">
  <div class="container">
    <div class="section__head">
      <h2><?= e(setting('platform_directory_title', 'Les curses que ja hi són')) ?></h2>
      <p class="lead"><?= e(setting('platform_directory_lead', 'Cliqueu-ne una i aneu al seu web, amb les inscripcions, els recorreguts i els resultats.')) ?></p>
    </div>

    <?php if (!$instances): ?>
      <div class="notice-box">
        Encara no hi ha res publicat. Si sou els primers,
        <a href="#formulari">creeu-vos el web</a>.
      </div>
    <?php else: ?>
      <div class="cros-grid">
        <?php foreach ($instances as $cros): ?>
          <?php
          // L'adreça on viu de debò cada web, amb el seu domini, i la imatge
          // de la seva portada, que la serveix el mateix web.
          $url = \Cros\Platform\Instance::url($cros);
          $host = \Cros\Platform\Instance::host($cros);
          $hero = \Cros\Platform\Instance::heroUrl($cros);
          $date = (string) ($cros['event_date'] ?? '');
          $days = $date !== '' ? (int) floor((strtotime($date) - strtotime('today')) / 86400) : null;
          $state = $days === null ? 'Data per confirmar'
              : ($days < 0 ? 'Cursa passada' : ($days === 0 ? 'És avui!' : ($days <= 30 ? 'Falten ' . $days . ' dies' : 'Inscripcions obertes')));
          ?>
          <a class="cros-card" href="<?= e($url) ?>">
            <span class="cros-card__top<?= $hero !== '' ? ' cros-card__top--image' : '' ?>">
              <?php if ($hero !== ''): ?>
                <img class="cros-card__image" src="<?= e($hero) ?>" alt="" loading="lazy" decoding="async">
              <?php endif; ?>
              <span class="cros-card__state"><?= e($state) ?></span>
            </span>
            <span class="cros-card__body">
              <span class="cros-card__name"><?= e($cros['site_name']) ?></span>
              <?php if (!empty($cros['town'])): ?><span class="cros-card__town"><?= e($cros['town']) ?></span><?php endif; ?>
              <span class="cros-card__date">
                <?= Icons::svg('calendar', 'icon', 15) ?>
                <?= $date !== '' ? e(ca_date($date, true)) : 'data per confirmar' ?>
              </span>
              <span class="cros-card__url"><?= e($host) ?></span>
            </span>
          </a>
        <?php endforeach; ?>

        <a class="cros-card cros-card--new" href="#formulari">
          <span class="cros-card__plus"><?= Icons::svg('plus', 'icon', 20) ?></span>
          <strong>El vostre, aquí</strong>
          <span>Creeu el web de <?= e(setting('platform_activity_one', 'la vostra cursa')) ?> i sortireu en aquesta llista.</span>
        </a>
      </div>
      <p class="field__hint" style="margin-top:1.2rem">
        Només hi surt qui ja ha publicat el seu web. Cada entitat decideix si vol aparèixer-hi.
      </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (\Cros\Core\Settings::bool('platform_steps_show', true)): ?>
<section class="section section--tint" id="com-va">
  <div class="container">
    <div class="section__head section__head--center">
      <h2><?= e(setting('platform_steps_title', 'Com funciona')) ?></h2>
    </div>
    <div class="grid grid--3">
      <?php foreach ([1, 2, 3] as $pas): ?>
        <?php $titol = trim((string) setting('platform_step' . $pas . '_title', '')); ?>
        <?php if ($titol !== ''): ?>
          <div class="card">
            <div class="card__step"><?= $pas ?></div>
            <h3><?= e($titol) ?></h3>
            <p><?= nl2br(e((string) setting('platform_step' . $pas . '_text', ''))) ?></p>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
// Les preguntes freqüents: es desen com una llista al panell i surten aquí
// desplegables, sense necessitat de cap script.
$faqs = json_decode((string) setting('platform_faqs', ''), true);
$faqs = \Cros\Core\Settings::bool('platform_faqs_show', true) && is_array($faqs) ? $faqs : [];
?>
<?php if ($faqs !== []): ?>
<section class="section" id="preguntes">
  <div class="container-narrow">
    <div class="section__head">
      <h2><?= e(setting('platform_faqs_title', 'Preguntes freqüents')) ?></h2>
    </div>
    <div class="faq-list">
      <?php foreach ($faqs as $faq): ?>
        <?php
        $pregunta = trim((string) ($faq['q'] ?? ''));
        $resposta = trim((string) ($faq['a'] ?? ''));
        if ($pregunta === '' || $resposta === '') { continue; }
        ?>
        <details class="faq-item">
          <summary><?= e($pregunta) ?></summary>
          <div class="faq-item__body"><?= nl2br(e($resposta)) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
// I les mateixes preguntes en el format que entén Google, que les pot
// ensenyar desplegades al resultat de cerca.
$ld = [];
foreach ($faqs as $faq) {
    $pregunta = trim((string) ($faq['q'] ?? ''));
    $resposta = trim((string) ($faq['a'] ?? ''));
    if ($pregunta !== '' && $resposta !== '') {
        $ld[] = ['@type' => 'Question', 'name' => $pregunta,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $resposta]];
    }
}
?>
<?php if ($ld !== []): ?>
  <script type="application/ld+json"><?= str_replace('<', '\u003C', (string) json_encode(
      ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld],
      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
  )) ?></script>
<?php endif; ?>
<?php endif; ?>

<section class="section" id="formulari">
  <div class="container-narrow">
    <div class="section__head">
      <h2><?= e($crida) ?></h2>
      <p class="lead">
        Crear el compte és gratuït i entreu al panell de seguida. Només es paga
        el dia que voleu fer públic el web, un sol cop.
      </p>
    </div>

    <?php if (!\Cros\Core\Settings::bool('platform_requests_open', true)): ?>
      <div class="notice-box">
        <?= nl2br(e((string) setting('platform_requests_closed_text',
            'Ara mateix no donem altes noves. Escriviu-nos i us avisarem quan tornem a obrir.'))) ?>
        <?php if ($contacte = (string) setting('platform_contact_email', '')): ?>
          <br><a href="mailto:<?= e($contacte) ?>"><?= e($contacte) ?></a>
        <?php endif; ?>
      </div>
    <?php else: ?>

    <form method="post" action="<?= e(url('/registre')) ?>" class="card">
      <?= csrf_field() ?>
      <p class="visually-hidden" aria-hidden="true">
        <label for="website_url">No ompliu aquest camp</label>
        <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
      </p>

      <div class="form-row">
        <div class="field<?= isset($errors['site_name']) ? ' field--error' : '' ?>">
          <label for="site_name">Com es diu *</label>
          <input type="text" id="site_name" name="site_name" value="<?= e($old('site_name')) ?>"
                 placeholder="Cursa de Tardor de la Granada" required>
          <span class="field__hint">El nom que sortirà al web. Es pot canviar després.</span>
          <?php if (isset($errors['site_name'])): ?><span class="field__error"><?= e($errors['site_name']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="town">Població</label>
          <input type="text" id="town" name="town" value="<?= e($old('town')) ?>">
        </div>
      </div>

      <div class="field<?= isset($errors['slug']) ? ' field--error' : '' ?>">
        <label for="slug">L'adreça que voleu</label>
        <div class="slug-field">
          <input type="text" id="slug" name="slug" value="<?= e($old('slug')) ?>" placeholder="<?= e($exemple) ?>"
                 autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="30"
                 aria-describedby="slug-status" data-slug-check="<?= e(url('/registre/adreca')) ?>">
          <?php if (count($domains) > 1): ?>
            <select name="domain" aria-label="Domini">
              <?php foreach ($domains as $option): ?>
                <option value="<?= e($option) ?>"<?= $old('domain', $domain) === $option ? ' selected' : '' ?>>.<?= e($option) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <span>.<?= e($domain) ?></span>
          <?php endif; ?>
        </div>
        <span class="field__hint">
          Lletres, números i guions; si la deixeu buida, en fem una a partir del nom.
          El vostre panell serà aquesta mateixa adreça acabada amb <code>/admin</code>.
        </span>
        <?php if (isset($errors['slug'])): ?><span class="field__error"><?= e($errors['slug']) ?></span><?php endif; ?>
        <span class="slug-status" id="slug-status" role="status" aria-live="polite" hidden></span>
      </div>

      <div class="field<?= isset($errors['client_kind']) ? ' field--error' : '' ?>">
        <span class="label">Qui organitza la prova *</span>
        <div class="choice-row">
          <?php foreach (\Cros\Platform\Client::KINDS as $clau => $nom): ?>
            <label class="choice">
              <input type="radio" name="client_kind" value="<?= e($clau) ?>"
                     <?= $old('client_kind', 'company') === $clau ? 'checked' : '' ?>>
              <span><?= e($nom) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="field__hint">
          Ens cal per saber com us hem de fer la factura el dia que publiqueu el web.
          Ho podreu canviar més endavant.
        </span>
        <?php if (isset($errors['client_kind'])): ?><span class="field__error"><?= e($errors['client_kind']) ?></span><?php endif; ?>
      </div>

      <div class="form-row">
        <div class="field<?= isset($errors['admin_name']) ? ' field--error' : '' ?>">
          <label for="admin_name">Nom i cognoms *</label>
          <input type="text" id="admin_name" name="admin_name" value="<?= e($old('admin_name')) ?>" required>
          <?php if (isset($errors['admin_name'])): ?><span class="field__error"><?= e($errors['admin_name']) ?></span><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['admin_email']) ? ' field--error' : '' ?>">
          <label for="admin_email">Correu electrònic *</label>
          <input type="email" id="admin_email" name="admin_email" value="<?= e($old('admin_email')) ?>" required>
          <span class="field__hint">Hi enviem el botó per entrar al panell. Serveix també per confirmar l'adreça.</span>
          <?php if (isset($errors['admin_email'])): ?><span class="field__error"><?= e($errors['admin_email']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="event_date">Data prevista</label>
          <input type="date" id="event_date" name="event_date" value="<?= e($old('event_date')) ?>">
          <span class="field__hint">Si encara no la sabeu, deixeu-ho en blanc.</span>
        </div>
      </div>

      <label class="checkbox<?= isset($errors['consent']) ? ' field--error' : '' ?>">
        <input type="checkbox" name="consent" value="1" <?= $old('consent') === '1' ? 'checked' : '' ?>>
        <span>
          Accepto les <a href="<?= e(url('/condicions')) ?>" target="_blank" rel="noopener">condicions del servei</a>
          i que tracteu les meves dades per donar-me l'accés.
        </span>
      </label>
      <?php if (isset($errors['consent'])): ?><span class="field__error"><?= e($errors['consent']) ?></span><?php endif; ?>

      <div class="flex" style="margin-top:1.4rem">
        <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 18) ?> Crear el web</button>
        <span class="field__hint">En un moment el tindreu a punt.</span>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>
