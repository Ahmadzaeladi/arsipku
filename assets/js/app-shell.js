/**
 * ArsipKu — kerangka aplikasi (shell).
 *
 * Vanilla ES6+, tanpa dependensi. Mengurus lima interaksi area aplikasi:
 *   1. status navigasi aktif (dari ?page= pada URL)
 *   2. dropdown pemilih workspace
 *   3. dropdown akun
 *   4. drawer sidebar di layar sempit
 *   5. rail (sidebar menyusut) di desktop + baris cari di header
 *
 * Hanya sentuh kelas/status pada .ark-shell — tidak ada data bisnis di sini.
 * Pratinjau komponen punya runtime sendiri (assets/js/design-system.js);
 * berkas ini adalah versi produksi untuk halaman aplikasi.
 */
(function () {
  'use strict';

  var COLLAPSE_KEY = 'arsipku_sidebar_collapsed';
  var MOBILE = 1024;

  var shell = document.querySelector('.ark-shell');
  if (!shell) return;

  var header = shell.querySelector('.ark-header');
  var lastFocused = null;

  /* ------------------------------------------------------------- IKON */
  /* -------------------------------------------------- NAVIGASI AKTIF
     Server yang tahu halaman mana yang sedang dibuka, jadi sumber kebenaran
     tetap query string. Fallback data-shell-page dipakai pratinjau statis. */
  function syncActiveNav() {
    var current = new URLSearchParams(window.location.search).get('page') ||
      shell.getAttribute('data-shell-page');
    if (!current) return;
    shell.querySelectorAll('.ark-nav__item').forEach(function (item) {
      if (item.getAttribute('data-nav') === current) item.setAttribute('aria-current', 'page');
      else item.removeAttribute('aria-current');
    });
  }

  /* ----------------------------------------------------------- MENU */
  function menuTrigger(panel) {
    return shell.querySelector('[data-menu="#' + panel.id + '"]');
  }

  function closeMenus(except) {
    shell.querySelectorAll('.ark-menu:not([hidden])').forEach(function (panel) {
      if (panel === except) return;
      panel.hidden = true;
      panel.classList.remove('ark-menu--floating');
      panel.style.removeProperty('top');
      panel.style.removeProperty('left');
      var trigger = menuTrigger(panel);
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
    });
  }

  function positionTableMenu(trigger, panel) {
    if (!trigger.closest('.ark-table-wrap')) return;

    panel.classList.add('ark-menu--floating');
    panel.style.top = '0px';
    panel.style.left = '0px';
    var triggerRect = trigger.getBoundingClientRect();
    var panelRect = panel.getBoundingClientRect();
    var gap = 6;
    var margin = 8;
    var top = triggerRect.bottom + gap;
    if (top + panelRect.height > window.innerHeight - margin
      && triggerRect.top - panelRect.height - gap >= margin) {
      top = triggerRect.top - panelRect.height - gap;
    } else {
      top = Math.min(top, window.innerHeight - panelRect.height - margin);
    }
    var left = triggerRect.right - panelRect.width;
    left = Math.max(margin, Math.min(left, window.innerWidth - panelRect.width - margin));
    panel.style.top = Math.max(margin, top) + 'px';
    panel.style.left = left + 'px';
  }

  function openMenu(trigger) {
    var panel = shell.querySelector(trigger.getAttribute('data-menu'));
    if (!panel) return;
    closeMenus(panel);
    lastFocused = trigger;
    panel.hidden = false;
    positionTableMenu(trigger, panel);
    trigger.setAttribute('aria-expanded', 'true');
    var first = panel.querySelector('.ark-menu__item:not([disabled])');
    if (first) first.focus();
  }

  function closeMenu(panel) {
    panel.hidden = true;
    var trigger = menuTrigger(panel);
    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
      trigger.focus();
    }
  }

  function moveInMenu(panel, step) {
    var items = Array.prototype.slice.call(panel.querySelectorAll('.ark-menu__item:not([disabled])'));
    if (!items.length) return;
    var index = items.indexOf(document.activeElement);
    var next = (index + step + items.length) % items.length;
    items[next === -1 ? 0 : next].focus();
  }

  /* --------------------------------------------------------- DRAWER */
  function isMobile() { return window.innerWidth < MOBILE; }

  function setPressed(selector, active) {
    var button = shell.querySelector(selector);
    if (button) button.setAttribute('aria-expanded', String(active));
  }

  function openDrawer() {
    lastFocused = document.activeElement;
    shell.classList.add('is-drawer-open');
    document.body.classList.add('ark-drawer-lock');
    setPressed('[data-drawer="menu"]', true);
    var first = shell.querySelector('.ark-sidebar .ark-nav__item');
    if (first) first.focus();
  }

  function closeDrawer() {
    if (!shell.classList.contains('is-drawer-open')) return;
    shell.classList.remove('is-drawer-open');
    document.body.classList.remove('ark-drawer-lock');
    setPressed('[data-drawer="menu"]', false);
    if (lastFocused && lastFocused.isConnected) lastFocused.focus();
  }

  /* ----------------------------------------------------- RAIL + CARI */
  function setCollapsed(collapsed) {
    shell.classList.toggle('is-collapsed', collapsed);
    try { window.localStorage.setItem(COLLAPSE_KEY, String(collapsed)); } catch (e) { /* mode privat */ }
  }

  function restoreCollapsed() {
    var wants = false;
    try { wants = window.localStorage.getItem(COLLAPSE_KEY) === 'true'; } catch (e) { /* mode privat */ }
    if (wants && !isMobile()) shell.classList.add('is-collapsed');
  }

  function toggleSearch(open) {
    header.classList.toggle('is-search-open', open);
    setPressed('[data-search-toggle]', open);
    if (open) {
      var field = header.querySelector('.ark-search__field');
      if (field) field.focus();
    }
  }

  /* ------------------------------------------------------------ INIT */
  function init() {
    window.ArkIcons.hydrate(document);
    syncActiveNav();
    restoreCollapsed();

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-menu]');
      if (trigger) {
        var panel = shell.querySelector(trigger.getAttribute('data-menu'));
        if (panel && !panel.hidden) {
          panel.hidden = true;
          trigger.setAttribute('aria-expanded', 'false');
        } else {
          openMenu(trigger);
        }
        return;
      }

      if (event.target.closest('.ark-menu__item')) { closeMenus(); return; }

      if (!event.target.closest('.ark-menu-wrap')) closeMenus();

      var drawerBtn = event.target.closest('[data-drawer]');
      if (drawerBtn) {
        var kind = drawerBtn.getAttribute('data-drawer');
        if (kind === 'menu') {
          if (shell.classList.contains('is-drawer-open')) closeDrawer();
          else openDrawer();
        }
        if (kind === 'close') closeDrawer();
        if (kind === 'collapse') setCollapsed(!shell.classList.contains('is-collapsed'));
        return;
      }

      if (event.target.closest('[data-drawer-scrim]')) { closeDrawer(); return; }

      if (event.target.closest('[data-search-toggle]')) {
        toggleSearch(!header.classList.contains('is-search-open'));
        return;
      }

      var navItem = event.target.closest('.ark-nav__item');
      if (navItem && isMobile()) closeDrawer();
    });

    document.addEventListener('scroll', function (event) {
      if (event.target.closest && event.target.closest('.ark-menu--floating')) return;
      if (shell.querySelector('.ark-menu--floating:not([hidden])')) closeMenus();
    }, true);

    document.addEventListener('keydown', function (event) {
      var panel = event.target.closest && event.target.closest('.ark-menu');

      if (panel && !panel.hidden) {
        if (event.key === 'ArrowDown') { event.preventDefault(); moveInMenu(panel, 1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); moveInMenu(panel, -1); }
      }

      if (event.key === 'Escape') {
        /* Ditutup dari mana pun fokus berada: menu yang terbuka tapi tidak
           memegang fokus tetap harus kalah oleh Escape. */
        var open = shell.querySelector('.ark-menu:not([hidden])');
        if (open) { closeMenu(open); return; }
        if (header.classList.contains('is-search-open')) { toggleSearch(false); return; }
        closeDrawer();
      }

      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        var field = header.querySelector('.ark-search__field');
        if (!field) return;
        event.preventDefault();
        if (isMobile()) toggleSearch(true);
        else { field.focus(); field.select(); }
      }
    });

    /* Melebar ke desktop: drawer, baris cari, dan menu tidak boleh tertinggal
       terbuka di belakang layout baru. */
    window.addEventListener('resize', function () {
      if (isMobile()) return;
      closeDrawer();
      toggleSearch(false);
      closeMenus();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
