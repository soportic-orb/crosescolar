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

  /* Arribades a meta: registre ràpid sense recarregar la pàgina --------- */
  var arrivals = document.querySelector('[data-arrivals]');
  if (arrivals) {
    var form = document.getElementById('arrival-form');
    var input = document.getElementById('bib');
    var output = document.getElementById('arrival-output');
    var recent = document.getElementById('arrival-recent');

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var value = input.value.trim();
      if (value === '') { return; }
      var body = new FormData();
      body.append('_token', arrivals.getAttribute('data-token'));
      body.append('bib', value);
      fetch(arrivals.getAttribute('data-arrivals'), {
        method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
      }).then(function (response) { return response.json(); }).then(function (result) {
        var cls = result.status === 'ok' ? 'scan-result--ok' : (result.status === 'warning' ? 'scan-result--warning' : 'scan-result--error');
        output.innerHTML = '<div class="scan-result ' + cls + '">' + result.message + '</div>';
        if (result.status === 'ok' && recent) {
          var row = document.createElement('tr');
          row.innerHTML = '<td colspan="5">' + result.message + '</td>';
          row.style.background = '#e3f4e4';
          recent.insertBefore(row, recent.firstChild);
        }
        if (window.navigator.vibrate) { window.navigator.vibrate(result.status === 'ok' ? 60 : [50, 50, 50]); }
      }).catch(function () {
        output.innerHTML = '<div class="scan-result scan-result--error">Error de connexió en registrar l\'arribada.</div>';
      });
      input.value = '';
      input.focus();
    });
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

  /* Camps que només tenen sentit si n'hi ha un altre d'activat (data-show-if). */
  document.querySelectorAll('[data-show-if]').forEach(function (field) {
    var master = document.querySelector('[name="' + field.getAttribute('data-show-if') + '"]');
    if (!master) { return; }
    // S'amaga la cel·la sencera perquè no quedi un forat a la graella.
    var cell = field.parentElement && field.parentElement.parentElement
      && field.parentElement.parentElement.classList.contains('form-grid')
      ? field.parentElement : field;
    var update = function () { cell.style.display = master.checked ? '' : 'none'; };
    master.addEventListener('change', update);
    update();
  });

  /* Recorreguts d'una categoria: afegir, treure i ordenar files. */
  document.querySelectorAll('[data-laps]').forEach(function (box) {
    var addButton = box.querySelector('[data-laps-add]');
    var rows = function () { return Array.prototype.slice.call(box.querySelectorAll('[data-laps-row]')); };

    var clean = function (row) {
      row.querySelector('select').value = '';
      var laps = row.querySelector('input[type="number"]');
      if (laps) { laps.value = '1'; }
      return row;
    };

    if (addButton) {
      addButton.addEventListener('click', function () {
        var list = rows();
        var copy = clean(list[list.length - 1].cloneNode(true));
        box.insertBefore(copy, addButton);
        copy.querySelector('select').focus();
      });
    }

    box.addEventListener('click', function (event) {
      var button = event.target.closest('button');
      if (!button) { return; }
      var row = button.closest('[data-laps-row]');
      if (!row) { return; }
      if (button.hasAttribute('data-laps-remove')) {
        // Sempre hi ha d'haver una fila per poder afegir-ne.
        rows().length > 1 ? row.remove() : clean(row);
      } else if (button.hasAttribute('data-laps-up') && row.previousElementSibling) {
        box.insertBefore(row, row.previousElementSibling);
      } else if (button.hasAttribute('data-laps-down')) {
        var next = row.nextElementSibling;
        if (next && next.hasAttribute('data-laps-row')) { box.insertBefore(next, row); }
      }
    });
  });
  /* Editor visual dels textos del panell ------------------------------ */
  document.querySelectorAll('[data-editor]').forEach(function (editor) {
    var area = editor.querySelector('[data-editor-area]');
    var source = editor.querySelector('[data-editor-source]');
    if (!area || !source) { return; }

    // Amb JavaScript, l'HTML es veu format; el textarea queda de reserva.
    area.innerHTML = source.value.trim() || '<p><br></p>';
    editor.classList.add('editor--live');

    // Clicar l'etiqueta del camp ha de portar a l'àrea on s'escriu, no al
    // textarea de l'HTML, que amb JavaScript queda amagat.
    var label = source.id ? document.querySelector('label[for="' + source.id + '"]') : null;
    if (label) {
      label.addEventListener('click', function (event) {
        if (!editor.classList.contains('editor--source')) { event.preventDefault(); area.focus(); }
      });
    }

    // Etiquetes senzilles (<b>, <p>) en lloc d'estils dins de l'HTML: així el
    // correu es veu igual a tots els programes de correu.
    try { document.execCommand('styleWithCSS', false, false); } catch (e) {}
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    var sync = function () {
      var html = area.innerHTML.trim();
      // Un paràgraf buit no és contingut: així la comprovació del servidor l'enxampa.
      source.value = (html === '<p><br></p>' || html === '<br>') ? '' : html;
    };
    area.addEventListener('input', sync);
    area.addEventListener('blur', sync);

    // Enganxar sempre com a text: així no s'hi cola el format d'un altre web.
    area.addEventListener('paste', function (event) {
      event.preventDefault();
      var text = (event.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });

    var run = function (command, value) {
      area.focus();
      try { document.execCommand(command, false, value || null); } catch (e) {}
      sync();
    };

    var commands = {
      bold: function () { run('bold'); },
      italic: function () { run('italic'); },
      h2: function () { run('formatBlock', 'H2'); },
      h3: function () { run('formatBlock', 'H3'); },
      p: function () { run('formatBlock', 'P'); },
      ul: function () { run('insertUnorderedList'); },
      ol: function () { run('insertOrderedList'); },
      unlink: function () { run('unlink'); },
      clear: function () { run('removeFormat'); },
      link: function () {
        var url = window.prompt('Adreça de l\'enllaç', 'https://');
        if (url) { run('createLink', url); }
      },
      source: function () {
        var showing = editor.classList.toggle('editor--source');
        if (showing) {
          sync();
          source.focus();
        } else {
          area.innerHTML = source.value;
          area.focus();
        }
      }
    };

    editor.querySelector('.editor__bar').addEventListener('click', function (event) {
      var button = event.target.closest('[data-command]');
      if (!button) { return; }
      var command = commands[button.getAttribute('data-command')];
      if (command) { command(); }
    });

    // En desar, el que val és el textarea.
    var form = editor.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (!editor.classList.contains('editor--source')) { sync(); }
      });
    }
  });

  /* Destinataris: només es demana el que fa falta ---------------------- */
  (function () {
    var radios = document.querySelectorAll('[data-audience]');
    if (!radios.length) { return; }
    var blocks = document.querySelectorAll('[data-audience-for]');
    var update = function () {
      var chosen = '';
      radios.forEach(function (radio) { if (radio.checked) { chosen = radio.value; } });
      blocks.forEach(function (block) {
        var wanted = block.getAttribute('data-audience-for').split(' ');
        block.style.display = wanted.indexOf(chosen) === -1 ? 'none' : '';
      });
    };
    radios.forEach(function (radio) { radio.addEventListener('change', update); });
    update();
  })();

  /* Enviament per tandes amb barra de progrés ------------------------- */
  (function () {
    var box = document.querySelector('[data-send]');
    if (!box) { return; }
    var button = box.querySelector('[data-send-start]');
    var bar = box.querySelector('[data-send-bar]');
    var note = box.querySelector('[data-send-note]');
    var url = box.getAttribute('data-send');
    var token = box.getAttribute('data-token');
    var total = parseInt(box.getAttribute('data-total'), 10) || 0;
    if (!button) { return; }

    var show = function (sent, failed) {
      var done = sent + failed;
      if (bar) { bar.style.width = (total ? Math.round((done / total) * 100) : 100) + '%'; }
      if (note) {
        note.textContent = done + ' de ' + total + ' · ' + sent + ' enviats'
          + (failed ? ' · ' + failed + ' amb error' : '');
      }
    };

    var step = function () {
      var data = new FormData();
      data.append('_token', token);
      fetch(url, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('estat ' + response.status));
      }).then(function (result) {
        show(result.sentTotal || 0, result.failedTotal || 0);
        if (result.done) {
          if (note) { note.textContent += ' · fet!'; }
          window.setTimeout(function () { window.location.reload(); }, 900);
          return;
        }
        window.setTimeout(step, 400);
      }).catch(function (error) {
        button.disabled = false;
        button.textContent = 'Continuar l\'enviament';
        if (note) { note.textContent = 'S\'ha aturat: ' + error.message + '. Podeu continuar quan vulgueu.'; }
      });
    };

    button.addEventListener('click', function (event) {
      event.preventDefault();
      button.disabled = true;
      button.textContent = 'Enviant…';
      box.classList.add('is-sending');
      step();
    });
  })();
})();
