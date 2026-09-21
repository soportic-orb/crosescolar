<?php
/** Redacció d'un enviament de correu. */
use Cros\Models\Mailing;

$isNew = (int) ($row['id'] ?? 0) === 0;
$selected = array_filter(array_map('intval', is_array($row['categories'] ?? '')
    ? $row['categories']
    : explode(',', (string) ($row['categories'] ?? ''))));
$audience = (string) ($row['audience'] ?? 'all');
?>
<form method="post" action="<?= e(url('/admin/enviaments' . ($isNew ? '/nou' : '/' . (int) $row['id'] . '/editar'))) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($title) ?></h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/admin/enviaments')) ?>">← Tots els enviaments</a>
    </div>
    <div class="panel__body form-stack">

      <div class="field">
        <label for="subject">Tema del correu *</label>
        <input type="text" id="subject" name="subject" value="<?= e($row['subject'] ?? '') ?>"
               maxlength="190" required placeholder="Tot a punt per al cros de diumenge!">
        <span class="hint">És el que es llegeix a la safata d'entrada. Curt i clar.</span>
        <?php if (isset($errors['subject'])): ?><span class="error"><?= e($errors['subject']) ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="body">Cos del correu *</label>
        <?= \Cros\Core\View::partial('admin/partials/editor', [
            'name' => 'body',
            'value' => (string) ($row['body'] ?? ''),
            'id' => 'body',
            'label' => 'Cos del correu',
            'placeholder' => 'Escriviu aquí el correu…',
        ]) ?>
        <span class="hint">
          Podeu escriure-hi marcadors que se substitueixen a cada correu:
          <?php foreach (Mailing::PLACEHOLDERS as $tag => $what): ?>
            <code><?= e($tag) ?></code> (<?= e(mb_strtolower($what)) ?>)<?= $tag === array_key_last(Mailing::PLACEHOLDERS) ? '.' : ', ' ?>
          <?php endforeach; ?>
        </span>
        <?php if (isset($errors['body'])): ?><span class="error"><?= e($errors['body']) ?></span><?php endif; ?>
      </div>

      <h3 style="margin:.8rem 0 0">Destinataris</h3>
      <div class="field">
        <?php foreach (Mailing::AUDIENCES as $value => $label): ?>
          <label class="switch" style="margin-bottom:.4rem">
            <input type="radio" name="audience" value="<?= e($value) ?>" <?= $audience === $value ? 'checked' : '' ?>
                   data-audience>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="field" data-audience-for="category">
        <label>Categories</label>
        <div class="check-grid">
          <?php foreach ($categories as $category): ?>
            <label class="switch">
              <input type="checkbox" name="categories[]" value="<?= (int) $category['id'] ?>"
                     <?= in_array((int) $category['id'], $selected, true) ? 'checked' : '' ?>>
              <span><?= e($category['name']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($errors['categories'])): ?><span class="error"><?= e($errors['categories']) ?></span><?php endif; ?>
      </div>

      <div class="field" data-audience-for="manual">
        <label for="manual_emails">Adreces</label>
        <textarea id="manual_emails" name="manual_emails" rows="4"
                  placeholder="una@example.cat, altra@example.cat"><?= e($row['manual_emails'] ?? '') ?></textarea>
        <span class="hint">Separades per comes, espais o salts de línia. Serveix per avisar el voluntariat o fer proves.</span>
        <?php if (isset($errors['manual_emails'])): ?><span class="error"><?= e($errors['manual_emails']) ?></span><?php endif; ?>
      </div>

      <div class="field" data-audience-for="all category">
        <label for="reg_status">Quines inscripcions</label>
        <select id="reg_status" name="reg_status">
          <?php foreach (Mailing::STATUSES as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= (string) ($row['reg_status'] ?? 'confirmed') === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Les anul·lades no reben mai cap correu. Cada adreça en rep un de sol, encara que hi hagi més d'un participant a la família.</span>
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= \Cros\Core\Icons::svg('check', 'icon', 16) ?> Desar l'esborrany</button>
      <a class="btn btn--ghost" href="<?= e(url('/admin/enviaments')) ?>">Cancel·lar</a>
    </div>
  </div>
</form>
