<?php
/**
 * Halaman awal Songketin: sambutan + masuk (login) + daftar (register).
 * Alamat saat mendaftar wajib berhirarki: negara > provinsi > kabupaten/kota > kecamatan > desa.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/akun.php';

if (logged_in()) {
    redirect(home_for(user()['role']));
}

$tab = ($_GET['tab'] ?? '') === 'daftar' ? 'daftar' : 'masuk';
$galatMasuk = '';
$galatDaftar = [];
$lama = [];

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');

    // ------------------------------------------------------------ MASUK
    if ($aksi === 'masuk') {
        $tab = 'masuk';
        $ident = mb_strtolower(post('ident'));
        $sandi = (string) ($_POST['sandi'] ?? '');
        $lama['ident'] = post('ident');
        if (($sisa = login_terblokir()) > 0) {
            $galatMasuk = 'Terlalu banyak percobaan. Coba lagi sekitar ' . max(1, (int) ceil($sisa / 60)) . ' menit lagi.';
        } elseif ($ident === '' || $sandi === '') {
            $galatMasuk = 'Isi nama pengguna / email dan kata sandi.';
        } else {
            $u = row('SELECT * FROM users WHERE LOWER(username) = ? OR LOWER(email) = ?', [$ident, $ident]);
            if ($u && sandi_cocok($sandi, (string) $u['password'])) {
                if (sandi_perlu_hash_ulang((string) $u['password'])) { // hash peninggalan versi lama disimpan ulang sebagai teks biasa
                    q('UPDATE users SET password = ? WHERE id_user = ?', [sandi_hash($sandi), $u['id_user']]);
                }
                login_user($u);
                flash('ok', 'Selamat datang, ' . user()['nama'] . '!');
                redirect(tujuan_setelah_masuk(user()));
            }
            login_gagal();
            $galatMasuk = 'Nama pengguna / email atau kata sandi tidak cocok.';
        }
    }

    // ------------------------------------------------------------ DAFTAR
    if ($aksi === 'daftar') {
        $tab = 'daftar';
        $peran = post('peran') === 'penjual' ? 'penjual' : 'pembeli';
        $lama = akun_dari_post($peran);
        $sandi = (string) ($_POST['sandi'] ?? '');
        $sandi2 = (string) ($_POST['sandi2'] ?? '');
        [$galatDaftar, $f] = validasi_akun($lama, $sandi, $sandi2);

        if (!$galatDaftar) {
            $idUser = buat_akun($f, $sandi);
            login_user(row('SELECT * FROM users WHERE id_user = ?', [$idUser]));
            flash('ok', 'Akun berhasil dibuat. Selamat datang di Songketin, ' . user()['nama'] . '!');
            redirect(home_for($peran));
        }
    }
}

$daftarBank = pilihan_rekening();
$peranDipilih = ($lama['peran'] ?? 'pembeli') === 'penjual' ? 'penjual' : 'pembeli';

page_start($tab === 'daftar' ? 'Daftar' : 'Masuk', ['layout' => 'public', 'wilayah' => true, 'flash' => false, 'body' => 'halaman-awal']);
?>
<div class="auth">
  <section class="auth-hero" aria-label="Sambutan">
    <a class="brand" href="<?= e(url()) ?>"><?= logo_svg(40) ?><span><?= APP_NAME ?></span></a>
    <div>
      <h1>Selamat datang di <em>Songketin</em></h1>
      <p class="lead">Tempat bertemunya para penenun dan pencinta kain Nusantara. Masuk untuk berbelanja songket, tenun ikat, dan selendang langsung dari pengrajinnya &mdash; atau daftar sebagai penjual dan tenun usahamu sendiri.</p>
      <ul class="fitur">
        <li><span class="titik-emas"><?= ikon('star', 18) ?></span><div><b>Langsung dari penenun</b>Palembang, Pandai Sikek, Lombok, dan banyak daerah lain.</div></li>
        <li><span class="titik-emas"><?= ikon('card', 18) ?></span><div><b>Bayar sesuai caramu</b>Pilih metode pembayaran, struk bisa dicetak ke PDF.</div></li>
        <li><span class="titik-emas"><?= ikon('shield', 18) ?></span><div><b>Alamat yang rapi</b>Dari negara sampai desa, supaya kain sampai ke pintu yang tepat.</div></li>
      </ul>
    </div>
    <div class="hero-kain" aria-hidden="true">
      <?php for ($i = 0; $i < 22; $i++): ?><i style="animation-delay:-<?= number_format(fmod($i * 0.37, 3.2), 2) ?>s;animation-duration:<?= number_format(2.4 + ($i % 5) * 0.45, 2) ?>s"></i><?php endfor; ?>
    </div>
  </section>

  <section class="auth-panel">
    <div class="auth-card">
      <div class="card">
        <div class="salam">
          <h2>Halo, sugeng rawuh!</h2>
          <p>Masuk ke akunmu atau buat akun baru untuk mulai menenun belanja.</p>
        </div>
        <?= flash_render() ?>
        <div class="tab-saklar" role="tablist">
          <button type="button" role="tab" data-tab="masuk" aria-selected="<?= $tab === 'masuk' ? 'true' : 'false' ?>">Masuk</button>
          <button type="button" role="tab" data-tab="daftar" aria-selected="<?= $tab === 'daftar' ? 'true' : 'false' ?>">Daftar</button>
        </div>

        <!-- ===== MASUK ===== -->
        <div class="tab-isi" id="masuk" role="tabpanel" <?= $tab === 'masuk' ? '' : 'hidden' ?>>
          <?php if ($galatMasuk): ?><div class="alert alert-error"><span><?= e($galatMasuk) ?></span></div><?php endif; ?>
          <form method="post" autocomplete="on">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="masuk">
            <label class="field"><span class="field-label">Nama pengguna atau email</span>
              <input type="text" name="ident" value="<?= e($lama['ident'] ?? '') ?>" required autocomplete="username" autofocus></label>
            <label class="field"><span class="field-label">Kata sandi</span>
              <span class="kata-sandi-wrap"><input type="password" name="sandi" required autocomplete="current-password"><button type="button" data-lihat-sandi>Lihat</button></span></label>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Masuk</button>
          </form>
          <p class="tengah kecil mt">Belum punya akun? <a href="#daftar" data-tab="daftar">Daftar sekarang</a></p>
        </div>

        <!-- ===== DAFTAR ===== -->
        <div class="tab-isi" id="daftar" role="tabpanel" <?= $tab === 'daftar' ? '' : 'hidden' ?>>
          <?php if ($galatDaftar): ?>
            <div class="alert alert-error" role="alert"><div><b>Periksa kembali isian Anda:</b><ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($galatDaftar as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div></div>
          <?php endif; ?>
          <form method="post" id="form-daftar" autocomplete="off" novalidate="novalidate">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="daftar">
            <div class="peran-pilih" role="radiogroup" aria-label="Daftar sebagai">
              <label class="opsi"><input type="radio" name="peran" value="pembeli" <?= $peranDipilih === 'pembeli' ? 'checked' : '' ?>><b>Pembeli</b><small>Belanja kain tenun</small></label>
              <label class="opsi"><input type="radio" name="peran" value="penjual" <?= $peranDipilih === 'penjual' ? 'checked' : '' ?>><b>Penjual</b><small>Jual hasil tenunan</small></label>
            </div>

            <div class="seksi-judul">Akun</div>
            <div class="grid-2">
              <label class="field"><span class="field-label">Nama pengguna <b class="req">*</b></span><input type="text" name="username" value="<?= e($lama['username'] ?? '') ?>" required minlength="4" maxlength="30" pattern="[A-Za-z0-9_.]+" autocapitalize="none"></label>
              <label class="field"><span class="field-label">Email <b class="req">*</b></span><input type="email" name="email" value="<?= e($lama['email'] ?? '') ?>" required maxlength="100"></label>
              <label class="field"><span class="field-label">Kata sandi <b class="req">*</b></span><input type="password" name="sandi" required maxlength="255" autocomplete="new-password"></label>
              <label class="field"><span class="field-label">Ulangi kata sandi <b class="req">*</b></span><input type="password" name="sandi2" required maxlength="255" autocomplete="new-password"></label>
            </div>

            <div class="seksi-judul">Identitas</div>
            <label class="field"><span class="field-label" data-label-nama><?= $peranDipilih === 'penjual' ? 'Nama toko' : 'Nama lengkap' ?> <b class="req">*</b></span><input type="text" name="nama" value="<?= e($lama['nama'] ?? '') ?>" required minlength="3" maxlength="100"></label>
            <div class="hanya-penjual" <?= $peranDipilih === 'penjual' ? '' : 'hidden' ?>>
              <div class="grid-2">
                <label class="field"><span class="field-label">Nomor telepon <b class="req">*</b></span><input type="tel" name="telepon" value="<?= e($lama['telepon'] ?? '') ?>" inputmode="tel" placeholder="081234567890"></label>
                <label class="field"><span class="field-label">Bank / dompet digital <b class="req">*</b></span>
                  <?php if ($daftarBank): ?>
                  <select name="bank"><option value="">— pilih —</option><?php foreach ($daftarBank as $b): ?><option value="<?= e($b) ?>" <?= ($lama['bank'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?></select>
                  <?php else: ?>
                  <input type="text" name="bank" value="<?= e($lama['bank'] ?? '') ?>" maxlength="50" placeholder="mis. BCA, DANA">
                  <?php endif; ?></label>
              </div>
              <label class="field"><span class="field-label">Nomor rekening / akun <b class="req">*</b></span><input type="text" name="no_rekening" value="<?= e($lama['rekening'] ?? '') ?>" inputmode="numeric"><span class="hint">Tempat hasil penjualan Anda ditransfer.</span></label>
            </div>

            <div class="seksi-judul">Alamat</div>
            <p class="hint" style="margin-top:-4px">Pilih berurutan dari atas: tingkat berikutnya terbuka setelah tingkat sebelumnya dipilih.</p>
            <?= wilayah_fields(['negara' => $lama['negara'] ?? '', 'provinsi' => $lama['provinsi'] ?? '', 'kabupaten' => $lama['kabupaten'] ?? '', 'kecamatan' => $lama['kecamatan'] ?? '', 'desa' => $lama['desa'] ?? '']) ?>
            <label class="field"><span class="field-label">Detail alamat <b class="req">*</b></span><textarea name="detail_alamat" rows="3" required maxlength="300" placeholder="Nama jalan, nomor rumah, RT/RW, patokan"><?= e($lama['detail'] ?? '') ?></textarea></label>

            <button class="btn btn-gold btn-lg btn-block" type="submit">Buat akun</button>
          </form>
          <p class="tengah kecil mt">Sudah punya akun? <a href="#masuk" data-tab="masuk">Masuk di sini</a></p>
        </div>
      </div>
    </div>
  </section>
</div>
<script>
(function () {
  var f = document.getElementById('form-daftar');
  if (!f) return;
  function sinkron() {
    var penjual = f.querySelector('input[name=peran]:checked').value === 'penjual';
    var box = f.querySelector('.hanya-penjual');
    box.hidden = !penjual;
    [].forEach.call(box.querySelectorAll('input,select'), function (el) { el.disabled = !penjual; el.required = penjual; });
    f.querySelector('[data-label-nama]').firstChild.nodeValue = (penjual ? 'Nama toko' : 'Nama lengkap') + ' ';
  }
  [].forEach.call(f.querySelectorAll('input[name=peran]'), function (r) { r.addEventListener('change', sinkron); });
  f.removeAttribute('novalidate');
  sinkron();
})();
</script>
<?php page_end();
