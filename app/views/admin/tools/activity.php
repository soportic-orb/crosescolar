<?php /** Registre d'activitat. */ ?>
<div class="panel">
  <div class="panel__head"><h2>Registre d'activitat</h2></div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <thead><tr><th>Data</th><th>Usuari</th><th>Acció</th><th>Element</th><th>Detalls</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="text-soft" style="font-size:.85rem;white-space:nowrap"><?= e(dt($row['created_at'])) ?></td>
            <td><?= e($row['user_name'] ?? 'Sistema') ?></td>
            <td><span class="badge"><?= e($row['action']) ?></span></td>
            <td class="text-soft"><?= e($row['entity']) ?><?= $row['entity_id'] ? ' #' . (int) $row['entity_id'] : '' ?></td>
            <td class="mono" style="font-size:.8rem"><?= e(excerpt((string) $row['details'], 60)) ?></td>
            <td class="text-soft" style="font-size:.8rem"><?= e($row['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-soft">Sense activitat registrada.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
