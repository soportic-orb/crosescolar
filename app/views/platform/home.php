<?php
/**
 * Portada de la plataforma: els cros que ja hi són i el formulari per demanar-ne un.
 * @var array<int,array<string,mixed>> $instances
 * @var array<string,string> $errors
 */
use Cros\Core\Icons;
use Cros\Platform\Platform;

$domain = Platform::domain();
$old = static fn (string $key, string $default = ''): string => (string) old($key, $default);
?>
<section class="platform-hero">
  <div class="container">
    <span class="eyebrow">Per a escoles, AFA i clubs</span>
    <h1>El web del vostre cros, a punt en una setmana</h1>
    <p class="lead">Inscripcions en línia, dorsals en PDF, resultats per categories i correus a les famílies. Tot amb la vostra imatge i a la vostra adreça.</p>
    <div class="flex" style="margin-top:1.6rem">
      <a class="btn btn--accent" href="#formulari"><?= Icons::svg('plus', 'icon', 18) ?> Crea la web per al teu cros</a>
      <a class="btn btn--light" href="#cros">Veure els cros que ja hi són</a>
    </div>
    <p class="platform-hero__address">La vostra adreça serà <strong>elvostrecros<span>.<?= e($domain) ?></span></strong></p>
  </div>
</section>

<section class="section" id="cros">
  <div class="container">
    <div class="section__head">
      <h2>Els cros escolars que ja hi corren</h2>
      <p class="lead">Cliqueu-ne un i aneu al seu web, amb les inscripcions, els recorreguts i els resultats.</p>
    </div>

    <?php if (!$instances): ?>
      <div class="notice-box">
        Encara no hi ha cap cros publicat. Si organitzeu el primer,
        <a href="#formulari">demaneu-nos el vostre web</a>.
      </div>
    <?php else: ?>
      <div class="cros-grid">
        <?php foreach ($instances as $cros): ?>
          <?php
          $url = 'https://' . $cros['slug'] . '.' . $domain;
          $date = (string) ($cros['event_date'] ?? '');
          $days = $date !== '' ? (int) floor((strtotime($date) - strtotime('today')) / 86400) : null;
          $state = $days === null ? 'Data per confirmar'
              : ($days < 0 ? 'Cursa passada' : ($days === 0 ? 'És avui!' : ($days <= 30 ? 'Falten ' . $days . ' dies' : 'Inscripcions obertes')));
          ?>
          <a class="cros-card" href="<?= e($url) ?>">
            <span class="cros-card__top"><span class="cros-card__state"><?= e($state) ?></span></span>
            <span class="cros-card__body">
              <span class="cros-card__name"><?= e($cros['site_name']) ?></span>
              <?php if (!empty($cros['town'])): ?><span class="cros-card__town"><?= e($cros['town']) ?></span><?php endif; ?>
              <span class="cros-card__date">
                <?= Icons::svg('calendar', 'icon', 15) ?>
                <?= $date !== '' ? e(ca_date($date, true)) : 'data per confirmar' ?>
              </span>
              <span class="cros-card__url"><?= e($cros['slug'] . '.' . $domain) ?></span>
            </span>
          </a>
        <?php endforeach; ?>

        <a class="cros-card cros-card--new" href="#formulari">
          <span class="cros-card__plus"><?= Icons::svg('plus', 'icon', 20) ?></span>
          <strong>El vostre, aquí</strong>
          <span>Creeu la web del vostre cros i sortireu en aquesta llista.</span>
        </a>
      </div>
      <p class="field__hint" style="margin-top:1.2rem">
        Només hi surten els cros que ja han publicat el seu web. Cada entitat decideix si vol aparèixer-hi.
      </p>
    <?php endif; ?>
  </div>
</section>

<section class="section section--tint" id="com-va">
  <div class="container">
    <div class="section__head section__head--center">
      <h2>Com funciona</h2>
    </div>
    <div class="grid grid--3">
      <div class="card"><div class="card__step">1</div><h3>Empleneu la sol·licitud</h3><p>Dades de l'entitat i de la persona que ho gestionarà.</p></div>
      <div class="card"><div class="card__step">2</div><h3>La revisem</h3><p>Us escrivim si ens falta alguna cosa. Us responem en 48 hores feineres.</p></div>
      <div class="card"><div class="card__step">3</div><h3>Rebeu les claus</h3><p>L'adreça del vostre web i l'accés per començar a preparar-lo.</p></div>
    </div>
  </div>
</section>

