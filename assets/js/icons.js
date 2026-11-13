/**
 * ArsipKu Design System — Ikon.
 *
 * SATU-SATUNYA sumber bentuk ikon untuk seluruh aplikasi: lapisan ark-* memakai
 * window.ArkIcons di browser, dan src/Helpers.php::renderSvgIcon() membaca
 * berkas yang sama saat render server. Menambah/mengubah bentuk cukup di sini.
 *
 * Kontrak format ( dibaca ulang oleh PHP, jangan diubah sembarangan ):
 *   - satu entri per baris:  'nama': '<isi svg>',
 *   - isi memakai tanda kutip ganda; tanda kutip tunggal hanya membungkus nilai,
 *   - nama: huruf kecil, digit, '-' (mis. 'file-text'),
 *   - PATHS  = bentuk ikon,
 *     ALIAS  = nama lama -> nama kanonik (agar markup lama tetap ter-render),
 *     FILLED = daftar nama yang digambar solid, tanpa stroke.
 *
 * Semua ikon outline: viewBox 24, stroke currentColor, stroke-width 1.75.
 * Ukuran bukan atribut SVG; itu ditentukan CSS lewat --ark-icon-size /
 * kelas .ark-icon--NN (lihat assets/css/base.css §Ikon).
 *
 * Dimuat sebelum design-system.js (pratinjau) dan sebelum app-shell.js
 * (kerangka aplikasi). Tanpa dependensi, tanpa framework.
 */
