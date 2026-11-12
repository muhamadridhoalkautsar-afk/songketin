<?php
/** Dashboard penjual — tambah produk baru atau ubah produk (?id=…), termasuk unggah foto. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/foto.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];

$lama = null;
if (get('id') !== '') {
    $lama = row('SELECT * FROM produk WHERE id_produk = ? AND id_penjual = ?', [get('id'), $idPj]);
    if (!$lama) {
        abort404(); // produk toko lain dianggap tidak ada
    }
}
$f = $lama ? [
    'nama' => $lama['nama_produk'], 'kategori' => $lama['id_kategori_produk'], 'daerah' => (string) $lama['id_asal_daerah'], 'pola' => (string) $lama['id_pola_kain'],
    'benang' => (string) $lama['id_jenis_benang'], 'warna' => (string) $lama['warna'], 'produksi' => (string) $lama['id_jenis_produksi'], 'harga' => (string) (int) $lama['harga_produk'],
    'diskon' => (string) $lama['id_diskon_harga'], 'stok' => (string) (int) $lama['stok'], 'deskripsi' => (string) $lama['deskripsi'],
] : [
    'nama' => '', 'kategori' => '', 'daerah' => '', 'pola' => '', 'benang' => '', 'warna' => '', 'produksi' => '',
    'harga' => '', 'diskon' => '', 'stok' => '1', 'deskripsi' => '',
];
$galat = [];

if (is_post()) {
    csrf_check();
    $f = [
        'nama' => trim(post('nama')), 'kategori' => post('kategori'), 'daerah' => post('daerah'), 'pola' => post('pola'), 'benang' => post('benang'),
        'warna' => trim(post('warna')), 'produksi' => post('produksi'), 'harga' => (string) angka_dari(post('harga')), 'diskon' => post('diskon'),
        'stok' => (string) angka_dari(post('stok')), 'deskripsi' => trim(post('deskripsi')),
    ];
    $harga = (int) $f['harga'];
    $dh = $f['diskon'] !== '' ? row('SELECT * FROM diskon_harga WHERE id_diskon_harga = ?', [$f['diskon']]) : null;
    $potongan = $dh ? (int) round((float) $dh['besaran_potongan_harga']) : 0;
    if (mb_strlen($f['nama']) < 3 || mb_strlen($f['nama']) > 150) {
        $galat[] = 'Nama produk harus 3–150 karakter.';
    }
    if (!val('SELECT 1 FROM kategori_produk WHERE id_kategori_produk = ?', [$f['kategori']])) {
        $galat[] = 'Pilih kategori produk.';
    }
    foreach (['daerah' => ['asal_daerah', 'id_asal_daerah', 'asal daerah'], 'pola' => ['pola_kain', 'id_pola_kain', 'motif'], 'benang' => ['jenis_benang', 'id_jenis_benang', 'jenis benang'],
              'produksi' => ['jenis_produksi', 'id_jenis_produksi', 'jenis produksi']] as $k => [$tabel, $kol, $nama]) {
        if ($f[$k] !== '' && !val("SELECT 1 FROM $tabel WHERE $kol = ?", [$f[$k]])) {
            $galat[] = 'Pilihan ' . $nama . ' tidak dikenal.';
        }
    }
    if (!$dh) {
        $galat[] = 'Pilih diskon harga (pilih “Tanpa Diskon” bila produk tidak didiskon).';
    }
    if ($harga < 1000 || $harga > 999999999) {
        $galat[] = 'Harga harus antara Rp1.000 dan Rp999.999.999.';
    } elseif ($harga - $potongan < 500) {
        $galat[] = 'Diskon yang dipilih membuat harga akhir kurang dari Rp500. Pilih diskon yang lebih kecil.';
    }
    if ((int) $f['stok'] > 100000) {
        $galat[] = 'Stok maksimal 100.000.';
    }
    if (mb_strlen($f['warna']) > 50) {
        $galat[] = 'Warna maksimal 50 karakter.';
    }
    if (mb_strlen($f['deskripsi']) > 2000) {
        $galat[] = 'Deskripsi maksimal 2000 karakter.';
    }
    if ($err = foto_cek($_FILES['foto'] ?? null, false, 100)) {
        $galat[] = $err;
    }

    if (!$galat) {
        $id = $lama['id_produk'] ?? null;
        $fotoLama = (string) ($lama['foto_produk'] ?? '');
        $fotoBaru = null;
        try {
            transaksi(function () use (&$id, $lama, $idPj, $f, $harga, $potongan, $fotoLama, &$fotoBaru) {
                $id = $id ?? next_id('produk', 'id_produk', 'PRD');
                $path = $fotoLama;
                if (foto_ada_unggahan($_FILES['foto'] ?? null)) {
                    $fotoBaru = foto_simpan($_FILES['foto'], 'produk', $id);
                    if ($fotoBaru === null) {
                        throw new RuntimeException('Foto gagal disimpan di server. Coba unggah ulang.');
                    }
                    $path = $fotoBaru;
                } elseif (post('hapus_foto') === '1') {
                    $path = '';
                }
                // besaran_potongan_harga menyalin nilai diskon yang dipilih (id_diskon_harga) supaya harga akhir mudah dihitung di semua halaman
                $nilai = [$f['kategori'], $f['daerah'] ?: null, $f['pola'] ?: null, $f['benang'] ?: null, $f['produksi'] ?: null, $f['nama'], $harga, $potongan,
                    (int) $f['stok'], $f['deskripsi'] !== '' ? $f['deskripsi'] : null, $f['warna'] !== '' ? $f['warna'] : null, $path, $f['diskon']];
                if ($lama) {
                    q('UPDATE produk SET id_kategori_produk = ?, id_asal_daerah = ?, id_pola_kain = ?, id_jenis_benang = ?, id_jenis_produksi = ?, nama_produk = ?, harga_produk = ?,
                              besaran_potongan_harga = ?, stok = ?, deskripsi = ?, warna = ?, foto_produk = ?, id_diskon_harga = ? WHERE id_produk = ? AND id_penjual = ?',
                        array_merge($nilai, [$id, $idPj]));
                } else {
                    q('INSERT INTO produk (id_produk, id_penjual, id_kategori_produk, id_asal_daerah, id_pola_kain, id_jenis_benang, id_jenis_produksi, nama_produk, harga_produk,
                              besaran_potongan_harga, stok, deskripsi, warna, foto_produk, id_diskon_harga) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                        array_merge([$id, $idPj], $nilai));
                }
            });
            if ($fotoLama !== '' && ($fotoBaru !== null || post('hapus_foto') === '1')) {
                foto_hapus($fotoLama);   // berkas lama dibersihkan setelah data tersimpan
            }
            flash('ok', $lama ? 'Perubahan “' . $f['nama'] . '” disimpan.' : '“' . $f['nama'] . '” ditambahkan ke daftar produk Anda.');
            redirect('penjual/produk.php');
        } catch (RuntimeException | PDOException $e) {
            foto_hapus($fotoBaru);
            $galat[] = $e instanceof RuntimeException ? $e->getMessage() : 'Produk gagal disimpan karena galat database.';
        }
    }
}

$kategori = rows('SELECT * FROM kategori_produk ORDER BY nama_kategori_produk');
$daerah = rows('SELECT * FROM asal_daerah ORDER BY nama_asal_daerah');
$pola = rows('SELECT * FROM pola_kain ORDER BY nama_pola_kain');
$benang = rows('SELECT * FROM jenis_benang ORDER BY nama_benang');
$produksi = rows('SELECT * FROM jenis_produksi ORDER BY nama_jenis_produksi');
$diskonHarga = rows('SELECT * FROM diskon_harga ORDER BY besaran_potongan_harga, nama_diskon_harga');
$fotoAda = $lama && foto_url($lama['foto_produk']) !== null;
$srcAwal = $fotoAda ? foto_produk_url($lama['id_produk'], $lama['foto_produk'])
    : url('api/swatch.php?pratinjau=1&warna=' . rawurlencode($f['warna']) . '&pola=' . rawurlencode($f['pola']) . '&benang=' . rawurlencode($f['benang']));

function opsi_pilih(array $rs, string $idKol, string $namaKol, string $dipilih, ?string $tambahan = null): string
{
    $o = '';
    foreach ($rs as $r) {
        $o .= '<option value="' . e($r[$idKol]) . '"' . ($r[$idKol] === $dipilih ? ' selected' : '') . '>' . e($r[$namaKol] . ($tambahan && !empty($r[$tambahan]) ? ' · ' . $r[$tambahan] : '')) . '</option>';
    }
    return $o;
}

$judul = $lama ? 'Ubah Produk' : 'Tambah Produk';
page_start($judul, ['menu' => $lama ? 'produk' : 'tambah', 'judul' => $judul]);
?>
<div class="krumb"><a href="<?= e(url('penjual/produk.php')) ?>">Produk Saya</a> &rsaquo; <?= e($judul) ?><?= $lama ? ' · ' . e($lama['id_produk']) : '' ?></div>
<?php if ($galat): ?><div class="alert alert-error" role="alert"><span><b>Belum bisa disimpan:</b> <?= e(implode(' ', $galat)) ?></span></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="dua-kolom" id="form-produk" data-pratinjau="<?= e(url('api/swatch.php')) ?>" data-foto-ada="<?= $fotoAda ? '1' : '0' ?>">
  <?= csrf_field() ?>
  <div>
    <section class="card mb">
      <h3>Informasi produk</h3>
      <label class="field"><span class="field-label">Nama produk <b class="req">*</b></span><input type="text" name="nama" value="<?= e($f['nama']) ?>" required minlength="3" maxlength="150" placeholder="mis. Kain Songket Palembang Lepus Berantai Merah Marun"></label>
      <div class="grid-2">
        <label class="field"><span class="field-label">Kategori <b class="req">*</b></span>
          <select name="kategori" required><option value="">— pilih kategori —</option><?= opsi_pilih($kategori, 'id_kategori_produk', 'nama_kategori_produk', $f['kategori']) ?></select></label>
        <label class="field"><span class="field-label">Asal daerah</span>
          <select name="daerah"><option value="">— pilih daerah —</option><?= opsi_pilih($daerah, 'id_asal_daerah', 'nama_asal_daerah', $f['daerah']) ?></select></label>
      </div>
      <label class="field"><span class="field-label">Deskripsi</span><textarea name="deskripsi" rows="4" maxlength="2000" placeholder="Ceritakan bahan, ukuran, motif, dan kegunaan kain ini."><?= e($f['deskripsi']) ?></textarea><span class="hint">Maksimal 2000 karakter.</span></label>
    </section>

    <section class="card mb">
      <h3>Detail kain</h3>
      <div class="grid-2">
        <label class="field"><span class="field-label">Motif</span>
          <select name="pola"><option value="">— pilih motif —</option><?= opsi_pilih($pola, 'id_pola_kain', 'nama_pola_kain', $f['pola']) ?></select></label>
        <label class="field"><span class="field-label">Jenis benang</span>
          <select name="benang"><option value="">— pilih benang —</option><?= opsi_pilih($benang, 'id_jenis_benang', 'nama_benang', $f['benang'], 'kualitas_benang') ?></select></label>
        <label class="field"><span class="field-label">Warna</span><input type="text" name="warna" value="<?= e($f['warna']) ?>" maxlength="50" placeholder="mis. Merah Marun" autocomplete="off"><span class="hint">Dipakai juga untuk membuat gambar kain otomatis.</span></label>
        <label class="field"><span class="field-label">Jenis produksi</span>
          <select name="produksi"><option value="">— pilih jenis produksi —</option><?= opsi_pilih($produksi, 'id_jenis_produksi', 'nama_jenis_produksi', $f['produksi']) ?></select></label>
      </div>
    </section>

    <section class="card mb">
      <h3>Harga &amp; stok</h3>
      <div class="grid-3">
        <label class="field"><span class="field-label">Harga (Rp) <b class="req">*</b></span><input type="number" name="harga" value="<?= e($f['harga']) ?>" required min="1000" max="999999999" inputmode="numeric" placeholder="485000"></label>
        <label class="field"><span class="field-label">Diskon harga <b class="req">*</b></span>
          <select name="diskon" required>
            <option value="">— pilih diskon —</option>
            <?php foreach ($diskonHarga as $d): ?><option value="<?= e($d['id_diskon_harga']) ?>" data-besaran="<?= (int) round((float) $d['besaran_potongan_harga']) ?>" <?= $f['diskon'] === $d['id_diskon_harga'] ? 'selected' : '' ?>><?= e($d['nama_diskon_harga']) ?> (<?= rp($d['besaran_potongan_harga']) ?>)</option><?php endforeach; ?>
          </select><span class="hint">Daftar diskon diatur admin.</span></label>
        <label class="field"><span class="field-label">Stok <b class="req">*</b></span><input type="number" name="stok" value="<?= e($f['stok']) ?>" required min="0" max="100000" inputmode="numeric"></label>
      </div>
      <div class="alert alert-info" id="hasil-harga" aria-live="polite"><span>Harga jual di katalog: <b id="harga-akhir">—</b></span></div>
      <p class="hint" style="margin:8px 0 0">Produk dengan stok 0 tampil sebagai “Habis” dan tidak bisa dibeli.</p>
    </section>
  </div>

  <aside class="card sticky">
    <h3>Foto produk</h3>
    <div class="detail-gambar" style="aspect-ratio:1/1;box-shadow:none;margin-bottom:12px"><img id="pratinjau" src="<?= e($srcAwal) ?>" alt="Pratinjau foto produk"></div>
    <label class="field"><span class="field-label">Unggah foto (opsional)</span><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"><span class="hint">JPG, PNG, atau WebP, maksimal 2 MB. Tanpa foto, Songketin membuat gambar kain dari motif, benang, dan warna.</span></label>
    <?php if ($fotoAda): ?>
      <label class="opsi" style="margin-bottom:14px"><input type="checkbox" name="hapus_foto" value="1"><span><b>Hapus foto unggahan</b><small>Kembali memakai gambar kain otomatis.</small></span></label>
    <?php endif; ?>
    <button class="btn btn-primary btn-lg btn-block" type="submit"><?= $lama ? 'Simpan perubahan' : 'Tambahkan produk' ?></button>
    <a class="btn btn-ghost btn-block mt" href="<?= e(url('penjual/produk.php')) ?>">Batal</a>
  </aside>
</form>
<?php page_end(['js/produk_form.js']);