<section class="section" id="formulari">
  <div class="container-narrow">
    <div class="section__head">
      <h2>Demaneu la vostra instància</h2>
      <p class="lead">És gratuït demanar-la i no hi ha cap pagament en línia.</p>
    </div>

    <form method="post" action="<?= e(url('/sollicitud')) ?>" class="card">
      <?= csrf_field() ?>
      <p class="visually-hidden" aria-hidden="true">
        <label for="website_url">No ompliu aquest camp</label>
        <input type="text" id="website_url" name="website_url" tabindex="-1" autocomplete="off">
      </p>

      <h3>L'entitat</h3>
      <div class="form-row">
        <div class="field<?= isset($errors['entity']) ? ' field--error' : '' ?>">
          <label for="entity">Nom de l'entitat *</label>
          <input type="text" id="entity" name="entity" value="<?= e($old('entity')) ?>" placeholder="AFA Escola Jacint Verdaguer" required>
          <?php if (isset($errors['entity'])): ?><span class="field__error"><?= e($errors['entity']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="nif">NIF</label>
          <input type="text" id="nif" name="nif" value="<?= e($old('nif')) ?>" placeholder="G00000000">
        </div>
        <div class="field<?= isset($errors['town']) ? ' field--error' : '' ?>">
          <label for="town">Població *</label>
          <input type="text" id="town" name="town" value="<?= e($old('town')) ?>" required>
          <?php if (isset($errors['town'])): ?><span class="field__error"><?= e($errors['town']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="website">Web actual</label>
          <input type="url" id="website" name="website" value="<?= e($old('website')) ?>" placeholder="https://">
        </div>
      </div>

      <h3 style="margin-top:1.6rem">Qui ho gestionarà</h3>
      <div class="form-row">
        <div class="field<?= isset($errors['contact_name']) ? ' field--error' : '' ?>">
          <label for="contact_name">Nom i cognoms *</label>
          <input type="text" id="contact_name" name="contact_name" value="<?= e($old('contact_name')) ?>" required>
          <?php if (isset($errors['contact_name'])): ?><span class="field__error"><?= e($errors['contact_name']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="contact_role">Càrrec</label>
          <input type="text" id="contact_role" name="contact_role" value="<?= e($old('contact_role')) ?>" placeholder="Presidència de l'AFA">
        </div>
        <div class="field<?= isset($errors['contact_email']) ? ' field--error' : '' ?>">
          <label for="contact_email">Correu electrònic *</label>
          <input type="email" id="contact_email" name="contact_email" value="<?= e($old('contact_email')) ?>" required>
          <span class="field__hint">Aquí hi enviarem les claus d'accés.</span>
          <?php if (isset($errors['contact_email'])): ?><span class="field__error"><?= e($errors['contact_email']) ?></span><?php endif; ?>
        </div>
        <div class="field<?= isset($errors['contact_phone']) ? ' field--error' : '' ?>">
          <label for="contact_phone">Telèfon *</label>
          <input type="tel" id="contact_phone" name="contact_phone" value="<?= e($old('contact_phone')) ?>" required>
          <?php if (isset($errors['contact_phone'])): ?><span class="field__error"><?= e($errors['contact_phone']) ?></span><?php endif; ?>
        </div>
      </div>

      <h3 style="margin-top:1.6rem">La cursa</h3>
      <div class="form-row">
        <div class="field<?= isset($errors['slug']) ? ' field--error' : '' ?>">
          <label for="slug">Adreça que voleu</label>
          <div class="slug-field">
            <input type="text" id="slug" name="slug" value="<?= e($old('slug')) ?>" placeholder="elvostrecros">
            <span>.<?= e($domain) ?></span>
          </div>
          <span class="field__hint">Lletres, números i guions. Us direm si ja està agafada.</span>
          <?php if (isset($errors['slug'])): ?><span class="field__error"><?= e($errors['slug']) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label for="event_date">Data prevista de la cursa</label>
          <input type="date" id="event_date" name="event_date" value="<?= e($old('event_date')) ?>">
        </div>
        <div class="field">
          <label for="participants">Participants que espereu</label>
          <input type="number" id="participants" name="participants" value="<?= e($old('participants')) ?>" min="1" max="10000" placeholder="300">
        </div>
      </div>
      <div class="field">
        <label for="message">Expliqueu-nos què necessiteu</label>
        <textarea id="message" name="message" rows="3" placeholder="Nombre de curses, categories, si feu esmorzar…"><?= e($old('message')) ?></textarea>
      </div>

      <label class="checkbox<?= isset($errors['consent']) ? ' field--error' : '' ?>">
        <input type="checkbox" name="consent" value="1" <?= $old('consent') === '1' ? 'checked' : '' ?>>
        <span>Accepto que tracteu les meves dades per atendre aquesta sol·licitud.</span>
      </label>
      <?php if (isset($errors['consent'])): ?><span class="field__error"><?= e($errors['consent']) ?></span><?php endif; ?>

      <div class="flex" style="margin-top:1.4rem">
        <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 18) ?> Enviar la sol·licitud</button>
        <span class="field__hint">Us responem en 48 h feineres.</span>
      </div>
    </form>
  </div>
</section>
