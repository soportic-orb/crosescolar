<?php /** Error dins del panell d'administració. */ ?>
<div class="login-card" style="text-align:center">
  <p class="text-soft" style="margin:0 0 .3rem;font-size:.85rem;letter-spacing:.1em;text-transform:uppercase">Error <?= (int) $status ?></p>
  <h1 style="font-size:1.25rem"><?= e($message) ?></h1>
  <?php if (!empty($details)): ?>
    <pre class="mono" style="text-align:left;white-space:pre-wrap;background:#f4f6f3;border:1px solid #dde4d9;padding:.8rem;border-radius:10px;font-size:.78rem;overflow:auto"><?= e($details) ?></pre>
  <?php endif; ?>
  <div class="flex" style="justify-content:center;margin-top:1.2rem">
    <a class="btn" href="<?= e(url('/admin')) ?>">Tornar al panell</a>
    <a class="btn btn--ghost" href="<?= e(url('/admin/acces')) ?>">Iniciar sessió</a>
  </div>
</div>
