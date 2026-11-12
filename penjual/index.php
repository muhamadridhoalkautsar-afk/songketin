<?php
/** Dashboard penjual — ringkasan toko: angka utama, grafik pendapatan, pesanan terbaru, stok menipis. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/report.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];

$dari = date('Y-m-d', mktime(0, 0, 0, (int) date('n') - 5, 1, (int) date('Y')));
$d = laporan_data($dari, date('Y-m-d'), $idPj);
$bulanIni = $d['bulan'][date('Y-m')] ?? ['nota' => 0, 'qty' => 0, 'kotor' => 0.0, 'bersih' => 0.0];
$prod = row('SELECT COUNT(*) AS semua, COALESCE(SUM(CASE WHEN stok > 0 THEN 1 ELSE 0 END), 0) AS aktif FROM produk WHERE id_penjual = ?', [$idPj]);
$menipis = rows('SELECT id_produk, nama_produk, stok FROM produk WHERE id_penjual = ? AND stok <= 5 ORDER BY stok, nama_produk LIMIT 6', [$idPj]);
$jumlahMenipis = (int) val('SELECT COUNT(*) FROM produk WHERE id_penjual = ? AND stok <= 5', [$idPj]);
$refundMenunggu = refund_jumlah_menunggu(SP_RF_AJU, $idPj);
$perluKemas = (int) val(
    'SELECT COUNT(*) FROM nota_penjual np JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
      WHERE ' . sql_nota_terhitung('nb') . ' AND np.id_status_pengiriman = ?
        AND EXISTS (SELECT 1 FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual AND p.id_penjual = ?)
        AND EXISTS (SELECT 1 FROM rincian_pesanan r2 WHERE r2.id_nota_penjual = np.id_nota_penjual AND r2.id_status_pesanan = ?)',
    [SK_BELUM, $idPj, SP_DIPROSES]
);
$tk = row('SELECT * FROM tingkat_penjual WHERE id_tingkat_penjual = ?', [$pj['id_tingkat_penjual'] ?? 'TJ01']) ?: ['nama_tingkat_penjual' => '-', 'persen_biaya_admin' => 0, 'minimal_transaksi' => 0];
$omzet = omzet_selesai_penjual($idPj);
$berikut = row('SELECT * FROM tingkat_penjual WHERE minimal_transaksi > ? ORDER BY minimal_transaksi LIMIT 1', [$omzet]);
$persenTingkat = $berikut ? (int) min(100, max(0, round(($omzet - (float) $tk['minimal_transaksi']) / max(1, (float) $berikut['minimal_transaksi'] - (float) $tk['minimal_transaksi']) * 100))) : 100;
$ur = row('SELECT AVG(np.rating) AS rata, COUNT(*) AS jml FROM nota_penjual np
            WHERE np.rating IS NOT NULL AND EXISTS (SELECT 1 FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual AND p.id_penjual = ?)', [$idPj]);
$terbaru = array_slice(array_values($d['nota']), 0, 5);
$terlaris = array_slice($d['produk'], 0, 5);
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');

page_start('Ringkasan', ['menu' => 'ringkasan', 'judul' => 'Ringkasan Toko']);
?>
<p class="muted" style="margin-top:-4px">Halo, <strong><?= e($pj['nama_penjual']) ?></strong>. Berikut kabar toko Anda.</p>

<?php if ($refundMenunggu > 0): ?>
<div class="alert alert-warn" role="alert"><span><b><?= angka($refundMenunggu) ?> pengajuan refund menunggu keputusan Anda.</b> <a href="<?= e(url('penjual/refund.php')) ?>">Periksa sekarang</a></span></div>
<?php endif; ?>
<div class="stats">
  <div class="stat"><div class="label">Pendapatan bersih bulan ini</div><div class="nilai"><?= rp($bulanIni['bersih']) ?></div><div class="sub"><?= angka($bulanIni['qty']) ?> barang · <?= angka($bulanIni['nota']) ?> pesanan</div></div>
  <a class="stat" href="<?= e(url('penjual/pesanan.php?status=kemas')) ?>" style="color:inherit;text-decoration:none"><div class="label">Perlu dikemas</div><div class="nilai"><?= angka($perluKemas) ?></div><div class="sub"><?= $perluKemas ? 'Segera kemas pesanan yang sudah dibayar' : 'Semua pesanan sudah ditangani' ?></div></a>
  <a class="stat" href="<?= e(url('penjual/produk.php')) ?>" style="color:inherit;text-decoration:none"><div class="label">Produk di katalog</div><div class="nilai"><?= angka($prod['aktif']) ?> <small style="font:500 .9rem var(--f-isi);color:var(--tinta-3)">dari <?= angka($prod['semua']) ?></small></div><div class="sub">Kelola produk &amp; stok</div></a>
  <a class="stat" href="<?= e(url('penjual/ulasan.php')) ?>" style="color:inherit;text-decoration:none"><div class="label">Rating toko</div><div class="nilai"><?= (int) $ur['jml'] ? number_format((float) $ur['rata'], 1, ',', '') . ' <small style="font:500 .9rem var(--f-isi);color:var(--tinta-3)">/ 5</small>' : '—' ?></div><div class="sub"><?= (int) $ur['jml'] ? angka($ur['jml']) . ' ulasan pembeli' : 'Belum ada ulasan' ?></div></a>
  <a class="stat" href="<?= e(url('penjual/produk.php?status=menipis')) ?>" style="color:inherit;text-decoration:none"><div class="label">Stok menipis</div><div class="nilai"><?= angka($jumlahMenipis) ?></div><div class="sub"><?= $jumlahMenipis ? 'Produk dengan stok ≤ 5' : 'Stok semua produk aman' ?></div></a>
</div>

<div class="dua-kolom">
  <div>
    <section class="card mb">
      <div class="card-head"><h3>Pendapatan bersih 6 bulan terakhir</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('penjual/laporan.php')) ?>">Laporan lengkap</a></div>
      <?php if ($d['tot']['nota'] === 0): ?>
        <div class="kosong" style="padding:24px 10px"><?= ilustrasi_kosong() ?><p>Belum ada penjualan terbayar. Pastikan produk Anda tampil di katalog.</p></div>
      <?php else: ?>
        <?= grafik_bulanan_html($d['bulan'], 'bersih', 'Pendapatan bersih per bulan, enam bulan terakhir') ?>
      <?php endif; ?>
    </section>

    <section class="card mb">
      <div class="card-head"><h3>Pesanan terbaru</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('penjual/pesanan.php')) ?>">Semua pesanan</a></div>
      <?php if (!$terbaru): ?><p class="muted" style="margin:0">Belum ada pesanan terbayar.</p><?php else: ?>
      <div class="daftar-ringkas">
        <?php foreach ($terbaru as $n): ?>
          <div class="baris-ringkas">
            <div class="isi"><div class="judul"><?= e(implode(', ', $n['item'])) ?></div><small><?= e($n['pembeli']) ?> · <?= e(tgl($n['tanggal'])) ?> · <span class="mono"><?= e($n['id']) ?></span></small></div>
            <div class="kanan"><span class="tebal mono"><?= rp($n['bersih']) ?></span><?= badge_status_kirim($n['status_kirim']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <section class="card mb">
      <h3>Tingkat toko</h3>
      <div class="baris rata"><span class="chip chip-emas"><?= ikon('star', 15) ?> <?= e($tk['nama_tingkat_penjual']) ?></span><span class="tebal">Biaya layanan <?= e($fmt($tk['persen_biaya_admin'])) ?>%</span></div>
      <div class="progres" role="progressbar" aria-valuenow="<?= $persenTingkat ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $persenTingkat ?>%"></i></div>
      <?php if ($berikut): ?>
        <small>Selesaikan penjualan <?= rp((float) $berikut['minimal_transaksi'] - $omzet) ?> lagi (yang sudah diterima pembeli) untuk naik ke <b><?= e($berikut['nama_tingkat_penjual']) ?></b> dengan biaya layanan <?= e($fmt($berikut['persen_biaya_admin'])) ?>%.</small>
      <?php else: ?>
        <small>Anda berada di tingkat tertinggi. Terima kasih sudah menenun bersama Songketin!</small>
      <?php endif; ?>
      <div class="ringkas-baris kecil mt"><span>Penjualan diterima pembeli</span><span><?= rp($omzet) ?></span></div>
    </section>

    <section class="card mb">
      <h3>Produk terlaris</h3>
      <?php if (!$terlaris): ?><p class="muted" style="margin:0">Belum ada produk terjual.</p><?php endif; ?>
      <?php foreach ($terlaris as $p): ?>
        <div class="thumb-baris" style="padding:6px 0"><img src="<?= e(foto_produk_url($p['id'], $p['foto'] ?? null)) ?>" alt="" loading="lazy">
          <div style="min-width:0;flex:1"><div class="nama"><?= e($p['nama']) ?></div><small><?= angka($p['qty']) ?> terjual</small></div></div>
      <?php endforeach; ?>
    </section>

    <?php if ($menipis): ?>
    <section class="card">
      <h3>Stok menipis</h3>
      <?php foreach ($menipis as $m): ?>
        <div class="item-ringkas"><a href="<?= e(url('penjual/produk_form.php?id=' . rawurlencode($m['id_produk']))) ?>"><?= e($m['nama_produk']) ?></a><span><?= (int) $m['stok'] === 0 ? badge('Habis', 'bad') : badge('Sisa ' . (int) $m['stok'], 'warn') ?></span></div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php page_end();
