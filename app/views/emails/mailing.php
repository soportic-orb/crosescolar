<?php /** Cos d'un enviament escrit des del panell. */ ?>
<?php if (!empty($subject)): ?>
  <h1 style="font-size:20px;margin:0 0 14px;color:#1b452a"><?= e($subject) ?></h1>
<?php endif; ?>
<div style="font-size:15px;line-height:1.6"><?= $body ?></div>
