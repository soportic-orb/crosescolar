<?php
/** Tauler de la plataforma. */
use Cros\Core\Icons;
use Cros\Platform\Instance;
?>
<div class="grid-cards">
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('run', 'icon', 16) ?> Instàncies</div>
    <div class="kpi__value"><?= (int) $stats['instances'] ?></div>
    <div class="kpi__foot"><?= (int) $stats['active'] ?> en marxa · <?= (int) $stats['published'] ?> publicades</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('users', 'icon', 16) ?> Clients</div>
    <div class="kpi__value"><?= (int) $stats['clients'] ?></div>
    <div class="kpi__foot">entitats amb el web en actiu</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('mail', 'icon', 16) ?> Sol·licituds</div>
    <div class="kpi__value"><?= (int) $stats['pending'] ?></div>
    <div class="kpi__foot">esperant resposta</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('chart', 'icon', 16) ?> Inscripcions</div>
    <div class="kpi__value"><?= number_format((int) $stats['registrations'], 0, ',', '.') ?></div>
    <div class="kpi__foot">sumades de tots els cros</div>
  </div>
</div>

<?php if ($requests): ?>
  <div class="panel mt-2">
    <div class="panel__head">
      <h2>Sol·licituds pendents</h2>
      <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/sollicituds')) ?>">Veure-les totes</a>
    </div>
    <div class="panel__body table-wrap">
      <table class="admin-table">
        <thead><tr><th>Codi</th><th>Entitat</th><th>Població</th><th>Contacte</th><th>Rebuda</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($requests as $request): ?>
          <tr>
            <td><strong><?= e($request['code']) ?></strong></td>
            <td><?= e($request['entity']) ?></td>
            <td><?= e($request['town'] ?? '—') ?></td>
            <td><?= e($request['contact_name']) ?><br><small class="text-soft"><?= e($request['contact_email']) ?></small></td>
            <td><?= e(ca_date(substr((string) $request['created_at'], 0, 10))) ?></td>
            <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/sollicituds/' . (int) $request['id'])) ?>">Obrir</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert--success mt-2">No hi ha cap sol·licitud pendent.</div>
<?php endif; ?>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Properes curses</h2>
    <a class="btn btn--ghost btn--sm spacer" href="<?= e(url('/instancies')) ?>">Totes les instàncies</a>
  </div>
  <div class="panel__body table-wrap">
    <?php if (!$upcoming): ?>
      <p class="text-soft" style="margin:0">Cap cursa amb data futura, de moment.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Cros</th><th>Adreça</th><th>Data</th><th>Inscrits</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($upcoming as $row): ?>
          <tr>
            <td><strong><?= e($row['site_name']) ?></strong><?php if ($row['town']): ?><br><small class="text-soft"><?= e($row['town']) ?></small><?php endif; ?></td>
            <td><code><?= e($row['slug']) ?></code></td>
            <td><?= e(ca_date((string) $row['event_date'])) ?></td>
            <td><?= (int) $row['registrations'] ?></td>
            <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/instancies/' . (int) $row['id'])) ?>">Fitxa</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Últimes instàncies</h2>
    <a class="btn btn--sm spacer" href="<?= e(url('/instancies/nova')) ?>">Nova instància</a>
  </div>
  <div class="panel__body table-wrap">
    <?php if (!$instances): ?>
      <p class="text-soft" style="margin:0">Encara no hi ha cap instància. La primera es crea des de «Nova instància».</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Cros</th><th>Adreça</th><th>Estat</th><th>Creada</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($instances as $row): ?>
          <tr>
            <td><strong><?= e($row['site_name']) ?></strong></td>
            <td><code><?= e($row['slug']) ?></code></td>
            <td><span class="badge badge--<?= e(Instance::tone((string) $row['status'])) ?>"><?= e(Instance::label((string) $row['status'])) ?></span></td>
            <td><?= e(ca_date(substr((string) $row['created_at'], 0, 10))) ?></td>
            <td class="text-right"><a class="btn btn--ghost btn--sm" href="<?= e(url('/instancies/' . (int) $row['id'])) ?>">Fitxa</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
