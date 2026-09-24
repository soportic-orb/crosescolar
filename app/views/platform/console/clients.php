<?php /** Llista de clients. */ ?>
<div class="panel">
  <div class="panel__head"><h2>Clients</h2></div>
  <div class="panel__body table-wrap">
    <?php if (!$clients): ?>
      <p class="text-soft" style="margin:0">Encara no hi ha cap client. Se'n crea un en donar d'alta una instància.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Entitat</th><th>Població</th><th>Contacte</th><th>Instàncies</th><th>Alta</th></tr></thead>
        <tbody>
        <?php foreach ($clients as $client): ?>
          <tr>
            <td><strong><?= e($client['name']) ?></strong><?php if ($client['nif']): ?><br><small class="text-soft">NIF <?= e($client['nif']) ?></small><?php endif; ?></td>
            <td><?= e($client['town'] ?? '—') ?></td>
            <td>
              <?= e($client['contact_name']) ?><br>
              <small class="text-soft"><a href="mailto:<?= e($client['contact_email']) ?>"><?= e($client['contact_email']) ?></a></small>
            </td>
            <td><?= (int) ($counts[(int) $client['id']] ?? 0) ?></td>
            <td><?= e(ca_date(substr((string) $client['created_at'], 0, 10))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
