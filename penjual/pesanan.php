<?php
/**
 * Dashboard penjual — pesanan masuk untuk toko ini.
 * Penjual menandai “sudah dikemas”; status pengiriman selanjutnya diurus admin/kurir (dashboard admin).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';
require_once __DIR__ . '/../includes/refund.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];

/** [label, jenis badge, kunci tab] satu nota toko dari sudut pandang penjual. */
function status_pesanan_penjual(array $r): array
{
    $bayar = $r['label_bayar'];   // label turunan (sql_status_bayar)
    $sk = $r['id_status_pengiriman'];
    if ($bayar === BAYAR_BATAL || $sk === SK_BATAL) {
        return ['Dibatalkan', 'bad', 'batal'];
    }
    if (in_array($bayar, [BAYAR_BELUM, BAYAR_DITOLAK, BAYAR_VERIF], true)) {
        return [$bayar === BAYAR_VERIF ? 'Menunggu Verifikasi' : ($bayar === BAYAR_DITOLAK ? 'Pembayaran Ditolak' : 'Belum Dibayar'), 'warn', 'bayar'];
    }
    if ($sk === SK_DITERIMA) {
        return ['Selesai', 'ok', 'selesai'];
    }
    if (in_array($sk, [SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR], true)) {
        return ['Dalam Pengiriman', 'info', 'kirim'];
    }
    return $r['id_status_pesanan'] === SP_DIKEMAS ? ['Siap Dikirim', 'info', 'siap'] : ['Perlu Dikemas', 'warn', 'kemas'];
}

function milik_penjual(string $idNpj, string $idPj): bool
{
    return (bool) val('SELECT 1 FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = ? AND p.id_penjual = ?', [$idNpj, $idPj]);
}

