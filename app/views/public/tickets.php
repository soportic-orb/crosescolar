<?php
/** Compra de tiquets de l'esmorzar. */
$currency = (string) setting('payments_currency', 'EUR');
$quantities = $quantities ?? [];
$errors = $errors ?? [];
?>
<?= \Cros\Core\View::partial('partials/page-header', [
    'title' => setting('tickets_title', 'Tiquets per a l\'esmorzar'),
    'subtitle' => setting('event_date', '') ? 'Esmorzar popular del ' . ca_date(setting('event_date')) : '',
    'breadcrumb' => ['Esmorzar' => ''],
]) ?>

<section class="section">
  <div class="container">
    <div class="prose" style="max-width:70ch"><?= setting_html('tickets_intro') ?></div>

    <?php if (!($saleMode ?? false)): ?>
      <!-- Mode informatiu: el web explica l'esmorzar però no s'hi compra -->
      <div class="split" style="margin-top:2rem">
        <div>
          <?php if ($types): ?>
            <h2>Què hi haurà</h2>
            <div class="ticket-list">
              <?php foreach ($types as $type): ?>
                <div class="ticket-type">
                  <div>
                    <h3><?= e($type['name']) ?></h3>
                    <?php if (!empty($type['description'])): ?><p><?= e($type['description']) ?></p><?php endif; ?>
                  </div>
                  <div class="ticket-type__side">
                    <span class="ticket-type__price"><?= e(money((int) $type['price_cents'], (string) setting('payments_currency', 'EUR'))) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <aside>
          <?php if (setting('tickets_info', '')): ?>
            <div class="card">
              <h3><?= \Cros\Core\Icons::svg('info', 'icon', 20) ?> Informació pràctica</h3>
              <div style="font-size:.95rem;color:var(--ink-soft)"><?= setting_html('tickets_info') ?></div>
            </div>
          <?php endif; ?>
          <div class="card" style="margin-top:1.2rem">
            <h3><?= \Cros\Core\Icons::svg('run', 'icon', 20) ?> Vols córrer?</h3>
            <p style="margin:0 0 1rem;color:var(--ink-soft);font-size:.95rem">
              La cursa és oberta a totes les edats i la inscripció és gratuïta.
            </p>
            <a class="btn btn--block" href="<?= e(url('/inscripcio')) ?>">Inscripcions al cros</a>
          </div>
        </aside>
      </div>

    <?php elseif (!$salesOpen): ?>
      <div class="notice-box" style="margin-top:1.5rem"><?= setting_html('tickets_closed_text') ?></div>
      <p style="margin-top:1.2rem"><a class="btn btn--ghost" href="<?= e(url('/els-meus-tiquets')) ?>">Consulta els tiquets que ja has comprat</a></p>
    <?php else: ?>

      <?php if (!$stripeReady): ?>
        <div class="alert alert--warning" style="margin-top:1.5rem">
          El pagament en línia encara no està activat. Torneu-ho a provar més tard o contacteu amb l'organització.
        </div>
      <?php endif; ?>
      <?php if (isset($errors['qty'])): ?>
        <div class="alert alert--error" style="margin-top:1.5rem"><?= e($errors['qty']) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/esmorzar')) ?>" data-order-form data-currency="€" style="margin-top:2rem">
        <?= csrf_field() ?>
        <div class="honeypot"><label>No omplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="split">
          <div>
            <h2>Tria els teus tiquets</h2>
            <div class="ticket-list">
              <?php foreach ($types as $type): ?>
                <?php
                $available = $type['available'];
                $soldOut = $available !== null && $available <= 0;
                $max = $soldOut ? 0 : min((int) $type['max_per_order'], $available ?? (int) $type['max_per_order']);
                $value = (int) ($quantities[$type['id']] ?? 0);
                ?>
                <div class="ticket-type">
                  <div>
                    <h3><?= e($type['name']) ?></h3>
                    <?php if (!empty($type['description'])): ?><p><?= e($type['description']) ?></p><?php endif; ?>
                    <?php if ($soldOut): ?>
                      <span class="chip chip--accent" style="margin-top:.5rem">Exhaurit</span>
                    <?php elseif ($available !== null && $available < 20): ?>
                      <span class="chip chip--muted" style="margin-top:.5rem">Queden <?= (int) $available ?> unitats</span>
                    <?php endif; ?>
                  </div>
                  <div class="ticket-type__side">
                    <span class="ticket-type__price"><?= e(money((int) $type['price_cents'], $currency)) ?></span>
                    <div class="stepper">
                      <button type="button" data-step="-1" aria-label="Menys">−</button>
                      <input type="number" name="qty[<?= (int) $type['id'] ?>]" value="<?= $value ?>" min="0" max="<?= $max ?>"
                             inputmode="numeric" data-qty data-price="<?= (int) $type['price_cents'] ?>"
                             data-name="<?= e($type['name']) ?>" aria-label="Unitats de <?= e($type['name']) ?>"
                             <?= $soldOut ? 'disabled' : '' ?>>
                      <button type="button" data-step="1" aria-label="Més">+</button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <h2 style="margin-top:2.5rem">Les teves dades</h2>
            <div class="form">
              <div class="form-row">
                <div class="field<?= isset($errors['name']) ? ' field--error' : '' ?>">
                  <label for="name">Nom i cognoms *</label>
                  <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" required autocomplete="name">
                  <?php if (isset($errors['name'])): ?><span class="field__error"><?= e($errors['name']) ?></span><?php endif; ?>
                </div>
                <div class="field<?= isset($errors['email']) ? ' field--error' : '' ?>">
                  <label for="email">Correu electrònic *</label>
                  <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
                  <span class="field__hint">Hi enviarem els tiquets amb el codi QR.</span>
                  <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
                </div>
              </div>
              <div class="form-row">
                <div class="field">
                  <label for="phone">Telèfon</label>
                  <input type="tel" id="phone" name="phone" value="<?= e(old('phone')) ?>" autocomplete="tel">
                </div>
                <div class="field">
                  <label for="notes">Al·lèrgies o comentaris</label>
                  <input type="text" id="notes" name="notes" value="<?= e(old('notes')) ?>" maxlength="500">
                </div>
              </div>
              <label class="checkbox<?= isset($errors['terms']) ? ' field--error' : '' ?>">
                <input type="checkbox" name="terms" value="1" required>
                <span>Accepto les <a href="<?= e(url('/privacitat')) ?>" target="_blank">condicions i la política de privacitat</a>. *</span>
              </label>
              <?php if (isset($errors['terms'])): ?><span class="field__error"><?= e($errors['terms']) ?></span><?php endif; ?>
            </div>
          </div>

          <aside>
            <div class="order-summary">
              <h3>Resum de la comanda</h3>
              <div class="order-summary__lines" data-summary-lines></div>
              <div class="order-summary__total"><span>Total</span><span data-summary-total>0,00 €</span></div>
              <button class="btn btn--accent btn--block" type="submit" data-submit style="margin-top:1.2rem" <?= $stripeReady ? '' : 'disabled' ?>>
                <?= \Cros\Core\Icons::svg('card', 'icon', 18) ?> Pagar amb targeta
              </button>
              <small>Pagament segur amb Stripe. No desem cap dada de la targeta.</small>
            </div>

            <?php if (setting('tickets_info', '')): ?>
              <div class="card" style="margin-top:1.2rem">
                <h3><?= \Cros\Core\Icons::svg('info', 'icon', 20) ?> Informació pràctica</h3>
                <div style="font-size:.95rem;color:var(--ink-soft)"><?= setting_html('tickets_info') ?></div>
              </div>
            <?php endif; ?>
          </aside>
        </div>
      </form>

      <?php if (setting('tickets_terms', '')): ?>
        <div class="prose" style="margin-top:2.5rem;font-size:.92rem;color:var(--ink-soft);max-width:70ch">
          <?= setting_html('tickets_terms') ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($saleMode ?? false): ?>
      <p style="margin-top:2rem">
        Ja has comprat els tiquets? <a href="<?= e(url('/els-meus-tiquets')) ?>">Consulta'ls aquí</a>.
      </p>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($sponsors)): ?>
<section class="section section--tint">
  <div class="container"><?= \Cros\Core\View::partial('partials/sponsors', ['sponsors' => $sponsors]) ?></div>
</section>
<?php endif; ?>
