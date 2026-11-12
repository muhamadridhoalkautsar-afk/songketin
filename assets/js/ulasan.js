/* Songketin — formulir ulasan: cek ukuran foto (maks. 2 MB), pratinjau, dan rating wajib. Sama seperti unggah foto produk & bukti pembayaran. */
(function () {
  'use strict';
  var MAKS = 2 * 1024 * 1024, TIPE = ['image/jpeg', 'image/png', 'image/webp'];
  function toast(p, j) { if (window.SK && window.SK.toast) window.SK.toast(p, j); }

  Array.prototype.forEach.call(document.querySelectorAll('form.form-ulasan'), function (f) {
    var berkas = f.elements['foto'], img = f.querySelector('.ulasan-pratinjau');
    if (berkas) {
      berkas.addEventListener('change', function () {
        var b = berkas.files && berkas.files[0];
        img.hidden = true;
        if (!b) return;
        if (TIPE.indexOf(b.type) === -1) { toast('Format foto harus JPG, PNG, atau WebP.', 'error'); berkas.value = ''; return; }
        if (b.size > MAKS) { toast('Ukuran foto maksimal 2 MB.', 'error'); berkas.value = ''; return; }
        var r = new FileReader();
        r.onload = function () { img.src = r.result; img.hidden = false; };
        r.readAsDataURL(b);
      });
    }
    f.addEventListener('submit', function (e) {
      if (!f.querySelector('input[name="rating"]:checked')) {
        e.preventDefault();
        toast('Pilih rating 1 sampai 5 bintang dulu.', 'error');
        var pertama = f.querySelector('input[name="rating"]'); if (pertama) pertama.focus();
      }
    });
  });
})();
