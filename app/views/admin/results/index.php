<?php
/** Control de les arribades a meta. */
use Cros\Core\Icons;
use Cros\Models\Bib;
use Cros\Models\RaceResult;

$localLabel = RaceResult::localPrizeLabel();
?>
<div class="grid-cards mb-2">
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('flag', 'icon', 16) ?> Arribades registrades</div>
    <div class="kpi__value"><?= (int) $stats['total'] ?></div>
    <div class="kpi__foot">de <?= (int) $stats['registrations'] ?> inscripcions</div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('users', 'icon', 16) ?> Categories amb resultats</div>
    <div class="kpi__value"><?= (int) $stats['categories'] ?></div>
  </div>
  <div class="kpi">
    <div class="kpi__label"><?= Icons::svg('eye', 'icon', 16) ?> Publicats al web</div>
    <div class="kpi__value" style="font-size:1.2rem"><?= $published ? 'Sí' : 'No' ?></div>
    <div class="kpi__foot">
      <form method="post" action="<?= e(url('/admin/resultats/publicar')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="enable" value="<?= $published ? '0' : '1' ?>">
        <button class="btn btn--ghost btn--sm" type="submit"><?= $published ? 'Deixar de publicar' : 'Publicar al web' ?></button>
      </form>
    </div>
  </div>
</div>

