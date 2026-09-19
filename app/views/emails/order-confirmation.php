<?php /** Correu amb els tiquets comprats. */ ?>
<h1 style="font-size:20px;margin:0 0 12px;color:#1b452a">Gràcies, <?= e(explode(' ', (string) $order['name'])[0]) ?>!</h1>
<p style="margin:0 0 16px"><?= nl2br(e(setting('tickets_email_intro', ''))) ?></p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3eade;border-radius:12px;margin:0 0 20px">
  <tr><td style="padding:16px 18px">
    <div style="font-size:12px;color:#5a6b60;text-transform:uppercase;letter-spacing:.08em">Comanda</div>
    <div style="font-size:18px;font-weight:700;letter-spacing:.06em"><?= e($order['code']) ?></div>
    <table role="presentation" width="100%" style="margin-top:12px;font-size:14px">
      <?php foreach ($items as $item): ?>
        <tr>
          <td style="padding:4px 0"><?= (int) $item['qty'] ?> × <?= e($item['name']) ?></td>
          <td align="right" style="padding:4px 0"><?= e(money((int) $item['subtotal_cents'], (string) $order['currency'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td style="padding:8px 0 0;border-top:1px solid #e3eade;font-weight:700">Total</td>
        <td align="right" style="padding:8px 0 0;border-top:1px solid #e3eade;font-weight:700"><?= e(money((int) $order['total_cents'], (string) $order['currency'])) ?></td>
      </tr>
    </table>
  </td></tr>
</table>

<p style="margin:0 0 10px;font-weight:700">Els teus tiquets (<?= count($tickets) ?>)</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-bottom:20px">
  <?php foreach ($tickets as $ticket): ?>
    <tr>
      <td style="padding:8px 10px;border:1px solid #e3eade;border-radius:8px">
        <strong><?= e($ticket['type_name'] ?? 'Tiquet') ?></strong><br>
        <span style="font-family:Consolas,monospace;letter-spacing:.1em;font-size:16px"><?= e($ticket['code']) ?></span>
      </td>
    </tr>
    <tr><td style="height:6px"></td></tr>
  <?php endforeach; ?>
</table>

<p style="text-align:center;margin:24px 0">
  <a href="<?= e(\Cros\Models\Order::ticketsUrl($order)) ?>"
     style="display:inline-block;background:<?= e(setting('color_accent', '#c8552b')) ?>;color:#fff;text-decoration:none;padding:13px 26px;border-radius:999px;font-weight:700">
    Veure i imprimir els tiquets
  </a>
</p>
<p style="font-size:13px;color:#5a6b60;margin:0">
  Presenta el codi QR de cada tiquet (des del mòbil o imprès) a la carpa de l'AFA.
  Guarda aquest correu: l'enllaç et permet recuperar els tiquets sempre que vulguis.
</p>
