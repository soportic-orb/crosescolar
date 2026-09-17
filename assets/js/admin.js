/* Panell d'administració — Cros Escolar La Granada */
(function () {
  'use strict';

  /* Menú lateral en mòbil -------------------------------------------- */
  var toggle = document.querySelector('.menu-toggle');
  var sidebar = document.querySelector('.sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.add('is-open');
      var backdrop = document.createElement('div');
      backdrop.className = 'backdrop';
      backdrop.addEventListener('click', function () {
        sidebar.classList.remove('is-open');
        backdrop.remove();
      });
      document.body.appendChild(backdrop);
    });
  }

  /* Confirmació d'esborrat ------------------------------------------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
    });
  });

  /* Previsualització d'imatges --------------------------------------- */
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var target = document.querySelector(input.getAttribute('data-preview'));
      if (!target || !input.files || !input.files[0]) { return; }
      var reader = new FileReader();
      reader.onload = function (event) { target.innerHTML = '<img src="' + event.target.result + '" alt="">'; };
      reader.readAsDataURL(input.files[0]);
    });
  });

  /* Slug automàtic ---------------------------------------------------- */
  var slugInput = document.querySelector('[data-slug-target]');
  if (slugInput) {
    var source = document.querySelector(slugInput.getAttribute('data-slug-target'));
    if (source) {
      source.addEventListener('blur', function () {
        if (slugInput.value.trim() !== '') { return; }
        slugInput.value = source.value.toString().toLowerCase()
          .normalize('NFD').replace(/[̀-ͯ]/g, '')
          .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      });
    }
  }

  /* Reordenació de files per arrossegament ---------------------------- */
  var sortable = document.querySelector('[data-sortable]');
  if (sortable) {
    var dragged = null;
    sortable.querySelectorAll('tr[draggable="true"]').forEach(function (row) {
      row.addEventListener('dragstart', function () { dragged = row; row.classList.add('dragging'); });
      row.addEventListener('dragend', function () {
        row.classList.remove('dragging');
        var ids = Array.prototype.map.call(sortable.querySelectorAll('tr[data-id]'), function (tr) { return tr.getAttribute('data-id'); });
        var body = new FormData();
        body.append('_token', sortable.getAttribute('data-token'));
        ids.forEach(function (id) { body.append('order[]', id); });
        fetch(sortable.getAttribute('data-sortable'), {
          method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
        });
      });
      row.addEventListener('dragover', function (event) {
        event.preventDefault();
        if (!dragged || dragged === row) { return; }
        var rect = row.getBoundingClientRect();
        var after = (event.clientY - rect.top) / rect.height > .5;
        row.parentNode.insertBefore(dragged, after ? row.nextSibling : row);
      });
    });
  }

  /* Lector de codis QR (càmera) --------------------------------------- */
  var scanner = document.querySelector('[data-scanner]');
  if (scanner) {
    var video = document.getElementById('scanner-video');
    var startButton = document.getElementById('scanner-start');
    var output = document.getElementById('scanner-output');
    var codeInput = document.querySelector('[data-code-input]');
    var stream = null;
    var lastCode = '';
    var lastTime = 0;

    var show = function (result) {
      if (!output) { return; }
      var cls = result.status === 'ok' ? 'scan-result--ok' : (result.status === 'warning' ? 'scan-result--warning' : 'scan-result--error');
      output.innerHTML = '<div class="scan-result ' + cls + '">' + result.message
        + (result.ticket ? '<div class="text-soft mono" style="margin-top:.4rem">' + result.ticket.code
          + ' · ' + (result.ticket.buyer_name || '') + '</div>' : '') + '</div>';
      if (window.navigator.vibrate) { window.navigator.vibrate(result.status === 'ok' ? 80 : [60, 60, 60]); }
    };

    var send = function (code) {
      var now = Date.now();
      if (code === lastCode && now - lastTime < 3000) { return; }
      lastCode = code; lastTime = now;
      var body = new FormData();
      body.append('_token', scanner.getAttribute('data-token'));
      body.append('code', code);
      fetch(scanner.getAttribute('data-scanner'), {
        method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      }).then(function (response) { return response.json(); }).then(show).catch(function () {
        show({ status: 'error', message: 'Error de connexió en validar el tiquet.' });
      });
    };

    if (codeInput) {
      codeInput.form.addEventListener('submit', function (event) {
        if (!scanner.hasAttribute('data-ajax')) { return; }
        event.preventDefault();
        if (codeInput.value.trim() !== '') { send(codeInput.value.trim()); codeInput.value = ''; codeInput.focus(); }
      });
    }

    if (startButton) {
      startButton.addEventListener('click', function () {
        if (stream) {
          stream.getTracks().forEach(function (track) { track.stop(); });
          stream = null;
          startButton.textContent = 'Activar la càmera';
          return;
        }
        if (!('BarcodeDetector' in window)) {
          show({ status: 'error', message: 'Aquest navegador no pot llegir codis QR. Feu servir Chrome a Android o introduïu el codi a mà.' });
          return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (media) {
          stream = media;
          video.srcObject = media;
          video.play();
          startButton.textContent = 'Aturar la càmera';
          var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
          var scan = function () {
            if (!stream) { return; }
            detector.detect(video).then(function (codes) {
              if (codes.length) { send(codes[0].rawValue); }
            }).catch(function () {});
            setTimeout(scan, 400);
          };
          scan();
        }).catch(function () {
          show({ status: 'error', message: 'No s\'ha pogut accedir a la càmera. Comproveu els permisos.' });
        });
      });
    }
  }

  /* Avís abans de sortir amb canvis sense desar ------------------------ */
  document.querySelectorAll('form[data-dirty-check]').forEach(function (form) {
    var dirty = false;
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) {
      if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
  });
})();
