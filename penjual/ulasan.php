<?php
/**
 * Ulasan pembeli untuk toko ini. Rating, komentar, dan foto ulasan tersimpan pada nota_penjual:
 * satu ulasan per pesanan toko, sehingga berlaku untuk semua barang toko ini di pesanan tersebut.
 * Penjual hanya membaca ulasan (tidak dapat mengubah/menghapusnya).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];

$milik = 'EXISTS (SELECT 1 FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual AND p.id_penjual = ?)';
$ringkas = row("SELECT AVG(np.rating) AS rata, COUNT(*) AS jml FROM nota_penjual np WHERE np.rating IS NOT NULL AND $milik", [$idPj]);
$sebaran = array_fill(1, 5, 0);
foreach (rows("SELECT ROUND(np.rating) AS b, COUNT(*) AS n FROM nota_penjual np WHERE np.rating IS NOT NULL AND $milik GROUP BY ROUND(np.rating)", [$idPj]) as $s) {
    $sebaran[max(1, min(5, (int) $s['b']))] += (int) $s['n'];
}
$jumlah = (int) $ringkas['jml'];
$filter = (int) get('bintang');
$filter = $filter >= 1 && $filter <= 5 ? $filter : 0;

$kond = "np.rating IS NOT NULL AND $milik";
$par = [$idPj];
if ($filter) {
    $kond .= ' AND ROUND(np.rating) = ?';
    $par[] = $filter;
}
$total = (int) val("SELECT COUNT(*) FROM nota_penjual np WHERE $kond", $par);
[$halaman, $totalHal, $offset] = paginasi($total, 8, (int) get('hal', '1'));
$daftar = rows("SELECT np.id_nota_penjual, np.id_nota_pembeli, np.rating, np.komentar, np.foto_ulasan, np.tanggal_sampai, pb.nama_pembeli
                  FROM nota_penjual np JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
                 WHERE $kond ORDER BY np.tanggal_sampai DESC, np.id_nota_penjual DESC LIMIT 8 OFFSET " . (int) $offset, $par);

page_start('Ulasan', ['menu' => 'ulasan', 'judul' => 'Ulasan Pembeli']);
?>
<p class="muted">Penilaian pembeli setelah pesanan diterima. Satu ulasan berlaku untuk semua barang toko Anda pada pesanan yang sama.</p>

<?php if (!$jumlah): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Belum ada ulasan</h3><p>Ulasan akan muncul di sini setelah pembeli menerima pesanan dan menilainya.</p></div>
<?php else: ?>
<section class="card mb ulasan-ringkas">
  <div class="ulasan-skor"><div class="nilai"><?= number_format((float) $ringkas['rata'], 1, ',', '') ?></div><?= bintang_html((float) $ringkas['rata']) ?><small class="muted"><?= angka($jumlah) ?> ulasan</small></div>
  <div class="ulasan-sebaran">
    <?php for ($b = 5; $b >= 1; $b--): $persen = (int) round($sebaran[$b] / $jumlah * 100); ?>
      <a class="sebaran-baris <?= $filter === $b ? 'aktif' : '' ?>" href="?bintang=<?= $b ?>" title="Tampilkan ulasan <?= $b ?> bintang"><span><?= $b ?> ★</span><span class="batang"><i style="width:<?= $persen ?>%"></i></span><span class="mono"><?= angka($sebaran[$b]) ?></span></a>
    <?php endfor; ?>
  </div>
</section>

<nav class="tab-status" aria-label="Filter rating">
  <a class="chip-link <?= $filter === 0 ? 'aktif' : '' ?>" href="?">Semua <small><?= angka($jumlah) ?></small></a>
  <?php for ($b = 5; $b >= 1; $b--): ?><a class="chip-link <?= $filter === $b ? 'aktif' : '' ?>" href="?bintang=<?= $b ?>"><?= $b ?> ★ <small><?= angka($sebaran[$b]) ?></small></a><?php endfor; ?>
</nav>

<?php if (!$daftar): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Tidak ada ulasan <?= $filter ?> bintang</h3></div>
<?php else: foreach ($daftar as $r):
    $item = rows('SELECT rp.id_produk, rp.qty, p.nama_produk, p.foto_produk FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk
                   WHERE rp.id_nota_penjual = ? AND p.id_penjual = ? ORDER BY p.nama_produk', [$r['id_nota_penjual'], $idPj]); ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_penjual']) ?></span><span class="kecil">Pesanan <?= e($r['id_nota_pembeli']) ?> · tiba <?= e(tgl($r['tanggal_sampai'])) ?></span></div>
      <?= bintang_html((float) $r['rating']) ?>
    </div>
    <div class="nota-isi">
      <?php foreach ($item as $it): ?>
        <div class="thumb-baris"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'])) ?>" alt="" loading="lazy">
          <div style="min-width:0;flex:1"><a class="nama" href="<?= e(url('pembeli/produk.php?id=' . rawurlencode($it['id_produk']))) ?>"><?= e($it['nama_produk']) ?></a><br><small><?= (int) $it['qty'] ?> barang</small></div></div>
      <?php endforeach; ?>
      <?= ulasan_item_html($r + ['tanggal_sampai' => null]) ?>
    </div>
  </article>
<?php endforeach; ?>
  <?= paginasi_html($halaman, $totalHal, array_filter(['bintang' => $filter ?: ''], fn($v) => $v !== '')) ?>
<?php endif; endif; ?>
<?php page_end();
