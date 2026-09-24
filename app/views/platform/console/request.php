<?php
/** Fitxa d'una sol·licitud. */
use Cros\Platform\Request;

$tones = ['pending' => 'amber', 'info' => 'blue', 'approved' => 'green', 'rejected' => 'red'];
$status = (string) $request['status'];
?>
<p><a href="<?= e(url('/sollicituds')) ?>">← Totes les sol·licituds</a></p>

<div class="panel">
  <div class="panel__head">
    <h2><?= e($request['entity']) ?></h2>
    <span class="badge badge--<?= e($tones[$status] ?? '') ?>"><?= e(Request::STATUSES[$status] ?? $status) ?></span>
    <div class="spacer">
      <?php if ($status !== 'approved'): ?>
        <a class="btn btn--sm" href="<?= e(url('/instancies/nova', ['peticio' => (int) $request['id']])) ?>">Crear la instància</a>
      <?php elseif ($instance): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/instancies/' . (int) $instance['id'])) ?>">Veure la instància</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="panel__body">
    <table class="admin-table">
      <tbody>
        <tr><th style="width:230px">Número</th><td><strong><?= e($request['code']) ?></strong></td></tr>
        <tr><th>Entitat</th><td><?= e($request['entity']) ?><?= $request['nif'] ? ' · NIF ' . e($request['nif']) : '' ?></td></tr>
        <tr><th>Població</th><td><?= e($request['town'] ?? '—') ?></td></tr>
        <?php if ($request['website']): ?>
          <tr><th>Web actual</th><td><a href="<?= e($request['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e($request['website']) ?></a></td></tr>
        <?php endif; ?>
        <tr><th>Contacte</th><td>
          <?= e($request['contact_name']) ?><?= $request['contact_role'] ? ' · ' . e($request['contact_role']) : '' ?><br>
          <a href="mailto:<?= e($request['contact_email']) ?>"><?= e($request['contact_email']) ?></a>
          <?= $request['contact_phone'] ? ' · ' . e($request['contact_phone']) : '' ?>
        </td></tr>
        <tr><th>Adreça demanada</th><td><?= $request['slug'] ? '<code>' . e($request['slug']) . '</code>' : '—' ?></td></tr>
        <tr><th>Idioma</th><td><?= $request['language'] === 'es' ? 'Castellà' : 'Català' ?></td></tr>
        <tr><th>Cursa prevista</th><td><?= $request['event_date'] ? e(ca_date((string) $request['event_date'], true)) : '—' ?></td></tr>
        <tr><th>Participants previstos</th><td><?= $request['participants'] ? (int) $request['participants'] : '—' ?></td></tr>
        <?php if ($request['referral']): ?><tr><th>Com ens han conegut</th><td><?= e($request['referral']) ?></td></tr><?php endif; ?>
        <?php if ($request['message']): ?><tr><th>Missatge</th><td><?= nl2br(e($request['message'])) ?></td></tr><?php endif; ?>
        <tr><th>Rebuda</th><td><?= e(ca_date(substr((string) $request['created_at'], 0, 10), true)) ?> · <?= e(substr((string) $request['created_at'], 11, 5)) ?> h<?= $request['ip'] ? ' · ' . e($request['ip']) : '' ?></td></tr>
        <?php if ($request['reason']): ?><tr><th>Motiu de la decisió</th><td><?= nl2br(e($request['reason'])) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (in_array($status, ['pending', 'info'], true)): ?>
  <div class="panel mt-2">
    <div class="panel__head"><h2>Desestimar</h2></div>
    <div class="panel__body">
      <form method="post" action="<?= e(url('/sollicituds/' . (int) $request['id'] . '/decidir')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject">
        <div class="field">
          <label for="reason">Motiu</label>
          <textarea id="reason" name="reason" rows="3" placeholder="Si l'ompliu, s'envia aquest text per correu a qui la va enviar."></textarea>
          <p class="hint">Si el deixeu buit, la sol·licitud es tanca sense avisar ningú.</p>
        </div>
        <div class="form-actions">
          <button class="btn btn--danger" type="submit">Desestimar la sol·licitud</button>
        </div>
      </form>
    </div>
  </div>
<?php elseif ($status === 'rejected'): ?>
  <form method="post" action="<?= e(url('/sollicituds/' . (int) $request['id'] . '/decidir')) ?>" class="mt-2">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="pending">
    <button class="btn btn--ghost" type="submit">Tornar-la a deixar pendent</button>
  </form>
<?php endif; ?>
