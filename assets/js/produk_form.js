/* Songketin — formulir produk penjual: pratinjau foto/gambar otomatis dan harga akhir. */
(function () {
  'use strict';
  var f = document.getElementById('form-produk');
  if (!f) return;
  var img = document.getElementById('pratinjau'), base = f.getAttribute('data-pratinjau');
  var berkas = f.elements['foto'], hapus = f.elements['hapus_foto'];
  var fotoAda = f.getAttribute('data-foto-ada') === '1', pilihBaru = false, t = null;

  function rp(n) { return 'Rp' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }

  function otomatis() {
    if (pilihBaru || (fotoAda && !(hapus && hapus.checked))) return; // ada foto sungguhan → jangan ditimpa pratinjau otomatis
    clearTimeout(t);
    t = setTimeout(function () {
      img.src = base + '?pratinjau=1&warna=' + encodeURIComponent(f.elements['warna'].value) +
        '&pola=' + encodeURIComponent(f.elements['pola'].value) + '&benang=' + encodeURIComponent(f.elements['benang'].value);
    }, 250);
  }
  ['warna', 'pola', 'benang'].forEach(function (n) {
    f.elements[n].addEventListener('input', otomatis);
    f.elements[n].addEventListener('change', otomatis);
  });

  berkas.addEventListener('change', function () {
    var b = berkas.files && berkas.files[0];
    if (!b) { pilihBaru = false; otomatis(); return; }
    if (b.size > 2 * 1024 * 1024) { window.SK.toast('Ukuran foto maksimal 2 MB.', 'error'); berkas.value = ''; return; }
    var r = new FileReader();
    r.onload = function () { img.src = r.result; pilihBaru = true; };
    r.readAsDataURL(b);
  });
  var srcAsli = img.getAttribute('src');
  if (hapus) hapus.addEventListener('change', function () {
    if (pilihBaru) return;
    if (hapus.checked) otomatis(); else img.src = srcAsli; // batal hapus → tampilkan foto asli lagi
  });

  var hasil = document.getElementById('harga-akhir');
  function hitung() {
    var h = parseInt(f.elements['harga'].value, 10) || 0, op = f.elements['diskon'].selectedOptions[0], p = op ? parseInt(op.getAttribute('data-besaran'), 10) || 0 : 0;
    if (!h) { hasil.textContent = '—'; return; }
    var akhir = Math.max(0, h - p);
    hasil.textContent = rp(akhir) + (p > 0 ? ' (hemat ' + Math.round(p / h * 100) + '%, harga asli ' + rp(h) + ')' : '');
    hasil.parentNode.parentNode.className = 'alert ' + (p >= h || akhir < 500 ? 'alert-error' : 'alert-info');
  }
  f.elements['harga'].addEventListener('input', hitung);
  f.elements['diskon'].addEventListener('change', hitung);
  hitung();
})();
