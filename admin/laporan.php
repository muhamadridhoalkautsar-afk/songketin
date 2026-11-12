<?php
/** Dashboard admin — laporan barang terjual seluruh platform (per produk, toko, kategori, pesanan), bisa diunduh sebagai PDF. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/report_pdf.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('admin');
[$dari, $sampai] = rentang_laporan();

$daftarToko = rows('SELECT id_penjual, nama_penjual FROM penjual ORDER BY nama_penjual');
$daftarKategori = rows('SELECT id_kategori_produk, nama_kategori_produk FROM kategori_produk ORDER BY nama_kategori_produk');
$idToko = get('toko');
$idKat = get('kategori');
$namaToko = null;
$namaKat = null;
foreach ($daftarToko as $t) {
    if ($t['id_penjual'] === $idToko) {
        $namaToko = $t['nama_penjual'];
    }
}
foreach ($daftarKategori as $k) {
    if ($k['id_kategori_produk'] === $idKat) {
        $namaKat = $k['nama_kategori_produk'];
    }
}
if ($namaToko === null) {
    $idToko = '';
}
if ($namaKat === null) {
    $idKat = '';
}
$d = laporan_data($dari, $sampai, $idToko ?: null, $idKat ?: null);
$t = $d['tot'];
$cakupan = ($namaToko ?? 'Semua toko') . ($namaKat ? ' · ' . $namaKat : ' · Semua kategori');

if (get('format') === 'pdf') {
    laporan_pdf(['judul' => 'Laporan Barang Terjual', 'subjudul' => $cakupan, 'periode' => periode_teks($dari, $sampai), 'tampil_penjual' => true, 'semua' => true], $d)
        ->kirim('laporan-barang-terjual_' . $dari . '_' . $sampai . '.pdf', get('lihat') !== '1');
    exit;
}

$kueri = array_filter(['dari' => $dari, 'sampai' => $sampai, 'toko' => $idToko, 'kategori' => $idKat], fn($v) => $v !== '');
$tabs = ['produk' => 'Per produk', 'toko' => 'Per toko', 'kategori' => 'Per kategori', 'pesanan' => 'Per pesanan'];
$tab = isset($tabs[get('bagian')]) ? get('bagian') : 'produk';
$sumber = ['produk' => $d['produk'], 'toko' => $d['penjual'], 'kategori' => $d['kategori'], 'pesanan' => array_values($d['nota'])][$tab];
$perHal = $tab === 'pesanan' ? 12 : 10;
[$halaman, $totalHal, $offset] = paginasi(count($sumber), $perHal, (int) get('hal', '1'));
$baris = array_slice($sumber, $offset, $perHal);

page_start('Barang Terjual', ['menu' => 'laporan', 'judul' => 'Laporan Barang Terjual']);
?>
<div class="baris rata mb">
  <div><strong><?= e($cakupan) ?></strong> <span class="muted">· <?= e(periode_teks($dari, $sampai)) ?></span></div>
  <div class="baris">
    <a class="btn btn-primary btn-sm" href="?<?= e(http_build_query($kueri + ['format' => 'pdf'])) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('download', 15) ?> Unduh PDF</a>
    <a class="btn btn-ghost btn-sm" href="?<?= e(http_build_query($kueri + ['format' => 'pdf', 'lihat' => '1'])) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('file', 15) ?> Cetak</a>
  </div>
</div>
<?= periode_pintas_html($dari, $sampai, array_filter(['toko' => $idToko, 'kategori' => $idKat], fn($v) => $v !== '')) ?>
<form class="card card-flat filter-kartu" method="get">
  <div class="filter" style="margin-bottom:0">
    <label class="field"><span class="field-label">Dari tanggal</span><input type="date" name="dari" value="<?= e($dari) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
    <label class="field"><span class="field-label">Sampai tanggal</span><input type="date" name="sampai" value="<?= e($sampai) ?>"></label>
    <label class="field"><span class="field-label">Toko</span>
      <select name="toko"><option value="">Semua toko</option><?php foreach ($daftarToko as $x): ?><option value="<?= e($x['id_penjual']) ?>" <?= $idToko === $x['id_penjual'] ? 'selected' : '' ?>><?= e($x['nama_penjual']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span class="field-label">Kategori</span>
      <select name="kategori"><option value="">Semua kategori</option><?php foreach ($daftarKategori as $x): ?><option value="<?= e($x['id_kategori_produk']) ?>" <?= $idKat === $x['id_kategori_produk'] ? 'selected' : '' ?>><?= e($x['nama_kategori_produk']) ?></option><?php endforeach; ?></select></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit">Terapkan</button></div>
  </div>
</form>

<div class="stats">
  <div class="stat"><div class="label">Barang terjual</div><div class="nilai"><?= angka($t['qty']) ?></div><div class="sub"><?= angka($t['nota']) ?> pesanan · <?= angka(count($d['produk'])) ?> produk berbeda</div></div>
  <div class="stat"><div class="label">Penjualan kotor</div><div class="nilai"><?= rp($t['kotor']) ?></div><div class="sub">setelah diskon harga produk</div></div>
  <div class="stat"><div class="label">Pendapatan platform</div><div class="nilai" style="color:var(--hijau)"><?= rp($t['admin']) ?></div><div class="sub">biaya layanan dari penjual</div></div>
  <div class="stat"><div class="label">Bersih untuk penjual</div><div class="nilai"><?= rp($t['bersih']) ?></div><div class="sub">diterima <?= rp($t['bersih_selesai']) ?> · proses <?= rp($t['bersih_proses']) ?></div></div>
</div>

<section class="card mb">
  <div class="card-head"><h3>Penjualan kotor per bulan</h3><small>Hanya pesanan berstatus Lunas atau Bayar di Tempat yang dihitung.</small></div>
  <?php if ($t['nota'] === 0): ?>
    <p class="muted">Belum ada penjualan terbayar pada periode dan filter ini.</p>
  <?php else: ?>
    <?= grafik_bulanan_html($d['bulan'], 'kotor', 'Penjualan kotor per bulan, ' . periode_teks($dari, $sampai)) ?>
  <?php endif; ?>
  <div class="mt"><?= tabel_bulanan_html($d['bulan']) ?></div>
</section>

<section class="card">
  <div class="card-head"><h3>Rincian barang terjual</h3><small><?= angka(count($sumber)) ?> baris</small></div>
  <nav class="tab-status no-print" aria-label="Pilih sudut pandang">
    <?php foreach ($tabs as $k => $l): ?><a class="chip-link <?= $tab === $k ? 'aktif' : '' ?>" href="?<?= e(http_build_query($kueri + ['bagian' => $k])) ?>"><?= e($l) ?></a><?php endforeach; ?>
  </nav>
  <?php if (!$baris): ?><p class="muted">Belum ada data pada periode dan filter ini.</p><?php else: ?>
  <div class="tabel-bungkus"><table class="tabel rapat">
  <?php if ($tab === 'produk'): ?>
    <thead><tr><th>#</th><th>Produk</th><th>Toko</th><th>Kategori</th><th class="angka">Terjual</th><th class="angka">Omzet kotor</th><th class="angka">Bersih</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $i => $p): ?>
      <tr><td><?= $offset + $i + 1 ?></td>
        <td><div class="thumb-baris"><img src="<?= e(foto_produk_url($p['id'], $p['foto'] ?? null)) ?>" alt="" loading="lazy"><span class="nama"><?= e($p['nama']) ?></span></div></td>
        <td><?= e($p['penjual']) ?></td><td><?= e($p['kategori']) ?></td><td class="angka"><?= angka($p['qty']) ?></td><td class="angka"><?= rp($p['omzet']) ?></td><td class="angka tebal"><?= rp($p['bersih']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4">Jumlah seluruhnya</td><td class="angka"><?= angka($t['qty']) ?></td><td class="angka"><?= rp($t['kotor']) ?></td><td class="angka"><?= rp($t['bersih']) ?></td></tr></tfoot>
  <?php elseif ($tab === 'toko' || $tab === 'kategori'): ?>
    <thead><tr><th>#</th><th><?= $tab === 'toko' ? 'Toko' : 'Kategori' ?></th><th class="angka">Pesanan</th><th class="angka">Barang</th><th class="angka">Penjualan kotor</th><th class="angka">Biaya layanan</th><th class="angka">Bersih</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $i => $g): ?>
      <tr><td><?= $offset + $i + 1 ?></td><td class="tebal"><?= e($g['nama']) ?></td><td class="angka"><?= angka($g['nota']) ?></td><td class="angka"><?= angka($g['qty']) ?></td>
        <td class="angka"><?= rp($g['kotor']) ?></td><td class="angka"><?= $g['admin'] > 0 ? '− ' . rp($g['admin']) : '–' ?></td><td class="angka tebal"><?= rp($g['bersih']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2">Jumlah seluruhnya</td><td class="angka"><?= angka($t['nota']) ?></td><td class="angka"><?= angka($t['qty']) ?></td><td class="angka"><?= rp($t['kotor']) ?></td><td class="angka">− <?= rp($t['admin']) ?></td><td class="angka"><?= rp($t['bersih']) ?></td></tr></tfoot>
  <?php else: ?>
    <thead><tr><th>Tanggal &amp; nota</th><th>Pembeli, toko &amp; barang</th><th>Pengiriman</th><th class="angka">Kotor</th><th class="angka">Biaya</th><th class="angka">Bersih</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $n): ?>
      <tr><td class="nowrap"><?= e(tgl($n['tanggal'])) ?><br><small class="mono"><a href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($n['id_nota_pembeli']))) ?>"><?= e($n['id']) ?></a></small></td>
        <td style="min-width:200px"><b><?= e($n['pembeli']) ?></b> <small>→ <?= e($n['penjual']) ?></small><br><small><?= e(implode(', ', $n['item'])) ?></small></td><td><?= badge_status_kirim($n['status_kirim']) ?></td>
        <td class="angka"><?= rp($n['kotor']) ?></td>
        <td class="angka"><?= $n['admin'] > 0 ? '− ' . rp($n['admin']) : '–' ?></td><td class="angka tebal"><?= rp($n['bersih']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  <?php endif; ?>
  </table></div>
  <?= paginasi_html($halaman, $totalHal, $kueri + ['bagian' => $tab]) ?>
  <?php endif; ?>
</section>
<?php page_end();
