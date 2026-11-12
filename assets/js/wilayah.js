/* Songketin — alamat berhirarki: negara > provinsi > kabupaten/kota > kecamatan > desa/kelurahan.
   Tingkat berikutnya baru terbuka setelah tingkat di atasnya dipilih, dan dimuat dari api/wilayah.php. */
(function () {
  'use strict';
  var SEMUA = ['negara', 'provinsi', 'kabupaten', 'kecamatan', 'desa'];
  var NAMA = { provinsi: 'provinsi', kabupaten: 'kabupaten / kota', kecamatan: 'kecamatan', desa: 'desa / kelurahan' };

  function mulai(box) {
    var api = box.getAttribute('data-api');
    var URUT = SEMUA.filter(function (l) { return box.querySelector('select[data-level="' + l + '"]'); }); // kotak boleh berhenti di tingkat mana pun
    var sel = {};
    URUT.forEach(function (l) { sel[l] = box.querySelector('select[data-level="' + l + '"]'); });

    function kosongkan(level, pesan) {
      var s = sel[level];
      s.innerHTML = '<option value="">' + pesan + '</option>';
      s.disabled = true;
    }
    function bersihkanSesudah(idx) {
      for (var i = idx + 1; i < URUT.length; i++) kosongkan(URUT[i], '— pilih ' + NAMA[URUT[i]] + ' —');
    }
    function muat(idx) { // isi tingkat URUT[idx] berdasarkan pilihan tingkat sebelumnya
      var level = URUT[idx], induk = sel[URUT[idx - 1]].value;
      if (!induk) return;
      kosongkan(level, 'Memuat…');
      var u = api + '?level=' + encodeURIComponent(level) + '&induk=' + encodeURIComponent(induk);
      fetch(u, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
        var s = sel[level];
        if (!d.ok || !d.data.length) { kosongkan(level, 'Data tidak tersedia'); return; }
        s.innerHTML = '<option value="">— pilih ' + NAMA[level] + ' —</option>' + d.data.map(function (x) {
          return '<option value="' + x.id.replace(/"/g, '&quot;') + '">' + x.nama.replace(/</g, '&lt;') + '</option>';
        }).join('');
        s.disabled = false;
      }).catch(function () { kosongkan(level, 'Gagal memuat, coba lagi'); });
    }

    URUT.forEach(function (level, idx) {
      sel[level].addEventListener('change', function () {
        bersihkanSesudah(idx);
        if (idx < URUT.length - 1 && sel[level].value) muat(idx + 1);
      });
    });

    // Bila hanya ada satu negara, pilih otomatis supaya pengguna langsung bisa memilih provinsi.
    var n = sel.negara;
    if (!n.value && n.options.length === 2) { n.selectedIndex = 1; n.dispatchEvent(new Event('change')); }
  }

  document.querySelectorAll('[data-wilayah]').forEach(mulai);
})();
