<?php
/**
 * Editor visual senzill: el que s'escriu es veu tal com quedarà.
 * Sense JavaScript continua sent una àrea de text amb HTML, que és el que es desa.
 *
 * @var string $name
 * @var string $value
 */
$id = 'ed_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
$buttons = [
    ['bold', 'B', 'Negreta', 'font-weight:800'],
    ['italic', 'I', 'Cursiva', 'font-style:italic'],
    ['h2', 'Títol', 'Títol', 'font-weight:700'],
    ['h3', 'Subtítol', 'Subtítol', ''],
    ['p', '¶', 'Paràgraf normal', ''],
    ['ul', '• Llista', 'Llista de punts', ''],
    ['ol', '1. Llista', 'Llista numerada', ''],
    ['link', 'Enllaç', 'Posar un enllaç', ''],
    ['unlink', 'Treure l\'enllaç', 'Treure l\'enllaç', ''],
    ['clear', 'Netejar', 'Treure el format', ''],
];
?>
<div class="editor" data-editor>
  <div class="editor__bar">
    <?php foreach ($buttons as [$command, $label, $hint, $style]): ?>
      <button type="button" class="editor__btn" data-command="<?= e($command) ?>"
              title="<?= e($hint) ?>" style="<?= e($style) ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
    <button type="button" class="editor__btn editor__btn--right" data-command="source" title="Veure i editar l'HTML">
      &lt;/&gt; HTML
    </button>
  </div>
  <div class="editor__area" contenteditable="true" role="textbox" aria-multiline="true"
       aria-label="Cos del correu" data-editor-area></div>
  <textarea class="editor__source" id="<?= e($id) ?>" name="<?= e($name) ?>" rows="12"
            data-editor-source><?= e($value) ?></textarea>
</div>
