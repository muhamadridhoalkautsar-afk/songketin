<?php
/**
 * Refund pembeli.
 *   (tanpa parameter)  → daftar pengajuan refund milik pembeli
 *   ?npj=NPJ00001      → satu toko pada pesanan: ajukan refund, lihat keputusan penjual, ajukan banding, atau tarik pengajuan
 * Alur: pembeli ajukan → penjual setujui/tolak (dengan alasan) → bila ditolak pembeli boleh banding → admin memutuskan (final).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');
$pb = profil_pembeli();
$idNpj = get('npj') !== '' ? get('npj') : post('npj');

// ================================================================ DAFTAR
if ($idNpj === '') {
    $daftar = refund_daftar(['pembeli' => $pb['id_pembeli']]);
    page_start('Refund', ['menu' => 'refund']);
    ?>
<h1>Refund</h1>
<p class="muted">Pengajuan refund Anda dan keputusannya. Untuk mengajukan refund baru, buka <a href="<?= e(url('pembeli/pesanan.php')) ?>">Pesanan Saya</a> lalu pilih pesanannya.</p>
<?php if (!$daftar): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Belum ada pengajuan refund</h3><p>Pengajuan refund akan muncul di sini.</p><a class="btn btn-primary" href="<?= e(url('pembeli/pesanan.php')) ?>">Ke Pesanan Saya</a></div>
<?php else: foreach ($daftar as $r): ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_penjual']) ?></span><span class="kecil">Pesanan <?= e($r['id_nota_pembeli']) ?> · <?= e((string) $r['nama_toko']) ?></span></div>
      <?= refund_badge($r['id_status_pesanan']) ?>
    </div>
    <div class="nota-isi"><b><?= e(refund_jenis_teks($r['jenis'])) ?></b> · <?= e($r['alasan']) ?><br><small>Diajukan <?= e(tgl($r['tanggal_ajuan'], true)) ?> · nominal maksimal <?= rp($r['jumlah']) ?></small></div>
    <div class="nota-bawah"><span></span><a class="btn btn-primary btn-sm" href="<?= e(url('pembeli/refund.php?npj=' . rawurlencode($r['id_nota_penjual']))) ?>">Lihat rincian</a></div>
  </article>
<?php endforeach; endif;
    page_end();
    exit;
}

// ================================================================ DETAIL / PENGAJUAN
$idNota = (string) val('SELECT id_nota_pembeli FROM nota_penjual WHERE id_nota_penjual = ?', [$idNpj]);
$n = $idNota !== '' ? nota_lengkap($idNota) : null;
if (!$n || $n['id_pembeli'] !== $pb['id_pembeli']) {
    abort404(); // nota milik pembeli lain dianggap tidak ada
}
$t = refund_toko($n, $idNpj);
if (!$t) {
    abort404();
}
$balikPesanan = 'pembeli/pesanan.php?id=' . rawurlencode($idNota);
$aksiUrl = 'pembeli/refund.php?npj=' . rawurlencode($idNpj);

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $in = ['alasan' => post('alasan'), 'keterangan' => post('keterangan'), 'tujuan' => post('tujuan')];
    $file = $_FILES['foto'] ?? null;
    if ($aksi === 'ajukan') {
        [$ok, $pesan] = refund_ajukan($idNota, $idNpj, $pb['id_pembeli'], $in, $file);
    } elseif ($aksi === 'banding') {
        [$ok, $pesan] = refund_banding($idNota, $idNpj, $pb['id_pembeli'], $in, $file);
    } elseif ($aksi === 'tarik') {
        [$ok, $pesan] = refund_tarik($idNota, $idNpj, $pb['id_pembeli']);
    } else {
        [$ok, $pesan] = [false, 'Aksi tidak dikenal.'];
    }
    if ($ok) {
        flash('ok', $pesan);
        redirect($aksiUrl);
    }
    flash('error', $pesan);
    $_SESSION['_in_refund'] = $in;   // isian dipertahankan supaya pembeli tidak mengetik ulang
    redirect($aksiUrl);
}
$in = $_SESSION['_in_refund'] ?? ['alasan' => '', 'keterangan' => '', 'tujuan' => ''];
unset($_SESSION['_in_refund']);

$r = refund_ambil($idNpj);
$sp = $r['id_status_pesanan'] ?? null;
[$boleh, $alasanTolak, $jenis] = $r ? [false, '', $r['jenis']] : refund_cek_boleh($n, $t);
$maks = refund_bagian_toko($n, $t);
$batas = refund_batas_akhir($t);

page_start('Refund', ['menu' => 'refund']);
?>
<div class="krumb"><a href="<?= e(url('pembeli/pesanan.php')) ?>">Pesanan Saya</a> &rsaquo; <a href="<?= e(url($balikPesanan)) ?>"><?= e($idNota) ?></a> &rsaquo; Refund <?= e((string) $t['nama_penjual']) ?></div>
<h1>Refund &middot; <?= e((string) $t['nama_penjual']) ?></h1>

<div class="dua-kolom">
  <div>
<?php if ($r): ?>
    <section class="card mb">
      <div class="card-head"><h3>Status pengajuan</h3><?= refund_badge($sp) ?></div>
      <dl class="kunci-nilai">
        <dt>Jenis</dt><dd><?= e(refund_jenis_teks($r['jenis'])) ?></dd>
        <dt>Alasan</dt><dd><?= e($r['alasan']) ?><?= $r['keterangan'] !== '' ? '<br><small>' . nl2br(e($r['keterangan'])) . '</small>' : '' ?></dd>
        <dt>Nominal maksimal</dt><dd><?= rp($r['jumlah']) ?></dd>
        <dt>Tujuan dana</dt><dd><?= e($r['tujuan']) ?></dd>
      </dl>
      <?php
        $info = [
            SP_RF_AJU => ['info', 'Menunggu keputusan penjual. Anda dapat menarik pengajuan selama belum diputuskan.'],
            SP_RF_SETUJU => ['info', 'Penjual menyetujui refund. ' . ($r['jenis'] === RJ_BATAL ? 'Pesanan toko ini dibatalkan dan stok dikembalikan.' : 'Penjualan toko ini dicatat sebagai refund.')],
            SP_RF_TOLAK => ['warn', 'Penjual menolak refund. Alasannya ada di riwayat di bawah. Anda boleh mengajukan banding ke admin (satu kali).'],
            SP_BD_AJU => ['info', 'Banding sedang diperiksa admin. Keputusan admin bersifat final.'],
            SP_BD_SETUJU => ['info', 'Admin menyetujui banding Anda. Keputusan ini final.'],
            SP_BD_TOLAK => ['warn', 'Admin menolak banding Anda. Keputusan ini final.'],
        ][$sp] ?? null; ?>
      <?php if ($info): ?><div class="alert alert-<?= e($info[0]) ?> mt" role="status"><span><?= e($info[1]) ?></span></div><?php endif; ?>
    </section>

    <section class="card mb">
      <div class="card-head"><h3>Riwayat</h3></div>
      <?= refund_riwayat_html($r['riwayat'], $idNpj) ?>
    </section>

    <?php if ($sp === SP_RF_AJU): ?>
      <form method="post" action="<?= e(url($aksiUrl)) ?>" data-konfirmasi="Tarik pengajuan refund ini?">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="tarik"><button class="link-hapus" type="submit">Tarik pengajuan</button>
      </form>
    <?php elseif ($sp === SP_RF_TOLAK): ?>
      <form method="post" action="<?= e(url($aksiUrl)) ?>" enctype="multipart/form-data" class="card master-form" data-konfirmasi="Kirim banding ke admin? Keputusan admin final.">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="banding">
        <h3>Ajukan banding ke admin</h3>
        <label class="field"><span class="field-label">Alasan banding <b class="req">*</b></span>
          <textarea name="alasan" rows="4" minlength="10" maxlength="500" required placeholder="Jelaskan mengapa keputusan penjual perlu ditinjau ulang"><?= e($in['alasan']) ?></textarea></label>
        <label class="field"><span class="field-label">Foto pendukung <small>(opsional)</small></span>
          <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
          <small class="hint">JPG, PNG, atau WebP, maksimal <?= (int) (MAX_UPLOAD_BYTES / 1048576) ?> MB.</small></label>
        <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Kirim banding</button>
      </form>
    <?php endif; ?>

<?php elseif (!$boleh): ?>
    <div class="alert alert-warn" role="alert"><span><b>Refund belum bisa diajukan.</b><br><?= e($alasanTolak) ?></span></div>
    <a class="btn btn-ghost" href="<?= e(url($balikPesanan)) ?>"><?= ikon('back', 16) ?> Kembali ke pesanan</a>

<?php else: ?>
    <form method="post" action="<?= e(url($aksiUrl)) ?>" enctype="multipart/form-data" class="card master-form" autocomplete="off"
          <?= $jenis === RJ_BATAL ? 'data-konfirmasi="Ajukan refund? Bila disetujui, pesanan toko ini dibatalkan."' : '' ?>>
      <?= csrf_field() ?><input type="hidden" name="aksi" value="ajukan">
      <p class="muted" style="margin-top:0"><b><?= e(refund_jenis_teks($jenis)) ?>.</b> Pengajuan dikirim ke penjual (<?= e((string) $t['nama_penjual']) ?>); bila ditolak, Anda bisa banding ke admin.</p>
      <label class="field"><span class="field-label">Alasan refund <b class="req">*</b></span>
        <select name="alasan" required>
          <option value="">— pilih alasan —</option>
          <?php foreach (refund_alasan($jenis) as $a): ?><option value="<?= e($a) ?>" <?= $in['alasan'] === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
        </select></label>
      <label class="field"><span class="field-label">Keterangan <small>(wajib bila alasannya “Lainnya”)</small></span>
        <textarea name="keterangan" rows="4" maxlength="500" placeholder="Ceritakan masalahnya agar penjual mudah memeriksa"><?= e($in['keterangan']) ?></textarea></label>
      <label class="field"><span class="field-label">Foto bukti <?= $jenis === RJ_BARANG ? '<b class="req">*</b>' : '<small>(opsional)</small>' ?></span>
        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" <?= $jenis === RJ_BARANG ? 'required' : '' ?>>
        <small class="hint">Foto yang jelas membantu pemeriksaan. JPG, PNG, atau WebP, minimal 100 × 100 piksel, maksimal <?= (int) (MAX_UPLOAD_BYTES / 1048576) ?> MB.</small></label>
      <label class="field"><span class="field-label">Tujuan pengembalian dana <b class="req">*</b></span>
        <input type="text" name="tujuan" value="<?= e($in['tujuan']) ?>" maxlength="200" required placeholder="Mis. BCA 1234567890 a.n. Nama Anda, atau DANA 0812xxxxxxx">
        <small class="hint">Rekening atau e-wallet penerima dana. Pastikan nomor dan nama pemiliknya benar.</small></label>
      <div class="baris rata mt">
        <a class="btn btn-ghost" href="<?= e(url($balikPesanan)) ?>">Batal</a>
        <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Kirim pengajuan refund</button>
      </div>
    </form>
<?php endif; ?>
  </div>

  <aside class="card sticky">
    <h3>Barang dari <?= e((string) $t['nama_penjual']) ?></h3>
    <?php foreach ($t['items'] as $it): ?>
      <div class="thumb-baris" style="padding:6px 0"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" loading="lazy">
        <div style="min-width:0;flex:1"><div class="nama"><?= e($it['nama_produk']) ?></div><small><?= (int) $it['qty'] ?> barang</small></div>
        <span class="tebal mono"><?= rp($it['sub_harga']) ?></span></div>
    <?php endforeach; ?>
    <hr>
    <div class="ringkas-baris total"><span>Maksimal kembali</span><span><?= rp($maks) ?></span></div>
    <p class="kecil" style="margin:10px 0 0">Sudah termasuk ongkos kirim toko ini dan memperhitungkan potongan yang Anda pakai.
      <?= $batas && !$r ? '<br>Batas pengajuan: <b>' . e(tgl(date('Y-m-d H:i:s', $batas))) . '</b> (' . refund_batas_hari() . ' hari sejak barang diterima).' : '' ?></p>
  </aside>
</div>
<?php page_end();
