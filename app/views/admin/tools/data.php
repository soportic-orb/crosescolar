<?php
/** «Les meves dades»: exportació completa del cros. */
use Cros\Core\Icons;

$size = static function (int $bytes): string {
    $units = ['B', 'kB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
};
?>
<div class="panel">
  <div class="panel__head"><h2>Emporteu-vos les vostres dades</h2></div>
  <div class="panel__body">
    <p>
      Aquest web és vostre i les dades també. Amb un clic us les descarregueu totes
      en un sol fitxer ZIP, sense haver de demanar res a ningú.
    </p>

    <div class="grid-cards mt-2">
      <?php foreach ($counts as $label => $total): ?>
        <div class="kpi">
          <div class="kpi__label"><?= e($label) ?></div>
          <div class="kpi__value"><?= number_format($total, 0, ',', '.') ?></div>
        </div>
      <?php endforeach; ?>
      <div class="kpi">
        <div class="kpi__label"><?= Icons::svg('image', 'icon', 16) ?> Fitxers pujats</div>
        <div class="kpi__value"><?= number_format($uploads, 0, ',', '.') ?></div>
        <div class="kpi__foot"><?= e($size((int) $bytes)) ?></div>
      </div>
    </div>

    <h3 style="margin:1.6rem 0 .6rem;font-size:1rem">Què hi ha dins</h3>
    <table class="admin-table">
      <tbody>
        <tr>
          <th style="width:220px"><code>fulls/</code></th>
          <td>Un full de càlcul per cada llista (inscripcions, resultats, categories,
            comandes, configuració…). S'obren amb l'Excel, el Numbers o el LibreOffice.</td>
        </tr>
        <tr>
          <th><code>base-de-dades.sql</code></th>
          <td>Còpia completa de la base de dades, per si algun dia voleu tornar a muntar
            el web en un altre servidor.</td>
        </tr>
        <tr>
          <th><code>fitxers/</code></th>
          <td>Les imatges i els documents que heu pujat.</td>
        </tr>
      </tbody>
    </table>

    <div class="alert alert--warning mt-2">
      Dins hi ha <strong>dades personals de les famílies inscrites</strong> (noms, correus
      i telèfons). Deseu el fitxer en un lloc segur i no el compartiu sense pensar-hi.
    </div>

    <form method="post" action="<?= e(url('/admin/dades')) ?>" class="form-actions">
      <?= csrf_field() ?>
      <button class="btn" type="submit"><?= Icons::svg('download', 'icon', 16) ?> Descarregar-ho tot</button>
      <span class="hint">Amb molts inscrits i fotos pot trigar uns segons.</span>
    </form>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Llistes incloses</h2></div>
  <div class="panel__body">
    <p class="text-soft" style="margin:0 0 .6rem">
      <?= count($tables) ?> llistes en total:
    </p>
    <p style="margin:0;line-height:2">
      <?php foreach ($tables as $table): ?><span class="badge"><?= e($table) ?></span> <?php endforeach; ?>
    </p>
  </div>
</div>
