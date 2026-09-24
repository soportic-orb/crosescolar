<?php /** Registre d'activitat de la plataforma. */ ?>
<div class="panel">
  <div class="panel__head"><h2>Registre d'activitat</h2></div>
  <div class="panel__body table-wrap">
    <?php if (!$rows): ?>
      <p class="text-soft" style="margin:0">Encara no hi ha res apuntat.</p>
    <?php else: ?>
      <table class="admin-table">
        <thead><tr><th>Quan</th><th>Qui</th><th>Acció</th><th>Sobre</th><th>Detall</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><small><?= e(substr((string) $row['created_at'], 0, 16)) ?></small></td>
            <td><?= e($row['user_name'] ?? '—') ?><?php if ($row['ip']): ?><br><small class="text-soft"><?= e($row['ip']) ?></small><?php endif; ?></td>
            <td><code><?= e($row['action']) ?></code></td>
            <td><?= $row['subject'] ? e($row['subject'] . ' #' . (int) $row['subject_id']) : '—' ?></td>
            <td><small class="text-soft"><?= e((string) ($row['context'] ?? '')) ?></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
