/* Songketin — ringkasan checkout yang ikut berubah saat pengiriman/diskon ongkir/koin dipilih. Angka dihitung server. */
(function () {
  'use strict';
  var f = document.getElementById('form-checkout');
  if (!f || !window.fetch) return;
  var box = document.getElementById('ringkasan'), btn = document.getElementById('btn-pesan'), t = null;

  function hitung() {
    clearTimeout(t);
    t = setTimeout(function () {
      var fd = new FormData(f);
      fd.delete('aksi');
      box.style.opacity = '.55';
      fetch(f.getAttribute('data-hitung'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) return;
          box.innerHTML = d.html;
          btn.disabled = !d.siap;
        })
        .catch(function () {})
        .then(function () { box.style.opacity = ''; });
    }, 120);
  }
  f.addEventListener('change', hitung);
})();
