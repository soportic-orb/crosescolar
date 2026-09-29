<?php
/**
 * El fil d'una consulta: els missatges, de dalt a baix.
 *
 * El fan servir els dos panells —el del client i el del suport— perquè tots
 * dos han de veure la mateixa conversa. L'única diferència és que al del
 * suport també hi surten les notes internes, i aquí es distingeixen bé perquè
 * no hi hagi manera de confondre-les amb el que llegeix el client.
 *
 * @var array<int,array<string,mixed>> $messages
 * @var string $side  'client' o 'support': qui mira la conversa
 */
$side = $side ?? 'client';
?>
<div class="thread">
  <?php foreach ($messages as $message): ?>
    <?php
    $mine = (string) $message['sender'] === $side;
    $internal = !empty($message['internal']);
    $who = $internal
        ? 'Nota interna'
        : (((string) $message['sender'] === 'support') ? 'Suport' : (trim((string) ($message['author_name'] ?? '')) ?: 'El client'));
    ?>
    <article class="thread__msg<?= $mine ? ' thread__msg--mine' : '' ?><?= $internal ? ' thread__msg--internal' : '' ?>">
      <header class="thread__meta">
        <strong><?= e($who) ?></strong>
        <span><?= e(dt($message['created_at'])) ?></span>
      </header>
      <div class="thread__body"><?= $message['body'] ?></div>
    </article>
  <?php endforeach; ?>
</div>
