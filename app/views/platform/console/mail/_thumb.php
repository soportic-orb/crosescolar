<?php
/**
 * Miniatura esquemàtica d'un correu: una franja per bloc, sense carregar cap imatge.
 *
 * @var array{settings:array,blocks:array} $doc
 * @var string $size 'sm' (llista) o 'lg' (tria de plantilla)
 * @var array<string,string> $brand els colors del domini (Brand::of)
 */
$lg = ($size ?? 'sm') === 'lg';
$primary = (string) ($brand['primary'] ?? '#2f6b3c');
$accent = ($doc['settings']['accent'] ?? '') ?: (string) ($brand['accent'] ?? '#c8552b');
$heading = ($doc['settings']['headingColor'] ?? '') ?: $primary;
$bars = [];
foreach (array_slice($doc['blocks'] ?? [], 0, $lg ? 14 : 7) as $b) {
    $bars[] = match ($b['type']) {
        'header' => ['h' => $lg ? 14 : 8, 'bg' => '#e6ebe4', 'w' => '46%', 'top' => ($b['borderTop'] ?? '') === 'brand' ? $primary : ($b['borderTop'] ?? '')],
        'heading' => ['h' => $lg ? 7 : 4, 'bg' => (int) ($b['level'] ?? 1) === 2 ? $heading : '#3d4a40', 'w' => '68%'],
        'text' => ['h' => $lg ? 16 : 7, 'bg' => 'repeating-linear-gradient(#d3dbd0 0 3px, transparent 3px 7px)', 'w' => '100%'],
        'image', 'imagetext' => ['h' => $lg ? 30 : 12, 'bg' => '#e1e7de', 'w' => '100%'],
        'button' => ['h' => $lg ? 12 : 6, 'bg' => ($b['btnBg'] ?? '') ?: $accent, 'w' => '42%', 'center' => true],
        'link' => ['h' => $lg ? 5 : 3, 'bg' => $primary, 'w' => '36%'],
        'box' => ['h' => $lg ? 22 : 9, 'bg' => $b['boxBg'] ?? '#eef3ec', 'w' => '100%'],
        'columns' => ['h' => $lg ? 24 : 10, 'bg' => 'linear-gradient(90deg,#eef1ec 0 47%, transparent 47% 53%, #eef1ec 53%)', 'w' => '100%'],
        'divider' => ['h' => 1, 'bg' => '#cdd6c9', 'w' => '100%'],
        'footer' => ['h' => $lg ? 14 : 6, 'bg' => '#eef1ec', 'w' => '60%', 'center' => true],
        'race' => ['h' => $lg ? 22 : 9, 'bg' => 'linear-gradient(90deg,' . $primary . ' 0 30%, #eef1ec 30%)', 'w' => '100%'],
        'news' => ['h' => $lg ? 22 : 9, 'bg' => 'linear-gradient(#fbeee9 0 30%, #f4f6f3 30%)', 'w' => '100%'],
        default => null,
    };
}
$bars = array_filter($bars);
?>
<span class="ml-thumb<?= $lg ? ' lg' : '' ?>" aria-hidden="true">
  <?php $i = 0; foreach ($bars as $bar): ?>
    <span style="height:<?= (int) $bar['h'] ?>px;width:<?= e($bar['w']) ?>;background:<?= e($bar['bg']) ?>;<?= !empty($bar['center']) || $i === 0 ? 'align-self:center;' : '' ?><?= !empty($bar['top']) ? 'box-shadow:0 -2px 0 ' . e($bar['top']) . ';' : '' ?>"></span>
  <?php $i++; endforeach; ?>
</span>
