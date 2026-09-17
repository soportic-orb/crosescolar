<?php /** Registre de correus. */ ?>
<div class="panel mb-2">
  <div class="panel__head"><h2>Prova d'enviament</h2></div>
  <div class="panel__body">
    <form method="post" action="<?= e(url('/admin/correus/prova')) ?>" class="flex">
      <?= csrf_field() ?>
      <input type="email" name="to" placeholder="adreça@exemple.cat" value="<?= e(\Cros\Core\Auth::user()['email'] ?? '') ?>" style="max-width:280px">
      <button class="btn" type="submit">Enviar correu de prova</button>
      <a class="btn btn--ghost" href="<?= e(url('/admin/configuracio/email')) ?>">Configuració de correu</a>
    </form>
  </div>
</div>

<div class="panel">
  <div class="panel__head"><h2>Correus enviats</h2></div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <thead><tr><th>Data</th><th>Destinatari</th><th>Assumpte</th><th>Estat</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td class="text-soft" style="font-size:.85rem;white-space:nowrap"><?= e(dt($row['created_at'])) ?></td>
            <td><?= e($row['recipient']) ?></td>
            <td><?= e($row['subject']) ?></td>
            <td>
              <span class="badge <?= $row['status'] === 'sent' ? 'badge--green' : 'badge--red' ?>"><?= e($row['status']) ?></span>
              <?php if (!empty($row['error'])): ?><div class="text-soft" style="font-size:.8rem"><?= e($row['error']) ?></div><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="4" class="text-soft">Encara no s'ha enviat cap correu.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
