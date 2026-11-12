/* Songketin — perilaku antarmuka umum (tanpa pustaka luar) */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- loading: penenun muncul saat memuat & berpindah halaman ---------- */
  var loader = document.getElementById('loader');
  var mulai = performance.now(), MIN = 700;
  function sembunyi() {
    if (!loader) return;
    var sisa = Math.max(0, MIN - (performance.now() - mulai));
    setTimeout(function () { loader.classList.add('sembunyi'); }, sisa);
  }
  function bakukan(p) { return String(p).replace(/index\.php$/, ''); }
  function halamanSama(path) { return bakukan(path) === bakukan(location.pathname); }
  function tampil() {
    if (!loader) return;
    mulai = performance.now();
    loader.classList.remove('sembunyi');
    setTimeout(sembunyi, 9000); // pengaman bila navigasi dibatalkan / berupa unduhan
  }
  if (document.readyState === 'complete') sembunyi(); else window.addEventListener('load', sembunyi);
  setTimeout(sembunyi, 6000);
  window.addEventListener('pageshow', function (e) { if (e.persisted && loader) loader.classList.add('sembunyi'); });
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if ((a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-tanpa-loader')) return;
    var h = a.getAttribute('href') || '';
    if (!h || h.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(h) || a.origin !== location.origin) return;
    if (halamanSama(a.pathname)) return; // tetap di halaman yang sama (tab, filter, halaman ke-2…): tanpa animasi
    tampil();
  });
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || f.hasAttribute('data-tanpa-loader') || f.target === '_blank') return;
    var tujuan;
    try { tujuan = new URL(f.getAttribute('action') || location.href, location.href); } catch (x) { tujuan = location; }
    if (tujuan.origin === location.origin && halamanSama(tujuan.pathname)) return; // simpan/cari/ubah di halaman ini: tanpa animasi
    tampil();
  });

  /* ---------- toast ---------- */
  function toast(pesan, jenis) {
    var area = $('.toast-area');
    if (!area) { area = document.createElement('div'); area.className = 'toast-area'; area.setAttribute('aria-live', 'polite'); document.body.appendChild(area); }
    var t = document.createElement('div');
    t.className = 'toast toast-' + (jenis || 'ok');
    t.textContent = pesan;
    area.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('masuk'); });
    setTimeout(function () { t.classList.remove('masuk'); setTimeout(function () { t.remove(); }, 300); }, 3200);
  }
  window.SK = window.SK || {};
  window.SK.toast = toast;

  /* ---------- klik delegasi ---------- */
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-tutup],[data-sidebar-buka],[data-sidebar-tutup],[data-qty],[data-lihat-sandi],[data-isi-login],[data-tab],[data-cetak]') : null;
    if (!el) return;
    if (el.hasAttribute('data-cetak')) window.print();
    else if (el.hasAttribute('data-tutup')) { var al = el.closest('.alert'); if (al) al.remove(); }
    else if (el.hasAttribute('data-sidebar-buka')) document.body.classList.toggle('sidebar-buka');
    else if (el.hasAttribute('data-sidebar-tutup')) document.body.classList.remove('sidebar-buka');
    else if (el.hasAttribute('data-qty')) {
      var inp = $('input', el.closest('.qty'));
      var min = parseInt(inp.min || '1', 10), max = parseInt(inp.max || '999999', 10);
      var v = (parseInt(inp.value, 10) || min) + parseInt(el.getAttribute('data-qty'), 10);
      inp.value = Math.min(max, Math.max(min, v));
      inp.dispatchEvent(new Event('change', { bubbles: true }));
    } else if (el.hasAttribute('data-lihat-sandi')) {
      var p = $('input', el.parentNode); var tersembunyi = p.type === 'password';
      p.type = tersembunyi ? 'text' : 'password'; el.textContent = tersembunyi ? 'Sembunyikan' : 'Lihat';
    } else if (el.hasAttribute('data-isi-login')) {
      var f = el.closest('.tab-isi') || document;
      $('[name=ident]', f).value = el.getAttribute('data-isi-login');
      $('[name=sandi]', f).value = el.getAttribute('data-sandi');
      $('[name=sandi]', f).focus();
    } else if (el.hasAttribute('data-tab')) {
      var id = el.getAttribute('data-tab');
      $$('[data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b === el || b.getAttribute('data-tab') === id ? 'true' : 'false'); });
      $$('.tab-isi').forEach(function (p) { p.hidden = p.id !== id; });
      history.replaceState(null, '', '#' + id);
    }
  });
  if (location.hash && $('.tab-isi' + location.hash)) { var tb = $('[data-tab="' + location.hash.slice(1) + '"]'); if (tb) tb.click(); }

  /* ---------- konfirmasi sebelum aksi berbahaya ---------- */
  document.addEventListener('submit', function (e) {
    var t = e.submitter && e.submitter.getAttribute('data-konfirmasi') || e.target.getAttribute('data-konfirmasi');
    if (t && !window.confirm(t)) { e.preventDefault(); e.stopImmediatePropagation(); }
  }, true);

  /* ---------- filter yang langsung terkirim ---------- */
  document.addEventListener('change', function (e) {
    if (e.target.hasAttribute && e.target.hasAttribute('data-auto-submit')) e.target.form.submit();
  });

  /* ---------- tambah ke keranjang tanpa pindah halaman ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target, sub = e.submitter || null;
    if (!f.hasAttribute || !f.hasAttribute('data-ajax-keranjang') || !window.fetch) return;
    if (sub && sub.hasAttribute('data-tanpa-ajax')) return; // mis. "Beli sekarang": kirim biasa lalu pindah halaman
    e.preventDefault();
    var fd = new FormData(f);
    if (sub && sub.name) fd.set(sub.name, sub.value);
    var btn = f.querySelector('button[type=submit]'); if (btn) btn.disabled = true;
    fetch(f.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        toast(d.pesan || 'Selesai', d.ok ? 'ok' : 'error');
        if (d.ok) $$('[data-cart-count]').forEach(function (c) { c.textContent = d.jumlah; c.hidden = !d.jumlah; c.classList.remove('loncat'); void c.offsetWidth; c.classList.add('loncat'); });
      })
      .catch(function () { f.removeAttribute('data-ajax-keranjang'); f.submit(); })
      .then(function () { if (btn) btn.disabled = false; });
  }, true);

  /* ---------- batang grafik tumbuh saat tampil ---------- */
  $$('.grafik .batang i[data-h]').forEach(function (b) { b.style.height = '0'; requestAnimationFrame(function () { requestAnimationFrame(function () { b.style.height = b.getAttribute('data-h'); }); }); });
})();
