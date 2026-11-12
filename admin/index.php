<?php
/** Dashboard admin — ringkasan platform: pengguna, hal yang perlu ditindak, penjualan 6 bulan, pesanan terbaru. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/report.php';
require_once __DIR__ . '/../includes/refund.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('admin');

$dari = date('Y-m-d', mktime(0, 0, 0, (int) date('n') - 5, 1, (int) date('Y')));
$d = laporan_data($dari, date('Y-m-d'));
$bulanIni = $d['bulan'][date('Y-m')] ?? ['nota' => 0, 'qty' => 0, 'kotor' => 0.0, 'admin' => 0.0, 'bersih' => 0.0];

$akun = row("SELECT COUNT(*) AS semua,
                    COALESCE(SUM(CASE WHEN role = 'pembeli' THEN 1 ELSE 0 END), 0) AS pembeli,
                    COALESCE(SUM(CASE WHEN role = 'penjual' THEN 1 ELSE 0 END), 0) AS penjual,
                    COALESCE(SUM(CASE WHEN role IN ('admin', 'super_admin') THEN 1 ELSE 0 END), 0) AS admin FROM users");
$produkAktif = (int) val('SELECT COUNT(*) FROM produk WHERE stok > 0');
// pembayaran non-tunai yang sudah dikirim buktinya dan menunggu pengecekan admin
$kondisiVerif = "nb.status_pembayaran = '" . DB_MENUNGGU . "' AND NOT " . sql_cod('nb') . ' AND NOT ' . sql_dibatalkan('nb');
$perluVerifikasi = (int) val("SELECT COUNT(*) FROM nota_pembeli nb WHERE $kondisiVerif");
$bandingMenunggu = refund_jumlah_menunggu(SP_BD_AJU);
$refundJalan = refund_jumlah_menunggu(SP_RF_AJU);
$sqlKirim = 'SELECT COUNT(*) FROM nota_penjual np JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
              WHERE ' . sql_nota_terhitung('nb') . ' AND np.id_status_pengiriman ';
$perluKirim = (int) val($sqlKirim . '= ?', [SK_BELUM]);
$dalamKirim = (int) val($sqlKirim . 'IN (?, ?, ?, ?)', [SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR]);
$menunggu = rows(
    "SELECT nb.id_nota_pembeli, nb.tanggal_pembelian, nb.total_harga_beli, pb.nama_pembeli, nb.id_jenis_pembayaran, jp.nama_jenis_pembayaran
       FROM nota_pembeli nb JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli LEFT JOIN jenis_pembayaran jp ON jp.id_jenis_pembayaran = nb.id_jenis_pembayaran
      WHERE $kondisiVerif ORDER BY nb.tanggal_pembelian, nb.id_nota_pembeli LIMIT 5"
);
$terbaru = rows(
    'SELECT nb.id_nota_pembeli, nb.tanggal_pembelian, nb.total_harga_beli, ' . sql_status_bayar('nb') . ' AS status_pembayaran, pb.nama_pembeli
       FROM nota_pembeli nb JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
      ORDER BY nb.tanggal_pembelian DESC, nb.id_nota_pembeli DESC LIMIT 6'
);
$toko = array_slice($d['penjual'], 0, 5);
$terlaris = array_slice($d['produk'], 0, 5);

page_start('Ringkasan', ['menu' => 'ringkasan', 'judul' => 'Ringkasan Platform']);
?>
<p class="muted" style="margin-top:-4px">Halo, <strong><?= e($u['nama']) ?></strong>. Berikut kondisi Songketin hari ini.</p>

<div class="stats">
  <a class="stat" href="<?= e(url('admin/pesanan.php?status=verif')) ?>" style="color:inherit;text-decoration:none"><div class="label">Perlu diverifikasi</div><div class="nilai"><?= angka($perluVerifikasi) ?></div><div class="sub"><?= $perluVerifikasi ? 'Pembayaran menunggu pengecekan admin' : 'Tidak ada pembayaran yang menunggu' ?></div></a>
  <a class="stat" href="<?= e(url('admin/pengiriman.php?status=SK01')) ?>" style="color:inherit;text-decoration:none"><div class="label">Siap diserahkan ke kurir</div><div class="nilai"><?= angka($perluKirim) ?></div><div class="sub"><?= angka($dalamKirim) ?> paket sedang dalam perjalanan</div></a>
  <a class="stat" href="<?= e(url('admin/refund.php')) ?>" style="color:inherit;text-decoration:none"><div class="label">Banding refund</div><div class="nilai"><?= angka($bandingMenunggu) ?></div><div class="sub"><?= $bandingMenunggu ? 'Menunggu keputusan admin' : 'Tidak ada banding yang menunggu' ?><?= $refundJalan ? ' · ' . angka($refundJalan) . ' refund menunggu penjual' : '' ?></div></a>
  <div class="stat"><div class="label">Penjualan bulan ini</div><div class="nilai"><?= rp($bulanIni['kotor']) ?></div><div class="sub"><?= angka($bulanIni['qty']) ?> barang · <?= angka($bulanIni['nota']) ?> pesanan</div></div>
  <div class="stat"><div class="label">Pendapatan platform bulan ini</div><div class="nilai" style="color:var(--hijau)"><?= rp($bulanIni['admin']) ?></div><div class="sub">biaya layanan dari penjual</div></div>
</div>

<div class="dua-kolom">
  <div>
    <section class="card mb">
      <div class="card-head"><h3>Penjualan kotor 6 bulan terakhir</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/laporan.php')) ?>">Laporan barang terjual</a></div>
      <?php if ($d['tot']['nota'] === 0): ?>
        <div class="kosong" style="padding:24px 10px"><?= ilustrasi_kosong() ?><p>Belum ada penjualan terbayar pada enam bulan terakhir.</p></div>
      <?php else: ?>
        <?= grafik_bulanan_html($d['bulan'], 'kotor', 'Penjualan kotor per bulan, enam bulan terakhir') ?>
      <?php endif; ?>
    </section>

    <section class="card mb">
      <div class="card-head"><h3>Pesanan terbaru</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/pesanan.php')) ?>">Semua pesanan</a></div>
      <?php if (!$terbaru): ?><p class="muted" style="margin:0">Belum ada pesanan.</p><?php else: ?>
      <div class="daftar-ringkas">
        <?php foreach ($terbaru as $n): ?>
          <div class="baris-ringkas">
            <div class="isi"><div class="judul"><a href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($n['id_nota_pembeli']))) ?>"><?= e($n['id_nota_pembeli']) ?></a> · <?= e($n['nama_pembeli']) ?></div><small><?= e(tgl($n['tanggal_pembelian'], true)) ?></small></div>
            <div class="kanan"><span class="tebal mono"><?= rp($n['total_harga_beli']) ?></span><?= badge($n['status_pembayaran']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <aside>
    <section class="card mb">
      <h3>Perlu tindakan</h3>
      <?php if (!$menunggu): ?>
        <p class="muted" style="margin:0">Semua pembayaran sudah diperiksa. Kerja bagus!</p>
      <?php else: foreach ($menunggu as $m): ?>
        <div class="item-ringkas"><span><a href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($m['id_nota_pembeli']))) ?>"><?= e($m['id_nota_pembeli']) ?></a><br><small><?= e($m['nama_pembeli']) ?> · <?= e((string) $m['nama_jenis_pembayaran']) ?></small></span><span><?= rp($m['total_harga_beli']) ?></span></div>
      <?php endforeach; ?>
        <a class="btn btn-primary btn-sm btn-block mt" href="<?= e(url('admin/pesanan.php?status=verif')) ?>">Periksa pembayaran</a>
      <?php endif; ?>
    </section>

    <section class="card mb">
      <h3>Pengguna</h3>
      <div class="ringkas-baris"><span>Total akun</span><span class="tebal"><?= angka($akun['semua']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Pembeli</span><span><?= angka($akun['pembeli']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Penjual</span><span><?= angka($akun['penjual']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Admin &amp; Super Admin</span><span><?= angka($akun['admin']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Produk di katalog</span><span><?= angka($produkAktif) ?></span></div>
    </section>

    <section class="card mb">
      <h3>Toko terlaris</h3>
      <?php if (!$toko): ?><p class="muted" style="margin:0">Belum ada penjualan.</p><?php endif; ?>
      <?php foreach ($toko as $t): ?>
        <div class="item-ringkas"><span><?= e($t['nama']) ?><br><small><?= angka($t['qty']) ?> barang</small></span><span><?= rp($t['kotor']) ?></span></div>
      <?php endforeach; ?>
    </section>

    <section class="card">
      <h3>Produk terlaris</h3>
      <?php if (!$terlaris): ?><p class="muted" style="margin:0">Belum ada produk terjual.</p><?php endif; ?>
      <?php foreach ($terlaris as $p): ?>
        <div class="thumb-baris" style="padding:6px 0"><img src="<?= e(foto_produk_url($p['id'], $p['foto'] ?? null)) ?>" alt="" loading="lazy">
          <div style="min-width:0;flex:1"><div class="nama"><?= e($p['nama']) ?></div><small><?= angka($p['qty']) ?> terjual · <?= e($p['penjual']) ?></small></div></div>
      <?php endforeach; ?>
    </section>
  </aside>
</div>
<?php page_end();
