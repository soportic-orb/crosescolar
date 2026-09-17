<?php
/** Validació de tiquets amb lector de QR. */
use Cros\Core\Icons;
?>
<div class="grid-cards mb-2">
  <div class="kpi"><div class="kpi__label">Tiquets pendents de validar</div><div class="kpi__value"><?= (int) $stats['valid'] ?></div></div>
  <div class="kpi"><div class="kpi__label">Tiquets validats</div><div class="kpi__value"><?= (int) $stats['used'] ?></div></div>
</div>

<div class="panel" data-scanner="<?= e(url('/admin/validacio')) ?>" data-token="<?= e(csrf_token()) ?>" data-ajax>
  <div class="panel__head"><h2><?= Icons::svg('qr', 'icon', 18) ?> Validació de tiquets</h2></div>
  <div class="panel__body">
    <div class="grid-cards" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr))">
      <div>
        <video id="scanner-video" class="scanner-video" playsinline muted></video>
        <div class="flex mt-2">
          <button class="btn" type="button" id="scanner-start">Activar la càmera</button>
        </div>
        <p class="text-soft mt-1" style="font-size:.85rem">
          El lector funciona amb Chrome a Android. Si no hi ha càmera, introduïu el codi a mà.
        </p>
      </div>
      <div>
        <form method="post" action="<?= e(url('/admin/validacio')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <div class="field">
            <label for="code">Codi del tiquet</label>
            <input type="text" id="code" name="code" data-code-input placeholder="TXXXXXXXXX" autofocus autocomplete="off" style="text-transform:uppercase">
          </div>
          <div class="flex">
            <button class="btn" type="submit">Validar</button>
            <label class="switch"><input type="checkbox" name="check_only" value="1"> <span>Només consultar</span></label>
          </div>
        </form>
        <div id="scanner-output" class="mt-2">
          <?php if (!empty($result)): ?>
            <div class="scan-result scan-result--<?= e($result['status'] === 'ok' ? 'ok' : ($result['status'] === 'warning' ? 'warning' : 'error')) ?>">
              <?= e($result['message']) ?>
              <?php if (!empty($result['ticket'])): ?>
                <div class="text-soft mono mt-1"><?= e($result['ticket']['code']) ?> · <?= e($result['ticket']['buyer_name'] ?? '') ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head"><h2>Últims tiquets validats</h2></div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <tbody>
        <?php foreach ($recent as $ticket): ?>
          <tr>
            <td class="mono"><?= e($ticket['code']) ?></td>
            <td><?= e($ticket['type_name'] ?? '') ?></td>
            <td class="text-soft"><?= e(dt($ticket['used_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td class="text-soft">Encara no s'ha validat cap tiquet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
