<?php
/** Dashboard admin — tambah akun baru atau ubah akun (?id=…): data akun, identitas, alamat berhirarki, kata sandi. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/akun.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_super_admin();   // mengelola akun = tugas Super Admin

$idUser = get('id');
$lama = null;
$peranBoleh = ['pembeli', 'penjual', 'admin', 'super_admin'];   // pembatasan juga diperiksa di includes/akun.php
$peranAwal = in_array(get('peran'), $peranBoleh, true) ? get('peran') : 'pembeli'; // ?peran=admin dari halaman Kelola Admin
$f = akun_kosong($peranAwal);
if ($idUser !== '') {
    $lama = row('SELECT * FROM users WHERE id_user = ?', [$idUser]);
    if (!$lama) {
        abort404();
    }
    $f = akun_ke_isian($idUser);
}
$galat = [];

if (is_post()) {
    csrf_check();
    $peran = $lama ? $lama['role'] : (in_array(post('peran'), $peranBoleh, true) ? post('peran') : 'pembeli');
    $f = akun_dari_post($peran);
    $sandi = (string) ($_POST['sandi'] ?? '');
    $sandi2 = (string) ($_POST['sandi2'] ?? '');
    [$galat, $f] = validasi_akun($f, $sandi, $sandi2, $lama['id_user'] ?? null);

    if (!$galat) {
        if ($lama) {
            ubah_akun($lama['id_user'], $peran, $f, $sandi);
            if ($lama['id_user'] === $u['id']) { // mengubah akun sendiri: sesi ikut diperbarui
                $_SESSION['user']['username'] = $f['username'];
                $_SESSION['user']['email'] = $f['email'];
                $_SESSION['user']['nama'] = in_array($peran, PERAN_ADMIN, true) ? $f['username'] : $f['nama'];
            }
            flash('ok', 'Perubahan akun “' . $f['username'] . '” disimpan' . ($sandi !== '' ? ', termasuk kata sandi barunya.' : '.'));
        } else {
            buat_akun($f, $sandi);
            flash('ok', 'Akun ' . $peran . ' “' . $f['username'] . '” berhasil dibuat.');
        }
        redirect('admin/akun.php');
    }
}

$peranDipilih = $f['peran'];
$daftarBank = pilihan_rekening();
$judul = $lama ? 'Ubah Akun' : 'Tambah Akun';
$infoPeran = ['pembeli' => ['Pembeli', 'Belanja kain tenun'], 'penjual' => ['Penjual', 'Jual hasil tenunan'], 'admin' => ['Admin', 'Kelola platform'], 'super_admin' => ['Super Admin', 'Semua hak + kelola admin']];
$infoPeran = array_intersect_key($infoPeran, array_flip($lama ? [$peranDipilih] : $peranBoleh));
$laAdmin = in_array($peranDipilih, PERAN_ADMIN, true);
$jenisPeran = ['pembeli' => 'info', 'penjual' => 'ok', 'admin' => 'warn', 'super_admin' => 'bad'];
$kembali = 'admin/akun.php';

page_start($judul, ['menu' => 'akun', 'judul' => $judul, 'wilayah' => true]);
?>
<div class="krumb"><a href="<?= e(url($kembali)) ?>">Kelola Akun</a> &rsaquo; <?= e($judul) ?><?= $lama ? ' · ' . e($lama['id_user']) : '' ?></div>
<?php if ($galat): ?>
  <div class="alert alert-error" role="alert"><div><b>Belum bisa disimpan:</b><ul style="margin:6px 0 0;padding-left:18px"><?php foreach ($galat as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<form method="post" id="form-akun" autocomplete="off" class="dua-kolom" novalidate="novalidate">
  <?= csrf_field() ?>
  <div>
    <section class="card mb">
      <h3>Peran</h3>
      <?php if ($lama): ?>
        <p style="margin:0"><?= badge(label_peran($peranDipilih), $jenisPeran[$peranDipilih]) ?> <span class="muted"><?= $laAdmin ? 'Hak akses admin diubah dari daftar Kelola Akun.' : 'Peran akun tidak dapat diubah setelah dibuat.' ?></span></p>
        <input type="hidden" name="peran" value="<?= e($peranDipilih) ?>">
      <?php else: ?>
        <div class="peran-pilih" style="grid-template-columns:repeat(<?= count($infoPeran) ?>,minmax(0,1fr));margin-bottom:0" role="radiogroup" aria-label="Peran akun">
          <?php foreach ($infoPeran as $k => [$nama, $ket]): ?>
            <label class="opsi"><input type="radio" name="peran" value="<?= e($k) ?>" <?= $peranDipilih === $k ? 'checked' : '' ?>><b><?= e($nama) ?></b><small><?= e($ket) ?></small></label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card mb">
      <h3>Akun</h3>
      <div class="grid-2">
        <label class="field"><span class="field-label">Nama pengguna <b class="req">*</b></span><input type="text" name="username" value="<?= e($f['username']) ?>" required minlength="4" maxlength="30" pattern="[A-Za-z0-9_.]+" autocapitalize="none"></label>
        <label class="field"><span class="field-label">Email <b class="req">*</b></span><input type="email" name="email" value="<?= e($f['email']) ?>" required maxlength="100"></label>
        <label class="field"><span class="field-label">Kata sandi <?= $lama ? '' : '<b class="req">*</b>' ?></span>
          <input type="password" name="sandi" <?= $lama ? '' : 'required' ?> maxlength="255" autocomplete="new-password">
          <span class="hint"><?= $lama ? 'Kosongkan jika tidak ingin mengganti.' : 'Bebas, tanpa aturan khusus.' ?></span></label>
        <label class="field"><span class="field-label">Ulangi kata sandi <?= $lama ? '' : '<b class="req">*</b>' ?></span><input type="password" name="sandi2" <?= $lama ? '' : 'required' ?> maxlength="255" autocomplete="new-password">
          <span class="hint"><button type="button" class="link-kecil" data-sandi-acak>Buat kata sandi acak</button> <span data-sandi-hasil></span></span></label>
      </div>
    </section>

    <section class="card mb hanya-profil" <?= $laAdmin ? 'hidden' : '' ?>>
      <h3>Identitas &amp; alamat</h3>
      <label class="field"><span class="field-label" data-label-nama><?= $peranDipilih === 'penjual' ? 'Nama toko' : 'Nama lengkap' ?> <b class="req">*</b></span><input type="text" name="nama" value="<?= e($f['nama']) ?>" required minlength="3" maxlength="100"></label>
      <div class="hanya-penjual" <?= $peranDipilih === 'penjual' ? '' : 'hidden' ?>>
        <div class="grid-2">
          <label class="field"><span class="field-label">Nomor telepon <b class="req">*</b></span><input type="tel" name="telepon" value="<?= e($f['telepon']) ?>" inputmode="tel" placeholder="081234567890"></label>
          <label class="field"><span class="field-label">Bank / dompet digital <b class="req">*</b></span>
            <select name="bank"><option value="">— pilih —</option><?php foreach ($daftarBank as $b): ?><option value="<?= e($b) ?>" <?= $f['bank'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="field"><span class="field-label">Nomor rekening / akun <b class="req">*</b></span><input type="text" name="no_rekening" value="<?= e($f['rekening']) ?>" inputmode="numeric"><span class="hint">Tempat hasil penjualan toko ditransfer.</span></label>
      </div>
      <div class="seksi-judul">Alamat</div>
      <p class="hint" style="margin-top:-4px">Pilih berurutan dari atas: tingkat berikutnya terbuka setelah tingkat sebelumnya dipilih.</p>
      <?= wilayah_fields(['negara' => $f['negara'], 'provinsi' => $f['provinsi'], 'kabupaten' => $f['kabupaten'], 'kecamatan' => $f['kecamatan'], 'desa' => $f['desa']]) ?>
      <label class="field"><span class="field-label">Detail alamat <b class="req">*</b></span><textarea name="detail_alamat" rows="3" required maxlength="300" placeholder="Nama jalan, nomor rumah, RT/RW, patokan"><?= e($f['detail']) ?></textarea></label>
    </section>
  </div>

  <aside class="card sticky">
    <h3><?= $lama ? 'Simpan perubahan' : 'Buat akun' ?></h3>
    <?php if ($lama): ?>
      <div class="ringkas-baris kecil"><span>ID akun</span><span class="mono"><?= e($lama['id_user']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Terdaftar</span><span><?= e(tgl($lama['created_at'])) ?></span></div>
      <?php if ($lama['id_user'] === $u['id']): ?><div class="alert alert-info mt"><span>Ini akun yang sedang Anda pakai.</span></div><?php endif; ?>
    <?php else: ?>
      <p class="muted kecil">Akun baru langsung aktif. Berikan nama pengguna dan kata sandinya kepada pemilik akun.</p>
    <?php endif; ?>
    <button class="btn btn-primary btn-lg btn-block mt" type="submit"><?= $lama ? 'Simpan perubahan' : 'Buat akun' ?></button>
    <a class="btn btn-ghost btn-block mt" href="<?= e(url($kembali)) ?>">Batal</a>
  </aside>
</form>
<?php page_end(['js/akun_form.js']);
