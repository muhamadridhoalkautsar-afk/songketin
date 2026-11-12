<?php
/**
 * Dashboard admin — Banding refund.
 * Alur: pembeli ajukan → penjual setujui/tolak → bila ditolak pembeli boleh banding → ADMIN memutuskan (final).
 * Admin hanya memutuskan pengajuan berstatus "Banding Diajukan"; tahap lain hanya bisa dipantau.
 *   (tanpa parameter)  → daftar
 *   ?npj=NPJ00001      → rincian + keputusan (setujui / tolak, alasan wajib)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';
require_once __DIR__ . '/../includes/refund.php';

$u = require_role('admin');

// ---------------------------------------------------------------- aksi
if (is_post()) {
    csrf_check();
    $idNpj = post('npj');
    $r = refund_ambil($idNpj);
    if (!$r) {
        abort404();
    }
    $aksi = post('aksi');
    if ($aksi === 'setujui' || $aksi === 'tolak') {
        [$ok, $pesan] = refund_putus_admin($idNpj, $aksi === 'setujui', post('alasan'), $u['nama']);
        if (!$ok) {
            $_SESSION['_in_putusan'] = post('alasan');
        }
    } else {
        [$ok, $pesan] = [false, 'Aksi tidak dikenal.'];
    }
    flash($ok ? 'ok' : 'error', $pesan);
    redirect('admin/refund.php?npj=' . rawurlencode($idNpj));
}

// ================================================================ DETAIL
if (get('npj') !== '') {
    $r = refund_ambil(get('npj'));
    if (!$r) {
        abort404();
    }
    $idNpj = $r['id_nota_penjual'];
    $n = nota_lengkap($r['id_nota_pembeli']);
    $idNota = $n['id_nota_pembeli'];
    $sp = $r['id_status_pesanan'];
    $isiAlasan = (string) ($_SESSION['_in_putusan'] ?? '');
    unset($_SESSION['_in_putusan']);
    $items = rows('SELECT rp.id_produk, rp.qty, rp.sub_harga, p.nama_produk, p.foto_produk FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk
                    WHERE rp.id_nota_penjual = ? ORDER BY p.nama_produk', [$idNpj]);

    page_start('Refund ' . $idNpj, ['menu' => 'refund', 'judul' => 'Detail Refund']);
    ?>
<div class="krumb"><a href="<?= e(url('admin/refund.php')) ?>">Banding Refund</a> &rsaquo; <?= e($idNpj) ?></div>
<div class="nota-kepala">
  <div><h2 style="margin:0">Refund <?= e($idNpj) ?></h2>
    <span class="muted">Pembeli <?= e($r['nama_pembeli']) ?> · toko <?= e((string) $r['nama_toko']) ?> · diajukan <?= e(tgl($r['tanggal_ajuan'], true)) ?></span></div>
  <?= refund_badge($sp) ?>
</div>

<div class="dua-kolom mt">
  <div>
    <section class="card mb">
      <h3>Pengajuan pembeli</h3>
      <dl class="kunci-nilai">
        <dt>Jenis</dt><dd><?= e(refund_jenis_teks($r['jenis'])) ?></dd>
        <dt>Alasan</dt><dd><?= e($r['alasan']) ?><?= $r['keterangan'] !== '' ? '<br><small>' . nl2br(e($r['keterangan'])) . '</small>' : '' ?></dd>
        <dt>Nominal</dt><dd><?= rp($r['jumlah']) ?></dd>
        <dt>Tujuan dana</dt><dd><?= e($r['tujuan']) ?></dd>
      </dl>
    </section>

    <section class="card mb">
      <div class="card-head"><h3>Riwayat</h3></div>
      <?= refund_riwayat_html($r['riwayat'], $idNpj) ?>
    </section>

    <?php if ($sp === SP_BD_AJU): ?>
    <section class="card mb" id="putuskan">
      <h3>Putuskan banding</h3>
      <div class="alert alert-warn kecil"><span>Keputusan admin bersifat <b>final</b>. <?= $r['jenis'] === RJ_BATAL ? 'Bila disetujui, pesanan toko ini dibatalkan dan stok dikembalikan.' : 'Bila disetujui, penjualan toko ini tidak lagi dihitung.' ?> Pengembalian dana ke pembeli dilakukan sesuai tujuan dana di atas.</span></div>
      <form method="post" class="master-form" style="max-width:none" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="npj" value="<?= e($idNpj) ?>">
        <label class="field"><span class="field-label">Alasan keputusan <b class="req">*</b></span>
          <textarea name="alasan" rows="4" minlength="5" maxlength="500" required placeholder="Tulis alasan yang jelas. Alasan ini dibaca oleh pembeli dan penjual."><?= e($isiAlasan) ?></textarea>
          <small class="hint">Wajib diisi untuk menyetujui maupun menolak.</small></label>
        <div class="baris rata">
          <button class="btn btn-danger" type="submit" name="aksi" value="tolak" data-konfirmasi="Tolak banding ini? Keputusan final dan tidak bisa diubah.">Tolak banding</button>
          <button class="btn btn-primary" type="submit" name="aksi" value="setujui" data-konfirmasi="Setujui banding ini? Keputusan final dan tidak bisa diubah."><?= ikon('check', 16) ?> Setujui banding</button>
        </div>
      </form>
    </section>
    <?php elseif ($sp === SP_RF_AJU): ?>
      <div class="alert alert-info" role="status"><span>Pengajuan ini masih menunggu tanggapan penjual. Admin baru dapat memutuskan bila pembeli mengajukan banding.</span></div>
    <?php elseif ($sp === SP_RF_TOLAK): ?>
      <div class="alert alert-info" role="status"><span>Penjual menolak refund ini. Admin baru dapat memutuskan bila pembeli mengajukan banding.</span></div>
    <?php elseif ($sp === SP_BD_SETUJU || $sp === SP_BD_TOLAK): ?>
      <div class="alert alert-info" role="status"><span>Banding ini sudah diputuskan (final).</span></div>
    <?php else: ?>
      <div class="alert alert-info" role="status"><span>Refund ini sudah disetujui penjual; tidak ada yang perlu diputuskan admin.</span></div>
    <?php endif; ?>
  </div>

  <aside class="card sticky">
    <h3>Pesanan terkait</h3>
    <div class="ringkas-baris kecil"><span>No. pesanan</span><a class="mono" href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($idNota))) ?>"><?= e($idNota) ?></a></div>
    <div class="ringkas-baris kecil"><span>Status pembayaran</span><span><?= badge($n['status_pembayaran']) ?></span></div>
    <?php if ($n['id_jenis_pembayaran']): ?>
      <div class="ringkas-baris kecil"><span>Dibayar via</span><span><?= e((string) $n['nama_metode_pembayaran']) ?> · <?= e((string) $n['nama_jenis_pembayaran']) ?></span></div>
    <?php endif; ?>
    <div class="ringkas-baris kecil"><span>Total dibayar</span><span class="mono"><?= rp($n['total_harga_beli']) ?></span></div>
    <hr>
    <?php foreach ($items as $it): ?>
      <div class="thumb-baris" style="padding:6px 0"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'])) ?>" alt="" loading="lazy">
        <div style="min-width:0;flex:1"><div class="nama"><?= e($it['nama_produk']) ?></div><small><?= (int) $it['qty'] ?> barang</small></div>
        <span class="tebal mono"><?= rp($it['sub_harga']) ?></span></div>
    <?php endforeach; ?>
    <div class="mt" style="display:grid;gap:10px">
      <a class="btn btn-ghost btn-block" href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($idNota))) ?>">Buka detail pesanan</a>
    </div>
  </aside>
</div>
<?php
    page_end();
    exit;
}

// ================================================================ DAFTAR
$q = trim(get('q'));
$tabs = ['' => 'Semua', 'banding' => 'Perlu diputuskan', 'penjual' => 'Menunggu penjual', 'ditolak' => 'Ditolak penjual', 'setuju' => 'Disetujui', 'final' => 'Banding ditolak'];
$statusTab = [
    'banding' => [SP_BD_AJU], 'penjual' => [SP_RF_AJU], 'ditolak' => [SP_RF_TOLAK], 'setuju' => [SP_RF_SETUJU, SP_BD_SETUJU], 'final' => [SP_BD_TOLAK],
];
$tab = isset($tabs[get('status')]) ? get('status') : '';

$semua = refund_daftar();
if ($q !== '') {
    $k = mb_strtolower($q);
    $semua = array_values(array_filter($semua, fn($r) => str_contains(mb_strtolower($r['id_nota_penjual'] . ' ' . $r['id_nota_pembeli'] . ' ' . $r['nama_pembeli'] . ' ' . $r['nama_toko']), $k)));
}
$hitung = array_fill_keys(array_keys($tabs), 0);
foreach ($semua as $r) {
    $hitung['']++;
    foreach ($statusTab as $kunci => $daftarSp) {
        if (in_array($r['id_status_pesanan'], $daftarSp, true)) {
            $hitung[$kunci]++;
        }
    }
}
$tampil = $tab === '' ? $semua : array_values(array_filter($semua, fn($r) => in_array($r['id_status_pesanan'], $statusTab[$tab], true)));
// banding yang menunggu keputusan admin tampil paling atas
usort($tampil, fn($a, $b) => (($b['id_status_pesanan'] === SP_BD_AJU) <=> ($a['id_status_pesanan'] === SP_BD_AJU)));
[$halaman, $totalHal, $offset] = paginasi(count($tampil), 8, (int) get('hal', '1'));
$tampil = array_slice($tampil, $offset, 8);
$kueri = array_filter(['q' => $q, 'status' => $tab], fn($v) => $v !== '');

page_start('Banding Refund', ['menu' => 'refund', 'judul' => 'Banding Refund']);
?>
<p class="muted" style="margin-top:-4px">Penjual memutuskan refund lebih dulu. Bila ditolak, pembeli dapat banding dan admin memutuskan di sini; keputusan admin final.</p>
<nav class="tab-status" aria-label="Filter status refund">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="chip-link <?= $tab === $k ? 'aktif' : '' ?>" href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $q], fn($v) => $v !== ''))) ?>"><?= e($label) ?> <small><?= (int) $hitung[$k] ?></small></a>
  <?php endforeach; ?>
</nav>
<form class="card card-flat filter-kartu" method="get">
  <?php if ($tab !== ''): ?><input type="hidden" name="status" value="<?= e($tab) ?>"><?php endif; ?>
  <div class="filter" style="margin-bottom:0">
    <label class="field" style="flex:1 1 280px"><span class="field-label">Cari refund</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="no. nota, nama pembeli, atau nama toko"></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Cari</button><?php if ($q !== ''): ?><a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['status' => $tab]))) ?>">Hapus pencarian</a><?php endif; ?></div>
  </div>
</form>

<?php if (!$tampil): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3><?= $hitung[''] ? 'Tidak ada refund yang cocok' : 'Belum ada pengajuan refund' ?></h3><p><?= $hitung[''] ? 'Coba pilih tab lain atau ubah kata kunci.' : 'Pengajuan refund dan banding akan muncul di sini.' ?></p></div>
<?php else: foreach ($tampil as $r): ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_penjual']) ?></span><span class="kecil"><?= e(tgl($r['tanggal_ajuan'], true)) ?></span><span class="kecil"><?= e($r['nama_pembeli']) ?> · <?= e((string) $r['nama_toko']) ?> · pesanan <?= e($r['id_nota_pembeli']) ?></span></div>
      <?= refund_badge($r['id_status_pesanan']) ?>
    </div>
    <div class="nota-isi">
      <div class="tebal"><?= e(refund_jenis_teks($r['jenis'])) ?></div>
      <div class="muted kecil" style="margin-top:2px">Alasan: <?= e($r['alasan']) ?><?= $r['keterangan'] ? ' — ' . e(mb_strimwidth((string) $r['keterangan'], 0, 120, '…')) : '' ?></div>
    </div>
    <div class="nota-bawah">
      <div><small>Nominal</small><div class="tebal mono" style="font-size:1.1rem"><?= rp($r['jumlah']) ?></div></div>
      <div class="baris"><a class="btn <?= $r['id_status_pesanan'] === SP_BD_AJU ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="<?= e(url('admin/refund.php?npj=' . rawurlencode($r['id_nota_penjual']))) ?>"><?= $r['id_status_pesanan'] === SP_BD_AJU ? 'Periksa &amp; putuskan' : 'Lihat detail' ?></a></div>
    </div>
  </article>
<?php endforeach; ?>
  <?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
