/*
 * L'editor de correus de blocs del panell (Enviaments).
 *
 * És el del Mailing del Tauler de Gestió: el correu es dibuixa al mig tal com
 * sortirà, el text s'escriu a sobre, els blocs s'arrosseguen i les opcions del
 * bloc triat són a la dreta. El que canvia: els colors que es deixen en blanc
 * són els del domini que envia, hi ha camps personals ({{nom}}…), el bloc
 * «Cursa» copia un web del directori, i en comptes d'exportar s'envia.
 *
 * El servidor ho torna a netejar tot en desar (Mail\Doc): el que fa aquí la
 * neteja és només perquè l'editor no s'embruti.
 */
(function () {
  'use strict';
  var cfgEl = document.getElementById('me-cfg');
  if (!cfgEl) return;
  var CFG = JSON.parse(cfgEl.textContent);
  var doc = CFG.doc, subject = CFG.subject, preheader = CFG.preheader, templateName = CFG.templateName;
  var domain = CFG.domain, version = CFG.version;
  var $ = function (id) { return document.getElementById(id); };
  var mailEl = $('me-mail'), propsEl = $('me-props'), rtbar = $('me-rtbar'), fieldsPop = $('me-fields-pop');
  var selected = null, editing = null, fieldTarget = null;
  var SWATCHES = ['#2f6b3c', '#1b452a', '#c8552b', '#1f5480', '#18251d', '#333333', '#000000', '#6b7a70', '#a5401d', '#8a5a06', '#eaf2e6', '#fbeee9', '#e4eef8', '#fdf1dc', '#f4f6f3', '#ffffff'];

  // ------------------------------------------------------------ utilitats
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function uid() { return 'b' + Math.random().toString(16).slice(2, 12); }
  function S() { return doc.settings; }
  function B() { return CFG.brands[domain] || CFG.brands[Object.keys(CFG.brands)[0]]; }
  function font() { return (CFG.fonts[S().font] || CFG.fonts.arial).stack; }
  function headC() { return S().headingColor || B().primary; }
  function linkC() { return S().linkColor || B().primary; }
  function accC() { return S().accent || B().accent; }
  function tone(c) { return c === 'brand' ? B().primary : c; }
  function find(id) { for (var i = 0; i < doc.blocks.length; i++) if (doc.blocks[i].id === id) return i; return -1; }
  function block(id) { var i = find(id); return i < 0 ? null : doc.blocks[i]; }
  function imgUrl(src) {
    if (!src) return '';
    if (src === 'brand:logo') return B().logo || '';
    if (CFG.images[src]) return CFG.images[src];
    return /^https:\/\//.test(src) ? src : '';
  }
  function toast(msg, err) {
    document.querySelectorAll('.me-toast').forEach(function (x) { x.remove(); });
    var t = document.createElement('div'); t.className = 'me-toast' + (err ? ' err' : ''); t.setAttribute('role', 'status'); t.textContent = msg;
    document.body.appendChild(t); setTimeout(function () { t.remove(); }, err ? 6500 : 3500);
  }
  function api(url, opts) {
    opts = opts || {};
    var headers = Object.assign({ 'X-CSRF-TOKEN': CFG.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, opts.headers || {});
    if (opts.json !== undefined) { headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.json); }
    return fetch(url, { method: opts.method || 'GET', headers: headers, body: opts.body, credentials: 'same-origin' });
  }
  function readJson(r) {
    return r.json().catch(function () { return { ok: false, message: 'Resposta no vàlida del servidor (error ' + r.status + ').' }; }).then(function (j) {
      if (!r.ok || j.ok === false) { var e = new Error(j.message || 'No s\'ha pogut fer (error ' + r.status + ').'); e.status = r.status; e.data = j; throw e; }
      return j;
    });
  }
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-close]'); if (c) { var d = c.closest('dialog'); if (d) d.close(); }
    document.querySelectorAll('details.me-menu[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
  });

  // L'HTML enganxat: només el format bàsic.
  var OK_TAGS = { P: 'p', DIV: 'p', BR: 'br', B: 'strong', STRONG: 'strong', I: 'em', EM: 'em', U: 'u', A: 'a', UL: 'ul', OL: 'ol', LI: 'li', H1: 'h3', H2: 'h3', H3: 'h3', H4: 'h4', BLOCKQUOTE: 'blockquote' };
  function cleanHtml(html) {
    var src = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html').body;
    function walk(node) {
      var out = '';
      node.childNodes.forEach(function (n) {
        if (n.nodeType === 3) { out += esc(n.nodeValue); return; }
        if (n.nodeType !== 1) return;
        if (/^(SCRIPT|STYLE|IFRAME|OBJECT|EMBED|IMG|SVG|VIDEO|AUDIO|HEAD|META|LINK|TITLE)$/.test(n.tagName)) return;
        var tag = OK_TAGS[n.tagName], inner = walk(n);
        if (!tag) { out += inner; return; }
        if (tag === 'br') { out += '<br>'; return; }
        if (tag === 'a') { var h = n.getAttribute('href') || ''; out += /^(https?:|mailto:|\{\{)/i.test(h) ? '<a href="' + esc(h) + '">' + inner + '</a>' : inner; return; }
        out += '<' + tag + '>' + inner + '</' + tag + '>';
      });
      return out;
    }
    return walk(src);
  }
  // Els camps personals, marcats a la vista (només fora del bloc que s'edita).
  function markFields(el) {
    var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null), nodes = [];
    while (walker.nextNode()) { if (/\{\{[a-z]+\}\}/.test(walker.currentNode.nodeValue)) nodes.push(walker.currentNode); }
    nodes.forEach(function (n) {
      if (n.parentNode.closest && n.parentNode.closest('[contenteditable="true"]')) return;
      var frag = document.createDocumentFragment();
      n.nodeValue.split(/(\{\{[a-z]+\}\})/).forEach(function (part) {
        if (/^\{\{[a-z]+\}\}$/.test(part)) { var s = document.createElement('span'); s.className = 'me-field'; s.textContent = part; frag.appendChild(s); }
        else if (part) frag.appendChild(document.createTextNode(part));
      });
      n.parentNode.replaceChild(frag, n);
    });
  }

  // ------------------------------------------------------------ desfer / desar
  var undo = [], redo = [], last = snap(), savedState = snap(), commitTimer = null;
  function snap() { return JSON.stringify({ doc: doc, subject: subject, preheader: preheader, templateName: templateName, domain: domain }); }
  function commit(now) {
    clearTimeout(commitTimer);
    var run = function () {
      var cur = snap();
      if (cur !== last) { undo.push(last); if (undo.length > 80) undo.shift(); redo = []; last = cur; }
      updateButtons();
    };
    if (now) run(); else commitTimer = setTimeout(run, 600);
    updateButtons();
  }
  function restore(state) {
    var s = JSON.parse(state); doc = s.doc; subject = s.subject; preheader = s.preheader; templateName = s.templateName; domain = s.domain;
    paintMeta(); last = state;
    if (selected && find(selected) < 0) selected = null;
    renderAll(); renderProps(); updateButtons();
  }
  function doUndo() { commit(true); if (!undo.length) return; redo.push(last); restore(undo.pop()); }
  function doRedo() { if (!redo.length) return; undo.push(last); restore(redo.pop()); }
  function dirty() { return snap() !== savedState; }
  function updateButtons() {
    $('me-undo').disabled = !undo.length && snap() === last;
    $('me-redo').disabled = !redo.length;
    $('me-dirty').hidden = !dirty();
  }
  $('me-undo').addEventListener('click', doUndo);
  $('me-redo').addEventListener('click', doRedo);

  var saving = null;
  function save(opts) {
    opts = opts || {};
    if (saving) return saving.then(function () { return save(opts); });
    commit(true);
    var body = { subject: subject, preheader: preheader, doc: doc, domain: domain, base_version: version, force: !!opts.force };
    if (CFG.isTemplate) body.template_name = templateName;
    var btn = $('me-save'); btn.disabled = true; btn.textContent = 'Desant…';
    var sent = snap();
    saving = api(CFG.urls.save, { method: 'POST', json: body }).then(readJson).then(function (j) {
      version = j.version; savedState = sent;
      $('me-saved').textContent = j.saved; $('me-ver').textContent = 'versió ' + j.version;
      updateButtons();
      if (!opts.quiet) toast(j.result === 'unchanged' ? 'No hi havia canvis per desar.' : 'Desat (versió ' + j.version + ').');
      return j;
    }).catch(function (e) {
      if (e.status === 409) {
        saving = null;
        if (confirm(e.message + '\n\nVoleu desar igualment?')) return save(Object.assign({}, opts, { force: true }));
        throw new Error('cancel');
      }
      if (e.message !== 'cancel') toast(e.message || 'No s\'ha pogut desar.', true);
      throw e;
    }).finally(function () { saving = null; btn.disabled = false; btn.textContent = 'Desa'; });
    return saving;
  }
  function ensureSaved() { return dirty() ? save({ quiet: true }) : Promise.resolve(); }
  $('me-save').addEventListener('click', function () { save().catch(function () {}); });
  window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });
  document.querySelectorAll('form[data-leave]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (f.id === 'me-delete-form' && !confirm(CFG.isTemplate ? 'Voleu eliminar aquesta plantilla base? Els correus que ja se n\'han fet no canvien.' : 'Voleu eliminar aquest correu i totes les seves versions?')) { e.preventDefault(); return; }
      if (f.id !== 'me-delete-form' && dirty() && !confirm('Teniu canvis sense desar que no es copiaran. Voleu continuar?')) { e.preventDefault(); return; }
      savedState = snap();
    });
  });

  // ------------------------------------------------------------ assumpte, safata i domini
  function paintMeta() {
    $('me-title').value = CFG.isTemplate ? templateName : subject;
    if ($('me-subject')) $('me-subject').value = subject;
    $('me-pre').value = preheader;
    $('me-domain').value = domain;
    if ($('me-sender')) $('me-sender').textContent = B().sender || '';
    document.documentElement.style.setProperty('--me-brand1', B().primary);
    document.documentElement.style.setProperty('--me-brand2', B().accent);
  }
  $('me-title').addEventListener('input', function () { if (CFG.isTemplate) templateName = this.value; else subject = this.value; commit(); });
  if ($('me-subject')) $('me-subject').addEventListener('input', function () { subject = this.value; commit(); });
  $('me-pre').addEventListener('input', function () { preheader = this.value; commit(); });
  $('me-domain').addEventListener('change', function () { domain = this.value; paintMeta(); renderAll(); renderProps(); commit(true); });

  // ------------------------------------------------------------ dibuix del correu (vista d'edició)
  function px(b) { return 'padding:' + b.pt + 'px ' + S().padX + 'px ' + b.pb + 'px;' + (b.bg ? 'background:' + b.bg + ';' : ''); }
  function base(size, color) { size = size || S().textSize; return 'font-family:' + font() + ';font-size:' + size + 'px;line-height:' + Math.round(size * 1.45) + 'px;color:' + (color || S().textColor) + ';'; }
  function ph(text, h) { return '<div class="me-ph" style="min-height:' + (h || 120) + 'px">' + text + '</div>'; }
  function imgTag(src, alt, width, radius, placeholder) {
    var u = imgUrl(src);
    if (!u) return placeholder === false ? '' : ph('<span>🖼</span>Afegiu una imatge<br><small style="font-weight:400">Arrossegueu-la aquí o trieu-la a la dreta</small>', 110);
    return '<img src="' + esc(u) + '" alt="' + esc(alt) + '" style="display:inline-block;width:' + width + 'px;max-width:100%;height:auto;border:0;' + (radius ? 'border-radius:' + radius + 'px;' : '') + '">';
  }
  function rich(html, size, color, align) {
    return '<div class="rt" style="' + base(size, color) + 'text-align:' + (align || 'left') + ';">' + (html || '') + '</div>';
  }
  function inner() { return 600 - 2 * S().padX; }
  function para(text, size, color, extra) { return text ? '<div style="' + base(size, color) + (extra || '') + 'white-space:pre-line;">' + esc(text) + '</div>' : ''; }
  function colHtml(c, width) {
    var out = '';
    if (c.src) out += '<div style="margin:0 0 12px">' + imgTag(c.src, c.alt, width, 0) + '</div>';
    if (c.title) out += '<div style="font-family:' + font() + ';font-size:' + (S().textSize + 2) + 'px;font-weight:bold;color:' + headC() + ';margin:0 0 6px">' + esc(c.title) + '</div>';
    out += rich(c.html);
    if (c.linkText) out += '<div style="' + base() + 'margin-top:8px;font-weight:bold;color:' + linkC() + '">' + esc(c.linkText) + '</div>';
    return out;
  }
  function footerName(b) {
    if (b.name) return b.name;
    return (B().entity || B().name) + ' · ' + B().domain;
  }
  var R = {
    header: function (b) {
      var img = imgTag(b.src, b.alt, b.width, 0, false)
        || '<span style="font-family:' + font() + ';font-size:22px;line-height:28px;font-weight:bold;color:' + B().primary + ';">' + esc(b.name || B().name) + '</span>';
      return '<div style="' + px(b) + 'text-align:' + b.align + ';' + (b.borderTop && b.borderWidth ? 'border-top:' + b.borderWidth + 'px solid ' + tone(b.borderTop) + ';' : '') + '">' + img + '</div>';
    },
    heading: function (b) {
      var color = b.color || (b.level === 2 ? headC() : '#1f2a22');
      return '<div style="' + px(b) + '"><div data-edit="heading" style="font-family:' + font() + ';font-size:' + b.size + 'px;line-height:' + Math.round(b.size * 1.35) + 'px;font-weight:bold;color:' + color + ';text-align:' + b.align + ';">' + esc(b.text) + '</div></div>';
    },
    text: function (b) { return '<div style="' + px(b) + '"><div data-edit="rich" class="rt" style="' + base(b.size, b.color) + 'text-align:' + b.align + ';">' + (b.html || '<p></p>') + '</div></div>'; },
    image: function (b) {
      var w = b.width ? Math.min(b.width, inner()) : inner();
      return '<div style="' + px(b) + 'text-align:' + b.align + ';" data-drop-image="1">' + imgTag(b.src, b.alt, w, b.radius) + '</div>';
    },
    button: function (b) {
      var bg = b.btnBg || accC(), h = Math.round(b.size * 2.9);
      return '<div style="' + px(b) + 'text-align:' + b.align + ';"><span style="display:' + (b.full ? 'block' : 'inline-block') + ';background:' + bg + ';color:' + b.color + ';font-family:' + font() + ';font-size:' + b.size + 'px;font-weight:bold;line-height:' + h + 'px;padding:0 28px;border-radius:' + b.radius + 'px;text-align:center;">' + esc(b.text || 'Botó') + '</span></div>';
    },
    link: function (b) {
      return '<div style="' + px(b) + '"><div style="' + base(b.size) + 'text-align:' + b.align + ';color:' + (b.color || linkC()) + ';' + (b.bold ? 'font-weight:bold;' : '') + '">' + esc(b.text || 'Enllaç') + '</div></div>';
    },
    box: function (b) {
      return '<div style="' + px(b) + '"><div style="background:' + b.boxBg + ';border-radius:' + b.radius + 'px;padding:16px 20px;"><div data-edit="rich" class="rt" style="' + base(b.size, b.color) + '">' + (b.html || '<p></p>') + '</div></div></div>';
    },
    columns: function (b) {
      var w = Math.floor((inner() + 20) / b.count) - 20;
      return '<div style="' + px(b) + 'display:grid;grid-template-columns:repeat(' + b.count + ',minmax(0,1fr));gap:20px;">' + b.cols.map(function (c) { return '<div>' + colHtml(c, w) + '</div>'; }).join('') + '</div>';
    },
    imagetext: function (b) {
      var wi = Math.floor((inner() + 20) * 0.45) - 20;
      var img = '<div>' + imgTag(b.src, b.alt, wi, 0) + '</div>', txt = '<div>' + colHtml(Object.assign({}, b, { src: '' }), 0) + '</div>';
      return '<div style="' + px(b) + 'display:grid;grid-template-columns:' + (b.side === 'right' ? '55fr 45fr' : '45fr 55fr') + ';gap:20px;align-items:start;">' + (b.side === 'right' ? txt + img : img + txt) + '</div>';
    },
    divider: function (b) { return '<div style="' + px(b) + '"><div style="width:' + b.widthPct + '%;margin:0 auto;border-top:' + b.thickness + 'px solid ' + b.color + ';"></div></div>'; },
    spacer: function (b) { return '<div style="' + px(b) + 'height:' + b.height + 'px;background-image:repeating-linear-gradient(45deg,transparent 0 6px,rgba(47,107,60,.07) 6px 12px);"></div>'; },
    race: function (b) {
      if (!b.title) return '<div style="' + px(b) + '">' + ph('<span>🏃</span>Trieu un web del directori de curses', 90) + '</div>';
      var size = S().textSize, where = [b.when, b.town].filter(Boolean).join(' · ');
      var text = para(b.title, size + 2, '#1f2a22', 'font-weight:bold;margin-bottom:4px;') + para(where, size - 1, headC(), 'font-weight:bold;margin-bottom:6px;')
        + para(b.desc, size - 1) + (b.href && b.btnText ? '<div style="' + base(size - 1, linkC()) + 'font-weight:bold;margin-top:8px;">' + esc(b.btnText) + '</div>' : '');
      var box = '<div style="background:' + b.boxBg + ';border-radius:12px;padding:14px 16px;">' + text + '</div>';
      var u = imgUrl(b.src);
      return '<div style="' + px(b) + '">' + (u ? '<div style="display:grid;grid-template-columns:38fr 62fr;gap:20px;align-items:start;"><img src="' + esc(u) + '" alt="" style="display:block;width:100%;height:auto;border-radius:10px;">' + box + '</div>' : box) + '</div>';
    },
    news: function (b) {
      var size = S().textSize, u = imgUrl(b.src);
      return '<div style="' + px(b) + '"><div style="background:' + b.boxBg + ';border-radius:12px;padding:16px 18px;">'
        + (u ? '<img src="' + esc(u) + '" alt="" style="display:block;width:100%;height:auto;border-radius:8px;margin:0 0 12px;">' : '')
        + (b.kicker ? '<div style="font-family:' + font() + ';font-size:11px;font-weight:bold;letter-spacing:1px;color:' + headC() + ';margin-bottom:4px;">' + esc(b.kicker.toUpperCase()) + '</div>' : '')
        + para(b.title || 'Títol de la novetat', size + 2, '#1f2a22', 'font-weight:bold;margin-bottom:6px;') + para(b.desc, size - 1)
        + (b.href && b.btnText ? '<div style="' + base(size - 1, linkC()) + 'font-weight:bold;margin-top:8px;">' + esc(b.btnText) + '</div>' : '')
        + '</div></div>';
    },
    footer: function (b) {
      var out = '';
      if (b.lineColor) out += '<div style="border-top:1px solid ' + tone(b.lineColor) + ';margin-bottom:16px;"></div>';
      if (b.width) out += '<div style="text-align:center;margin-bottom:10px;">' + (imgTag(b.src || 'brand:logo', b.alt, b.width, 0, false) || '') + '</div>';
      out += '<div style="font-family:' + font() + ';font-size:12px;line-height:18px;text-align:center;color:' + b.color + ';font-weight:bold;margin-bottom:6px;">' + esc(footerName(b)) + '</div>';
      if (b.links.length) out += '<div style="font-family:' + font() + ';font-size:12px;line-height:18px;text-align:center;color:#5a6b60;">' + b.links.map(function (l) { return '<span style="color:' + (b.linkColor || linkC()) + '">' + esc(l.text) + '</span>'; }).join('&nbsp;&nbsp;·&nbsp;&nbsp;') + '</div>';
      if (b.note) out += '<div style="font-family:' + font() + ';font-size:11px;line-height:16px;text-align:center;color:' + b.color + ';margin-top:12px;white-space:pre-line;">' + esc(b.note) + '</div>';
      return '<div style="' + px(b) + '">' + out + '</div>';
    }
  };

  function blockEl(b) {
    var el = document.createElement('div');
    el.className = 'me-b' + (b.id === selected ? ' sel' : '');
    el.dataset.id = b.id; el.tabIndex = 0;
    el.setAttribute('role', 'group'); el.setAttribute('aria-label', 'Bloc: ' + CFG.types[b.type]);
    el.innerHTML = '<span class="me-b-name">' + esc(CFG.types[b.type]) + '</span>'
      + '<span class="me-b-tools">'
      + '<button type="button" class="grip" draggable="true" data-act="grip" title="Arrossegueu per moure" aria-label="Arrossegueu per moure">⠿</button>'
      + '<button type="button" data-act="up" title="Puja" aria-label="Puja el bloc">↑</button>'
      + '<button type="button" data-act="down" title="Baixa" aria-label="Baixa el bloc">↓</button>'
      + '<button type="button" data-act="dup" title="Duplica" aria-label="Duplica el bloc">⧉</button>'
      + '<button type="button" data-act="del" title="Elimina" aria-label="Elimina el bloc">✕</button>'
      + '</span>'
      + '<div class="me-b-in">' + (R[b.type] ? R[b.type](b) : '') + '</div>';
    if (b.hideMobile) el.querySelector('.me-b-in').style.opacity = '.9';
    if (b.id === selected) makeEditable(el, b); else markFields(el.querySelector('.me-b-in'));
    return el;
  }
  function renderAll() {
    mailEl.style.background = S().contentBg;
    mailEl.parentNode.style.background = S().bodyBg === '#ffffff' ? '#e3e9e0' : S().bodyBg;
    mailEl.innerHTML = '';
    if (!doc.blocks.length) { mailEl.innerHTML = '<div class="me-empty">El correu és buit. Arrossegueu-hi blocs de l\'esquerra.</div>'; }
    doc.blocks.forEach(function (b) { mailEl.appendChild(blockEl(b)); });
    // La baixa la posa sempre el servidor: aquí només es veu on anirà.
    var off = document.createElement('div');
    off.style.cssText = 'padding:6px ' + S().padX + 'px 26px;text-align:center;font-family:' + font() + ';font-size:11px;line-height:16px;color:#8a948d;';
    off.innerHTML = 'Si no voleu rebre més correus d\'aquesta mena, <u>doneu-vos de baixa</u>.';
    off.title = 'Aquesta línia hi va sempre, amb l\'enllaç de baixa de cada persona.';
    mailEl.appendChild(off);
    var drop = document.createElement('div'); drop.className = 'me-drop'; drop.id = 'me-drop'; drop.innerHTML = '<span>Deixeu anar aquí</span>';
    mailEl.appendChild(drop);
    hideRt();
  }
  function renderBlock(id) {
    var old = mailEl.querySelector('.me-b[data-id="' + id + '"]'), b = block(id);
    if (!old || !b) { renderAll(); return; }
    old.replaceWith(blockEl(b));
  }

  // Edició del text directament al correu (títols, text i requadres).
  function makeEditable(el, b) {
    el.querySelectorAll('[data-edit]').forEach(function (t) {
      t.contentEditable = 'true';
      t.spellcheck = true;
      if (t.dataset.edit === 'heading') {
        t.addEventListener('focus', function () { editing = t; showRt(t, true); });
        t.addEventListener('input', function () { b.text = t.textContent.replace(/\s+/g, ' ').trim(); commit(); });
        t.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); t.blur(); } });
        t.addEventListener('blur', function () { var f = propsEl.querySelector('input[type="text"]'); if (f && selected === b.id && b.type === 'heading') f.value = b.text; });
        t.addEventListener('paste', function (e) { e.preventDefault(); document.execCommand('insertText', false, (e.clipboardData.getData('text/plain') || '').replace(/\s+/g, ' ')); });
      } else {
        t.addEventListener('focus', function () { editing = t; showRt(t, false); try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (x) {} });
        t.addEventListener('input', function () { b.html = t.innerHTML; commit(); });
        t.addEventListener('paste', function (e) {
          e.preventDefault();
          var html = e.clipboardData.getData('text/html'), text = e.clipboardData.getData('text/plain');
          document.execCommand('insertHTML', false, html ? cleanHtml(html) : esc(text).replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>'));
        });
      }
    });
  }

  // La barra de format del text (en un títol, només els camps).
  function showRt(target, fieldsOnly) {
    rtbar.querySelectorAll('button').forEach(function (b) { b.hidden = fieldsOnly && b.dataset.cmd !== 'fields'; });
    var r = target.getBoundingClientRect();
    rtbar.style.display = 'flex';
    var top = window.scrollY + r.top - rtbar.offsetHeight - 8, left = window.scrollX + r.left;
    rtbar.style.top = Math.max(window.scrollY + 70, top) + 'px';
    rtbar.style.left = Math.max(8, Math.min(left, window.scrollX + document.documentElement.clientWidth - rtbar.offsetWidth - 8)) + 'px';
  }
  function hideRt() { rtbar.style.display = 'none'; fieldsPop.style.display = 'none'; editing = null; }
  rtbar.addEventListener('mousedown', function (e) { e.preventDefault(); });
  fieldsPop.addEventListener('mousedown', function (e) { e.preventDefault(); });
  rtbar.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-cmd]'); if (!b || !editing) return;
    var cmd = b.dataset.cmd;
    if (cmd === 'fields') { openFields(b, editing); return; }
    if (cmd === 'link') {
      var url = prompt('Adreça de l\'enllaç (https://… o mailto:…)', 'https://');
      if (!url || url === 'https://') return;
      if (/^www\./i.test(url)) url = 'https://' + url;
      if (!/^(https?:\/\/|mailto:)/i.test(url)) { toast('L\'enllaç ha de començar per https:// o mailto:', true); return; }
      document.execCommand('createLink', false, url);
    } else if (cmd === 'h3') {
      var cur = (document.queryCommandValue('formatBlock') || '').toLowerCase();
      document.execCommand('formatBlock', false, cur === 'h3' ? 'p' : 'h3');
    } else {
      document.execCommand(cmd, false, null);
    }
    editing.dispatchEvent(new Event('input', { bubbles: true }));
  });
  // El menú dels camps: a un text de l'editor o a un camp de formulari (l'assumpte).
  function openFields(anchor, target) {
    fieldTarget = target;
    var r = anchor.getBoundingClientRect();
    fieldsPop.style.display = 'flex';
    fieldsPop.style.top = (window.scrollY + r.bottom + 6) + 'px';
    fieldsPop.style.left = Math.max(8, Math.min(window.scrollX + r.left, window.scrollX + document.documentElement.clientWidth - fieldsPop.offsetWidth - 8)) + 'px';
  }
  fieldsPop.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-field]'); if (!b || !fieldTarget) return;
    var tag = b.dataset.field, t = fieldTarget;
    fieldsPop.style.display = 'none';
    if (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA') {
      var s = t.selectionStart == null ? t.value.length : t.selectionStart, en = t.selectionEnd == null ? s : t.selectionEnd;
      t.value = t.value.slice(0, s) + tag + t.value.slice(en);
      t.focus(); t.setSelectionRange(s + tag.length, s + tag.length);
      t.dispatchEvent(new Event('input', { bubbles: true }));
      return;
    }
    t.focus(); document.execCommand('insertText', false, tag);
    t.dispatchEvent(new Event('input', { bubbles: true }));
  });
  if ($('me-subject-fields')) $('me-subject-fields').addEventListener('click', function (e) { e.stopPropagation(); openFields(this, $('me-title')); });
  document.addEventListener('click', function (e) { if (!e.target.closest('#me-fields-pop, #me-rtbar, #me-subject-fields, [data-fields]')) fieldsPop.style.display = 'none'; });
  document.addEventListener('focusin', function (e) { if (editing && !e.target.closest('[data-edit], .me-rich, #me-rtbar, #me-fields-pop')) hideRt(); });
  window.addEventListener('scroll', function () { if (editing) showRt(editing, editing.dataset.edit === 'heading'); }, { passive: true });

  // ------------------------------------------------------------ selecció i accions dels blocs
  function select(id, focusProps) {
    if (selected === id) return;
    var prev = selected; selected = id;
    if (prev && block(prev)) renderBlock(prev);
    if (id) renderBlock(id);
    renderProps();
    if (focusProps) { var f = propsEl.querySelector('input, select, textarea'); if (f) f.focus(); }
  }
  mailEl.addEventListener('click', function (e) {
    var act = e.target.closest('[data-act]'), el = e.target.closest('.me-b');
    if (!el) return;
    var id = el.dataset.id;
    if (act) {
      var i = find(id);
      if (act.dataset.act === 'up' && i > 0) move(i, i - 1);
      else if (act.dataset.act === 'down' && i < doc.blocks.length - 1) move(i, i + 2);
      else if (act.dataset.act === 'dup') duplicate(id);
      else if (act.dataset.act === 'del') removeBlock(id);
      return;
    }
    if (id !== selected) {
      select(id);
      var t = mailEl.querySelector('.me-b[data-id="' + id + '"] [data-edit]');
      if (t && e.target.closest('[data-edit]')) t.focus();
    }
  });
  function duplicate(id) { commit(true); var i = find(id), c = clone(doc.blocks[i]); c.id = uid(); doc.blocks.splice(i + 1, 0, c); selected = c.id; renderAll(); renderProps(); commit(true); }
  function move(from, to) {
    commit(true);
    var b = doc.blocks.splice(from, 1)[0];
    if (to > from) to--;
    doc.blocks.splice(Math.max(0, Math.min(to, doc.blocks.length)), 0, b);
    renderAll(); commit(true);
    var el = mailEl.querySelector('.me-b[data-id="' + b.id + '"]'); if (el) el.focus();
  }
  function removeBlock(id) {
    var i = find(id); if (i < 0) return;
    commit(true);
    doc.blocks.splice(i, 1);
    if (selected === id) selected = doc.blocks[Math.min(i, doc.blocks.length - 1)] ? doc.blocks[Math.min(i, doc.blocks.length - 1)].id : null;
    renderAll(); renderProps(); commit(true);
    toast('Bloc eliminat. Es pot desfer amb Ctrl+Z.');
  }
  function newBlock(type) { var b = clone(CFG.defaults[type]); b.type = type; b.id = uid(); return b; }
  function insert(type, index) {
    commit(true);
    var b = newBlock(type);
    doc.blocks.splice(index, 0, b);
    selected = b.id; renderAll(); renderProps(); commit(true);
    var el = mailEl.querySelector('.me-b[data-id="' + b.id + '"]');
    if (el) { el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); var t = el.querySelector('[data-edit]'); if (t) setTimeout(function () { t.focus(); }, 50); }
    if (type === 'race') setTimeout(function () { openPicker(b.id); }, 60);
    return b;
  }
  /** On va un bloc clicat a la paleta: sota el seleccionat; si no, abans del peu. */
  function defaultIndex() {
    var i = selected ? find(selected) : -1;
    if (i >= 0) return i + 1;
    var n = doc.blocks.length;
    return n && doc.blocks[n - 1].type === 'footer' ? n - 1 : n;
  }
  ['me-pal', 'me-pal-own'].forEach(function (id) { $(id).addEventListener('click', function (e) { var b = e.target.closest('button[data-type]'); if (b) insert(b.dataset.type, defaultIndex()); }); });

  document.addEventListener('keydown', function (e) {
    var inField = e.target.closest('input, textarea, select, [contenteditable="true"]');
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save().catch(function () {}); return; }
    if (inField) return;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); if (e.shiftKey) doRedo(); else doUndo(); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); doRedo(); return; }
    var el = e.target.closest && e.target.closest('.me-b');
    if (!el || e.target !== el) return;
    var i = find(el.dataset.id);
    if (e.key === 'Enter') select(el.dataset.id, true);
    else if (e.key === 'Escape') select(null);
    else if (e.altKey && e.key === 'ArrowUp' && i > 0) { e.preventDefault(); move(i, i - 1); }
    else if (e.altKey && e.key === 'ArrowDown' && i < doc.blocks.length - 1) { e.preventDefault(); move(i, i + 2); }
    else if (e.key === 'Delete') removeBlock(el.dataset.id);
  });

  // ------------------------------------------------------------ arrossegar i deixar anar
  var dragData = null;
  ['me-pal', 'me-pal-own'].forEach(function (id) {
    $(id).addEventListener('dragstart', function (e) {
      var b = e.target.closest('button[data-type]'); if (!b) return;
      dragData = { kind: 'new', type: b.dataset.type };
      e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain', 'bloc:' + b.dataset.type);
    });
  });
  mailEl.addEventListener('dragstart', function (e) {
    var g = e.target.closest('[data-act="grip"]'); if (!g) return;
    var el = g.closest('.me-b');
    dragData = { kind: 'move', id: el.dataset.id };
    e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'bloc');
    try { e.dataTransfer.setDragImage(el, 20, 20); } catch (x) {}
    setTimeout(function () { el.classList.add('dragging'); }, 0);
  });
  document.addEventListener('dragend', function () { dragData = null; hideDrop(); mailEl.querySelectorAll('.dragging').forEach(function (x) { x.classList.remove('dragging'); }); });
  function dropIndex(y) {
    var els = mailEl.querySelectorAll('.me-b');
    for (var i = 0; i < els.length; i++) { var r = els[i].getBoundingClientRect(); if (y < r.top + r.height / 2) return i; }
    return els.length;
  }
  function showDrop(index, label) {
    var d = $('me-drop'), els = mailEl.querySelectorAll('.me-b'), top;
    if (!d) return;
    var box = mailEl.getBoundingClientRect();
    if (!els.length) top = 20;
    else if (index < els.length) top = els[index].getBoundingClientRect().top - box.top - 2;
    else top = els[els.length - 1].getBoundingClientRect().bottom - box.top - 2;
    d.style.top = top + 'px'; d.style.display = 'block'; d.querySelector('span').textContent = label;
  }
  function hideDrop() { var d = $('me-drop'); if (d) d.style.display = 'none'; }
  function isFileDrag(e) { return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') >= 0; }
  mailEl.addEventListener('dragover', function (e) {
    if (!dragData && !isFileDrag(e)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = dragData && dragData.kind === 'move' ? 'move' : 'copy';
    if (isFileDrag(e) && e.target.closest('[data-drop-image]')) { hideDrop(); return; }
    showDrop(dropIndex(e.clientY), dragData ? (dragData.kind === 'new' ? 'Deixeu anar aquí: ' + CFG.types[dragData.type] : 'Mou aquí') : 'Deixeu anar aquí: imatge');
  });
  mailEl.addEventListener('dragleave', function (e) { if (!mailEl.contains(e.relatedTarget)) hideDrop(); });
  mailEl.addEventListener('drop', function (e) {
    e.preventDefault(); hideDrop();
    var index = dropIndex(e.clientY);
    if (isFileDrag(e) && e.dataTransfer.files.length) {
      var file = e.dataTransfer.files[0], onImage = e.target.closest('[data-drop-image]');
      var target = onImage ? block(onImage.closest('.me-b').dataset.id) : insert('image', index);
      upload(file, function (src) { target.src = src; if (!target.alt) target.alt = file.name.replace(/\.[a-z0-9]+$/i, ''); selected = target.id; renderAll(); renderProps(); commit(true); });
      return;
    }
    if (!dragData) return;
    if (dragData.kind === 'new') insert(dragData.type, index);
    else { var from = find(dragData.id); if (from >= 0 && index !== from && index !== from + 1) move(from, index); }
    dragData = null;
  });

  // ------------------------------------------------------------ imatges
  function upload(file, done) {
    if (!/^image\//.test(file.type)) { toast('Només es poden afegir imatges (JPG, PNG o GIF).', true); return; }
    var fd = new FormData(); fd.append('image', file);
    toast('Pujant la imatge…');
    api(CFG.urls.upload, { method: 'POST', body: fd }).then(readJson)
      .then(function (j) { CFG.images[j.src] = j.url; done(j.src, j); })
      .catch(function (e) { toast(e.message, true); });
  }

  // ------------------------------------------------------------ panell d'opcions
  function h(tag, attrs, html) { var el = document.createElement(tag); Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, attrs[k]); }); if (html != null) el.innerHTML = html; return el; }
  function changed(obj, rerender) {
    if (rerender === 'all') renderAll(); else if (selected) renderBlock(selected);
    commit();
  }
  function fText(parent, label, obj, key, opts) {
    opts = opts || {};
    var f = h('label', { 'class': 'me-f' }, esc(label));
    var input = h(opts.area ? 'textarea' : 'input', opts.area ? { maxlength: opts.max || 1500 } : { type: opts.type || 'text', maxlength: opts.max || 300 });
    input.value = obj[key] == null ? '' : obj[key];
    if (opts.placeholder) input.placeholder = opts.placeholder;
    input.addEventListener('input', function () { obj[key] = input.value; changed(obj, opts.rerender); });
    f.appendChild(input);
    if (opts.fields) {
      var fb = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm', 'data-fields': '1', style: 'align-self:flex-start' }, '{ } Camps');
      fb.addEventListener('click', function (e) { e.stopPropagation(); openFields(fb, input); });
      f.appendChild(fb);
    }
    parent.appendChild(f); return input;
  }
  function fUrl(parent, label, obj, key) {
    var input = fText(parent, label, obj, key, { type: 'text', max: 1000, placeholder: 'https://…' });
    input.addEventListener('change', function () {
      var v = input.value.trim();
      if (/^www\./i.test(v)) { v = 'https://' + v; input.value = v; obj[key] = v; changed(obj); }
      input.setCustomValidity(v && !/^(https?:\/\/|mailto:|tel:)/i.test(v) ? 'Ha de començar per https://, mailto: o tel:' : '');
      input.reportValidity();
    });
    return input;
  }
  function fRange(parent, label, obj, key, min, max, step, unit, rerender) {
    var f = h('div', { 'class': 'me-f' }), id = 'r' + Math.random().toString(16).slice(2);
    f.appendChild(h('label', { 'for': id }, esc(label)));
    var row = h('div', { 'class': 'row' }), input = h('input', { type: 'range', min: min, max: max, step: step || 1, id: id }), val = h('span', { 'class': 'val' });
    input.value = obj[key]; val.textContent = obj[key] + (unit || '');
    input.addEventListener('input', function () { obj[key] = Number(input.value); val.textContent = input.value + (unit || ''); changed(obj, rerender); });
    row.appendChild(input); row.appendChild(val); f.appendChild(row); parent.appendChild(f);
  }
  function fSeg(parent, label, obj, key, options, rerender, after) {
    var f = h('div', { 'class': 'me-f' }); f.appendChild(h('span', {}, esc(label)));
    var seg = h('div', { 'class': 'seg', role: 'group', 'aria-label': label });
    options.forEach(function (o) {
      var b = h('button', { type: 'button', 'aria-pressed': String(obj[key]) === String(o[0]) ? 'true' : 'false' }, esc(o[1]));
      b.addEventListener('click', function () {
        obj[key] = o[0]; seg.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', 'false'); }); b.setAttribute('aria-pressed', 'true');
        changed(obj, rerender); if (after) after();
      });
      seg.appendChild(b);
    });
    f.appendChild(seg); parent.appendChild(f);
  }
  function fSelect(parent, label, obj, key, options, rerender) {
    var f = h('label', { 'class': 'me-f' }, esc(label)), sel = h('select');
    options.forEach(function (o) { var op = h('option', { value: o[0] }, esc(o[1])); if (String(obj[key]) === String(o[0])) op.selected = true; sel.appendChild(op); });
    sel.addEventListener('change', function () { obj[key] = isNaN(Number(sel.value)) || sel.value === '' ? sel.value : Number(sel.value); changed(obj, rerender); });
    f.appendChild(sel); parent.appendChild(f);
  }
  function fCheck(parent, label, obj, key, rerender) {
    var l = h('label', { 'class': 'me-chk' }), c = h('input', { type: 'checkbox' }); c.checked = !!obj[key];
    c.addEventListener('change', function () { obj[key] = c.checked; changed(obj, rerender); });
    l.appendChild(c); l.appendChild(document.createTextNode(label)); parent.appendChild(l);
  }
  /** empty: '' es permet («cap» o «el del domini»); brand: el color principal del domini com a opció. */
  function fColor(parent, label, obj, key, empty, rerender, brand) {
    var f = h('div', { 'class': 'me-f' }); f.appendChild(h('span', {}, esc(label)));
    var sw = h('div', { 'class': 'me-sw', role: 'group', 'aria-label': label });
    function paint() { sw.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', (x.dataset.c || '') === (obj[key] || '') ? 'true' : 'false'); }); picker.value = /^#/.test(obj[key] || '') ? obj[key] : '#ffffff'; }
    if (empty) {
      var n = h('button', { type: 'button', 'class': empty === 'domain' ? 'brand' : 'none', title: empty === 'domain' ? 'El del domini que envia' : 'Cap', 'aria-label': empty === 'domain' ? 'El color del domini' : 'Cap color' });
      n.dataset.c = ''; sw.appendChild(n);
    }
    if (brand) { var bb = h('button', { type: 'button', 'class': 'brand', title: 'El color del domini que envia', 'aria-label': 'El color del domini' }); bb.dataset.c = 'brand'; sw.appendChild(bb); }
    SWATCHES.forEach(function (c) { var b = h('button', { type: 'button', title: c, 'aria-label': 'Color ' + c }); b.style.background = c; b.dataset.c = c; sw.appendChild(b); });
    var picker = h('input', { type: 'color', title: 'Un altre color', 'aria-label': 'Un altre color' });
    sw.appendChild(picker);
    sw.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; obj[key] = b.dataset.c || ''; paint(); changed(obj, rerender); });
    picker.addEventListener('input', function () { obj[key] = picker.value; paint(); changed(obj, rerender); });
    f.appendChild(sw); parent.appendChild(f); paint();
  }
  function fImage(parent, obj, key, opts) {
    opts = opts || {};
    var g = h('div', { 'class': 'me-group' }); g.appendChild(h('b', {}, opts.title || 'Imatge'));
    var prev = h('img', { 'class': 'me-imgprev', alt: '' });
    function paint() { var u = imgUrl(obj[key]); prev.hidden = !u; if (u) prev.src = u; }
    g.appendChild(prev); paint();
    var row = h('div', { style: 'display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;' });
    var file = h('input', { type: 'file', accept: 'image/png,image/jpeg,image/gif,image/webp', hidden: '' });
    var up = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm' }, 'Puja una imatge');
    up.addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () { if (file.files[0]) upload(file.files[0], function (src) { obj[key] = src; paint(); url.value = ''; changed(obj); commit(true); renderProps(); }); });
    row.appendChild(up); row.appendChild(file);
    if (opts.logo) {
      var lg = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm' }, 'Logotip del domini');
      lg.addEventListener('click', function () { obj[key] = 'brand:logo'; paint(); changed(obj); renderProps(); });
      row.appendChild(lg);
    }
    if (obj[key]) { var rm = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm' }, 'Treu-la'); rm.addEventListener('click', function () { obj[key] = ''; paint(); changed(obj); renderProps(); }); row.appendChild(rm); }
    g.appendChild(row);
    if (obj[key] === 'brand:logo' && !B().logo) g.appendChild(h('p', { 'class': 'me-small' }, 'Aquest domini no té logotip en imatge (o és un SVG, que els programes de correu no ensenyen): hi surt el nom.'));
    var tmp = { u: /^https:\/\//.test(obj[key] || '') ? obj[key] : '' };
    var url = fText(g, 'O l\'adreça d\'una imatge d\'internet', tmp, 'u', { type: 'url', max: 1000, placeholder: 'https://…' });
    url.addEventListener('change', function () { var v = url.value.trim(); if (/^https:\/\//.test(v)) { obj[key] = v; paint(); changed(obj); } else if (v) toast('L\'adreça de la imatge ha de començar per https://', true); });
    if (opts.alt !== false) fText(g, 'Text alternatiu (si no es veu la imatge)', obj, 'alt', { max: 200 });
    parent.appendChild(g);
  }
  function fRich(parent, label, obj, key) {
    var f = h('div', { 'class': 'me-f' }); f.appendChild(h('span', {}, esc(label)));
    var ed = h('div', { 'class': 'me-rich', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': label });
    ed.innerHTML = obj[key] || '';
    ed.addEventListener('focus', function () { editing = ed; showRt(ed, false); try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (x) {} });
    ed.addEventListener('input', function () { obj[key] = ed.innerHTML; if (selected) renderBlock(selected); commit(); });
    ed.addEventListener('paste', function (e) { e.preventDefault(); var html = e.clipboardData.getData('text/html'), text = e.clipboardData.getData('text/plain'); document.execCommand('insertHTML', false, html ? cleanHtml(html) : esc(text).replace(/\n/g, '<br>')); });
    f.appendChild(ed); parent.appendChild(f);
  }
  function fCommon(parent, b) {
    parent.appendChild(h('div', { 'class': 'me-sep' }));
    fRange(parent, 'Espai a dalt', b, 'pt', 0, 80, 2, ' px');
    fRange(parent, 'Espai a baix', b, 'pb', 0, 80, 2, ' px');
    fColor(parent, 'Fons de la franja', b, 'bg', true);
    fCheck(parent, 'Amaga-ho al mòbil', b, 'hideMobile');
  }
  var ALIGN = [['left', 'Esquerra'], ['center', 'Centre'], ['right', 'Dreta']];
  var SIZES = [[0, 'Per defecte'], [13, '13 px'], [14, '14 px'], [15, '15 px'], [16, '16 px'], [17, '17 px'], [18, '18 px'], [20, '20 px']];
  function note(p, text) { p.appendChild(h('p', { 'class': 'me-small' }, text)); }

  var PANELS = {
    header: function (p, b) {
      fImage(p, b, 'src', { title: 'Logotip', logo: true });
      fRange(p, 'Amplada del logotip', b, 'width', 60, 560, 5, ' px');
      fText(p, 'Nom, si no hi ha logotip (en blanc: el del domini)', b, 'name', { max: 120 });
      fSeg(p, 'Alineació', b, 'align', ALIGN);
      fUrl(p, 'Enllaç del logotip (opcional)', b, 'href');
      fColor(p, 'Línia de dalt', b, 'borderTop', true, null, true);
      fRange(p, 'Gruix de la línia', b, 'borderWidth', 0, 8, 1, ' px');
    },
    heading: function (p, b) {
      fText(p, 'Text del títol', b, 'text', { max: 300, fields: true });
      fSeg(p, 'Tipus', b, 'level', [[1, 'Títol'], [2, 'Subtítol']], null, function () { b.size = b.level === 2 ? 18 : 22; b.color = ''; renderProps(); renderBlock(b.id); });
      fRange(p, 'Mida', b, 'size', 14, 34, 1, ' px');
      fColor(p, 'Color (cap = el de l\'estil)', b, 'color', true);
      fSeg(p, 'Alineació', b, 'align', ALIGN);
    },
    text: function (p, b) {
      note(p, 'Escriviu directament al correu. Seleccioneu text per posar-hi negreta, enllaços, llistes o camps personals ({ } Camps).');
      fSelect(p, 'Mida del text', b, 'size', SIZES);
      fColor(p, 'Color del text (cap = el de l\'estil)', b, 'color', true);
      fSeg(p, 'Alineació', b, 'align', ALIGN);
    },
    image: function (p, b) {
      fImage(p, b, 'src');
      fSelect(p, 'Amplada', b, 'width', [[0, 'Tota l\'amplada'], [480, '480 px'], [400, '400 px'], [300, '300 px'], [200, '200 px'], [120, '120 px']]);
      fSeg(p, 'Alineació', b, 'align', ALIGN);
      fRange(p, 'Cantonades arrodonides', b, 'radius', 0, 30, 1, ' px');
      fUrl(p, 'Enllaç en clicar la imatge (opcional)', b, 'href');
    },
    button: function (p, b) {
      fText(p, 'Text del botó', b, 'text', { max: 80 });
      fUrl(p, 'Enllaç (es pot fer servir {{web}})', b, 'href');
      fColor(p, 'Color del botó (cap = el del domini)', b, 'btnBg', 'domain');
      fColor(p, 'Color del text', b, 'color', false);
      fSeg(p, 'Alineació', b, 'align', ALIGN);
      fSeg(p, 'Amplada', b, 'full', [[false, 'Segons el text'], [true, 'Tota l\'amplada']]);
      fRange(p, 'Mida del text', b, 'size', 13, 22, 1, ' px');
      fRange(p, 'Cantonades', b, 'radius', 0, 30, 1, ' px');
      fCheck(p, 'Al mòbil, tota l\'amplada', b, 'mobileFull');
      note(p, 'A l\'Outlook per a Windows el botó es dibuixa amb un format especial perquè es vegi igual.');
    },
    link: function (p, b) {
      fText(p, 'Text de l\'enllaç', b, 'text', { max: 160 });
      fUrl(p, 'Adreça', b, 'href');
      fColor(p, 'Color (cap = el dels enllaços)', b, 'color', true);
      fCheck(p, 'Negreta', b, 'bold');
      fSelect(p, 'Mida', b, 'size', SIZES);
      fSeg(p, 'Alineació', b, 'align', ALIGN);
    },
    box: function (p, b) {
      note(p, 'Escriviu directament dins del requadre, al correu.');
      fColor(p, 'Fons del requadre', b, 'boxBg', false);
      fColor(p, 'Color del text (cap = el de l\'estil)', b, 'color', true);
      fSelect(p, 'Mida del text', b, 'size', SIZES);
      fRange(p, 'Cantonades', b, 'radius', 0, 30, 1, ' px');
    },
    columns: function (p, b) {
      fSeg(p, 'Columnes', b, 'count', [[2, '2'], [3, '3']], null, function () {
        while (b.cols.length < b.count) b.cols.push(clone(CFG.defaults.columns.cols[0]));
        b.cols.length = b.count; renderProps(); renderBlock(b.id);
      });
      note(p, 'Al mòbil, les columnes es posen una sota l\'altra.');
      b.cols.forEach(function (c, i) {
        var g = h('div', { 'class': 'me-group' }); g.appendChild(h('b', {}, 'Columna ' + (i + 1)));
        colFields(g, c);
        p.appendChild(g);
      });
    },
    imagetext: function (p, b) {
      fSeg(p, 'Imatge a', b, 'side', [['left', 'L\'esquerra'], ['right', 'La dreta']]);
      colFields(p, b);
    },
    divider: function (p, b) {
      fColor(p, 'Color', b, 'color', false);
      fRange(p, 'Gruix', b, 'thickness', 1, 6, 1, ' px');
      fRange(p, 'Amplada', b, 'widthPct', 10, 100, 5, ' %');
    },
    spacer: function (p, b) { fRange(p, 'Alçada', b, 'height', 4, 120, 2, ' px'); },
    race: function (p, b) {
      var row = h('div', { style: 'display:flex;gap:6px;margin:0 0 12px;flex-wrap:wrap;' });
      var pick = h('button', { type: 'button', 'class': 'btn' + (b.title ? ' btn--ghost' : ''), style: 'flex:1;' }, b.title ? 'Trieu-ne un altre' : 'Trieu un web del directori');
      pick.addEventListener('click', function () { openPicker(b.id); });
      row.appendChild(pick); p.appendChild(row);
      if (!b.title) return;
      fText(p, 'Nom', b, 'title', { max: 200 });
      fText(p, 'Quan', b, 'when', { max: 120 });
      fText(p, 'On', b, 'town', { max: 120 });
      fText(p, 'Text (opcional)', b, 'desc', { area: true, max: 1000 });
      fText(p, 'Text de l\'enllaç', b, 'btnText', { max: 80 });
      fUrl(p, 'Adreça del web', b, 'href');
      fImage(p, b, 'src', { title: 'Imatge de portada', alt: false });
      fColor(p, 'Fons del requadre', b, 'boxBg', false);
    },
    news: function (p, b) {
      fText(p, 'Etiqueta', b, 'kicker', { max: 40, placeholder: 'Novetat' });
      fText(p, 'Títol', b, 'title', { max: 200 });
      fText(p, 'Text', b, 'desc', { area: true, max: 1500 });
      fText(p, 'Text de l\'enllaç', b, 'btnText', { max: 80 });
      fUrl(p, 'Adreça (per exemple, la pàgina de funcionalitats)', b, 'href');
      fImage(p, b, 'src', { alt: false });
      fColor(p, 'Fons del requadre', b, 'boxBg', false);
    },
    footer: function (p, b) {
      fImage(p, b, 'src', { title: 'Logotip (en blanc: el del domini)', logo: true });
      fRange(p, 'Amplada del logotip (0 = sense)', b, 'width', 0, 400, 5, ' px');
      fText(p, 'Nom (en blanc: el de l\'entitat i el domini)', b, 'name', { max: 200 });
      var g = h('div', { 'class': 'me-group' }); g.appendChild(h('b', {}, 'Enllaços'));
      b.links.forEach(function (l, i) {
        var row = h('div', { style: 'display:grid;grid-template-columns:1fr auto;gap:6px;align-items:end;' });
        var col = h('div');
        fText(col, 'Text', l, 'text', { max: 60 }); fUrl(col, 'Adreça', l, 'href');
        var rm = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm', style: 'margin-bottom:12px;', 'aria-label': 'Treu l\'enllaç ' + (i + 1) }, '✕');
        rm.addEventListener('click', function () { b.links.splice(i, 1); renderProps(); renderBlock(b.id); commit(true); });
        row.appendChild(col); row.appendChild(rm); g.appendChild(row);
      });
      if (b.links.length < 6) {
        var add = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm' }, '+ Afegeix un enllaç');
        add.addEventListener('click', function () { b.links.push({ text: 'Enllaç', href: '' }); renderProps(); renderBlock(b.id); commit(true); });
        g.appendChild(add);
      }
      p.appendChild(g);
      fText(p, 'Per què el reben, o un text petit (opcional)', b, 'note', { area: true, max: 1500 });
      note(p, 'A sota del peu hi va sempre la línia per donar-se de baixa: no cal posar-la.');
      fColor(p, 'Línia de dalt', b, 'lineColor', true, null, true);
      fColor(p, 'Color del text', b, 'color', false);
      fColor(p, 'Color dels enllaços (cap = el de l\'estil)', b, 'linkColor', true);
    }
  };
  function colFields(g, c) {
    if (CFG.ai.show) {
      var owner = selected, row = h('div', { style: 'display:flex;gap:6px;' });
      var bt = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm me-ai-btn', style: 'margin:0 0 10px;' }, '✨ Text amb IA');
      bt.addEventListener('click', function () { openAi('text', { block: owner, col: c }); });
      var bi = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm me-ai-btn', style: 'margin:0 0 10px;' }, '✨ Imatge amb IA');
      bi.addEventListener('click', function () { openAi('image', { block: owner, col: c }); });
      row.appendChild(bt); row.appendChild(bi); g.appendChild(row);
    }
    fImage(g, c, 'src');
    fText(g, 'Títol', c, 'title', { max: 200 });
    fRich(g, 'Text', c, 'html');
    fText(g, 'Text de l\'enllaç (opcional)', c, 'linkText', { max: 80, placeholder: 'Més informació ›' });
    fUrl(g, 'Adreça de l\'enllaç', c, 'href');
  }

  function renderProps() {
    propsEl.innerHTML = '';
    var b = selected ? block(selected) : null;
    if (!b) {
      propsEl.innerHTML = '<span class="me-kicker">Opcions</span><p class="me-small" style="font-size:.84rem">Cliqueu un bloc del correu per canviar-ne el text, els colors, les imatges o els enllaços.</p>'
        + '<p class="me-small"><b>Camps personals:</b> ' + Object.keys(CFG.fields).map(function (k) { return '<code>' + esc(k) + '</code>'; }).join(' ') + '. Cada persona el rep amb les seves dades.</p>'
        + '<p class="me-small"><b>Dreceres:</b> Ctrl+S desa · Ctrl+Z desfà · amb un bloc seleccionat, Alt+↑/↓ el mou i Supr l\'elimina.</p>';
      return;
    }
    var head = h('div', { style: 'display:flex;align-items:center;gap:8px;margin-bottom:12px;' });
    head.appendChild(h('span', { 'class': 'me-kicker', style: 'margin:0;flex:1;' }, 'Bloc seleccionat'));
    head.appendChild(h('h3', {}, esc(CFG.types[b.type])));
    propsEl.appendChild(head);
    aiButtons(propsEl, b);
    (PANELS[b.type] || function () {})(propsEl, b);
    fCommon(propsEl, b);
    var actions = h('div', { style: 'display:flex;gap:6px;margin-top:6px;' });
    var dup = h('button', { type: 'button', 'class': 'btn btn--ghost', style: 'flex:1;' }, 'Duplica'), del = h('button', { type: 'button', 'class': 'btn btn--danger', style: 'flex:1;' }, 'Elimina');
    dup.addEventListener('click', function () { duplicate(b.id); });
    del.addEventListener('click', function () { removeBlock(b.id); });
    actions.appendChild(dup); actions.appendChild(del); propsEl.appendChild(actions);
  }

  // ------------------------------------------------------------ estils generals
  function renderStyles() {
    var p = $('me-styles'); p.innerHTML = '';
    var s = S();
    p.appendChild(h('span', { 'class': 'me-kicker' }, 'Tot el correu'));
    fSelect(p, 'Tipus de lletra', s, 'font', Object.keys(CFG.fonts).map(function (k) { return [k, CFG.fonts[k].label]; }), 'all');
    fRange(p, 'Mida del text', s, 'textSize', 13, 20, 1, ' px', 'all');
    fColor(p, 'Color del text', s, 'textColor', false, 'all');
    fColor(p, 'Color dels subtítols', s, 'headingColor', 'domain', 'all');
    fColor(p, 'Color dels enllaços', s, 'linkColor', 'domain', 'all');
    fColor(p, 'Color dels botons', s, 'accent', 'domain', 'all');
    fColor(p, 'Fons del correu', s, 'contentBg', false, 'all');
    fColor(p, 'Fons de fora', s, 'bodyBg', false, 'all');
    fRange(p, 'Marges laterals', s, 'padX', 0, 40, 2, ' px', 'all');
    p.appendChild(h('p', { 'class': 'me-small', style: 'margin-top:6px' }, 'Amb el color «del domini» (el rodó de dues meitats), el mateix correu surt amb els colors de cada domini. Els tipus de lletra són els que tenen tots els ordinadors i mòbils.'));
  }
  document.querySelector('.me-tabs').addEventListener('click', function (e) {
    var t = e.target.closest('button[data-tab]'); if (!t) return;
    document.querySelectorAll('.me-tabs button').forEach(function (b) { b.setAttribute('aria-selected', b === t ? 'true' : 'false'); });
    document.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== t.dataset.tab; });
    if (t.dataset.tab === 'styles') renderStyles();
  });

  // ------------------------------------------------------------ previsualització
  var prevHtml = '', prevMode = 'both';
  function setFrames() {
    var html = prevHtml;
    if ($('me-prev-noimg').checked) html = html.replace(/<img\b([^>]*?)\ssrc="[^"]*"/gi, '<img$1 src="data:,"');
    $('me-if-desk').srcdoc = html; $('me-if-phone').srcdoc = html;
  }
  function paintMode() {
    document.querySelectorAll('#me-prev-mode button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.mode === prevMode ? 'true' : 'false'); });
    document.querySelector('.me-pane.desk').hidden = prevMode === 'phone';
    document.querySelector('.me-pane.ph').hidden = prevMode === 'desk';
  }
  function openPreview(promise, label) {
    $('me-prev-title').textContent = label || 'Previsualització';
    prevHtml = '<p style="font:14px system-ui;padding:20px;color:#6b7a70">Carregant…</p>'; setFrames(); paintMode();
    $('me-prev').showModal();
    promise.then(function (r) { if (!r.ok) throw new Error(); return r.text(); })
      .then(function (t) { prevHtml = t; setFrames(); })
      .catch(function () { prevHtml = '<p style="font:14px system-ui;padding:20px;color:#a5401d">No s\'ha pogut carregar la previsualització.</p>'; setFrames(); });
  }
  $('me-preview').addEventListener('click', function () {
    commit(true);
    openPreview(api(CFG.urls.preview, { method: 'POST', json: { doc: doc, subject: subject, preheader: preheader, domain: domain }, headers: { Accept: 'text/html' } }), 'Previsualització: ' + (subject || 'sense assumpte'));
  });
  $('me-prev-mode').addEventListener('click', function (e) { var b = e.target.closest('button[data-mode]'); if (b) { prevMode = b.dataset.mode; paintMode(); } });
  $('me-prev-noimg').addEventListener('change', setFrames);

  // ------------------------------------------------------------ més opcions
  $('me-history').addEventListener('click', function () {
    $('me-more').removeAttribute('open');
    var list = $('me-hist-list'); list.innerHTML = '<span class="me-busy">Carregant…</span>';
    $('me-hist').showModal();
    api(CFG.urls.versions).then(readJson).then(function (j) {
      list.innerHTML = '';
      j.versions.forEach(function (v) {
        var row = h('div', { 'class': 'me-hist-row' });
        row.appendChild(h('div', { style: 'flex:1;min-width:0;' }, '<b>Versió ' + v.version + '</b>' + (v.current ? ' <span class="badge badge--green">Actual</span>' : '')
          + '<div class="me-small" style="margin:0">' + esc(v.when) + ' · ' + esc(v.by) + ' · ' + esc(v.subject) + '</div>'));
        var pv = h('button', { type: 'button', 'class': 'btn btn--ghost btn--sm' }, 'Mireu-la');
        pv.addEventListener('click', function () { $('me-hist').close(); openPreview(api(v.preview, { headers: { Accept: 'text/html' } }), 'Versió ' + v.version + ' · ' + v.when); });
        row.appendChild(pv);
        if (!v.current) {
          var f = h('form', { method: 'POST', action: v.restore }, '<input type="hidden" name="_token" value="' + esc(CFG.csrf) + '"><button type="submit" class="btn btn--ghost btn--sm">Recupereu-la</button>');
          f.addEventListener('submit', function (e) {
            if (!confirm('Recuperar la versió ' + v.version + '? Es desarà com a versió nova.' + (dirty() ? '\n\nEls canvis que no heu desat es perdran.' : ''))) { e.preventDefault(); return; }
            savedState = snap();
          });
          row.appendChild(f);
        }
        list.appendChild(row);
      });
    }).catch(function () { list.innerHTML = '<span style="color:#a5401d">No s\'ha pogut carregar l\'historial.</span>'; });
  });
  function sendTest() {
    return ensureSaved().then(function () { return api(CFG.urls.test, { method: 'POST', json: { email: CFG.me.email } }); })
      .then(readJson).then(function (j) { toast(j.message); return j; });
  }
  if ($('me-test')) $('me-test').addEventListener('click', function () {
    $('me-more').removeAttribute('open');
    sendTest().catch(function (e) { if (e.message !== 'cancel') toast(e.message, true); });
  });
  $('me-astemplate').addEventListener('click', function () {
    $('me-more').removeAttribute('open');
    var name = prompt('Nom de la plantilla base nova:', CFG.isTemplate ? templateName + ' (còpia)' : subject);
    if (!name) return;
    ensureSaved().then(function () { return api(CFG.urls.templates, { method: 'POST', json: { name: name, from_id: CFG.id } }); })
      .then(readJson).then(function (j) { toast(j.message); })
      .catch(function (e) { if (e && e.message && e.message !== 'cancel') toast(e.message, true); });
  });

  // ------------------------------------------------------------ el bloc «Cursa»: triar un web del directori
  var pickFor = null, pickTimer = null;
  function openPicker(id) {
    if (!block(id)) return;
    pickFor = id;
    $('me-src-q').value = '';
    $('me-src-dlg').showModal();
    loadPicker('');
    $('me-src-q').focus();
  }
  function loadPicker(q) {
    var list = $('me-src-list');
    list.innerHTML = '<div class="me-busy">Carregant…</div>';
    api(CFG.urls.races + (q ? '?q=' + encodeURIComponent(q) : '')).then(readJson).then(function (j) {
      list.innerHTML = '';
      if (!j.items.length) { list.innerHTML = '<p class="me-small" style="margin:10px 12px">' + (q ? 'No hi ha cap resultat.' : 'Encara no hi ha cap web publicat al directori.') + '</p>'; return; }
      j.items.forEach(function (it) {
        var btn = h('button', { type: 'button', 'class': 'me-src-item', role: 'option' }, '<b>' + esc(it.title) + '</b><span>' + esc(it.sub || '') + '</span>');
        btn.addEventListener('click', function () { applyRace(pickFor, it.id); });
        list.appendChild(btn);
      });
    }).catch(function (e) { list.innerHTML = '<p style="color:#a5401d;margin:10px 12px">' + esc(e.message) + '</p>'; });
  }
  $('me-src-q').addEventListener('input', function () { var v = this.value.trim(); clearTimeout(pickTimer); pickTimer = setTimeout(function () { loadPicker(v); }, 300); });
  function applyRace(id, slug) {
    var b = block(id); if (!b) return;
    $('me-src-list').innerHTML = '<div class="me-busy">Copiant les dades del web…</div>';
    api(CFG.urls.race.replace('__SLUG__', encodeURIComponent(slug))).then(readJson).then(function (j) {
      commit(true);
      Object.keys(j.data).forEach(function (k) { b[k] = j.data[k]; });
      selected = b.id; renderAll(); renderProps(); commit(true);
      $('me-src-dlg').close();
      toast('Afegit. Podeu retocar-ne el text a la dreta.');
    }).catch(function (e) { toast(e.message, true); loadPicker($('me-src-q').value.trim()); });
  }

  // ------------------------------------------------------------ envia o programa
  var sendDlg = $('me-send-dlg'), countTimer = null, countSeq = 0;
  function delivery() {
    var f = $('me-send-form'), v = function (n) { var el = f.querySelector('[name="' + n + '"]:checked') || f.querySelector('[name="' + n + '"]:not([type="radio"])'); return el ? el.value : ''; };
    return {
      audience: v('audience'), instance_status: v('instance_status'), list_id: v('list_id'), manual_emails: v('manual_emails'),
      domain: v('domain'), all_domains: $('me-only-domain').checked ? 0 : 1, when: v('when'), date: v('date'), time: v('time')
    };
  }
  function paintSend() {
    var d = delivery(), personal = d.audience === 'list' || d.audience === 'manual';
    $('me-only-wrap').hidden = personal; $('me-only-note').hidden = !personal;
    var label = 'Envia ara';
    if (d.when === 'later') {
      var at = d.date && d.time ? new Date(d.date + 'T' + d.time) : null;
      label = at && !isNaN(at) ? 'Programa per al ' + at.toLocaleDateString('ca', { weekday: 'long', day: 'numeric', month: 'numeric' }) + ' a les ' + d.time : 'Programa';
    }
    $('me-send-go').textContent = $('me-test-first').checked ? 'Envia\'m la prova' : label;
  }
  function recount() {
    clearTimeout(countTimer);
    countTimer = setTimeout(function () {
      var seq = ++countSeq, sum = $('me-sum');
      sum.innerHTML = '<span class="me-busy">Comptant…</span>';
      api(CFG.urls.audience, { method: 'POST', json: delivery() }).then(readJson).then(function (j) {
        if (seq !== countSeq) return;
        var lim = (CFG.brands[delivery().domain] || {}).limit || 0;
        sum.innerHTML = '<b class="n">' + j.count + '</b><span>' + (j.count === 1 ? 'persona el rebrà' : 'persones el rebran')
          + (j.unsubscribed ? ' · ' + j.unsubscribed + ' que s\'han donat de baixa no hi compten' : '')
          + '. Surt per tandes de ' + CFG.batch + (lim ? ', com a molt ' + lim + ' per hora' : '') + '.'
          + (j.sample.length ? '<br><small style="color:#5a6b60">' + j.sample.map(esc).join(', ') + (j.count > j.sample.length ? '…' : '') + '</small>' : '') + '</span>';
        $('me-send-go').disabled = j.count === 0 && !$('me-test-first').checked;
      }).catch(function (e) { if (seq === countSeq) sum.innerHTML = '<span style="color:#a5401d">' + esc(e.message) + '</span>'; });
    }, 250);
  }
  if (sendDlg) {
    $('me-send').addEventListener('click', function () {
      if (!subject.trim()) { toast('Falta l\'assumpte del correu.', true); $('me-title').focus(); return; }
      var f = $('me-send-form'), d = CFG.delivery;
      var setRadio = function (n, val) { var el = f.querySelector('[name="' + n + '"][value="' + val + '"]') || f.querySelector('[name="' + n + '"]'); if (el) el.checked = true; };
      setRadio('audience', d.audience); setRadio('domain', domain); setRadio('when', CFG.status === 'scheduled' ? 'later' : 'now');
      f.querySelector('[name="instance_status"]').value = d.instance_status;
      if (f.querySelector('[name="list_id"]') && d.list_id) f.querySelector('[name="list_id"]').value = d.list_id;
      f.querySelector('[name="manual_emails"]').value = d.manual_emails;
      f.querySelector('[name="date"]').value = d.date; f.querySelector('[name="time"]').value = d.time;
      $('me-only-domain').checked = !d.all_domains;
      $('me-test-first').checked = false;
      paintSend(); recount();
      sendDlg.showModal();
    });
    $('me-send-form').addEventListener('change', function (e) {
      if (e.target.name === 'domain' && e.target.value !== domain) { domain = e.target.value; paintMeta(); renderAll(); renderProps(); commit(true); }
      if (e.target.closest('.me-opt') && e.target.type !== 'radio') { var r = e.target.closest('.me-opt').querySelector('input[type="radio"]'); if (r) r.checked = true; }
      paintSend(); if (e.target.id !== 'me-test-first') recount(); else $('me-send-go').disabled = false;
    });
    $('me-send-form').addEventListener('input', function (e) { if (e.target.name === 'manual_emails') { var r = e.target.closest('.me-opt').querySelector('input[type="radio"]'); r.checked = true; paintSend(); recount(); } });
    $('me-send-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var go = $('me-send-go');
      if ($('me-test-first').checked) {
        go.disabled = true;
        sendTest().then(function () { $('me-test-first').checked = false; paintSend(); recount(); })
          .catch(function (err) { toast(err.message, true); }).finally(function () { go.disabled = false; });
        return;
      }
      var d = delivery();
      if (d.when === 'now' && !confirm('S\'enviarà ara mateix ' + (/^[aeiouh]/i.test(d.domain) ? 'des d\'' : 'des de ') + d.domain + '. Un cop comença, ja no es pot canviar el correu. Endavant?')) return;
      go.disabled = true; go.textContent = d.when === 'now' ? 'Enviant…' : 'Programant…';
      ensureSaved().then(function () { return api(CFG.urls.send, { method: 'POST', json: d }); }).then(readJson)
        .then(function (j) { savedState = snap(); window.location.href = j.url; })
        .catch(function (err) { if (err.message !== 'cancel') toast(err.message, true); go.disabled = false; paintSend(); });
    });
  }

  // ------------------------------------------------------------ IA (textos i imatges)
  var AI_TEXT = { text: 'html', box: 'html', heading: 'plain', button: 'plain', link: 'plain' };
  var AI_IMAGE = { image: 1, imagetext: 1, news: 1 };
  var aiMode = 'text', aiTarget = null, aiResult = null, aiRefs = [];
  function aiButtons(p, b) {
    if (!CFG.ai.show || b.type === 'columns') return;
    if (AI_TEXT[b.type]) {
      var bt = h('button', { type: 'button', 'class': 'btn btn--ghost me-ai-btn' }, b.type === 'heading' || b.type === 'button' || b.type === 'link' ? '✨ Escriu-lo amb IA' : '✨ Escriu el text amb IA');
      bt.addEventListener('click', function () { openAi('text', { block: b.id }); });
      p.appendChild(bt);
    }
    if (AI_IMAGE[b.type]) {
      var bi = h('button', { type: 'button', 'class': 'btn btn--ghost me-ai-btn' }, '✨ Crea la imatge amb IA');
      bi.addEventListener('click', function () { openAi('image', { block: b.id }); });
      p.appendChild(bi);
    }
    if (b.type === 'imagetext') {
      var bt2 = h('button', { type: 'button', 'class': 'btn btn--ghost me-ai-btn' }, '✨ Escriu el text amb IA');
      bt2.addEventListener('click', function () { openAi('text', { block: b.id, col: b }); });
      p.appendChild(bt2);
    }
  }
  function plainOf(html) { var d = document.createElement('div'); d.innerHTML = (html || '').replace(/<\/(p|li|h3|h4)>/g, '$&\n'); return d.textContent.replace(/\n{3,}/g, '\n\n').trim(); }
  function mailText() {
    var out = [];
    doc.blocks.forEach(function (b) {
      if (b.type === 'heading' && b.text) out.push(b.text);
      else if ((b.type === 'text' || b.type === 'box') && b.html) out.push(plainOf(b.html));
      else if (b.type === 'columns') b.cols.forEach(function (c) { out.push([c.title, plainOf(c.html)].filter(Boolean).join('\n')); });
      else if (b.type === 'imagetext') out.push([b.title, plainOf(b.html)].filter(Boolean).join('\n'));
      else if ((b.type === 'button' || b.type === 'link') && b.text) out.push(b.text);
      else if ((b.type === 'race' || b.type === 'news') && b.title) out.push([b.kicker, b.title, b.when, b.town, b.desc].filter(Boolean).join('\n'));
    });
    return out.filter(Boolean).join('\n\n').slice(0, 8000);
  }
  function aiInfo(t) {
    var b = t && block(t.block);
    if (!b) return null;
    if (t.col) return { b: b, obj: t.col, text: 'html', image: true, label: t.col === b ? 'el text del bloc «Imatge i text»' : 'una columna' };
    return { b: b, obj: b, text: AI_TEXT[b.type] || null, image: !!AI_IMAGE[b.type], label: 'el bloc «' + CFG.types[b.type] + '»' };
  }
  function setSeg(id, attr, v) { var g = $(id); if (g) g.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x.dataset[attr] === v ? 'true' : 'false'); }); }
  function segVal(id, attr, def) { var x = $(id) && $(id).querySelector('button[aria-pressed="true"]'); return x ? x.dataset[attr] : def; }
  function openAi(mode, target) {
    var dlg = $('me-ai-dlg'); if (!dlg) return;
    aiTarget = target || (selected ? { block: selected } : null);
    var info = aiInfo(aiTarget);
    if (mode === 'text' && info && !info.text && info.image) mode = 'image';
    if (mode === 'image' && info && !info.image && info.text) mode = 'text';
    aiMode = mode; paintAi();
    var fmt = 'paragraphs';
    if (info && info.text && info.obj === info.b) fmt = info.b.type === 'heading' ? 'heading' : (info.b.type === 'button' || info.b.type === 'link') ? 'button' : 'paragraphs';
    if ($('me-ai-format')) $('me-ai-format').value = fmt;
    var cur = info && info.text ? (info.text === 'html' ? plainOf(info.obj.html) : info.obj.text) : '';
    if ($('me-ai-rewrite-wrap')) { $('me-ai-rewrite-wrap').hidden = !cur; $('me-ai-rewrite').checked = false; }
    $('me-ai-target').textContent = info ? 'Per a ' + info.label + ' seleccionat. Podeu substituir-lo o afegir el resultat com a bloc nou.' : 'El resultat s\'afegirà com a bloc nou sota el bloc seleccionat.';
    aiResult = null; $('me-ai-out').hidden = true; $('me-ai-out').innerHTML = ''; paintAiActions();
    if ($('me-ai-refs')) {
      clearRefs();
      var curImg = info && info.image && /^img:/.test(info.obj.src || '') ? info.obj.src : '';
      $('me-ai-usecur-wrap').hidden = !curImg; $('me-ai-usecur').checked = false; $('me-ai-usecur').dataset.src = curImg;
      paintRefs();
    }
    dlg.showModal();
    var ta = $(aiMode === 'text' ? 'me-ai-prompt' : 'me-ai-iprompt'); if (ta) ta.focus();
  }
  function paintAi() {
    setSeg('me-ai-mode', 'mode', aiMode);
    document.querySelectorAll('[data-ai-pane]').forEach(function (p) { p.hidden = p.dataset.aiPane !== aiMode || (aiMode === 'text' && !CFG.ai.text); });
    $('me-ai-go').hidden = aiMode === 'text' ? !CFG.ai.text : !CFG.ai.image;
  }
  function paintAiActions() {
    var info = aiInfo(aiTarget), r = aiResult;
    $('me-ai-new').hidden = !r || (r.kind === 'text' && r.format === 'subject');
    $('me-ai-replace').hidden = !r || !info || (r.kind === 'image' ? !info.image : (!info.text || r.format === 'subject'));
    if (info) $('me-ai-replace').textContent = 'Substitueix ' + (r && r.kind === 'image' ? 'la imatge' : 'el text') + ' del bloc';
    $('me-ai-subject').hidden = !r || r.kind !== 'text' || r.format !== 'subject';
    $('me-ai-go').textContent = r ? 'Torna a generar' : 'Genera';
    $('me-ai-go').className = r ? 'btn btn--ghost' : 'btn';
  }
  if ($('me-ai')) $('me-ai').addEventListener('click', function () { openAi('text'); });
  if ($('me-ai-dlg')) {
    $('me-ai-mode').addEventListener('click', function (e) { var b = e.target.closest('button[data-mode]'); if (!b) return; aiMode = b.dataset.mode; aiResult = null; $('me-ai-out').hidden = true; paintAi(); paintAiActions(); });
    ['me-ai-provider', 'me-ai-shape', 'me-ai-style'].forEach(function (id) {
      if ($(id)) $(id).addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; $(id).querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); });
    });
    $('me-ai-go').addEventListener('click', generateAi);
    [$('me-ai-prompt'), $('me-ai-iprompt')].forEach(function (ta) { if (ta) ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); generateAi(); } }); });
    $('me-ai-new').addEventListener('click', function () { applyAi(false); });
    $('me-ai-replace').addEventListener('click', function () { applyAi(true); });
    $('me-ai-subject').addEventListener('click', function () {
      subject = aiResult.text;
      if (aiResult.preheader) preheader = aiResult.preheader;
      paintMeta(); commit(true); $('me-ai-dlg').close(); toast('Assumpte i resum posats. Recordeu desar.');
    });
  }
  // Imatges de referència (es redueixen al navegador abans de pujar-les).
  function clearRefs() { aiRefs.forEach(function (r) { if (r.url) URL.revokeObjectURL(r.url); }); aiRefs = []; }
  function paintRefs() {
    var box = $('me-ai-refs'); if (!box) return;
    box.querySelectorAll('.me-ref').forEach(function (x) { x.remove(); });
    aiRefs.forEach(function (r, i) {
      var el = h('div', { 'class': 'me-ref' }, '<img src="' + r.url + '" alt="' + esc(r.name) + '"><button type="button" aria-label="Treu ' + esc(r.name) + '" title="Treu-la">✕</button>');
      el.querySelector('button').addEventListener('click', function () { URL.revokeObjectURL(r.url); aiRefs.splice(i, 1); paintRefs(); });
      box.insertBefore(el, $('me-ai-refs-add'));
    });
    $('me-ai-refs-add').hidden = aiRefs.length >= CFG.ai.maxRefs;
    $('me-ai-keep-wrap').hidden = !aiRefs.length && !$('me-ai-usecur').checked;
  }
  function shrinkRef(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () {
        var w = img.naturalWidth, hh = img.naturalHeight, max = Math.max(w, hh);
        if (max <= 2048 && file.size <= 1.5 * 1024 * 1024 && /^image\/(png|jpeg|webp)$/.test(file.type)) { URL.revokeObjectURL(url); resolve(file); return; }
        var k = Math.min(1, 2048 / max), c = document.createElement('canvas');
        c.width = Math.round(w * k); c.height = Math.round(hh * k);
        var ctx = c.getContext('2d'); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, c.width, c.height); ctx.drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob(function (b) { if (b) resolve(b); else reject(new Error()); }, 'image/jpeg', 0.9);
      };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error()); };
      img.src = url;
    });
  }
  function addRefs(files) {
    Array.prototype.forEach.call(files, function (file) {
      if (!/^image\//.test(file.type)) { toast('«' + (file.name || 'fitxer') + '» no és una imatge.', true); return; }
      if (aiRefs.length >= CFG.ai.maxRefs) { toast(CFG.ai.maxRefs + ' imatges de referència com a molt.', true); return; }
      var slot = { blob: null, url: '', name: '' }; aiRefs.push(slot);
      shrinkRef(file).then(function (blob) {
        slot.blob = blob; slot.url = URL.createObjectURL(blob);
        var baseName = (file.name || 'captura').replace(/\.[a-z0-9]+$/i, '') || 'captura';
        slot.name = baseName + (blob.type === 'image/jpeg' ? '.jpg' : blob.type === 'image/webp' ? '.webp' : '.png');
        paintRefs();
      }).catch(function () { aiRefs.splice(aiRefs.indexOf(slot), 1); toast('No s\'ha pogut llegir «' + (file.name || 'la imatge') + '».', true); paintRefs(); });
    });
  }
  if ($('me-ai-refs')) {
    $('me-ai-refs-add').addEventListener('click', function () { $('me-ai-refs-file').click(); });
    $('me-ai-refs-file').addEventListener('change', function () { addRefs(this.files); this.value = ''; });
    $('me-ai-usecur').addEventListener('change', paintRefs);
    var zone = $('me-ai-refs');
    zone.addEventListener('dragover', function (e) { if (isFileDrag(e)) { e.preventDefault(); zone.classList.add('over'); } });
    zone.addEventListener('dragleave', function () { zone.classList.remove('over'); });
    zone.addEventListener('drop', function (e) { e.preventDefault(); zone.classList.remove('over'); if (e.dataTransfer.files.length) addRefs(e.dataTransfer.files); });
    $('me-ai-dlg').addEventListener('paste', function (e) {
      var files = Array.prototype.filter.call((e.clipboardData && e.clipboardData.files) || [], function (f) { return /^image\//.test(f.type); });
      if (!files.length || !CFG.ai.image) return;
      e.preventDefault();
      if (aiMode !== 'image') { aiMode = 'image'; aiResult = null; $('me-ai-out').hidden = true; paintAi(); paintAiActions(); }
      addRefs(files);
    });
  }
  function generateAi() {
    var out = $('me-ai-out'), go = $('me-ai-go'), info = aiInfo(aiTarget), req;
    if (aiMode === 'text') {
      var prompt = $('me-ai-prompt').value.trim();
      if (!prompt) { $('me-ai-prompt').focus(); toast('Escriviu què voleu que escrigui la IA.', true); return; }
      var current = $('me-ai-rewrite') && $('me-ai-rewrite').checked && info ? (info.text === 'html' ? plainOf(info.obj.html) : info.obj.text) : '';
      req = { url: CFG.urls.aiText, body: { provider: segVal('me-ai-provider', 'provider', CFG.ai.provider), prompt: prompt, format: $('me-ai-format').value, tone: $('me-ai-tone').value, length: $('me-ai-length').value, title: subject, context: $('me-ai-context').checked ? mailText() : '', current: current, domain: domain } };
    } else {
      var ip = $('me-ai-iprompt').value.trim();
      if (!ip) { $('me-ai-iprompt').focus(); toast('Descriviu la imatge que voleu.', true); return; }
      if (aiRefs.some(function (r) { return !r.blob; })) { toast('Un moment: encara s\'estan preparant les imatges de referència.'); return; }
      var nRefs = aiRefs.length + ($('me-ai-usecur').checked ? 1 : 0);
      if (nRefs > CFG.ai.maxRefs) { toast(CFG.ai.maxRefs + ' imatges de referència com a molt.', true); return; }
      var fd = new FormData();
      fd.append('prompt', ip); fd.append('shape', segVal('me-ai-shape', 'v', 'landscape')); fd.append('style', segVal('me-ai-style', 'v', 'photo'));
      fd.append('no_text', $('me-ai-notext').checked ? '1' : '0'); fd.append('domain', domain);
      fd.append('keep_details', nRefs && $('me-ai-keep').checked ? '1' : '0');
      aiRefs.forEach(function (r) { fd.append('refs[]', r.blob, r.name); });
      if ($('me-ai-usecur').checked && $('me-ai-usecur').dataset.src) fd.append('ref_srcs[]', $('me-ai-usecur').dataset.src);
      req = { url: CFG.urls.aiImage, form: fd, body: { prompt: ip }, refs: nRefs };
    }
    go.disabled = true;
    out.hidden = false; out.innerHTML = '<div class="me-busy">' + (aiMode === 'text' ? 'Escrivint…' : 'Creant la imatge' + (req.refs ? ' a partir de ' + (req.refs === 1 ? 'la referència' : 'les ' + req.refs + ' referències') : '') + '… pot trigar fins a un minut.') + '</div>';
    var mode = aiMode;
    api(req.url, req.form ? { method: 'POST', body: req.form } : { method: 'POST', json: req.body }).then(readJson).then(function (j) {
      if (mode === 'text') {
        aiResult = { kind: 'text', format: req.body.format, html: j.html, text: j.text, preheader: j.preheader || '' };
        out.innerHTML = j.html ? '<div class="rt">' + j.html + '</div>' : '<div class="rt"><b>' + esc(j.text) + '</b>' + (j.preheader ? '<br><span style="color:#6b7a70">' + esc(j.preheader) + '</span>' : '') + '</div>';
      } else {
        CFG.images[j.src] = j.url;
        aiResult = { kind: 'image', src: j.src, alt: req.body.prompt.slice(0, 150) };
        out.innerHTML = '<img src="' + esc(j.url) + '" alt="' + esc(aiResult.alt) + '">';
      }
      paintAiActions();
    }).catch(function (e) {
      out.innerHTML = '<div style="color:#a5401d;font-size:.88rem">' + esc(e.message || 'No s\'ha pogut generar.') + '</div>';
    }).finally(function () { go.disabled = false; });
  }
  function applyAi(replace) {
    var r = aiResult, info = aiInfo(aiTarget); if (!r) return;
    commit(true);
    if (replace && info) {
      if (r.kind === 'image') { info.obj.src = r.src; info.obj.alt = info.obj.alt || r.alt; }
      else if (info.text === 'html') info.obj.html = r.html || '<p>' + esc(r.text) + '</p>';
      else info.obj.text = r.text || plainOf(r.html).split('\n')[0];
      selected = info.b.id;
    } else {
      var nb;
      if (r.kind === 'image') { nb = newBlock('image'); nb.src = r.src; nb.alt = r.alt; }
      else if (r.format === 'heading') { nb = newBlock('heading'); nb.text = r.text; }
      else if (r.format === 'button') { nb = newBlock('button'); nb.text = r.text; }
      else { nb = newBlock('text'); nb.html = r.html || '<p>' + esc(r.text) + '</p>'; }
      var at = aiTarget && find(aiTarget.block) >= 0 ? find(aiTarget.block) + 1 : defaultIndex();
      doc.blocks.splice(at, 0, nb); selected = nb.id;
    }
    renderAll(); renderProps(); commit(true);
    $('me-ai-dlg').close();
    var el = mailEl.querySelector('.me-b[data-id="' + selected + '"]'); if (el) el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    toast(replace ? 'Fet. Reviseu-ho i deseu quan vulgueu (es pot desfer amb Ctrl+Z).' : 'Afegit al correu. Reviseu-ho i deseu quan vulgueu.');
  }

  // ------------------------------------------------------------ inici
  paintMeta(); renderAll(); renderProps(); updateButtons();
})();
