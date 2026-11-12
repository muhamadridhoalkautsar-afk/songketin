<?php
/**
 * Refund untuk penjual.
 *   (tanpa parameter)  → daftar pengajuan refund pada toko ini (yang menunggu keputusan di atas)
 *   ?npj=NPJ00001      → rincian satu pengajuan: setujui / tolak dengan alasan tertulis
 * Penjual hanya memutuskan tahap pertama. Bila ditolak, pembeli dapat banding dan admin memutuskan (final).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';
require_once __DIR__ . '/../includes/refund.php';

$u = require_role('penjual');
$pj = profil_penjual();
$idPj = $pj['id_penjual'];
$idNpj = get('npj') !== '' ? get('npj') : post('npj');

// ================================================================ DAFTAR
if ($idNpj === '') {
    $daftar = refund_daftar(['penjual' => $idPj]);
    $menunggu = array_values(array_filter($daftar, fn($r) => $r['id_status_pesanan'] === SP_RF_AJU));
    $lain = array_values(array_filter($daftar, fn($r) => $r['id_status_pesanan'] !== SP_RF_AJU));
    page_start('Refund', ['menu' => 'refund', 'judul' => 'Refund']);
    $kartu = function (array $r): string {
        ob_start(); ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_penjual']) ?></span><span class="kecil">Pembeli <?= e($r['nama_pembeli']) ?> · Pesanan <?= e($r['id_nota_pembeli']) ?></span></div>
      <?= refund_badge($r['id_status_pesanan']) ?>
    </div>
    <div class="nota-isi"><b><?= e(refund_jenis_teks($r['jenis'])) ?></b> · <?= e($r['alasan']) ?><br><small>Diajukan <?= e(tgl($r['tanggal_ajuan'], true)) ?> · nominal <?= rp($r['jumlah']) ?></small></div>
    <div class="nota-bawah"><span></span>
      <a class="btn <?= $r['id_status_pesanan'] === SP_RF_AJU ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="<?= e(url('penjual/refund.php?npj=' . rawurlencode($r['id_nota_penjual']))) ?>"><?= $r['id_status_pesanan'] === SP_RF_AJU ? 'Tanggapi' : 'Lihat rincian' ?></a></div>
  </article>
<?php   return ob_get_clean();
    };
    ?>
<p class="muted">Pengajuan refund dari pembeli untuk produk toko Anda. Setiap keputusan wajib disertai alasan yang jelas.</p>
<?php if (!$daftar): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Belum ada pengajuan refund</h3><p>Pengajuan dari pembeli akan muncul di sini.</p></div>
<?php else: ?>
  <h3>Menunggu tanggapan Anda <small>(<?= count($menunggu) ?>)</small></h3>
  <?php if (!$menunggu): ?><p class="muted">Tidak ada pengajuan yang menunggu.</p><?php endif; ?>
  <?php foreach ($menunggu as $r) { echo $kartu($r); } ?>
  <?php if ($lain): ?><h3 class="mt">Riwayat</h3><?php foreach ($lain as $r) { echo $kartu($r); } endif; ?>
<?php endif;
    page_end();
    exit;
}

// ================================================================ DETAIL / KEPUTUSAN
$r = refund_ambil($idNpj);
if (!$r || $r['id_penjual'] !== $idPj) {
    abort404(); // pengajuan toko lain dianggap tidak ada
}
$aksiUrl = 'penjual/refund.php?npj=' . rawurlencode($idNpj);

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    if ($aksi !== 'setuju' && $aksi !== 'tolak') {
        flash('error', 'Aksi tidak dikenal.');
    } else {
        [$ok, $pesan] = refund_putus_penjual($idNpj, $idPj, $aksi === 'setuju', post('alasan'));
        flash($ok ? 'ok' : 'error', $pesan);
        if (!$ok) {
            $_SESSION['_in_putusan'] = post('alasan');
        }
    }
    redirect($aksiUrl);
}
$isiAlasan = (string) ($_SESSION['_in_putusan'] ?? '');
unset($_SESSION['_in_putusan']);

$sp = $r['id_status_pesanan'];
$items = rows('SELECT rp.id_produk, rp.qty, rp.sub_harga, p.nama_produk, p.foto_produk FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk
                WHERE rp.id_nota_penjual = ? ORDER BY p.nama_produk', [$idNpj]);

page_start('Refund ' . $idNpj, ['menu' => 'refund', 'judul' => 'Refund ' . $idNpj]);
?>
<div class="krumb"><a href="<?= e(url('penjual/refund.php')) ?>">Refund</a> &rsaquo; <?= e($idNpj) ?></div>
<div class="dua-kolom">
  <div>
    <section class="card mb">
      <div class="card-head"><h3>Pengajuan dari <?= e($r['nama_pembeli']) ?></h3><?= refund_badge($sp) ?></div>
      <dl class="kunci-nilai">
        <dt>Jenis</dt><dd><?= e(refund_jenis_teks($r['jenis'])) ?></dd>
        <dt>Alasan</dt><dd><?= e($r['alasan']) ?><?= $r['keterangan'] !== '' ? '<br><small>' . nl2br(e($r['keterangan'])) . '</small>' : '' ?></dd>
        <dt>Nominal</dt><dd><?= rp($r['jumlah']) ?></dd>
        <dt>Pesanan</dt><dd><?= e($r['id_nota_pembeli']) ?> · <?= e(tgl($r['tanggal_pembelian'], true)) ?></dd>
      </dl>
      <?php if ($sp === SP_RF_SETUJU || $sp === SP_BD_SETUJU): ?>
        <div class="alert alert-info mt" role="status"><span>Refund disetujui<?= $sp === SP_BD_SETUJU ? ' oleh admin (final)' : '' ?>. <?= $r['jenis'] === RJ_BATAL ? 'Pesanan toko ini dibatalkan dan stok dikembalikan.' : 'Penjualan toko ini tidak lagi dihitung.' ?></span></div>
      <?php elseif ($sp === SP_RF_TOLAK): ?>
        <div class="alert alert-warn mt" role="status"><span>Anda menolak refund ini. Pembeli dapat mengajukan banding ke admin.</span></div>
      <?php elseif ($sp === SP_BD_AJU): ?>
        <div class="alert alert-info mt" role="status"><span>Pembeli mengajukan banding. Admin yang akan memutuskan; keputusannya final.</span></div>
      <?php elseif ($sp === SP_BD_TOLAK): ?>
        <div class="alert alert-info mt" role="status"><span>Admin menolak banding pembeli. Keputusan ini final.</span></div>
      <?php endif; ?>
    </section>

    <section class="card mb">
      <div class="card-head"><h3>Riwayat</h3></div>
      <?= refund_riwayat_html($r['riwayat'], $idNpj) ?>
    </section>

    <?php if ($sp === SP_RF_AJU): ?>
      <form method="post" action="<?= e(url($aksiUrl)) ?>" class="card master-form" id="form-putusan">
        <?= csrf_field() ?>
        <h3>Keputusan Anda</h3>
        <label class="field"><span class="field-label">Alasan keputusan <b class="req">*</b></span>
          <textarea name="alasan" rows="4" minlength="5" maxlength="500" required placeholder="Tulis alasan yang jelas. Alasan ini dibaca oleh pembeli."><?= e($isiAlasan) ?></textarea>
          <small class="hint">Wajib diisi untuk menyetujui maupun menolak.</small></label>
        <div class="baris rata mt">
          <button class="btn btn-danger" type="submit" name="aksi" value="tolak" data-konfirmasi="Tolak refund ini? Pembeli dapat mengajukan banding ke admin.">Tolak refund</button>
          <button class="btn btn-primary" type="submit" name="aksi" value="setuju" data-konfirmasi="Setujui refund ini? <?= $r['jenis'] === RJ_BATAL ? 'Pesanan toko ini akan dibatalkan dan stok dikembalikan.' : 'Penjualan toko ini tidak lagi dihitung.' ?>"><?= ikon('check', 16) ?> Setujui refund</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <aside class="card sticky">
    <h3>Barang pada pesanan ini</h3>
    <?php foreach ($items as $it): ?>
      <div class="thumb-baris" style="padding:6px 0"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'])) ?>" alt="" loading="lazy">
        <div style="min-width:0;flex:1"><div class="nama"><?= e($it['nama_produk']) ?></div><small><?= (int) $it['qty'] ?> barang</small></div>
        <span class="tebal mono"><?= rp($it['sub_harga']) ?></span></div>
    <?php endforeach; ?>
    <hr>
    <div class="ringkas-baris total"><span>Pendapatan toko</span><span><?= rp($r['total_harga_jual']) ?></span></div>
  </aside>
</div>
<?php page_end();
