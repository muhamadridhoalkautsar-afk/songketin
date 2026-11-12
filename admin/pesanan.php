<?php
/**
 * Dashboard admin — Pesanan: daftar semua pesanan, verifikasi / tolak pembayaran,
 * ubah status pesanan tiap toko, dan pembatalan. Pengiriman diatur di admin/pengiriman.php.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';
require_once __DIR__ . '/../includes/refund.php';

$u = require_role('admin');

function nota_atau_404(string $id): array
{
    $n = $id !== '' ? nota_lengkap($id) : null;
    if (!$n) {
        abort404();
    }
    return $n;
}

// ---------------------------------------------------------------- aksi
if (is_post()) {
    csrf_check();
    $n = nota_atau_404(post('id'));
    $idNota = $n['id_nota_pembeli'];
    switch (post('aksi')) {
        case 'verifikasi':
            [$ok, $pesan] = verifikasi_pembayaran($idNota);
            break;
        case 'tolak':
            [$ok, $pesan] = tolak_pembayaran($idNota, post('catatan'));
            break;
        case 'batal':
            [$ok, $pesan] = batalkan_nota($idNota);
            break;
        case 'status':
            $npj = post('npj');
            $milik = array_filter($n['toko'], fn($t) => $t['id_nota_penjual'] === $npj);
            [$ok, $pesan] = $milik ? set_status_pesanan($npj, post('sp')) : [false, 'Pesanan toko itu tidak ditemukan.'];
            break;
        default:
            [$ok, $pesan] = [false, 'Aksi tidak dikenal.'];
    }
    flash($ok ? 'ok' : 'error', $pesan);
    redirect('admin/pesanan.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ================================================================ DETAIL
if (get('id') !== '') {
    $n = nota_atau_404(get('id'));
    $idNota = $n['id_nota_pembeli'];
    $st = $n['status_pembayaran'];
    [$labelSt, $jenisSt] = status_nota_lengkap($n);
    $pilihanSp = [SP_DIPROSES => 'Diproses', SP_DIKEMAS => 'Dikemas', SP_SELESAI => 'Selesai (diterima pembeli)'];

    page_start('Pesanan ' . $idNota, ['menu' => 'pesanan', 'judul' => 'Detail Pesanan']);
    ?>
<div class="krumb"><a href="<?= e(url('admin/pesanan.php')) ?>">Pesanan</a> &rsaquo; <?= e($idNota) ?></div>
<div class="nota-kepala">
  <div><h2 style="margin:0">Pesanan <?= e($idNota) ?></h2><span class="muted">Dipesan <?= e(tgl($n['tanggal_pembelian'], true)) ?> oleh <?= e($n['nama_pembeli']) ?></span></div>
  <div class="baris"><?= badge($st) ?><?php if (sudah_dibayar($n)): ?><?= badge($labelSt, $jenisSt) ?><?php endif; ?></div>
</div>

<div class="dua-kolom mt">
  <div>
    <?php foreach ($n['toko'] as $t): $final = in_array($t['id_status_pengiriman'], [SK_DITERIMA, SK_BATAL], true); ?>
    <section class="card mb">
      <div class="card-head"><h3><?= ikon('home', 18) ?> <?= e($t['nama_penjual']) ?> <small class="mono" style="font-weight:500"><?= e($t['id_nota_penjual']) ?></small></h3><span class="baris"><?= badge((string) $t['keterangan_status_pengiriman']) ?><?= ($rs = refund_status_toko($t)) ? refund_badge($rs) : '' ?></span></div>
      <?= lacak_html(langkah_lacak($n, $t)) ?>
      <?php foreach ($t['items'] as $it): ?>
        <div class="item-baris" style="grid-template-columns:56px minmax(0,1fr) auto;padding:8px 0">
          <img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" style="width:56px;height:56px" loading="lazy">
          <div><span class="tebal"><?= e($it['nama_produk']) ?></span><br><small><?= (int) $it['qty'] ?> × <?= rp((float) $it['sub_harga'] / max(1, (int) $it['qty'])) ?> · <?= e((string) $it['keterangan_status_pesanan']) ?></small></div>
          <div class="tebal mono"><?= rp($it['sub_harga']) ?></div>
        </div>
      <?php endforeach; ?>
      <dl class="kunci-nilai mt">
        <dt>Pengiriman</dt><dd><?= e($t['nama_opsi_pengiriman'] ?? '—') ?> <small>(<?= e($t['nama_jenis_pengiriman'] ?? '—') ?>)</small></dd>
        <dt>Rute</dt><dd><?= e((string) $t['kota_asal']) ?> &rarr; <?= e((string) $t['kota_tujuan']) ?></dd>
        <dt>Ongkos kirim</dt><dd><?= rp($t['ongkos_kirim']) ?></dd>
        <dt>Pendapatan toko</dt><dd><?= rp($t['total_harga_jual']) ?> <small>(setelah diskon harga produk)</small></dd>
      </dl>
      <?php if (!$final && sudah_dibayar($n)): ?>
        <form method="post" class="baris mt" style="align-items:flex-end">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= e($idNota) ?>"><input type="hidden" name="npj" value="<?= e($t['id_nota_penjual']) ?>">
          <label class="field" style="margin:0;min-width:220px"><span class="field-label">Status pesanan toko ini</span>
            <select name="sp"><?php foreach ($pilihanSp as $k => $l): ?><option value="<?= e($k) ?>" <?= $t['id_status_pesanan'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-primary" type="submit">Simpan status</button>
          <a class="btn btn-ghost" href="<?= e(url('admin/pengiriman.php?q=' . rawurlencode($t['id_nota_penjual']))) ?>"><?= ikon('truck', 15) ?> Atur pengiriman</a>
        </form>
      <?php elseif (!$final): ?>
        <p class="muted kecil mt" style="margin-bottom:0">Status pesanan dan pengiriman baru bisa diubah setelah pembayaran terverifikasi.</p>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <section class="card mb">
      <h3>Alamat pengiriman</h3>
      <strong><?= e($n['nama_pembeli']) ?></strong>
      <p class="muted" style="margin:2px 0 0"><?= e($n['alamat']) ?></p>
    </section>
  </div>

  <aside class="card sticky">
    <h3>Pembayaran</h3>
    <div class="ringkas-baris"><span>Status</span><span><?= badge($st) ?></span></div>
    <?php if (!empty($n['ditolak'])): ?>
      <div class="alert alert-warn kecil" style="margin:10px 0 0"><span><b>Pembayaran sebelumnya ditolak.</b><?= $n['catatan_tolak'] !== null && $n['catatan_tolak'] !== '' ? '<br>Catatan: ' . e($n['catatan_tolak']) : '' ?></span></div>
    <?php endif; ?>
    <?php if ($n['id_jenis_pembayaran']): ?>
      <div class="ringkas-baris kecil"><span>Sifat</span><span><?= e((string) $n['nama_sifat_pembayaran']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Metode</span><span><?= e((string) $n['nama_metode_pembayaran']) ?></span></div>
      <div class="ringkas-baris kecil"><span>Jenis</span><span><?= e((string) $n['nama_jenis_pembayaran']) ?></span></div>
      <?php if (!empty($n['rekening_jenis_pembayaran'])): ?><div class="ringkas-baris kecil"><span>Rekening tujuan</span><span class="mono"><?= e($n['rekening_jenis_pembayaran']) ?></span></div><?php endif; ?>
      <?php if (!sifat_tunai('', (string) $n['nama_sifat_pembayaran'])): ?>
        <?php $adaBukti = bukti_ada($idNota); ?>
        <div class="mt">
          <div class="kecil tebal" style="margin-bottom:8px">Bukti pembayaran</div>
          <?php if ($adaBukti): ?>
            <a href="<?= e(url('api/bukti.php?id=' . rawurlencode($idNota))) ?>" target="_blank" rel="noopener" title="Buka bukti ukuran penuh">
              <img src="<?= e(url('api/bukti.php?id=' . rawurlencode($idNota))) ?>" alt="Bukti pembayaran <?= e($idNota) ?>" style="width:100%;border-radius:10px;border:1px solid #ead9b6;display:block">
            </a>
          <?php else: ?>
            <div class="bukti-kosong kecil">Pembeli belum mengunggah bukti pembayaran.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="muted kecil" style="margin:6px 0 0">Pembeli belum memilih sifat dan metode pembayaran.</p>
    <?php endif; ?>
    <?php foreach ($n['toko'] as $t): if (!($rs = refund_status_toko($t))) { continue; } ?>
      <div class="alert alert-info kecil" style="margin:12px 0 0"><span><b>Refund <?= e($t['nama_penjual']) ?></b> <?= refund_badge($rs) ?><br>
        <a href="<?= e(url('admin/refund.php?npj=' . rawurlencode($t['id_nota_penjual']))) ?>">Buka refund</a></span></div>
    <?php endforeach; ?>
    <hr>
    <?= rincian_biaya_html($n) ?>

    <div class="mt" style="display:grid;gap:10px">
      <?php if ($st === BAYAR_VERIF): ?>
        <span id="tolak"></span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="verifikasi"><input type="hidden" name="id" value="<?= e($idNota) ?>">
          <button class="btn btn-primary btn-lg btn-block" type="submit"><?= ikon('check', 16) ?> Verifikasi: tandai Lunas</button></form>
        <form method="post" data-konfirmasi="Tolak pembayaran ini? Pembeli akan diminta membayar ulang."><?= csrf_field() ?><input type="hidden" name="aksi" value="tolak"><input type="hidden" name="id" value="<?= e($idNota) ?>">
          <label class="field" style="margin-bottom:10px"><span class="field-label">Catatan untuk pembeli <small>(opsional)</small></span>
            <textarea name="catatan" rows="3" maxlength="500" placeholder="Mis. nominal transfer kurang atau bukti tidak terbaca"></textarea></label>
          <button class="btn btn-ghost btn-block" type="submit">Tolak pembayaran</button></form>
      <?php endif; ?>
      <a class="btn btn-ghost btn-block" href="<?= e(url_struk($idNota, true)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('download', 16) ?> Unduh struk (PDF)</a>
      <a class="btn btn-ghost btn-block" href="<?= e(url_struk($idNota)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('file', 16) ?> Lihat &amp; cetak struk</a>
    </div>
    <?php if (bisa_dibatalkan($n)): ?>
      <form method="post" class="mt tengah" data-konfirmasi="<?= $st === BAYAR_LUNAS ? 'Batalkan pesanan yang sudah lunas ini? Stok dan koin dikembalikan, dan dananya tercatat sebagai refund yang harus Anda kembalikan ke pembeli.' : 'Batalkan pesanan ini? Stok dan koin pembeli akan dikembalikan.' ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= e($idNota) ?>">
        <button class="link-hapus" type="submit">Batalkan pesanan</button></form>
    <?php endif; ?>
  </aside>
</div>
<?php
    page_end();
    exit;
}

// ================================================================ DAFTAR
$q = trim(get('q'));
$tabs = ['' => 'Semua', 'verif' => 'Perlu verifikasi', 'bayar' => 'Belum dibayar', 'proses' => 'Diproses', 'kirim' => 'Dalam pengiriman', 'selesai' => 'Selesai', 'batal' => 'Dibatalkan'];
$tab = isset($tabs[get('status')]) ? get('status') : '';

$par = [BAYAR_BATAL, BAYAR_BELUM, BAYAR_DITOLAK, BAYAR_VERIF, SK_DITERIMA, SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR];
$cari = '';
if ($q !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)) . '%';
    $cari = " WHERE (LOWER(nb.id_nota_pembeli) LIKE ? ESCAPE '!' OR LOWER(pb.nama_pembeli) LIKE ? ESCAPE '!')";
    array_push($par, $like, $like);
}
$dasar = "SELECT t.*, CASE WHEN t.status_pembayaran = ? THEN 'batal' WHEN t.status_pembayaran IN (?, ?) THEN 'bayar' WHEN t.status_pembayaran = ? THEN 'verif'
                          WHEN t.jml_toko - t.jml_batal > 0 AND t.jml_terima >= t.jml_toko - t.jml_batal THEN 'selesai' WHEN t.jml_kirim > 0 OR t.jml_terima > 0 THEN 'kirim' ELSE 'proses' END AS kunci
            FROM (SELECT nb.id_nota_pembeli, nb.tanggal_pembelian, nb.total_harga_beli, nb.total_barang_beli, " . sql_status_bayar('nb') . " AS status_pembayaran, pb.nama_pembeli, jp.nama_jenis_pembayaran,
                         (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli) AS jml_toko,
                         (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli AND np.id_status_pengiriman = '" . SK_BATAL . "') AS jml_batal,
                         (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli AND np.id_status_pengiriman = ?) AS jml_terima,
                         (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli AND np.id_status_pengiriman IN (?, ?, ?, ?)) AS jml_kirim
                    FROM nota_pembeli nb JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
                    LEFT JOIN jenis_pembayaran jp ON jp.id_jenis_pembayaran = nb.id_jenis_pembayaran" . $cari . ') t';
$hitung = array_fill_keys(array_keys($tabs), 0);
foreach (rows('SELECT kunci, COUNT(*) AS n FROM (' . $dasar . ') x GROUP BY kunci', $par) as $r) {
    $hitung[$r['kunci']] = (int) $r['n'];
    $hitung['']             += (int) $r['n'];
}
[$halaman, $totalHal, $offset] = paginasi($hitung[$tab], 8, (int) get('hal', '1'));
$daftar = rows(
    'SELECT * FROM (' . $dasar . ') x' . ($tab !== '' ? ' WHERE kunci = ?' : '') . ' ORDER BY tanggal_pembelian DESC, id_nota_pembeli DESC LIMIT 8 OFFSET ' . (int) $offset,
    $tab !== '' ? array_merge($par, [$tab]) : $par
);
$kueri = array_filter(['q' => $q, 'status' => $tab], fn($v) => $v !== '');
$petaRefund = [];
if ($daftar) {
    $idn = array_column($daftar, 'id_nota_pembeli');
    foreach (rows('SELECT DISTINCT np.id_nota_pembeli, rp.id_status_pesanan FROM nota_penjual np JOIN rincian_pesanan rp ON rp.id_nota_penjual = np.id_nota_penjual
                    WHERE np.id_nota_pembeli IN (' . in_marks($idn) . ') AND rp.id_status_pesanan IN (' . in_marks(SP_REFUND_SEMUA) . ') ORDER BY rp.id_status_pesanan',
        array_merge($idn, SP_REFUND_SEMUA)) as $x) {
        $petaRefund[$x['id_nota_pembeli']][] = $x['id_status_pesanan'];
    }
}

page_start('Pesanan', ['menu' => 'pesanan', 'judul' => 'Pesanan & Pembayaran']);
?>
<nav class="tab-status" aria-label="Filter status">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="chip-link <?= $tab === $k ? 'aktif' : '' ?>" href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $q], fn($v) => $v !== ''))) ?>"><?= e($label) ?> <small><?= (int) $hitung[$k] ?></small></a>
  <?php endforeach; ?>
</nav>
<form class="card card-flat filter-kartu" method="get">
  <?php if ($tab !== ''): ?><input type="hidden" name="status" value="<?= e($tab) ?>"><?php endif; ?>
  <div class="filter" style="margin-bottom:0">
    <label class="field" style="flex:1 1 280px"><span class="field-label">Cari pesanan</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="nomor nota atau nama pembeli"></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Cari</button><?php if ($q !== ''): ?><a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['status' => $tab]))) ?>">Hapus pencarian</a><?php endif; ?></div>
  </div>
</form>

<?php if (!$daftar): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3><?= $hitung[''] ? 'Tidak ada pesanan yang cocok' : 'Belum ada pesanan' ?></h3><p><?= $hitung[''] ? 'Coba pilih tab lain atau ubah kata kunci.' : 'Pesanan dari pembeli akan muncul di sini.' ?></p></div>
<?php else: foreach ($daftar as $r):
    [$labelSt, $jenisSt] = status_ringkas($r);
    $n = nota_lengkap($r['id_nota_pembeli']);
    $item = [];
    foreach ($n['toko'] as $t) {
        foreach ($t['items'] as $it) {
            $item[] = $it + ['toko' => $t['nama_penjual']];
        }
    } ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_pembeli']) ?></span><span class="kecil"><?= e(tgl($r['tanggal_pembelian'], true)) ?></span><span class="kecil"><?= e($r['nama_pembeli']) ?></span></div>
      <div class="baris"><?= badge($r['status_pembayaran']) ?><?php if (!in_array($r['status_pembayaran'], [BAYAR_BELUM, BAYAR_DITOLAK, BAYAR_VERIF, BAYAR_BATAL], true)): ?><?= badge($labelSt, $jenisSt) ?><?php endif; ?><?php foreach ($petaRefund[$r['id_nota_pembeli']] ?? [] as $rfs): ?><?= refund_badge($rfs) ?><?php endforeach; ?></div>
    </div>
    <div class="nota-isi">
      <?php foreach (array_slice($item, 0, 2) as $it): ?>
        <div class="thumb-baris"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" loading="lazy">
          <div style="min-width:0"><div class="nama"><?= e($it['nama_produk']) ?></div><small><?= (int) $it['qty'] ?> barang · <?= e($it['toko']) ?></small></div></div>
      <?php endforeach; ?>
      <?php if (count($item) > 2): ?><small>+ <?= count($item) - 2 ?> barang lainnya</small><?php endif; ?>
    </div>
    <div class="nota-bawah">
      <div><small>Total · <?= $r['nama_jenis_pembayaran'] ? e($r['nama_jenis_pembayaran']) : 'belum memilih cara bayar' ?></small><div class="tebal mono" style="font-size:1.1rem"><?= rp($r['total_harga_beli']) ?></div></div>
      <div class="baris">
        <?php if ($r['status_pembayaran'] === BAYAR_VERIF): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="verifikasi"><input type="hidden" name="id" value="<?= e($r['id_nota_pembeli']) ?>"><button class="btn btn-gold btn-sm" type="submit"><?= ikon('check', 14) ?> Verifikasi</button></form>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($r['id_nota_pembeli']) . '#tolak')) ?>" title="Buka detail untuk menolak dan menulis catatan">Tolak…</a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($r['id_nota_pembeli']))) ?>">Detail &amp; status</a>
      </div>
    </div>
  </article>
<?php endforeach; ?>
  <?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
