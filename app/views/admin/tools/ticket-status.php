<?php /** Estat d'un tiquet (des del codi QR). */ ?>
<div class="panel" style="max-width:560px">
  <div class="panel__head"><h2>Estat del tiquet</h2></div>
  <div class="panel__body">
    <div class="scan-result scan-result--<?= e($result['status'] === 'ok' ? 'ok' : ($result['status'] === 'warning' ? 'warning' : 'error')) ?>">
      <?= e($result['message']) ?>
    </div>
    <?php if (!empty($result['ticket'])): ?>
      <table class="admin-table mt-2">
        <tbody>
          <tr><th>Codi</th><td class="mono"><?= e($result['ticket']['code']) ?></td></tr>
          <tr><th>Tipus</th><td><?= e($result['ticket']['type_name'] ?? '—') ?></td></tr>
          <tr><th>Comanda</th><td><?= e($result['ticket']['order_code']) ?></td></tr>
          <tr><th>Comprador</th><td><?= e($result['ticket']['buyer_name']) ?></td></tr>
          <tr><th>Estat</th><td><?= e($result['ticket']['status']) ?></td></tr>
        </tbody>
      </table>
      <?php if ($result['status'] === 'ok'): ?>
        <form method="post" action="<?= e(url('/admin/validacio')) ?>" class="mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="code" value="<?= e($result['ticket']['code']) ?>">
          <button class="btn" type="submit">Validar l'entrada</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
    <p class="mt-2"><a href="<?= e(url('/admin/validacio')) ?>">← Tornar al lector</a></p>
  </div>
</div>
