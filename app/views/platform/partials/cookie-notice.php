<?php
/**
 * Avís de galetes.
 *
 * Aquest web només fa servir una galeta tècnica (la de sessió, necessària per
 * protegir els formularis), i per a les galetes necessàries la normativa
 * europea demana **informar**, no demanar permís. Per això l'avís informa i es
 * tanca, i no bloqueja res ni té un botó de «rebutjar» que no faria res.
 *
 * El dia que la plataforma posi analítica o qualsevol galeta que no sigui
 * necessària, això s'ha de refer: aleshores caldrà consentiment de debò, amb
 * opcions separades i sense res carregat abans de tenir-lo.
 */
if (!\Cros\Core\Settings::bool('platform_cookie_banner', true)) {
    return;
}
$text = trim((string) setting('platform_cookie_text',
    'Aquest web fa servir una sola galeta tècnica, necessària per mantenir la sessió i protegir els formularis.'));
?>
<div class="cookie-notice" id="cookie-notice" hidden>
  <div class="cookie-notice__box" role="region" aria-label="Avís sobre les galetes">
    <p><?= e($text) ?> <a href="<?= e(url('/galetes')) ?>">Més detalls</a>.</p>
    <button type="button" class="btn btn--sm" data-cookie-ok>Entesos</button>
  </div>
</div>
<script>
// Sense llibreries ni galetes de tercers: es recorda al navegador mateix.
(function () {
  var clau = 'cros_avis_galetes';
  var avis = document.getElementById('cookie-notice');
  if (!avis) { return; }
  var llegit = false;
  try { llegit = window.localStorage.getItem(clau) === '1'; } catch (e) { llegit = false; }
  if (llegit) { return; }
  avis.hidden = false;
  avis.querySelector('[data-cookie-ok]').addEventListener('click', function () {
    avis.hidden = true;
    try { window.localStorage.setItem(clau, '1'); } catch (e) { /* navegació privada */ }
  });
})();
</script>
