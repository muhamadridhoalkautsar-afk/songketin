/* Formulir akun (admin): bagian yang tampil menurut peran, kata sandi acak. */
(function () {
  'use strict';
  var f = document.getElementById('form-akun');
  if (!f) return;
  var profil = f.querySelector('.hanya-profil');
  var penjual = f.querySelector('.hanya-penjual');
  var label = f.querySelector('[data-label-nama]');

  function peran() {
    var r = f.querySelector('input[name=peran]:checked');
    return r ? r.value : (f.querySelector('input[name=peran]') || {}).value;
  }
  function aktifkan(box, ya) {
    box.hidden = !ya;
    [].forEach.call(box.querySelectorAll('input,select,textarea'), function (el) {
      var wilayah = !!el.closest('.wilayah'); // keadaan aktif/nonaktif selektor wilayah diatur wilayah.js; di sini hanya "wajib"-nya
      if (!wilayah) el.disabled = !ya;
      el.required = ya && (wilayah || el.name === 'nama' || el.name === 'detail_alamat');
    });
  }
  function sinkron() {
    var p = peran();
    aktifkan(profil, p !== 'admin' && p !== 'super_admin');
    aktifkan(penjual, p === 'penjual');
    [].forEach.call(penjual.querySelectorAll('input[name=telepon],input[name=no_rekening],select[name=bank]'), function (el) { el.required = p === 'penjual'; });
    if (label) label.firstChild.nodeValue = (p === 'penjual' ? 'Nama toko' : 'Nama lengkap') + ' ';
  }
  [].forEach.call(f.querySelectorAll('input[name=peran]'), function (r) { r.addEventListener('change', sinkron); });

  /* kata sandi acak: huruf + angka tanpa karakter yang mudah tertukar */
  var tombol = f.querySelector('[data-sandi-acak]');
  if (tombol) tombol.addEventListener('click', function () {
    var huruf = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ', angka = '23456789', semua = huruf + angka, s = '';
    var buf = new Uint32Array(10); (window.crypto || window.msCrypto).getRandomValues(buf);
    s += huruf[buf[0] % huruf.length] + angka[buf[1] % angka.length];
    for (var i = 2; i < 10; i++) s += semua[buf[i] % semua.length];
    s = s.split('').sort(function () { return 0.5 - Math.random(); }).join('');
    var a = f.querySelector('[name=sandi]'), b = f.querySelector('[name=sandi2]');
    a.type = 'text'; b.type = 'text'; a.value = s; b.value = s;
    a.dispatchEvent(new Event('input', { bubbles: true }));
    var hasil = f.querySelector('[data-sandi-hasil]'); if (hasil) hasil.textContent = '→ ' + s;
  });

  f.removeAttribute('novalidate');
  sinkron();
})();
