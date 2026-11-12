<?php
/** Detail produk: foto/kain, spesifikasi, harga, dan tombol belanja. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/views.php';

require_role('pembeli');
$id = get('id');
$p = $id === '' ? null : row(
    'SELECT p.*, pj.nama_penjual, pj.id_desa AS desa_penjual, kt.nama_kategori_produk, ad.nama_asal_daerah, pk.nama_pola_kain,
            jb.nama_benang, jb.kualitas_benang, jpr.nama_jenis_produksi, dh.nama_diskon_harga,
            (SELECT COALESCE(SUM(rp.qty), 0) FROM rincian_pesanan rp WHERE rp.id_produk = p.id_produk) AS terjual
       FROM produk p
       JOIN penjual pj ON pj.id_penjual = p.id_penjual
       JOIN kategori_produk kt ON kt.id_kategori_produk = p.id_kategori_produk
       LEFT JOIN asal_daerah ad ON ad.id_asal_daerah = p.id_asal_daerah
       LEFT JOIN pola_kain pk ON pk.id_pola_kain = p.id_pola_kain
       LEFT JOIN jenis_benang jb ON jb.id_jenis_benang = p.id_jenis_benang
       LEFT JOIN jenis_produksi jpr ON jpr.id_jenis_produksi = p.id_jenis_produksi
       LEFT JOIN diskon_harga dh ON dh.id_diskon_harga = p.id_diskon_harga
      WHERE p.id_produk = ?',
    [$id]
);
if (!$p) {
    abort404();
}
$harga = harga_akhir($p);
$pot = (float) $p['besaran_potongan_harga'];
$stok = (int) $p['stok'];
$lokasi = kota_dari_desa($p['desa_penjual']);
$serupa = rows(
    "SELECT p.*, pj.nama_penjual, kt.nama_kategori_produk, ad.nama_asal_daerah, " . sql_rating_produk('p') . "
       FROM produk p JOIN penjual pj ON pj.id_penjual = p.id_penjual
       JOIN kategori_produk kt ON kt.id_kategori_produk = p.id_kategori_produk
       LEFT JOIN asal_daerah ad ON ad.id_asal_daerah = p.id_asal_daerah
      WHERE p.stok > 0 AND p.id_produk <> ? AND (p.id_kategori_produk = ? OR p.id_penjual = ?)
      ORDER BY p.id_produk DESC LIMIT 4",
    [$p['id_produk'], $p['id_kategori_produk'], $p['id_penjual']]
);
// Ulasan: rating/komentar/foto disimpan pada nota_penjual; ulasan sebuah produk = ulasan nota toko yang memuat produk ini.
$statUlasan = row("SELECT AVG(npr.rating) AS rata, COUNT(DISTINCT npr.id_nota_penjual) AS jml
                     FROM nota_penjual npr JOIN rincian_pesanan rpr ON rpr.id_nota_penjual = npr.id_nota_penjual
                    WHERE rpr.id_produk = ? AND npr.rating IS NOT NULL", [$p['id_produk']]);
$jumlahUlasan = (int) ($statUlasan['jml'] ?? 0);
$rataRating = $jumlahUlasan ? (float) $statUlasan['rata'] : 0.0;
$ulasan = rows("SELECT DISTINCT np.id_nota_penjual, np.rating, np.komentar, np.foto_ulasan, np.tanggal_sampai, pb.nama_pembeli
   FROM rincian_pesanan rp JOIN nota_penjual np ON np.id_nota_penjual = rp.id_nota_penjual
   JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
   WHERE rp.id_produk = ? AND np.rating IS NOT NULL ORDER BY np.tanggal_sampai DESC, np.id_nota_penjual DESC LIMIT 20", [$p['id_produk']]);
$dikeranjang = (int) ($_SESSION['cart'][$p['id_produk']] ?? 0);

page_start($p['nama_produk'], ['menu' => 'katalog']);
?>
<div class="krumb"><a href="<?= e(url('pembeli/index.php')) ?>">Katalog</a> &rsaquo; <a href="<?= e(url('pembeli/index.php?kategori=' . rawurlencode($p['id_kategori_produk']))) ?>"><?= e($p['nama_kategori_produk']) ?></a> &rsaquo; <?= e($p['nama_produk']) ?></div>
<div class="detail">
  <div class="detail-gambar"><img src="<?= e(foto_produk_url($p['id_produk'], $p['foto_produk'])) ?>" alt="<?= e($p['nama_produk']) ?>"></div>
  <div>
    <span class="produk-daerah"><?= e($p['nama_asal_daerah'] ?: $p['nama_kategori_produk']) ?></span>
    <h1 style="margin:4px 0 6px"><?= e($p['nama_produk']) ?></h1>
    <a class="rating-ringkas" href="#ulasan" style="text-decoration:none"><?= $jumlahUlasan ? '<span class="bintang" aria-hidden="true">★</span> <b>' . number_format($rataRating, 1, ',', '') . '</b> <small>(' . $jumlahUlasan . ' ulasan)</small>' : '<small class="muted">Belum ada ulasan</small>' ?></a>
    <p class="muted" style="margin-bottom:14px">Dijual oleh <strong><?= e($p['nama_penjual']) ?></strong><?= $lokasi ? ' · ' . e($lokasi) : '' ?><?= (int) $p['terjual'] > 0 ? ' · ' . angka($p['terjual']) . ' terjual' : '' ?></p>
    <div class="harga"><strong><?= rp($harga) ?></strong><?php if ($pot > 0): ?><s><?= rp($p['harga_produk']) ?></s><span class="badge badge-bad">Hemat <?= persen_diskon((float) $p['harga_produk'], $pot) ?>%</span><?php endif; ?></div>

    <div class="spek">
      <div><small>Kategori</small><b><?= e($p['nama_kategori_produk']) ?></b></div>
      <div><small>Motif</small><b><?= e($p['nama_pola_kain'] ?: '—') ?></b></div>
      <div><small>Benang</small><b><?= e($p['nama_benang'] ?: '—') ?><?= $p['kualitas_benang'] ? ' · ' . e($p['kualitas_benang']) : '' ?></b></div>
      <div><small>Warna</small><b><?= e($p['warna'] ?: '—') ?></b></div>
      <div><small>Cara produksi</small><b><?= e($p['nama_jenis_produksi'] ?: '—') ?></b></div>
      <div><small>Stok</small><b><?= $stok > 0 ? angka($stok) . ' tersedia' : 'Habis' ?></b></div>
    </div>

    <?php if ($p['deskripsi']): ?><p style="white-space:pre-line"><?= e($p['deskripsi']) ?></p><?php endif; ?>

    <?php if ($stok > 0): ?>
    <form method="post" action="<?= e(url('pembeli/keranjang.php')) ?>" data-ajax-keranjang class="baris" style="margin-top:18px">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($p['id_produk']) ?>">
      <div class="qty" aria-label="Jumlah"><button type="button" data-qty="-1" aria-label="Kurangi">−</button><input type="number" name="qty" value="1" min="1" max="<?= $stok ?>" aria-label="Jumlah"><button type="button" data-qty="1" aria-label="Tambah">+</button></div>
      <button class="btn btn-primary" type="submit" name="aksi" value="tambah"><?= ikon('cart', 18) ?> Masukkan keranjang</button>
      <button class="btn btn-gold" type="submit" name="aksi" value="beli" data-tanpa-ajax>Beli sekarang</button>
    </form>
    <?php if ($dikeranjang): ?><p class="kecil mt">Sudah ada <?= $dikeranjang ?> di keranjang Anda. <a href="<?= e(url('pembeli/keranjang.php')) ?>">Lihat keranjang</a></p><?php endif; ?>
    <?php else: ?>
      <div class="alert alert-warn mt"><span>Stok produk ini sedang habis. Coba lihat kain serupa di bawah.</span></div>
    <?php endif; ?>
  </div>
</div>

<section class="card mt" style="margin-top:34px" id="ulasan">
  <div class="card-head"><h3>Ulasan pembeli</h3><small><?= $jumlahUlasan ? bintang_html($rataRating) . ' ' . number_format($rataRating, 1, ',', '') . ' / 5 · ' . $jumlahUlasan . ' ulasan' : 'Belum ada ulasan' ?></small></div>
  <?php if (!$ulasan): ?><p class="muted">Belum ada pembeli yang menulis ulasan untuk produk ini.</p><?php endif; ?>
  <?php foreach ($ulasan as $ul) { echo ulasan_item_html($ul); } ?>
  <?php if ($jumlahUlasan > count($ulasan)): ?><p class="muted" style="margin:10px 0 0"><small>Menampilkan <?= count($ulasan) ?> ulasan terbaru dari <?= $jumlahUlasan ?>.</small></p><?php endif; ?>
</section>

<?php if ($serupa): ?>
<section class="mt" style="margin-top:44px">
  <h2>Kain yang mungkin Anda suka</h2>
  <div class="grid-produk"><?php foreach ($serupa as $s) { echo kartu_produk($s); } ?></div>
</section>
<?php endif; ?>
<?php page_end();
