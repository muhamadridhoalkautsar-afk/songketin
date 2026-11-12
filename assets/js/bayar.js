/* Songketin — pilihan pembayaran bertahap: sifat → metode → jenis. Server tetap memeriksa kecocokan rantainya. */
(function () {
  'use strict';
  var f = document.getElementById('form-bayar');
  if (!f) return;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var lS = $('#l-sifat'), lM = $('#l-metode'), lJ = $('#l-jenis'), lB = $('#l-bukti');
  var btn = $('#btn-bayar'), info = $('#info-bayar'), total = f.getAttribute('data-total') || '';
  var inBukti = $('#input-bukti'), pratinjau = $('#pratinjau-bukti');
  var berkasAkhir = null, urlAkhir = null;

  function dipilih(nama) {
    var r = f.querySelector('input[name="' + nama + '"]:checked');
    return r ? r.value : '';
  }

  /* Tampilkan hanya opsi yang anak dari pilihan sebelumnya; bila tinggal satu, langsung terpilih. */
  function saring(grup, atribut, nilai) {
    var cocok = [];
    $$('.opsi', grup).forEach(function (o) {
      var ok = nilai !== '' && o.getAttribute(atribut) === nilai, inp = $('input', o);
      o.hidden = !ok;
      inp.disabled = !ok;
      if (!ok) inp.checked = false; else cocok.push(inp);
    });
    var ket = $('[data-kosong]', grup);
    if (ket) ket.hidden = nilai !== '';
    if (cocok.length === 1) cocok[0].checked = true;
  }

  function segarkan() {
    var s = dipilih('sifat');
    saring(lM, 'data-sifat', s);
    var m = dipilih('metode');
    saring(lJ, 'data-metode', m);
    var j = dipilih('jenis');

    lS.classList.toggle('selesai', !!s);
    lM.classList.toggle('selesai', !!m); lM.classList.toggle('tunda', !s);
    lJ.classList.toggle('selesai', !!j); lJ.classList.toggle('tunda', !m);

    var lab = j ? $('input[name="jenis"]:checked', f).closest('.opsi') : null;
    if (lab) {
      var nama = $('b', lab).textContent, rek = lab.getAttribute('data-rekening') || '';
      var tunai = $('input[name="sifat"]:checked', f).closest('.opsi').hasAttribute('data-tunai');
      $('#info-judul').textContent = tunai ? 'Bayar tunai' : 'Bayar ke ' + nama;
      $('#info-nomor').textContent = rek || (tunai ? 'Kepada kurir saat pesanan tiba' : nama);
      $('#btn-salin').hidden = !rek;
      var qr = lab.getAttribute('data-qr') || '', gbr = $('#info-qr');
      if (gbr) { gbr.hidden = !qr; if (qr) gbr.src = qr; }
      $('#info-catatan').textContent = tunai
        ? 'Siapkan uang pas sebesar ' + total + '. Pesanan langsung diproses setelah dikonfirmasi.'
        : 'Bayar tepat ' + total + ', lalu tekan “Bayar sekarang”. Admin akan memverifikasi setelah dana masuk.';
      info.hidden = false;
      btn.textContent = (tunai ? 'Konfirmasi pesanan · ' : 'Bayar ' + total + ' · ') + nama;
    } else {
      info.hidden = true;
      btn.textContent = 'Pilih sifat, metode, dan jenis pembayaran';
    }
    /* Bukti pembayaran hanya untuk sifat Non-Tunai; wajib dipilih sebelum tombol bayar aktif. */
    var pakaiBukti = !!s && !$('input[name="sifat"]:checked', f).closest('.opsi').hasAttribute('data-tunai');
    lB.hidden = !pakaiBukti;
    inBukti.disabled = !pakaiBukti;
    inBukti.required = pakaiBukti;
    var berkas = inBukti.files && inBukti.files.length ? inBukti.files[0] : null;
    if (berkas !== berkasAkhir) {
      if (urlAkhir) URL.revokeObjectURL(urlAkhir);
      berkasAkhir = berkas;
      urlAkhir = berkas && window.URL ? URL.createObjectURL(berkas) : null;
      if (urlAkhir) pratinjau.src = urlAkhir;
    }
    pratinjau.hidden = !(pakaiBukti && urlAkhir);

    var siap = !!(s && m && j);
    if (siap && pakaiBukti && !berkas) btn.textContent = 'Unggah bukti pembayaran dulu';
    btn.disabled = !siap || (pakaiBukti && !berkas);
  }

  function salin(teks) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(teks);
    return new Promise(function (ok, gagal) {
      var a = document.createElement('textarea');
      a.value = teks; a.setAttribute('readonly', ''); a.style.cssText = 'position:fixed;opacity:0;top:0;left:0';
      document.body.appendChild(a); a.select();
      try { document.execCommand('copy') ? ok() : gagal(); } catch (e) { gagal(e); }
      a.remove();
    });
  }
  $('#btn-salin').addEventListener('click', function () {
    var t = ($('#info-nomor').textContent || '').split(/\s+a\.n\./)[0].trim();
    if (/^[\d\s-]+$/.test(t)) t = t.replace(/[\s-]/g, '');
    salin(t).then(function () { window.SK.toast('Disalin: ' + t); }, function () { window.SK.toast('Gagal menyalin, salin manual ya.', 'error'); });
  });

  f.addEventListener('change', segarkan);
  segarkan();
})();
