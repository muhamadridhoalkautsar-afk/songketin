<?php
/** Dashboard penjual — produk yang dijual: cari, ubah stok/status cepat, hapus. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/foto.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];

// ---------------------------------------------------------------- aksi cepat
if (is_post()) {
    csrf_check();
    $p = row('SELECT id_produk, nama_produk FROM produk WHERE id_produk = ? AND id_penjual = ?', [post('id'), $idPj]);
    if (!$p) {
        flash('error', 'Produk tidak ditemukan.');
    } else {
        $aksi = post('aksi');
        if ($aksi === 'stok') {
            $stok = max(0, min(100000, (int) post('stok')));
            q('UPDATE produk SET stok = ? WHERE id_produk = ?', [$stok, $p['id_produk']]);
            flash('ok', 'Stok “' . $p['nama_produk'] . '” menjadi ' . angka($stok) . '.');
        } elseif ($aksi === 'hapus') {
            if (val('SELECT 1 FROM rincian_pesanan WHERE id_produk = ?', [$p['id_produk']])) {
                q('UPDATE produk SET stok = 0 WHERE id_produk = ?', [$p['id_produk']]);
                flash('warn', '“' . $p['nama_produk'] . '” pernah dipesan sehingga tidak bisa dihapus permanen (riwayat pesanan harus tetap utuh). Stoknya dijadikan 0 sehingga tidak bisa dibeli lagi.');
            } else {
                $fotoLama = (string) val('SELECT foto_produk FROM produk WHERE id_produk = ?', [$p['id_produk']]);
                q('DELETE FROM produk WHERE id_produk = ?', [$p['id_produk']]);
                foto_hapus($fotoLama);
                flash('ok', '“' . $p['nama_produk'] . '” dihapus.');
            }
        }
    }
    redirect('penjual/produk.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ---------------------------------------------------------------- daftar
$q = trim(get('q'));
$st = in_array(get('status'), ['tersedia', 'habis', 'menipis'], true) ? get('status') : '';
$kat = get('kategori');
$halaman = max(1, (int) get('hal', '1'));

$where = ['p.id_penjual = ?'];
$par = [$idPj];
if ($q !== '') {
    $where[] = "LOWER(p.nama_produk) LIKE ? ESCAPE '!'";
    $par[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)) . '%';
}
if ($st === 'habis') {
    $where[] = 'p.stok <= 0';
} elseif ($st === 'menipis') {
    $where[] = 'p.stok <= 5';
} elseif ($st === 'tersedia') {
    $where[] = 'p.stok > 0';
}
if ($kat !== '') {
    $where[] = 'p.id_kategori_produk = ?';
    $par[] = $kat;
}
$dari = ' FROM produk p JOIN kategori_produk kt ON kt.id_kategori_produk = p.id_kategori_produk
          LEFT JOIN asal_daerah ad ON ad.id_asal_daerah = p.id_asal_daerah WHERE ' . implode(' AND ', $where);
$total = (int) val('SELECT COUNT(*)' . $dari, $par);
[$halaman, $totalHal, $offset] = paginasi($total, 10, $halaman);
$produk = rows(
    'SELECT p.*, kt.nama_kategori_produk, ad.nama_asal_daerah,
            (SELECT COALESCE(SUM(rp.qty), 0) FROM rincian_pesanan rp JOIN nota_penjual np ON np.id_nota_penjual = rp.id_nota_penjual
               JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
              WHERE rp.id_produk = p.id_produk AND ' . sql_nota_terhitung('nb') . ' AND np.id_status_pengiriman <> \'' . SK_BATAL . '\') AS terjual'
    . $dari . ' ORDER BY p.id_produk DESC LIMIT 10 OFFSET ' . $offset,
    $par
);
$kategori = rows('SELECT * FROM kategori_produk ORDER BY nama_kategori_produk');
$ringkas = row('SELECT COUNT(*) AS semua, COALESCE(SUM(CASE WHEN stok > 0 THEN 1 ELSE 0 END), 0) AS aktif,
                       COALESCE(SUM(CASE WHEN stok <= 0 THEN 1 ELSE 0 END), 0) AS habis FROM produk WHERE id_penjual = ?', [$idPj]);
$kueri = array_filter(['q' => $q, 'status' => $st, 'kategori' => $kat], fn($v) => $v !== '');

page_start('Produk Saya', ['menu' => 'produk', 'judul' => 'Produk Saya']);
?>
<div class="baris rata mb">
  <div class="muted"><b><?= angka($ringkas['semua']) ?></b> produk · <b><?= angka($ringkas['aktif']) ?></b> tersedia di katalog · <b><?= angka($ringkas['habis']) ?></b> stok habis</div>
  <a class="btn btn-primary" href="<?= e(url('penjual/produk_form.php')) ?>"><?= ikon('plus', 18) ?> Tambah produk</a>
</div>

<form class="card card-flat filter-kartu" method="get">
  <div class="filter" style="margin-bottom:0">
    <label class="field lebar"><span class="field-label">Cari nama produk</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="mis. songket, selendang"></label>
    <label class="field"><span class="field-label">Kategori</span>
      <select name="kategori" data-auto-submit><option value="">Semua kategori</option>
        <?php foreach ($kategori as $k): ?><option value="<?= e($k['id_kategori_produk']) ?>" <?= $kat === $k['id_kategori_produk'] ? 'selected' : '' ?>><?= e($k['nama_kategori_produk']) ?></option><?php endforeach; ?>
      </select></label>
    <label class="field"><span class="field-label">Status</span>
      <select name="status" data-auto-submit>
        <?php foreach (['' => 'Semua status', 'tersedia' => 'Tersedia (stok ada)', 'menipis' => 'Stok menipis (≤ 5)', 'habis' => 'Stok habis'] as $v => $l): ?><option value="<?= e($v) ?>" <?= $st === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Cari</button>
      <?php if ($kueri): ?><a class="btn btn-ghost" href="produk.php">Atur ulang</a><?php endif; ?></div>
  </div>
</form>

<?php if (!$produk): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?>
    <h3><?= $kueri ? 'Tidak ada produk yang cocok' : 'Belum ada produk' ?></h3>
    <p><?= $kueri ? 'Coba ubah kata kunci atau filternya.' : 'Tambahkan kain pertama Anda agar bisa dilihat pembeli.' ?></p>
    <a class="btn btn-primary btn-lg" href="<?= e(url('penjual/produk_form.php')) ?>">Tambah produk</a>
  </div>
<?php else: ?>
<div class="tabel-bungkus">
<table class="tabel">
  <thead><tr><th>Produk</th><th class="angka">Harga</th><th class="angka">Stok</th><th class="angka">Terjual</th><th>Status</th><th class="angka">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($produk as $p): $akhir = harga_akhir($p); $habis = (int) $p['stok'] <= 0; ?>
    <tr>
      <td>
        <div class="thumb-baris">
          <img src="<?= e(foto_produk_url($p['id_produk'], $p['foto_produk'])) ?>" alt="" loading="lazy">
          <div style="min-width:0"><a class="tebal" href="<?= e(url('penjual/produk_form.php?id=' . rawurlencode($p['id_produk']))) ?>"><?= e($p['nama_produk']) ?></a>
            <small><?= e($p['id_produk']) ?> · <?= e($p['nama_kategori_produk']) ?><?= $p['nama_asal_daerah'] ? ' · ' . e($p['nama_asal_daerah']) : '' ?></small></div>
        </div>
      </td>
      <td class="angka"><strong><?= rp($akhir) ?></strong><?php if ((float) $p['besaran_potongan_harga'] > 0): ?><br><small><s><?= rp($p['harga_produk']) ?></s> · -<?= persen_diskon((float) $p['harga_produk'], (float) $p['besaran_potongan_harga']) ?>%</small><?php endif; ?></td>
      <td class="angka">
        <form method="post" class="baris" style="justify-content:flex-end;gap:6px;flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="aksi" value="stok"><input type="hidden" name="id" value="<?= e($p['id_produk']) ?>">
          <input type="number" name="stok" value="<?= (int) $p['stok'] ?>" min="0" max="100000" style="width:84px;padding:7px 9px" aria-label="Stok <?= e($p['nama_produk']) ?>">
          <button class="btn btn-ghost btn-sm" type="submit" title="Simpan stok"><?= ikon('check', 14) ?></button>
        </form>
      </td>
      <td class="angka"><?= angka($p['terjual']) ?></td>
      <td><?= $habis ? badge('Habis', 'bad') : badge('Tersedia', 'ok') ?></td>
      <td class="angka aksi-sel">
        <div class="aksi-baris">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('penjual/produk_form.php?id=' . rawurlencode($p['id_produk']))) ?>"><?= ikon('edit', 14) ?> Ubah</a>
          <form method="post" data-konfirmasi="Hapus “<?= e($p['nama_produk']) ?>”? Produk yang pernah dipesan tidak dihapus, hanya stoknya dijadikan 0."><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= e($p['id_produk']) ?>">
            <button class="btn btn-danger btn-sm" type="submit" title="Hapus produk" aria-label="Hapus produk"><?= ikon('trash', 14) ?></button></form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
