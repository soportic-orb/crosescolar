<?php
/**
 * Redacció d'un enviament de la plataforma.
 * @var array<string,mixed> $row
 * @var array<string,string> $errors
 * @var array<int,array<string,mixed>> $lists
 */
use Cros\Core\Icons;
use Cros\Core\View;
use Cros\Platform\MailTemplate;
use Cros\Platform\Mailout;

$isNew = (int) ($row['id'] ?? 0) === 0;
$audience = (string) ($row['audience'] ?? 'clients');
?>
<form method="post" action="<?= e(url('/enviaments' . ($isNew ? '/nou' : '/' . (int) $row['id'] . '/editar'))) ?>" data-dirty-check>
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel__head">
      <h2><?= e($title) ?></h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/enviaments')) ?>">← Tots els enviaments</a>
    </div>
    <div class="panel__body form-stack">

      <div class="field">
        <label for="subject">Assumpte *</label>
        <input type="text" id="subject" name="subject" value="<?= e($row['subject'] ?? '') ?>"
               maxlength="190" required placeholder="Novetats del curs i com preparar el vostre cros">
        <span class="hint">També hi valen els marcadors: <code>{{entitat}}</code>, <code>{{nom}}</code>.</span>
        <?php if (isset($errors['subject'])): ?><span class="error"><?= e($errors['subject']) ?></span><?php endif; ?>
      </div>

      <div class="field">
        <label for="body">Cos del correu *</label>
        <?= View::partial('admin/partials/editor', [
            'name' => 'body',
            'value' => (string) ($row['body'] ?? ''),
            'id' => 'body',
            'label' => 'Cos del correu',
            'placeholder' => 'Escriviu aquí el correu…',
        ]) ?>
        <span class="hint">
          La capçalera i el peu els posa la <a href="<?= e(url('/enviaments/plantilla')) ?>">plantilla</a>.
          Marcadors que se substitueixen a cada correu:
          <?php foreach (MailTemplate::PLACEHOLDERS as $tag => $what): ?>
            <code><?= e($tag) ?></code> (<?= e(mb_strtolower($what)) ?>)<?= $tag === array_key_last(MailTemplate::PLACEHOLDERS) ? '.' : ', ' ?>
          <?php endforeach; ?>
        </span>
        <?php if (isset($errors['body'])): ?><span class="error"><?= e($errors['body']) ?></span><?php endif; ?>
      </div>

      <h3 style="margin:.8rem 0 0">A qui va</h3>
      <div class="field">
        <?php foreach (Mailout::AUDIENCES as $value => $label): ?>
          <label class="switch" style="margin-bottom:.4rem">
            <input type="radio" name="audience" value="<?= e($value) ?>" <?= $audience === $value ? 'checked' : '' ?> data-audience>
            <span><?= e($label) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="field" data-audience-for="list">
        <label for="list_id">Quina llista</label>
        <select id="list_id" name="list_id">
          <option value="0">— Trieu-ne una —</option>
          <?php foreach ($lists as $list): ?>
            <option value="<?= (int) $list['id'] ?>" <?= (int) ($row['list_id'] ?? 0) === (int) $list['id'] ? 'selected' : '' ?>>
              <?= e($list['name']) ?> (<?= (int) $list['active_contacts'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!$lists): ?>
          <span class="hint">Encara no n'hi ha cap: <a href="<?= e(url('/enviaments/llistes')) ?>">creeu-ne una</a>.</span>
        <?php endif; ?>
        <?php if (isset($errors['list_id'])): ?><span class="error"><?= e($errors['list_id']) ?></span><?php endif; ?>
      </div>

      <div class="field" data-audience-for="instances">
        <label for="instance_status">Quins webs</label>
        <select id="instance_status" name="instance_status">
          <?php foreach (Mailout::SCOPES as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= (string) ($row['instance_status'] ?? 'active') === $value ? 'selected' : '' ?>>
              <?= e($label) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" data-audience-for="manual">
        <label for="manual_emails">Adreces</label>
        <textarea id="manual_emails" name="manual_emails" rows="6"
                  placeholder="anna@example.cat&#10;Pau Soler &lt;pau@example.cat&gt;&#10;marta@example.cat, Marta Vila, AFA Sant Jordi"><?= e($row['manual_emails'] ?? '') ?></textarea>
        <span class="hint">
          Una per línia. S'accepta l'adreça sola, la forma <code>Nom &lt;adreça&gt;</code> i les columnes
          d'un full de càlcul separades per comes o punts i comes (adreça, nom, entitat).
        </span>
        <?php if (isset($errors['manual_emails'])): ?><span class="error"><?= e($errors['manual_emails']) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="panel__foot">
      <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Desar l'esborrany</button>
      <a class="btn btn--ghost" href="<?= e(url('/enviaments')) ?>">Cancel·lar</a>
    </div>
  </div>
</form>
