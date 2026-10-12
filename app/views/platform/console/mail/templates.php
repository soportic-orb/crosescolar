<?php
/**
 * Les plantilles base: el punt de partida dels correus nous.
 *
 * @var array<int,array<string,mixed>> $templates
 */
use Cros\Core\View;
use Cros\Platform\Mail\Brand;
use Cros\Platform\Mailout;
use Cros\Platform\Platform;

$brand = Brand::of(Platform::domain());
?>
<div class="ml">
  <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem">
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments')) ?>">← Enviaments</a>
    <p class="text-soft" style="margin:0;flex:1 1 320px">
      Cada correu nou en fa una còpia. No porten colors ni noms escrits: agafen els del domini des d'on surt el correu.
    </p>
    <form method="post" action="<?= e(url('/enviaments/plantilles')) ?>" style="display:flex;gap:6px">
      <?= csrf_field() ?>
      <input class="input" type="text" name="name" maxlength="190" placeholder="Nom de la plantilla nova" required aria-label="Nom de la plantilla nova">
      <button class="btn btn--sm" type="submit">Nova plantilla</button>
    </form>
  </div>
  <div class="ml-tpls" style="grid-template-columns:repeat(auto-fill, minmax(200px, 1fr))">
    <?php foreach ($templates as $t): ?>
      <div class="ml-tpl" style="cursor:default">
        <a href="<?= e(url('/enviaments/' . (int) $t['id'] . '/editar')) ?>" aria-label="Edita «<?= e($t['template_name']) ?>»">
          <?= View::partial('platform/console/mail/_thumb', ['doc' => Mailout::doc($t), 'size' => 'lg', 'brand' => $brand]) ?></a>
        <b><?= e($t['template_name']) ?></b>
        <span class="text-soft" style="font-size:.78rem"><?= e($t['subject'] ?: 'Sense assumpte suggerit') ?> · versió <?= (int) $t['version'] ?></span>
        <div class="ml-actions" style="justify-content:flex-start">
          <a class="btn btn--ghost btn--sm" href="<?= e(url('/enviaments/' . (int) $t['id'] . '/editar')) ?>">Edita</a>
          <form method="post" action="<?= e(url('/enviaments/' . (int) $t['id'] . '/accio')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="duplicate">
            <button class="btn btn--ghost btn--sm" type="submit">Duplica</button>
          </form>
          <form method="post" action="<?= e(url('/enviaments/' . (int) $t['id'] . '/accio')) ?>" data-confirm="Esborrar la plantilla «<?= e($t['template_name']) ?>»? Els correus que ja se n'han fet no canvien.">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <button class="btn btn--danger btn--sm" type="submit">Esborra</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
