<?php /** Plantilla per imprimir tiquets. */ ?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Tiquets') ?></title>
<meta name="robots" content="noindex, nofollow">
<style>
  *{box-sizing:border-box}
  body{font-family:"Segoe UI",system-ui,Arial,sans-serif;margin:0;padding:24px;color:#17261c;background:#fff}
  .print-actions{margin-bottom:20px;display:flex;gap:10px}
  .print-actions button,.print-actions a{padding:.6rem 1.1rem;border-radius:999px;border:1px solid #2f6b3c;background:#2f6b3c;color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
  .print-actions a{background:#fff;color:#2f6b3c}
  .tickets{display:grid;gap:14px}
  .tk{display:grid;grid-template-columns:120px 1fr;gap:16px;align-items:center;border:2px dashed #2f6b3c;border-radius:14px;padding:14px;page-break-inside:avoid}
  .tk h2{margin:0 0 4px;font-size:1.05rem;color:#1b452a}
  .tk .code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:1.1rem;letter-spacing:.1em;font-weight:700}
  .tk small{color:#5a6b60;display:block;line-height:1.5}
  .tk img{width:120px;height:120px}
  .head{border-bottom:2px solid #2f6b3c;padding-bottom:12px;margin-bottom:18px}
  .head h1{margin:0 0 4px;font-size:1.4rem;color:#1b452a}
  .head p{margin:0;color:#5a6b60}
  @media print{.print-actions{display:none}body{padding:0}.tk{border-color:#888}}
</style>
</head>
<body>
<?= $content ?>
<script>window.addEventListener('load',function(){if(location.hash==='#imprimir'){window.print();}});</script>
</body>
</html>
