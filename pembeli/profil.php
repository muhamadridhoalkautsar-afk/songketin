<?php
/** Profil pembeli: data diri & alamat bertingkat, ganti kata sandi, tingkat dan koin. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');
$pb = profil_pembeli();
$akun = row('SELECT username, email, password, created_at FROM users WHERE id_user = ?', [$u['id']]);
$rantai = wilayah_rantai($pb['id_desa']);

$f = [
    'nama' => $pb['nama_pembeli'], 'email' => $akun['email'], 'detail' => (string) $pb['detail_alamat'],
    'negara' => $rantai['negara']['id'] ?? '', 'provinsi' => $rantai['provinsi']['id'] ?? '', 'kabupaten' => $rantai['kabupaten']['id'] ?? '',
    'kecamatan' => $rantai['kecamatan']['id'] ?? '', 'desa' => $rantai['desa']['id'] ?? '',
];
$galat = [];
$galatSandi = [];

if (is_post()) {
    csrf_check();
    if (post('aksi') === 'profil') {
        $f = [
            'nama' => post('nama'), 'email' => mb_strtolower(post('email')), 'detail' => post('detail_alamat'),
            'negara' => post('negara'), 'provinsi' => post('provinsi'), 'kabupaten' => post('kabupaten'),
            'kecamatan' => post('kecamatan'), 'desa' => post('desa'),
        ];
        if (mb_strlen($f['nama']) < 3 || mb_strlen($f['nama']) > 100) {
            $galat[] = 'Nama lengkap harus 3–100 karakter.';
        }
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 100) {
            $galat[] = 'Format email belum benar.';
        } elseif (val('SELECT 1 FROM users WHERE LOWER(email) = ? AND id_user <> ?', [$f['email'], $u['id']])) {
            $galat[] = 'Email itu sudah dipakai akun lain.';
        }
        if (!wilayah_valid($f['negara'], $f['provinsi'], $f['kabupaten'], $f['kecamatan'], $f['desa'])) {
            $galat[] = 'Alamat harus dipilih berurutan: negara, provinsi, kabupaten/kota, kecamatan, lalu desa/kelurahan.';
        }
        if (mb_strlen($f['detail']) < 5 || mb_strlen($f['detail']) > 300) {
            $galat[] = 'Detail alamat harus 5–300 karakter.';
        }
        if (!$galat) {
            transaksi(function () use ($f, $u, $pb) {
                q('UPDATE users SET email = ? WHERE id_user = ?', [$f['email'], $u['id']]);
                q('UPDATE pembeli SET nama_pembeli = ?, id_desa = ?, detail_alamat = ? WHERE id_pembeli = ?', [$f['nama'], $f['desa'], $f['detail'], $pb['id_pembeli']]);
            });
            $_SESSION['user']['nama'] = $f['nama'];
            $_SESSION['user']['email'] = $f['email'];
            flash('ok', 'Profil Anda sudah diperbarui.');
            redirect('pembeli/profil.php');
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
            q('UPDATE users SET password = ? WHERE id_user = ?', [$baru, $u['id']]);
            session_regenerate_id(true);
            flash('ok', 'Kata sandi berhasil diganti.');
            redirect('pembeli/profil.php');
        }
    }
}

// tingkat & koin
$kn = koin_ringkasan($pb['id_pembeli']);
$saldo = $kn['saldo'];
$terkumpul = $kn['didapat'];
$tk = tingkat_pembeli_row($pb['id_tingkat_pembeli']);
$setaraRp = $saldo * NILAI_KOIN;
$berikut = row('SELECT * FROM tingkat_pembeli WHERE akumulasi_koin > ? ORDER BY akumulasi_koin LIMIT 1', [$terkumpul]);
$persen = $berikut ? (int) min(100, max(0, round(($terkumpul - (float) $tk['akumulasi_koin']) / max(1, (float) $berikut['akumulasi_koin'] - (float) $tk['akumulasi_koin']) * 100))) : 100;
$stat = row('SELECT COUNT(*) AS jml, COALESCE(SUM(total_harga_beli), 0) AS belanja FROM nota_pembeli nb WHERE id_pembeli = ? AND NOT ' . sql_dibatalkan('nb'), [$pb['id_pembeli']]);
$diskonTeks = rtrim(rtrim(number_format((float) $tk['besar_diskon'], 2, ',', ''), '0'), ',');

page_start('Profil', ['menu' => 'profil', 'wilayah' => true]);
?>
<div class="profil-kepala">
  <span class="avatar-besar"><?= e(mb_strtoupper(mb_substr($pb['nama_pembeli'], 0, 1))) ?></span>
  <div><h1 style="margin:0"><?= e($pb['nama_pembeli']) ?></h1><span class="muted">@<?= e($akun['username']) ?> · bergabung <?= e(tgl($akun['created_at'])) ?></span></div>
</div>

<div class="dua-kolom mt">
  <div>
    <form method="post" class="card mb" autocomplete="on">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
      <h3>Data diri &amp; alamat</h3>
      <?php if ($galat): ?><div class="alert alert-error" role="alert"><span><?= e(implode(' ', $galat)) ?></span></div><?php endif; ?>
      <div class="grid-2">
        <label class="field"><span class="field-label">Nama lengkap <b class="req">*</b></span><input type="text" name="nama" value="<?= e($f['nama']) ?>" required minlength="3" maxlength="100" autocomplete="name"></label>
        <label class="field"><span class="field-label">Email <b class="req">*</b></span><input type="email" name="email" value="<?= e($f['email']) ?>" required maxlength="100" autocomplete="email"></label>
      </div>
      <label class="field"><span class="field-label">Nama pengguna</span><input type="text" value="<?= e($akun['username']) ?>" disabled><span class="hint">Nama pengguna tidak dapat diubah.</span></label>
      <div class="seksi-judul" style="margin:6px 0 10px">Alamat pengiriman</div>
      <?= wilayah_fields($f) ?>
      <label class="field"><span class="field-label">Detail alamat <b class="req">*</b></span><textarea name="detail_alamat" rows="3" required maxlength="300" placeholder="Nama jalan, nomor rumah, RT/RW, patokan"><?= e($f['detail']) ?></textarea></label>
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
      <h3>Tingkat &amp; koin</h3>
      <div class="baris rata"><span class="chip chip-emas"><?= ikon('star', 15) ?> <?= e($tk['nama_tingkat_pembeli']) ?></span><span class="tebal"><?= (float) $tk['besar_diskon'] > 0 ? 'Diskon ' . e($diskonTeks) . '%' : 'Belum ada diskon' ?></span></div>
      <div class="progres" role="progressbar" aria-valuenow="<?= $persen ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $persen ?>%"></i></div>
      <?php if ($berikut): ?>
        <small>Kumpulkan <?= angka(max(0, (int) $berikut['akumulasi_koin'] - $terkumpul)) ?> koin lagi untuk naik ke <b><?= e($berikut['nama_tingkat_pembeli']) ?></b> (diskon <?= e(rtrim(rtrim(number_format((float) $berikut['besar_diskon'], 2, ',', ''), '0'), ',')) ?>%).</small>
      <?php else: ?>
        <small>Anda sudah di tingkat tertinggi. Terima kasih sudah setia menenun bersama kami!</small>
      <?php endif; ?>
      <hr>
      <div class="ringkas-baris"><span><?= ikon('coin', 16) ?> Saldo koin</span><b><?= angka($saldo) ?></b></div>
      <div class="ringkas-baris kecil"><span>Nilai 1 koin</span><span><?= rp(NILAI_KOIN) ?></span></div>
      <div class="ringkas-baris kecil"><span>Setara</span><span><?= rp($setaraRp) ?></span></div>
      <div class="ringkas-baris kecil"><span>Koin terkumpul sepanjang waktu</span><span><?= angka($terkumpul) ?></span></div>
      <p class="kecil mt" style="margin-bottom:0">Setiap Rp<?= angka(RUPIAH_PER_KOIN_DIDAPAT) ?> belanja yang sudah diterima memberi 1 koin (1 koin = <?= rp(NILAI_KOIN) ?> potongan). Saat memesan Anda bisa memakai koin; potongannya maksimal <?= (int) KOIN_MAKS_PERSEN ?>% dari harga barang. Tingkat naik mengikuti total koin yang pernah terkumpul.</p>
    </section>
    <section class="card">
      <h3>Aktivitas belanja</h3>
      <div class="ringkas-baris"><span>Jumlah pesanan</span><b><?= angka($stat['jml']) ?></b></div>
      <div class="ringkas-baris"><span>Total belanja</span><b><?= rp($stat['belanja']) ?></b></div>
      <a class="btn btn-ghost btn-block mt" href="<?= e(url('pembeli/pesanan.php')) ?>">Lihat pesanan saya</a>
    </section>
  </aside>
</div>
<?php page_end();
