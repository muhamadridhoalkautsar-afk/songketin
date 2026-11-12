<?php
/** Profil toko penjual: data toko & alamat (bertingkat), rekening penerima hasil penjualan, ganti kata sandi, tingkat toko. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/akun.php';

$u = require_role('penjual');
$pj = profil_penjual();
$akun = row('SELECT username, email, password, created_at FROM users WHERE id_user = ?', [$u['id']]);
$f = akun_ke_isian($u['id']);
$galat = [];
$galatSandi = [];

if (is_post()) {
    csrf_check();
    if (post('aksi') === 'profil') {
        $f = akun_dari_post('penjual');
        $f['username'] = $akun['username'];   // nama pengguna tidak dapat diubah di sini
        [$galat, $f] = validasi_akun($f, '', '', $u['id']);
        if (!$galat) {
            ubah_akun($u['id'], 'penjual', $f, '');
            $_SESSION['user']['nama'] = $f['nama'];
            $_SESSION['user']['email'] = $f['email'];
            flash('ok', 'Profil toko sudah diperbarui.');
            redirect('penjual/profil.php');
        }
    } elseif (post('aksi') === 'sandi') {
        $lama = $_POST['sandi_lama'] ?? '';
        $baru = $_POST['sandi_baru'] ?? '';
        $ulang = $_POST['sandi_ulang'] ?? '';
        if (!is_string($lama) || !sandi_cocok($lama, (string) $akun['password'])) {
            $galatSandi[] = 'Kata sandi saat ini tidak cocok.';
        }
        if (!is_string($baru) || $baru === '') {
            $galatSandi[] = 'Kata sandi baru wajib diisi.';
        } elseif (mb_strlen($baru) > 255) {
            $galatSandi[] = 'Kata sandi baru terlalu panjang (maksimal 255 karakter).';
        } elseif ($baru !== $ulang) {
            $galatSandi[] = 'Ulangi kata sandi baru dengan persis sama.';
        }
        if (!$galatSandi) {
            q('UPDATE users SET password = ? WHERE id_user = ?', [sandi_hash($baru), $u['id']]);
            session_regenerate_id(true);
            flash('ok', 'Kata sandi berhasil diganti.');
            redirect('penjual/profil.php');
        }
    }
}

$daftarBank = pilihan_rekening();
$tk = row('SELECT * FROM tingkat_penjual WHERE id_tingkat_penjual = ?', [$pj['id_tingkat_penjual']]);
$omzet = omzet_selesai_penjual($pj['id_penjual']);
$berikut = row('SELECT * FROM tingkat_penjual WHERE minimal_transaksi > ? ORDER BY minimal_transaksi LIMIT 1', [$omzet]);
$awal = (float) ($tk['minimal_transaksi'] ?? 0);
$persen = $berikut ? (int) min(100, max(0, round(($omzet - $awal) / max(1, (float) $berikut['minimal_transaksi'] - $awal) * 100))) : 100;
$stat = row('SELECT COUNT(*) AS produk, COALESCE(SUM(stok), 0) AS stok FROM produk WHERE id_penjual = ?', [$pj['id_penjual']]);

page_start('Profil Toko', ['menu' => 'profil', 'judul' => 'Profil Toko', 'wilayah' => true]);
?>
<div class="profil-kepala">
  <span class="avatar-besar"><?= e(mb_strtoupper(mb_substr($pj['nama_penjual'], 0, 1))) ?></span>
  <div><h1 style="margin:0"><?= e($pj['nama_penjual']) ?></h1><span class="muted">@<?= e($akun['username']) ?> · bergabung <?= e(tgl($akun['created_at'])) ?></span></div>
</div>

<div class="dua-kolom mt">
  <div>
    <form method="post" class="card mb" autocomplete="on">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
      <h3>Data toko &amp; alamat</h3>
      <?php if ($galat): ?><div class="alert alert-error" role="alert"><span><?= e(implode(' ', $galat)) ?></span></div><?php endif; ?>
      <div class="grid-2">
        <label class="field"><span class="field-label">Nama toko <b class="req">*</b></span><input type="text" name="nama" value="<?= e($f['nama']) ?>" required minlength="3" maxlength="100"></label>
        <label class="field"><span class="field-label">Email <b class="req">*</b></span><input type="email" name="email" value="<?= e($f['email']) ?>" required maxlength="100" autocomplete="email"></label>
        <label class="field"><span class="field-label">Nomor telepon <b class="req">*</b></span><input type="tel" name="telepon" value="<?= e($f['telepon']) ?>" inputmode="tel" placeholder="081234567890" required></label>
        <label class="field"><span class="field-label">Nama pengguna</span><input type="text" value="<?= e($akun['username']) ?>" disabled><span class="hint">Tidak dapat diubah.</span></label>
      </div>
      <div class="seksi-judul" style="margin:6px 0 10px">Alamat toko</div>
      <?= wilayah_fields($f) ?>
      <label class="field"><span class="field-label">Detail alamat <b class="req">*</b></span><textarea name="detail_alamat" rows="3" required maxlength="300" placeholder="Nama jalan, nomor, RT/RW, patokan"><?= e($f['detail']) ?></textarea></label>
      <div class="seksi-judul" style="margin:6px 0 10px">Rekening penerima hasil penjualan</div>
      <div class="grid-2">
        <label class="field"><span class="field-label">Bank / dompet digital <b class="req">*</b></span>
          <?php if ($daftarBank): ?>
            <select name="bank" required><option value="">— pilih —</option><?php foreach ($daftarBank as $b): ?><option value="<?= e($b) ?>" <?= $f['bank'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?></select>
          <?php else: ?>
            <input type="text" name="bank" value="<?= e($f['bank']) ?>" maxlength="50" required placeholder="mis. BCA, DANA">
          <?php endif; ?></label>
        <label class="field"><span class="field-label">Nomor rekening / akun <b class="req">*</b></span><input type="text" name="no_rekening" value="<?= e($f['rekening']) ?>" required inputmode="numeric"></label>
      </div>
      <button class="btn btn-primary" type="submit">Simpan perubahan</button>
    </form>

    <form method="post" class="card mb" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="sandi">
      <h3>Ganti kata sandi</h3>
      <?php if ($galatSandi): ?><div class="alert alert-error" role="alert"><span><?= e(implode(' ', $galatSandi)) ?></span></div><?php endif; ?>
      <label class="field"><span class="field-label">Kata sandi saat ini</span><input type="password" name="sandi_lama" required autocomplete="current-password"></label>
      <div class="grid-2">
        <label class="field"><span class="field-label">Kata sandi baru</span><input type="password" name="sandi_baru" required maxlength="255" autocomplete="new-password"></label>
        <label class="field"><span class="field-label">Ulangi kata sandi baru</span><input type="password" name="sandi_ulang" required maxlength="255" autocomplete="new-password"></label>
      </div>
      <button class="btn btn-ghost" type="submit">Ganti kata sandi</button>
    </form>
  </div>

  <aside>
    <section class="card mb">
      <h3>Tingkat toko</h3>
      <div class="baris rata"><span class="chip chip-emas"><?= ikon('star', 15) ?> <?= e($tk['nama_tingkat_penjual'] ?? '—') ?></span>
        <span class="tebal">Biaya layanan <?= e(rtrim(rtrim(number_format((float) ($tk['persen_biaya_admin'] ?? 0), 2, ',', ''), '0'), ',')) ?>%</span></div>
      <div class="progres" role="progressbar" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $persen ?>%"></i></div>
      <?php if ($berikut): ?>
        <small>Penjualan yang sudah diterima pembeli: <?= rp($omzet) ?>. Tambah <?= rp(max(0, (float) $berikut['minimal_transaksi'] - $omzet)) ?> lagi untuk naik ke <b><?= e($berikut['nama_tingkat_penjual']) ?></b>.</small>
      <?php else: ?>
        <small>Toko Anda sudah di tingkat tertinggi. Penjualan diterima: <?= rp($omzet) ?>.</small>
      <?php endif; ?>
    </section>
    <section class="card">
      <h3>Ringkasan toko</h3>
      <div class="ringkas-baris"><span>Jumlah produk</span><b><?= angka($stat['produk']) ?></b></div>
      <div class="ringkas-baris"><span>Total stok</span><b><?= angka($stat['stok']) ?></b></div>
      <a class="btn btn-ghost btn-block mt" href="<?= e(url('penjual/produk.php')) ?>">Kelola produk</a>
    </section>
  </aside>
</div>
<?php page_end();
