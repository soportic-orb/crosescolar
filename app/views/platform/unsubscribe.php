<?php
/** La baixa dels enviaments, des de l'enllaç del correu. */
use Cros\Core\Icons;

$mask = static function (string $email): string {
    [$user, $host] = array_pad(explode('@', $email, 2), 2, '');

    return mb_substr($user, 0, 2) . str_repeat('·', max(1, min(6, mb_strlen($user) - 2))) . '@' . $host;
};
?>
<section class="section">
  <div class="container-narrow">
    <div class="card" style="text-align:center">
      <?php if ($who === null): ?>
        <h1 style="margin:0 0 .6rem">Aquest enllaç no funciona</h1>
        <p class="lead" style="margin:0 auto;max-width:48ch">
          Potser s'ha tallat en copiar-lo. Obriu un altre cop l'enllaç del correu, o responeu-lo i us donarem de baixa nosaltres.
        </p>
      <?php elseif ($done): ?>
        <div class="card__icon" style="margin:0 auto 1rem"><?= Icons::svg('check', 'icon', 26) ?></div>
        <h1 style="margin:0 0 .6rem">Us hem donat de baixa</h1>
        <p class="lead" style="margin:0 auto;max-width:48ch">
          L'adreça <strong><?= e($mask($who['email'])) ?></strong> ja no rebrà més enviaments informatius.
          Els correus del vostre web (accessos, avisos, factures) us continuaran arribant.
        </p>
      <?php else: ?>
        <h1 style="margin:0 0 .6rem">Voleu deixar de rebre aquests correus?</h1>
        <p class="lead" style="margin:0 auto 1.4rem;max-width:48ch">
          L'adreça <strong><?= e($mask($who['email'])) ?></strong> no rebrà més enviaments informatius de la plataforma.
        </p>
        <form method="post" action="<?= e(url('/baixa/' . $token)) ?>">
          <button class="btn" type="submit">Doneu-me de baixa</button>
        </form>
      <?php endif; ?>
      <div class="flex" style="justify-content:center;margin-top:1.4rem">
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Tornar a la portada</a>
      </div>
    </div>
  </div>
</section>