<div class="panel" data-arrivals="<?= e(url('/admin/resultats/arribada')) ?>" data-token="<?= e(csrf_token()) ?>">
  <div class="panel__head"><h2><?= Icons::svg('flag', 'icon', 18) ?> Arribada a meta</h2></div>
  <div class="panel__body">
    <div class="grid-cards" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr))">
      <div>
        <form method="post" action="<?= e(url('/admin/resultats/arribada')) ?>" class="form-grid" id="arrival-form">
          <?= csrf_field() ?>
          <div class="field">
            <label for="bib">Número de dorsal</label>
            <input type="text" id="bib" name="bib" inputmode="numeric" autocomplete="off" autofocus
                   placeholder="001" style="font-size:1.6rem;font-weight:700;letter-spacing:.1em;text-align:center">
            <span class="hint">Escriviu el dorsal i premeu Retorn a mesura que van arribant.</span>
          </div>
          <button class="btn" type="submit"><?= Icons::svg('check', 'icon', 16) ?> Registrar l'arribada</button>
        </form>
        <div id="arrival-output" class="mt-2"></div>
      </div>

      <div>
        <h3 style="margin-top:0">Últimes arribades</h3>
        <div class="table-wrap">
          <table class="admin-table">
            <tbody id="arrival-recent">
              <?php foreach ($recent as $row): ?>
                <tr>
                  <td class="mono"><strong><?= e(Bib::number($row)) ?></strong></td>
                  <td><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                  <td class="text-soft"><?= e($row['category_name'] ?? '—') ?></td>
                  <td><span class="badge badge--green"><?= (int) $row['position'] ?>a</span></td>
                  <td class="actions">
                    <form method="post" action="<?= e(url('/admin/resultats/' . $row['id'] . '/esborrar')) ?>" data-confirm="Esborrar aquesta arribada?">
                      <?= csrf_field() ?>
                      <button class="btn btn--danger btn--sm" type="submit"><?= Icons::svg('trash', 'icon', 14) ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$recent): ?><tr><td class="text-soft">Encara no hi ha cap arribada.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="panel mt-2">
  <div class="panel__head">
    <h2>Classificació</h2>
    <div class="spacer"></div>
    <form method="get" class="filters">
      <label class="label" for="veure_categoria">Veure</label>
      <select id="veure_categoria" name="categoria" onchange="this.form.submit()">
        <option value="0">Totes les categories</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <form method="get" action="<?= e(url('/admin/resultats/pdf')) ?>" class="filters" target="_blank">
      <label class="label" for="print_categoria">Imprimir</label>
      <select id="print_categoria" name="categoria">
        <option value="0">Totes les categories</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
            <?= e(\Cros\Models\Content::title($category)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--ghost btn--sm" type="submit">
        <?= Icons::svg('download', 'icon', 15) ?> PDF
      </button>
    </form>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/resultats/pdf', ['tipus' => 'arribada'])) ?>" target="_blank">
      <?= Icons::svg('download', 'icon', 15) ?> PDF per ordre d'arribada
    </a>
    <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/resultats/csv')) ?>">CSV</a>
  </div>
  <div class="panel__body">
    <?php if (!$groups): ?>
      <div class="empty-state">
        <?= Icons::svg('trophy', 'icon', 42) ?>
        <p>Encara no s'ha registrat cap arribada.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($groups as $group): ?>
      <h3 class="mt-3"><?= e($group['name']) ?> <span class="text-soft" style="font-weight:400">(<?= count($group['rows']) ?>)</span>
        <a class="btn btn--ghost btn--sm" style="margin-left:.6rem" target="_blank"
           href="<?= e(url('/admin/resultats/pdf', ['categoria' => $group['id']])) ?>">PDF d'aquesta categoria</a>
      </h3>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th style="width:70px">Posició</th><th>Dorsal</th><th>Participant</th><th>Escola</th>
            <?php if (!empty($group['local_prize'])): ?><th style="width:130px"><?= e($localLabel) ?></th><?php endif; ?>
            <th></th></tr></thead>
          <tbody>
            <?php foreach ($group['rows'] as $row): ?>
              <?php $hasLocal = (int) ($row['local_prize'] ?? 0) === 1; ?>
              <tr>
                <td><strong><?= (int) $row['position'] ?></strong></td>
                <td class="mono"><?= e(Bib::number($row)) ?></td>
                <td><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                <td class="text-soft">
                  <?= e($row['school'] ?? '') ?>
                  <?php if (RaceResult::isLocalSchool($row['school'] ?? null)): ?>
                    <span class="badge badge--green" title="Escola del poble">local</span>
                  <?php endif; ?>
                </td>
                <?php if (!empty($group['local_prize'])): ?>
                  <td>
                    <form method="post" action="<?= e(url('/admin/resultats/' . $row['id'] . '/premi-local')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="enable" value="<?= $hasLocal ? '0' : '1' ?>">
                      <button class="btn btn--sm <?= $hasLocal ? '' : 'btn--ghost' ?>" type="submit"
                              title="<?= e($hasLocal ? 'Treure el premi «' . $localLabel . '»' : 'Donar-li el premi «' . $localLabel . '»') ?>">
                        <?= Icons::svg('trophy', 'icon', 14) ?> <?= $hasLocal ? 'Premiat' : 'Marcar' ?>
                      </button>
                    </form>
                  </td>
                <?php endif; ?>
                <td class="actions">
                  <form method="post" style="display:inline" action="<?= e(url('/admin/resultats/' . $row['id'] . '/moure')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="direccio" value="puja">
                    <button class="btn btn--ghost btn--sm" type="submit" title="Puja una posició">↑</button>
                  </form>
                  <form method="post" style="display:inline" action="<?= e(url('/admin/resultats/' . $row['id'] . '/moure')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="direccio" value="baixa">
                    <button class="btn btn--ghost btn--sm" type="submit" title="Baixa una posició">↓</button>
                  </form>
                  <form method="post" style="display:inline" action="<?= e(url('/admin/resultats/' . $row['id'] . '/esborrar')) ?>" data-confirm="Esborrar aquesta arribada?">
                    <?= csrf_field() ?>
                    <button class="btn btn--danger btn--sm" type="submit"><?= Icons::svg('trash', 'icon', 14) ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($pending): ?>
<div class="panel mt-2">
  <div class="panel__head"><h2>Encara no han arribat (<?= count($pending) ?>)</h2></div>
  <div class="panel__body table-wrap">
    <table class="admin-table">
      <tbody>
        <?php foreach ($pending as $row): ?>
          <tr>
            <td class="mono"><?= e(Bib::number($row)) ?></td>
            <td><?= e(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
            <td class="text-soft"><?= e($row['category_name'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
