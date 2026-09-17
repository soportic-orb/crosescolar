/* Cros Escolar La Granada — interaccions del web públic */
(function () {
  'use strict';

  /* Menú mòbil ------------------------------------------------------- */
  var toggle = document.querySelector('.nav-toggle');
  var nav = document.querySelector('.nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (event) {
      if (event.target.tagName === 'A') { nav.classList.remove('is-open'); }
    });
  }

  /* Ombra de la capçalera en fer scroll ------------------------------ */
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* Compte enrere ---------------------------------------------------- */
  var countdown = document.querySelector('[data-countdown]');
  if (countdown) {
    var target = new Date(countdown.getAttribute('data-countdown')).getTime();
    var units = {
      days: countdown.querySelector('[data-unit="days"]'),
      hours: countdown.querySelector('[data-unit="hours"]'),
      minutes: countdown.querySelector('[data-unit="minutes"]'),
      seconds: countdown.querySelector('[data-unit="seconds"]')
    };
    var tick = function () {
      var diff = target - Date.now();
      if (isNaN(target)) { countdown.style.display = 'none'; return; }
      if (diff <= 0) {
        countdown.innerHTML = '<div class="countdown__unit" style="min-width:auto;padding:.7rem 1.2rem">'
          + '<span class="countdown__num" style="font-size:1.1rem">Avui és el dia! Bona cursa!</span></div>';
        clearInterval(timer);
        return;
      }
      var seconds = Math.floor(diff / 1000);
      var values = {
        days: Math.floor(seconds / 86400),
        hours: Math.floor(seconds / 3600) % 24,
        minutes: Math.floor(seconds / 60) % 60,
        seconds: seconds % 60
      };
      Object.keys(units).forEach(function (key) {
        if (units[key]) { units[key].textContent = key === 'days' ? values[key] : ('0' + values[key]).slice(-2); }
      });
    };
    var timer = setInterval(tick, 1000);
    tick();
  }

  /* Mapes de Wikiloc: es carreguen en fer clic (privacitat i rendiment) */
  document.querySelectorAll('[data-embed]').forEach(function (holder) {
    holder.addEventListener('click', function () {
      var src = holder.getAttribute('data-embed');
      var frame = document.createElement('iframe');
      frame.src = src;
      frame.loading = 'lazy';
      frame.title = holder.getAttribute('data-title') || 'Mapa del recorregut';
      frame.setAttribute('allowfullscreen', '');
      frame.setAttribute('referrerpolicy', 'no-referrer-when-downgrade');
      holder.replaceWith(frame);
    });
  });

  /* Selectors de quantitat i resum de la comanda --------------------- */
  var form = document.querySelector('[data-order-form]');
  if (form) {
    var currency = form.getAttribute('data-currency') || '€';

    var formatMoney = function (cents) {
      return (cents / 100).toFixed(2).replace('.', ',') + ' ' + currency;
    };

    var updateSummary = function () {
      var lines = form.querySelectorAll('[data-qty]');
      var total = 0;
      var items = [];
      lines.forEach(function (input) {
        var qty = parseInt(input.value, 10) || 0;
        var price = parseInt(input.getAttribute('data-price'), 10) || 0;
        if (qty > 0) {
          total += qty * price;
          items.push({ name: input.getAttribute('data-name'), qty: qty, subtotal: qty * price });
        }
      });
      var list = document.querySelector('[data-summary-lines]');
      var totalEl = document.querySelector('[data-summary-total]');
      var submit = form.querySelector('[data-submit]');
      if (list) {
        list.innerHTML = items.length
          ? items.map(function (item) {
              return '<div class="order-summary__line"><span>' + item.qty + ' × ' + item.name
                + '</span><strong>' + formatMoney(item.subtotal) + '</strong></div>';
            }).join('')
          : '<div class="order-summary__line"><span>Encara no has triat cap tiquet</span></div>';
      }
      if (totalEl) { totalEl.textContent = formatMoney(total); }
      if (submit) { submit.classList.toggle('is-disabled', total <= 0 && items.length === 0); }
    };

    form.querySelectorAll('[data-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = button.parentNode.querySelector('[data-qty]');
        var step = parseInt(button.getAttribute('data-step'), 10);
        var max = parseInt(input.getAttribute('max'), 10);
        var value = (parseInt(input.value, 10) || 0) + step;
        if (value < 0) { value = 0; }
        if (!isNaN(max) && value > max) { value = max; }
        input.value = value;
        updateSummary();
      });
    });
    form.querySelectorAll('[data-qty]').forEach(function (input) {
      input.addEventListener('input', updateSummary);
      input.addEventListener('change', updateSummary);
    });
    form.addEventListener('submit', function () {
      var submit = form.querySelector('[data-submit]');
      if (submit) { submit.classList.add('is-disabled'); submit.textContent = 'Redirigint al pagament…'; }
    });
    updateSummary();
  }

  /* Galeria: visor senzill ------------------------------------------- */
  var gallery = document.querySelector('[data-gallery]');
  if (gallery) {
    gallery.addEventListener('click', function (event) {
      var img = event.target.closest('img');
      if (!img) { return; }
      var overlay = document.createElement('div');
      overlay.setAttribute('style', 'position:fixed;inset:0;z-index:999;background:rgba(18,48,28,.92);display:grid;place-items:center;padding:2rem;cursor:zoom-out');
      var big = document.createElement('img');
      big.src = img.getAttribute('data-full') || img.src;
      big.alt = img.alt;
      big.setAttribute('style', 'max-width:100%;max-height:90vh;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.5)');
      overlay.appendChild(big);
      overlay.addEventListener('click', function () { overlay.remove(); });
      document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', esc); }
      });
      document.body.appendChild(overlay);
    });
  }

  /* Missatges flash: es poden tancar --------------------------------- */
  document.querySelectorAll('[data-dismiss]').forEach(function (button) {
    button.addEventListener('click', function () {
      var alert = button.closest('.alert');
      if (alert) { alert.remove(); }
    });
  });
})();
