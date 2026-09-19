<?php /** «Les meves inscripcions»: pas 2, escriure el codi rebut. */ ?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => 'Les meves inscripcions',
    'subtitle' => 'Escriviu el codi que us hem enviat per correu.',
    'breadcrumb' => ['Les meves inscripcions' => '/les-meves-inscripcions', 'Codi' => ''],
]) ?>
<section class="section">
  <div class="container-narrow">
    <div class="card">
      <p style="margin-top:0">
        Hem enviat un codi de sis xifres a <strong><?= e($email) ?></strong>.
        Val <?= (int) $minutes ?> minuts.
      </p>
      <form method="post" action="<?= e(url('/les-meves-inscripcions/codi')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
          <label for="code">Codi d'accés *</label>
          <input type="text" id="code" name="code" required autofocus
                 inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code"
                 style="font-size:1.6rem;letter-spacing:.4rem;text-align:center;max-width:12rem">
        </div>
        <button class="btn" type="submit"><?= \Cros\Core\Icons::svg('check', 'icon', 18) ?> Entrar</button>
      </form>
    </div>
    <form method="post" action="<?= e(url('/les-meves-inscripcions/tornar')) ?>" style="margin-top:1.2rem">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit">Provar amb una altra adreça</button>
    </form>
  </div>
</section>
