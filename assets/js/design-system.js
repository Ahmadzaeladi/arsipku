/**
 * ArsipKu Design System — perilaku halaman pratinjau.
 *
 * Vanilla ES6+, tanpa dependensi. Meniru perilaku yang dibutuhkan komponen
 * (bukan kerangka kerja): ikon disuntik, menu/modal/toast/tab/segmented,
 * focus trap, dan scroll-spy. Produksi memakai pola yang sama di
 * assets/js/app.js — berkas ini sengaja berdiri sendiri agar pratinjau
 * bisa dibuka langsung tanpa memuat aplikasi.
 */
(function () {
  'use strict';

  /* ---------------------------------------------------------------- IKON
     Bentuk ikon tunggal ada di assets/js/icons.js (window.ArkIcons) supaya
     pratinjau dan kerangka aplikasi tidak punya dua salinan PATHS. */
  function iconMarkup(name) { return window.ArkIcons.markup(name); }
  function hydrateIcons(root) { window.ArkIcons.hydrate(root); }

  /* --------------------------------------------------------------- TOAST */
  var ICON_FOR = { success: 'check-circle', danger: 'x-circle', error: 'x-circle', warning: 'alert', info: 'info' };
  var TTL_MS = 6000;
  var region = document.getElementById('toast-container');

  function pushToast(opts) {
    var type = opts.type || 'info';
    var toast = document.createElement('div');
    toast.className = 'ark-toast ark-toast--' + type;

    var icon = document.createElement('span');
    icon.className = 'ark-toast__icon';
    icon.innerHTML = iconMarkup(ICON_FOR[type] || 'info');

    var body = document.createElement('div');
    body.className = 'ark-toast__content';
    var title = document.createElement('span');
    title.className = 'ark-toast__title';
    title.textContent = opts.title || '';
    body.appendChild(title);
    if (opts.desc) {
      var d = document.createElement('span');
      d.className = 'ark-toast__desc';
      d.textContent = opts.desc;
      body.appendChild(d);
    }
    if (opts.action) {
      var a = document.createElement('button');
      a.type = 'button';
      a.className = 'ark-toast__action';
      a.textContent = opts.action;
      a.addEventListener('click', dismiss);
      body.appendChild(a);
    }

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'ark-toast__close';
    close.setAttribute('aria-label', 'Tutup notifikasi');
    close.innerHTML = iconMarkup('x');
    close.addEventListener('click', dismiss);

    var bar = document.createElement('span');
    bar.className = 'ark-toast__timer';
    bar.setAttribute('aria-hidden', 'true');

    toast.appendChild(icon);
    toast.appendChild(body);
    toast.appendChild(close);
    toast.appendChild(bar);
    toast.style.setProperty('--ark-toast-ttl', TTL_MS + 'ms');
    region.appendChild(toast);

    // Hover menjeda: CSS membekukan batang (animation-play-state), JS menyimpan
    // sisa waktu yang sama lalu menghitung ulang saat kursor keluar.
    var startedAt = Date.now();
    var remaining = TTL_MS;
    var autoClose = window.setTimeout(dismiss, remaining);

    toast.addEventListener('mouseenter', function () {
      window.clearTimeout(autoClose);
      remaining -= Date.now() - startedAt;
    });
    toast.addEventListener('mouseleave', function () {
      startedAt = Date.now();
      autoClose = window.setTimeout(dismiss, Math.max(remaining, 600));
    });

    function dismiss() {
      window.clearTimeout(autoClose);
      if (!toast.isConnected) return;
      toast.classList.add('is-leaving');
      window.setTimeout(function () { toast.remove(); }, 240);
    }

    while (region.children.length > 4) region.firstElementChild.remove();
  }

  /* --------------------------------------------------------------- MENU */
  function closeAllMenus(except) {
    document.querySelectorAll('.ark-menu:not([hidden])').forEach(function (panel) {
      if (panel === except) return;
      panel.hidden = true;
      panel.classList.remove('is-animating');
      var trigger = document.querySelector('[data-menu="#' + panel.id + '"]');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
  }

  function openMenu(trigger) {
    var panel = document.querySelector(trigger.getAttribute('data-menu'));
    if (!panel) return;
    closeAllMenus(panel);
    panel.hidden = false;
    panel.classList.add('is-animating');
    trigger.setAttribute('aria-expanded', 'true');
    var first = panel.querySelector('.ark-menu__item:not([disabled])');
    if (first) first.focus();
    window.setTimeout(function () { panel.classList.remove('is-animating'); }, 200);
  }

  function menuKeys(event, panel) {
    var items = Array.prototype.slice.call(panel.querySelectorAll('.ark-menu__item:not([disabled])'));
    var index = items.indexOf(document.activeElement);
    if (event.key === 'ArrowDown') { event.preventDefault(); items[(index + 1 + items.length) % items.length].focus(); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); items[(index - 1 + items.length) % items.length].focus(); }
    else if (event.key === 'Home') { event.preventDefault(); items[0].focus(); }
    else if (event.key === 'End') { event.preventDefault(); items[items.length - 1].focus(); }
    else if (event.key === 'Escape') {
      var trigger = document.querySelector('[data-menu="#' + panel.id + '"]');
      closeAllMenus();
      if (trigger) trigger.focus();
    }
  }

  /* -------------------------------------------------------------- MODAL */
  var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
  var lastTrigger = null;

  function openModal(sel) {
    var scrim = document.querySelector(sel);
    if (!scrim) return;
    closeAllMenus();
    lastTrigger = document.activeElement;
    scrim.hidden = false;
    requestAnimationFrame(function () { scrim.classList.add('is-open'); });
    var modal = scrim.querySelector('.ark-modal');
    var target = scrim.querySelector('[data-autofocus]') || modal.querySelector(FOCUSABLE);
    if (target) target.focus();
    document.body.style.overflow = 'hidden';
  }

  function closeModal(scrim) {
    scrim.classList.remove('is-open');
    document.body.style.overflow = '';
    window.setTimeout(function () { scrim.hidden = true; }, 200);
    if (lastTrigger && lastTrigger.isConnected) lastTrigger.focus();
  }

  function trapFocus(event, scrim) {
    var modal = scrim.querySelector('.ark-modal');
    var items = Array.prototype.slice.call(modal.querySelectorAll(FOCUSABLE)).filter(function (el) {
      return el.offsetParent !== null;
    });
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  /* --------------------------------------------------------- SCROLL-SPY */
  function setupSpy() {
    var links = Array.prototype.slice.call(document.querySelectorAll('.ds-nav__link'));
    if (!links.length || !('IntersectionObserver' in window)) return;
    var byId = {};
    links.forEach(function (link) { byId[link.getAttribute('href').slice(1)] = link; });

    var visible = new Set();
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) visible.add(entry.target);
        else visible.delete(entry.target);
      });
      var kandidat = Array.prototype.slice.call(document.querySelectorAll('.ds-section'))
        .filter(function (section) { return visible.has(section); });
      links.forEach(function (link) { link.classList.remove('is-active'); });
      if (!kandidat.length) return;
      // Seksi yang paling dekat dengan pita baca di bagian atas viewport,
      // bukan seksi pertama yang masih tersisa di dalam pita.
      var pick = kandidat.reduce(function (best, section) {
        var d = Math.abs(section.getBoundingClientRect().top - 100);
        return d < best.d ? { section: section, d: d } : best;
      }, { section: kandidat[0], d: Infinity });
      var active = byId[pick.section.id];
      if (active) active.classList.add('is-active');
    }, { rootMargin: '-96px 0px -60% 0px', threshold: 0 });

    document.querySelectorAll('.ds-section').forEach(function (section) { observer.observe(section); });
  }

  /* --------------------------------------------------------------- INIT */
  function init() {
    hydrateIcons(document);
    setupSpy();

    document.addEventListener('click', function (event) {
      var copy = event.target.closest('[data-copy]');
      if (copy) {
        var name = copy.getAttribute('data-copy');
        if (navigator.clipboard) navigator.clipboard.writeText('var(' + name + ')').catch(function () {});
        pushToast({ type: 'info', title: name + ' disalin' });
        return;
      }

      var toastBtn = event.target.closest('[data-toast]');
      if (toastBtn) {
        pushToast({
          type: toastBtn.getAttribute('data-toast'),
          title: toastBtn.getAttribute('data-toast-title'),
          desc: toastBtn.getAttribute('data-toast-desc'),
          action: toastBtn.getAttribute('data-toast-action')
        });
      }

      var trigger = event.target.closest('[data-menu]');
      if (trigger) {
        var panel = document.querySelector(trigger.getAttribute('data-menu'));
        if (panel && !panel.hidden) { closeAllMenus(); trigger.focus(); }
        else { openMenu(trigger); }
        return;
      }

      var item = event.target.closest('.ark-menu__item');
      if (item && !item.disabled) {
        var owner = item.closest('.ark-menu');
        if (owner.getAttribute('role') === 'menu') closeAllMenus();
        return;
      }

      var open = event.target.closest('[data-open-modal]');
      if (open) { openModal(open.getAttribute('data-open-modal')); return; }

      var closeBtn = event.target.closest('[data-close-modal]');
      if (closeBtn) { closeModal(closeBtn.closest('.ark-scrim')); return; }

      var scrim = event.target.closest('.ark-scrim');
      if (scrim && event.target === scrim) { closeModal(scrim); return; }

      if (!event.target.closest('.ark-menu-wrap')) closeAllMenus();
    });

    document.addEventListener('keydown', function (event) {
      var panel = event.target.closest && event.target.closest('.ark-menu');
      if (panel) menuKeys(event, panel);

      if (event.key === 'Escape') {
        var openScrim = document.querySelector('.ark-scrim:not([hidden])');
        if (openScrim) { closeModal(openScrim); return; }
        var anyMenu = document.querySelector('.ark-menu:not([hidden])');
        if (anyMenu) {
          var t = document.querySelector('[data-menu="#' + anyMenu.id + '"]');
          closeAllMenus();
          if (t) t.focus();
        }
      }

      if (event.key === 'Tab') {
        var activeScrim = document.querySelector('.ark-scrim.is-open');
        if (activeScrim) trapFocus(event, activeScrim);
      }

      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        var field = document.querySelector('.ds-section .ark-search__field') || document.querySelector('.ark-search__field');
        if (field) { event.preventDefault(); field.focus(); field.select(); }
      }
    });

    /* Tab + segmented */
    document.querySelectorAll('[data-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var siblings = tab.parentElement.querySelectorAll('[data-tab]');
        siblings.forEach(function (s) { s.setAttribute('aria-selected', String(s === tab)); });
      });
    });

    document.querySelectorAll('.ark-segmented').forEach(function (group) {
      group.addEventListener('click', function (event) {
        var item = event.target.closest('.ark-segmented__item');
        if (!item) return;
        group.querySelectorAll('.ark-segmented__item').forEach(function (s) {
          s.setAttribute('aria-pressed', String(s === item));
        });
      });
    });

    /* Urutan kolom */
    document.querySelectorAll('[data-sort]').forEach(function (button) {
      button.addEventListener('click', function () {
        var order = { none: 'ascending', ascending: 'descending', descending: 'none' };
        button.setAttribute('aria-sort', order[button.getAttribute('aria-sort') || 'none']);
      });
    });

    /* Pilih semua (demo baris) */
    var selectAll = document.getElementById('select-all-demo');
    if (selectAll) {
      var box = selectAll.nextElementSibling;
      var rows = document.querySelectorAll('.ark-table__body .ark-check__input');
      selectAll.addEventListener('change', function () {
        var on = selectAll.checked;
        rows.forEach(function (input) {
          input.checked = on;
          input.closest('tr').classList.toggle('is-selected', on);
        });
        box.removeAttribute('aria-checked');
      });
      rows.forEach(function (input) {
        input.addEventListener('change', function () {
          input.closest('tr').classList.toggle('is-selected', input.checked);
          var count = document.querySelectorAll('.ark-table__body .ark-check__input:checked').length;
          box.setAttribute('aria-checked', count === 0 ? 'false' : count === rows.length ? 'true' : 'mixed');
        });
      });
    }

    /* Dropzone: status seret + tombol isi */
    document.querySelectorAll('.ark-dropzone').forEach(function (zone) {
      ['dragover', 'dragenter'].forEach(function (name) {
        zone.addEventListener(name, function (event) {
          event.preventDefault();
          zone.classList.add('is-dragover');
        });
      });
      ['dragleave', 'drop'].forEach(function (name) {
        zone.addEventListener(name, function (event) {
          event.preventDefault();
          zone.classList.remove('is-dragover');
        });
      });
      zone.addEventListener('click', function () {
        pushToast({ type: 'info', title: 'Pemilih berkas dibuka', desc: 'Pratinjau hanya menampilkan statusnya.' });
      });
    });

    /* Bersihkan field cari */
    document.querySelectorAll('.ark-search__clear').forEach(function (button) {
      button.addEventListener('click', function () {
        var field = button.parentElement.querySelector('.ark-search__field');
        field.value = '';
        field.focus();
      });
    });

    /* Contoh toast awal: sekali saja, tanpa mengganggu. */
    pushToast({ type: 'info', title: 'Design system v1.0', desc: '14 komponen, 0 framework. Coba tab "Tabel".' });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
