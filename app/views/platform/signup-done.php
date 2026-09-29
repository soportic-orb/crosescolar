<?php
/**
 * «Ja està: mireu el correu.»
 *
 * El web ja existeix, però la porta del panell és el botó que hem enviat a
 * l'adreça que han escrit. Per això aquí no hi ha cap enllaç per entrar:
 * seria saltar-se la validació del correu.
 *
 * @var array{slug:string,url:string,email:string} $alta
 */
use Cros\Core\Icons;
?>
<section class="section">
  <div class="container-narrow">
    <div class="card" style="text-align:center">
      <div class="card__icon" style="margin:0 auto 1rem"><?= Icons::svg('mail', 'icon', 26) ?></div>
      <h1 style="margin:0 0 .6rem">Ja teniu el vostre web</h1>
      <p class="lead" style="margin:0 auto;max-width:52ch">
        És a <strong><?= e(preg_replace('#^https?://#', '', (string) $alta['url'])) ?></strong>
        i de moment només el veieu vosaltres.
      </p>

      <div class="notice-box" style="margin-top:1.6rem;text-align:left">
        <p style="margin:0 0 .6rem"><strong>Mireu el correu</strong></p>
        <p style="margin:0">
          Hem escrit a <strong><?= e((string) $alta['email']) ?></strong> amb el botó
          «Accedeix al teu Panell d'Administració». En prémer-lo confirmareu que l'adreça és
          vostra i entrareu al panell, on us podreu posar una contrasenya. És l'única manera
          d'entrar-hi el primer cop.
        </p>
      </div>

      <p class="field__hint" style="margin-top:1.4rem">
        Si no us arriba en uns minuts, mireu la carpeta de correu brossa. El vostre panell
        serà sempre a <code><?= e(preg_replace('#^https?://#', '', (string) $alta['url'])) ?>/admin</code>.
      </p>
      <div class="flex" style="justify-content:center;margin-top:1.2rem">
        <a class="btn btn--ghost" href="<?= e(url('/')) ?>">Tornar a la portada</a>
      </div>
    </div>
  </div>
</section>