if (is_post()) {
    csrf_check();
    $np = nota_penjual_row(post('id'));
    if (!$np || !milik_penjual($np['id_nota_penjual'], $idPj)) {
        flash('error', 'Pesanan tidak ditemukan.');
    } elseif (post('aksi') === 'kemas') {
        if (refund_menahan_kirim($np['id_nota_penjual'])) {
            flash('warn', 'Ada pengajuan refund pada pesanan ini. Tanggapi refund-nya dulu.');
        } elseif ($np['id_status_pengiriman'] !== SK_BELUM) {
            flash('warn', 'Pesanan ini sudah masuk tahap pengiriman.');
        } else {
            [$ok, $pesan] = set_status_pesanan($np['id_nota_penjual'], SP_DIKEMAS);
            flash($ok ? 'ok' : 'error', $ok ? 'Pesanan ' . $np['id_nota_penjual'] . ' ditandai sudah dikemas. Admin akan mengatur pengirimannya.' : $pesan);
        }
    }
    redirect('penjual/pesanan.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$semua = rows(
    'SELECT np.id_nota_penjual, np.id_nota_pembeli, np.id_status_pengiriman, np.total_harga_jual, np.total_barang_jual, np.tanggal_sampai,
            nb.tanggal_pembelian, ' . sql_status_bayar('nb') . ' AS label_bayar, pb.nama_pembeli, pb.detail_alamat, pb.id_desa,
            pg.kota_tujuan, op.nama_opsi_pengiriman,
            (SELECT MIN(r2.id_status_pesanan) FROM rincian_pesanan r2 WHERE r2.id_nota_penjual = np.id_nota_penjual) AS id_status_pesanan
       FROM nota_penjual np
       JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
       JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
       LEFT JOIN pengiriman pg ON pg.id_pengiriman = np.id_pengiriman
       LEFT JOIN opsi_pengiriman op ON op.id_opsi_pengiriman = pg.id_opsi_pengiriman
      WHERE EXISTS (SELECT 1 FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual AND p.id_penjual = ?)
      ORDER BY nb.tanggal_pembelian DESC, np.id_nota_penjual DESC',
    [$idPj]
);
$tabs = ['' => 'Semua', 'kemas' => 'Perlu dikemas', 'siap' => 'Siap dikirim', 'kirim' => 'Dalam pengiriman', 'selesai' => 'Selesai', 'bayar' => 'Menunggu bayar', 'batal' => 'Dibatalkan', 'refund' => 'Refund'];
$tab = isset($tabs[get('status')]) ? get('status') : '';
$hitung = array_fill_keys(array_keys($tabs), 0);
$tampil = [];
foreach ($semua as $r) {
    $s = status_pesanan_penjual($r);
    $r['_label'] = $s[0];
    $r['_jenis'] = $s[1];
    $r['_refund'] = in_array($r['id_status_pesanan'], SP_REFUND_SEMUA, true) ? $r['id_status_pesanan'] : null;
    $hitung['']++;
    $hitung[$s[2]]++;
    if ($r['_refund']) {
        $hitung['refund']++;
    }
    if ($tab === '' || $tab === $s[2] || ($tab === 'refund' && $r['_refund'])) {
        $tampil[] = $r;
    }
}
[$halaman, $totalHal, $offset] = paginasi(count($tampil), 8, (int) get('hal', '1'));
$tampil = array_slice($tampil, $offset, 8);

page_start('Pesanan Masuk', ['menu' => 'pesanan', 'judul' => 'Pesanan Masuk']);
?>
<nav class="tab-status" aria-label="Filter status">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="chip-link <?= $tab === $k ? 'aktif' : '' ?>" href="?<?= e($k === '' ? '' : http_build_query(['status' => $k])) ?>"><?= e($label) ?> <small><?= (int) $hitung[$k] ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if (!$tampil): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3><?= $hitung[''] ? 'Tidak ada pesanan di tab ini' : 'Belum ada pesanan masuk' ?></h3>
    <p><?= $hitung[''] ? 'Coba pilih tab lain.' : 'Pesanan dari pembeli akan muncul di sini.' ?></p></div>
<?php else: foreach ($tampil as $r):
    $item = rows('SELECT rp.id_produk, rp.qty, rp.sub_harga, p.nama_produk, p.foto_produk FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk
                   WHERE rp.id_nota_penjual = ? AND p.id_penjual = ? ORDER BY p.nama_produk', [$r['id_nota_penjual'], $idPj]); ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_penjual']) ?></span><span class="kecil"><?= e(tgl($r['tanggal_pembelian'], true)) ?></span><span class="kecil">Nota pembeli <?= e($r['id_nota_pembeli']) ?></span></div>
      <div class="baris"><?= badge($r['_label'], $r['_jenis']) ?><?= $r['_refund'] ? refund_badge($r['_refund']) : '' ?></div>
    </div>
    <div class="nota-isi">
      <?php foreach ($item as $it): ?>
        <div class="thumb-baris"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'])) ?>" alt="" loading="lazy">
          <div style="min-width:0;flex:1"><div class="nama"><?= e($it['nama_produk']) ?></div><small><?= (int) $it['qty'] ?> barang</small></div>
          <span class="tebal mono"><?= rp($it['sub_harga']) ?></span></div>
      <?php endforeach; ?>
      <dl class="kunci-nilai" style="margin-top:6px">
        <dt>Pembeli</dt><dd><?= e($r['nama_pembeli']) ?></dd>
        <dt>Dikirim ke</dt><dd><?= e(alamat_teks($r['detail_alamat'], $r['id_desa'])) ?></dd>
        <dt>Layanan kirim</dt><dd><?= e($r['nama_opsi_pengiriman'] ?? '—') ?></dd>
      </dl>
    </div>
    <div class="nota-bawah">
      <div><small>Pendapatan toko</small><div class="tebal mono" style="font-size:1.1rem"><?= rp($r['total_harga_jual']) ?></div></div>
      <?php if ($r['_label'] === 'Perlu Dikemas' && in_array($r['_refund'], SP_REFUND_PROSES, true)): ?>
        <small>Selesaikan refund lebih dulu sebelum mengemas pesanan ini.</small>
      <?php elseif ($r['_label'] === 'Perlu Dikemas'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="kemas"><input type="hidden" name="id" value="<?= e($r['id_nota_penjual']) ?>">
          <button class="btn btn-primary" type="submit"><?= ikon('box', 16) ?> Tandai sudah dikemas</button></form>
      <?php elseif ($r['_label'] === 'Siap Dikirim'): ?>
        <small>Menunggu admin menyerahkan paket ke kurir.</small>
      <?php elseif (in_array($r['_label'], ['Belum Dibayar', 'Menunggu Verifikasi', 'Pembayaran Ditolak'], true)): ?>
        <small>Pesanan baru bisa dikemas setelah pembayaran terverifikasi.</small>
      <?php endif; ?>
      <?php if ($r['_refund'] === SP_RF_AJU): ?>
        <a class="btn btn-primary" href="<?= e(url('penjual/refund.php?npj=' . rawurlencode($r['id_nota_penjual']))) ?>">Tanggapi refund</a>
      <?php elseif ($r['_refund'] === SP_BD_AJU): ?>
        <small>Pembeli mengajukan banding; admin yang memutuskan.</small>
      <?php elseif ($r['_refund']): ?>
        <a class="btn btn-ghost" href="<?= e(url('penjual/refund.php?npj=' . rawurlencode($r['id_nota_penjual']))) ?>">Lihat riwayat refund</a>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
  <?= paginasi_html($halaman, $totalHal, array_filter(['status' => $tab], fn($v) => $v !== '')) ?>
<?php endif; ?>
<?php page_end();
