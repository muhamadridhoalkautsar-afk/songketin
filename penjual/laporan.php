<?php
/** Dashboard penjual — laporan penjualan & keuangan toko (bisa diunduh sebagai PDF atau dicetak). */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/report_pdf.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];
[$dari, $sampai] = rentang_laporan();
$d = laporan_data($dari, $sampai, $idPj);
$t = $d['tot'];
$tk = row('SELECT * FROM tingkat_penjual WHERE id_tingkat_penjual = ?', [$pj['id_tingkat_penjual'] ?? 'TJ01']) ?: ['nama_tingkat_penjual' => '-', 'persen_biaya_admin' => 0];
$persen = rtrim(rtrim(number_format((float) $tk['persen_biaya_admin'], 2, ',', ''), '0'), ',');

if (get('format') === 'pdf') {
    laporan_pdf(['judul' => 'Laporan Penjualan & Keuangan', 'subjudul' => $pj['nama_penjual'], 'periode' => periode_teks($dari, $sampai), 'tampil_penjual' => false], $d)
        ->kirim('laporan-' . strtolower($idPj) . '_' . $dari . '_' . $sampai . '.pdf', get('lihat') !== '1');
    exit;
}

$kueri = ['dari' => $dari, 'sampai' => $sampai];
$daftar = array_values($d['nota']);
[$halaman, $totalHal, $offset] = paginasi(count($daftar), 12, (int) get('hal', '1'));
$daftar = array_slice($daftar, $offset, 12);

page_start('Laporan', ['menu' => 'laporan', 'judul' => 'Laporan Penjualan & Keuangan']);
?>
<div class="baris rata mb no-print">
  <div><strong><?= e($pj['nama_penjual']) ?></strong> <span class="muted">· <?= e(periode_teks($dari, $sampai)) ?></span></div>
  <div class="baris">
    <a class="btn btn-primary btn-sm" href="?<?= e(http_build_query($kueri + ['format' => 'pdf'])) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('download', 15) ?> Unduh PDF</a>
    <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($kueri + ['format' => 'pdf', 'lihat' => '1'])) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('file', 15) ?> Cetak</a>
  </div>
</div>
<?= periode_pintas_html($dari, $sampai) ?>
<form class="card card-flat filter-kartu no-print" method="get">
  <div class="filter" style="margin-bottom:0">
    <label class="field"><span class="field-label">Dari tanggal</span><input type="date" name="dari" value="<?= e($dari) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
    <label class="field"><span class="field-label">Sampai tanggal</span><input type="date" name="sampai" value="<?= e($sampai) ?>"></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit">Terapkan</button></div>
  </div>
</form>

<div class="stats">
  <div class="stat"><div class="label">Penjualan kotor</div><div class="nilai"><?= rp($t['kotor']) ?></div><div class="sub"><?= angka($t['qty']) ?> barang · <?= angka($t['nota']) ?> pesanan</div></div>
  <div class="stat"><div class="label">Biaya layanan <?= e($persen) ?>%</div><div class="nilai">− <?= rp($t['admin']) ?></div><div class="sub">tingkat <?= e($tk['nama_tingkat_penjual']) ?></div></div>
  <div class="stat"><div class="label">Pendapatan bersih</div><div class="nilai" style="color:var(--hijau)"><?= rp($t['bersih']) ?></div><div class="sub">diterima <?= rp($t['bersih_selesai']) ?> · proses <?= rp($t['bersih_proses']) ?></div></div>
</div>

<section class="card mb">
  <div class="card-head"><h3>Pendapatan bersih per bulan</h3><small>Pendapatan bersih = penjualan kotor − biaya layanan.</small></div>
  <?php if ($t['nota'] === 0): ?>
    <p class="muted">Belum ada penjualan terbayar pada periode ini.</p>
  <?php else: ?>
    <?= grafik_bulanan_html($d['bulan'], 'bersih', 'Pendapatan bersih per bulan, ' . periode_teks($dari, $sampai)) ?>
  <?php endif; ?>
  <div class="mt"><?= tabel_bulanan_html($d['bulan']) ?></div>
</section>

<section class="card mb">
  <div class="card-head"><h3>Laporan penjualan per produk</h3><small><?= angka(count($d['produk'])) ?> produk terjual</small></div>
  <?php if (!$d['produk']): ?><p class="muted">Belum ada produk terjual pada periode ini.</p><?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>#</th><th>Produk</th><th>Kategori</th><th class="angka">Terjual</th><th class="angka">Omzet kotor</th><th class="angka">Bersih</th></tr></thead>
    <tbody>
    <?php foreach ($d['produk'] as $i => $p): ?>
      <tr><td><?= $i + 1 ?></td>
        <td><div class="thumb-baris"><img src="<?= e(foto_produk_url($p['id'], $p['foto'] ?? null)) ?>" alt="" loading="lazy"><span class="nama"><?= e($p['nama']) ?></span></div></td>
        <td><?= e($p['kategori']) ?></td><td class="angka"><?= angka($p['qty']) ?></td><td class="angka"><?= rp($p['omzet']) ?></td><td class="angka tebal"><?= rp($p['bersih']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3">Jumlah</td><td class="angka"><?= angka($t['qty']) ?></td><td class="angka"><?= rp($t['kotor']) ?></td><td class="angka"><?= rp($t['bersih']) ?></td></tr></tfoot>
  </table></div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card-head"><h3>Rincian pesanan</h3><small><?= angka(count($d['nota'])) ?> pesanan terbayar</small></div>
  <?php if (!$daftar): ?><p class="muted">Belum ada pesanan terbayar pada periode ini.</p><?php else: ?>
  <div class="tabel-bungkus"><table class="tabel rapat">
    <thead><tr><th>Tanggal &amp; nota</th><th>Pembeli &amp; barang</th><th>Pengiriman</th><th class="angka">Kotor</th><th class="angka">Biaya</th><th class="angka">Bersih</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $n): ?>
      <tr><td class="nowrap"><?= e(tgl($n['tanggal'])) ?><br><small class="mono"><?= e($n['id']) ?></small></td>
        <td style="min-width:190px"><b><?= e($n['pembeli']) ?></b><br><small><?= e(implode(', ', $n['item'])) ?></small></td><td><?= badge_status_kirim($n['status_kirim']) ?></td>
        <td class="angka"><?= rp($n['kotor']) ?></td>
        <td class="angka"><?= $n['admin'] > 0 ? '− ' . rp($n['admin']) : '–' ?></td><td class="angka tebal"><?= rp($n['bersih']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= paginasi_html($halaman, $totalHal, $kueri) ?>
  <?php endif; ?>
</section>
<?php page_end();