(function (window) {
  'use strict';

  var STROKE_WIDTH = '1.75';

  var PATHS = {
    'alert': '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'archive': '<rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/>',
    'bell': '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10.3 20a2 2 0 0 0 3.4 0"/>',
    'check': '<path d="M20 6 9 17l-5-5"/>',
    'check-circle': '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    'chevron-down': '<path d="m6 9 6 6 6-6"/>',
    'chevron-right': '<path d="m9 18 6-6-6-6"/>',
    'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.4l3.4 2"/>',
    'credit-card': '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19"/><path d="M6 15h4"/>',
    'download': '<path d="M12 3v12"/><path d="m7 11 5 5 5-5"/><path d="M4 20h16"/>',
    'dots': '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
    'dots-vertical': '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
    'eye': '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.6"/>',
    'file': '<path d="M14 3v5h5"/><path d="M19 21H5V3h9l5 5v13Z"/>',
    'file-code': '<path d="M14 3v5h5"/><path d="M19 21H5V3h9l5 5v13Z"/><path d="m10 12-2 2 2 2"/><path d="m14 12 2 2-2 2"/>',
    'file-text': '<path d="M14 3v5h5"/><path d="M19 21H5V3h9l5 5v13Z"/><path d="M9 13h6"/><path d="M9 17h4"/>',
    'filter': '<path d="M3 5h18l-7 8v6l-4-2v-4L3 5Z"/>',
    'folder': '<path d="M3 7v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-7l-2-2H5a2 2 0 0 0-2 2Z"/>',
    'folder-plus': '<path d="M3 7v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-7l-2-2H5a2 2 0 0 0-2 2Z"/><path d="M12 12v5"/><path d="M9.5 14.5h5"/>',
    'grid': '<rect x="4" y="4" width="7" height="7" rx="1.4"/><rect x="13" y="4" width="7" height="7" rx="1.4"/><rect x="4" y="13" width="7" height="7" rx="1.4"/><rect x="13" y="13" width="7" height="7" rx="1.4"/>',
    'hard-drive': '<path d="M4 12h16"/><path d="M6.5 12V7.6A1.6 1.6 0 0 1 8.1 6h7.8a1.6 1.6 0 0 1 1.6 1.6V12"/><path d="M6 12v4.4A1.6 1.6 0 0 0 7.6 18h8.8a1.6 1.6 0 0 0 1.6-1.6V12"/><path d="M16.5 15h.01"/>',
    'help': '<circle cx="12" cy="12" r="9"/><path d="M9.4 9.2a2.7 2.7 0 0 1 5.2.9c0 1.8-2.6 2.2-2.6 3.8"/><path d="M12 17.4h.01"/>',
    'home': '<path d="m4 10 8-6 8 6v9a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1Z"/>',
    'image': '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="9.5" r="1.6"/><path d="m4 18 5-5 4 4 3-2 4 3"/>',
    'info': '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
    'link': '<path d="M10 13a4 4 0 0 0 6 .5l2-2a4 4 0 0 0-5.6-5.6l-1 1"/><path d="M14 11a4 4 0 0 0-6-.5l-2 2A4 4 0 0 0 11.6 18l1-1"/>',
    'list': '<path d="M8 6h12"/><path d="M8 12h12"/><path d="M8 18h12"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/>',
    'log-out': '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 8-4 4 4 4"/><path d="M6 12h9"/>',
    'move': '<path d="M12 3v18"/><path d="m7 8 5-5 5 5"/><path d="M3 12h18"/><path d="m8 17-5-5 5-5"/>',
    'panel-left': '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M10 4v16"/>',
    'pencil': '<path d="M4 20h4L20 8a2.1 2.1 0 0 0-3-3L5 17Z"/><path d="m15 6 3 3"/>',
    'plus': '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'restore': '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/>',
    'search': '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
    'settings': '<circle cx="12" cy="12" r="3"/><path d="M19.1 14.6a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.8-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3.3 13H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 11 4.7V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.8h.2a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
    'share': '<circle cx="18" cy="5.4" r="2.6"/><circle cx="6" cy="12" r="2.6"/><circle cx="18" cy="18.6" r="2.6"/><path d="m8.3 10.8 7.4-4.2"/><path d="m8.3 13.2 7.4 4.2"/>',
    'shield': '<path d="M12 3 5 6v6c0 4.4 3 7.6 7 9 4-1.4 7-4.6 7-9V6Z"/><path d="m9.5 12 2 2 3.5-4"/>',
    'sort': '<path d="m7 5 0 14"/><path d="m4 8 3-3 3 3"/><path d="M17 19V5"/><path d="m14 16 3 3 3-3"/>',
    'star': '<path d="m12 3.8 2.5 5.2 5.7.8-4.2 4 1.1 5.7L12 16.8 6.9 19.5 8 13.8l-4.2-4 5.7-.8Z"/>',
    'star-fill': '<path d="m12 3.8 2.5 5.2 5.7.8-4.2 4 1.1 5.7L12 16.8 6.9 19.5 8 13.8l-4.2-4 5.7-.8Z"/>',
    'table': '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M9 10v10"/>',
    'trash': '<path d="M4 7h16"/><path d="M9 7V4.5h6V7"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6"/><path d="M14 11v6"/>',
    'upload': '<path d="M12 20V8"/><path d="m7 12 5-5 5 5"/><path d="M4 4h16"/>',
    'upload-cloud': '<path d="M7 18a4.5 4.5 0 0 1-.5-9 6 6 0 0 1 11.5 2H18a3.5 3.5 0 0 1 0 7Z"/><path d="M12 21v-7"/><path d="m9.5 16.5 2.5-2.5 2.5 2.5"/>',
    'user': '<circle cx="12" cy="8.4" r="3.6"/><path d="M5 20a7 7 0 0 1 14 0"/>',
    'users': '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.5a3.2 3.2 0 0 1 0 6"/><path d="M17.5 20a5.6 5.6 0 0 0-2-4.3"/>',
    'versions': '<path d="M6 4h9l4 4v12H6Z"/><path d="M14 4v5h5"/><path d="M9 14h6"/><path d="M9 17h4"/>',
    'x': '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
    'x-circle': '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6"/><path d="m15 9-6 6"/>'
  };

  // Nama lama yang masih dipakai markup warisan. Bentuknya tidak boleh diduplikasi.
  var ALIAS = {
    'alert-triangle': 'alert',
    'file-stack': 'versions',
    'layout-grid': 'grid',
    'more-horizontal': 'dots',
    'more-vertical': 'dots-vertical',
    'refresh': 'restore',
    'shield-check': 'shield',
    'star-solid': 'star-fill'
  };

  // Ikon yang digambar solid; sisanya outline.
  var FILLED = ['star-fill'];

  function resolve(name) {
    var n = PATHS[name] != null ? name : ALIAS[name];
    return n != null && PATHS[n] != null ? n : null;
  }

  function markup(name) {
    var key = resolve(name);
    if (key == null) return '';
    var filled = FILLED.indexOf(key) !== -1;
    return '<svg class="ark-icon" viewBox="0 0 24 24" fill="' + (filled ? 'currentColor' : 'none') +
      '" stroke="' + (filled ? 'none' : 'currentColor') +
      '" stroke-width="' + STROKE_WIDTH + '" stroke-linecap="round" stroke-linejoin="round" ' +
      'aria-hidden="true" focusable="false">' + PATHS[key] + '</svg>';
  }

  function hydrate(root) {
    (root || document).querySelectorAll('[data-icon]').forEach(function (host) {
      if (host.firstElementChild) return;
      host.innerHTML = markup(host.getAttribute('data-icon'));
    });
  }

  window.ArkIcons = {
    markup: markup,
    hydrate: hydrate,
    has: function (n) { return resolve(n) != null; },
    strokeWidth: STROKE_WIDTH
  };
})(window);
