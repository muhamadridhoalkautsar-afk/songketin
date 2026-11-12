<?php
/**
 * Dashboard admin — Pengiriman: pantau dan ubah status pengiriman tiap nota toko
 * (Belum Dikirim → Diserahkan ke Kurir → Dalam Perjalanan → Tiba di Kota Tujuan → Sedang Diantar → Diterima).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';
require_once __DIR__ . '/../includes/refund.php';

$u = require_role('admin');

const URUTAN_KIRIM = [SK_BELUM, SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR, SK_DITERIMA];
const LANGKAH_KIRIM = [SK_BELUM => 'Belum dikirim', SK_KURIR => 'Diserahkan ke kurir', SK_JALAN => 'Dalam perjalanan', SK_TIBA => 'Tiba di kota tujuan', SK_ANTAR => 'Sedang diantar', SK_DITERIMA => 'Diterima'];
$LABEL = LANGKAH_KIRIM; // nama status mengikuti data master (admin bisa mengubahnya)
foreach (rows('SELECT id_status_pengiriman, keterangan_status_pengiriman FROM status_pengiriman') as $r) {
    if (isset($LABEL[$r['id_status_pengiriman']]) && trim((string) $r['keterangan_status_pengiriman']) !== '') {
        $LABEL[$r['id_status_pengiriman']] = $r['keterangan_status_pengiriman'];
    }
}
const AKSI_LANJUT = [SK_BELUM => 'Serahkan ke kurir', SK_KURIR => 'Tandai dalam perjalanan', SK_JALAN => 'Tandai tiba di kota tujuan', SK_TIBA => 'Tandai sedang diantar', SK_ANTAR => 'Tandai diterima'];

// ---------------------------------------------------------------- aksi
if (is_post()) {
    csrf_check();
    $np = nota_penjual_row(post('npj'));
    if (!$np) {
        flash('error', 'Data pengiriman tidak ditemukan.');
    } else {
        $sk = post('sk');
        if (post('aksi') === 'lanjut') {
            $i = array_search($np['id_status_pengiriman'], URUTAN_KIRIM, true);
            $sk = $i !== false && isset(URUTAN_KIRIM[$i + 1]) ? URUTAN_KIRIM[$i + 1] : '';
        }
        if (!in_array($sk, URUTAN_KIRIM, true)) {
            flash('error', 'Status pengiriman tidak valid.');
        } elseif ($sk === $np['id_status_pengiriman']) {
            flash('warn', 'Status pengiriman tidak berubah.');
        } else {
            [$ok, $pesan] = set_status_pengiriman($np['id_nota_penjual'], $sk);
            flash($ok ? 'ok' : 'error', $ok ? $np['id_nota_penjual'] . ': ' . $pesan . ' (' . $LABEL[$sk] . ')' : $pesan);
        }
    }
    redirect('admin/pengiriman.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ---------------------------------------------------------------- daftar
$q = trim(get('q'));
$tabs = ['' => 'Semua'] + $LABEL;
$tab = isset($tabs[get('status')]) ? get('status') : '';

$par = [];
$dasar = ' FROM nota_penjual np JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
           LEFT JOIN pengiriman pg ON pg.id_pengiriman = np.id_pengiriman LEFT JOIN opsi_pengiriman op ON op.id_opsi_pengiriman = pg.id_opsi_pengiriman
           LEFT JOIN jenis_pengiriman jk ON jk.id_jenis_pengiriman = op.id_jenis_pengiriman
          WHERE ' . sql_nota_terhitung('nb') . ' AND np.id_status_pengiriman <> ?';
$par[] = SK_BATAL;
if ($q !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)) . '%';
    $dasar .= " AND (LOWER(np.id_nota_penjual) LIKE ? ESCAPE '!' OR LOWER(np.id_nota_pembeli) LIKE ? ESCAPE '!' OR LOWER(pb.nama_pembeli) LIKE ? ESCAPE '!'
                 OR LOWER(COALESCE(pg.kota_tujuan, '')) LIKE ? ESCAPE '!')";
    array_push($par, $like, $like, $like, $like);
}
$hitung = array_fill_keys(array_keys($tabs), 0);
foreach (rows('SELECT np.id_status_pengiriman AS sk, COUNT(*) AS n' . $dasar . ' GROUP BY np.id_status_pengiriman', $par) as $r) {
    $hitung[$r['sk']] = (int) $r['n'];
    $hitung['']       += (int) $r['n'];
}
$parTampil = $par;
$saring = '';
if ($tab !== '') {
    $saring = ' AND np.id_status_pengiriman = ?';
    $parTampil[] = $tab;
}
[$halaman, $totalHal, $offset] = paginasi($hitung[$tab], 8, (int) get('hal', '1'));
$daftar = rows(
    'SELECT np.id_nota_penjual, np.id_nota_pembeli, np.id_status_pengiriman, np.total_harga_jual, np.total_barang_jual, np.tanggal_sampai, nb.tanggal_pembelian,
            pb.nama_pembeli, pg.ongkos_kirim, pg.kota_asal, pg.kota_tujuan, pg.lama_pengiriman, pg.tanggal_pengiriman, op.nama_opsi_pengiriman, jk.nama_jenis_pengiriman,
            (SELECT MIN(pj.nama_penjual) FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk JOIN penjual pj ON pj.id_penjual = p.id_penjual WHERE rp.id_nota_penjual = np.id_nota_penjual) AS nama_penjual'
    . $dasar . $saring . ' ORDER BY nb.tanggal_pembelian DESC, np.id_nota_penjual DESC LIMIT 8 OFFSET ' . (int) $offset,
    $parTampil
);
$item = [];
if ($daftar) {
    $ids = array_column($daftar, 'id_nota_penjual');
    foreach (rows('SELECT rp.id_nota_penjual, rp.qty, p.nama_produk FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk
                    WHERE rp.id_nota_penjual IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY p.nama_produk', $ids) as $it) {
        $item[$it['id_nota_penjual']][] = $it['nama_produk'] . ' ×' . (int) $it['qty'];
    }
}
$kueri = array_filter(['q' => $q, 'status' => $tab], fn($v) => $v !== '');

page_start('Pengiriman', ['menu' => 'pengiriman', 'judul' => 'Pengiriman']);
?>
<nav class="tab-status" aria-label="Filter status pengiriman">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="chip-link <?= $tab === $k ? 'aktif' : '' ?>" href="?<?= e(http_build_query(array_filter(['status' => $k, 'q' => $q], fn($v) => $v !== ''))) ?>"><?= e($label) ?> <small><?= (int) $hitung[$k] ?></small></a>
  <?php endforeach; ?>
</nav>
<form class="card card-flat filter-kartu" method="get">
  <?php if ($tab !== ''): ?><input type="hidden" name="status" value="<?= e($tab) ?>"><?php endif; ?>
  <div class="filter" style="margin-bottom:0">
    <label class="field" style="flex:1 1 280px"><span class="field-label">Cari pengiriman</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="nomor nota toko / pembeli, nama pembeli, atau kota tujuan"></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Cari</button><?php if ($q !== ''): ?><a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['status' => $tab]))) ?>">Hapus pencarian</a><?php endif; ?></div>
  </div>
</form>
<p class="muted kecil" style="margin-top:-4px">Hanya pesanan yang pembayarannya sudah terverifikasi (Lunas / Bayar di Tempat) yang muncul di sini. Pesanan yang belum dibayar diatur di menu <a href="<?= e(url('admin/pesanan.php')) ?>">Pesanan</a>.</p>

<?php if (!$daftar): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3><?= $hitung[''] ? 'Tidak ada pengiriman di tab ini' : 'Belum ada pengiriman' ?></h3><p><?= $hitung[''] ? 'Coba pilih tab lain atau ubah kata kunci.' : 'Pengiriman muncul setelah pembayaran pesanan terverifikasi.' ?></p></div>
<?php else: foreach ($daftar as $r):
    $sk = $r['id_status_pengiriman'];
    $i = array_search($sk, URUTAN_KIRIM, true);
    $final = $sk === SK_DITERIMA;
    $langkah = [];
    foreach (URUTAN_KIRIM as $j => $kode) {
        $langkah[] = [$LABEL[$kode], $final || $j < $i ? 'selesai' : ($j === $i ? 'kini' : '')];
    } ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($r['id_nota_penjual']) ?></span><span class="kecil">Nota pembeli <a href="<?= e(url('admin/pesanan.php?id=' . rawurlencode($r['id_nota_pembeli']))) ?>"><?= e($r['id_nota_pembeli']) ?></a></span><span class="kecil"><?= e(tgl($r['tanggal_pembelian'], true)) ?></span></div>
      <?= badge($LABEL[$sk] ?? $sk, $final ? 'ok' : ($sk === SK_BELUM ? 'muted' : 'info')) ?>
    </div>
    <div class="nota-isi">
      <?= lacak_html($langkah) ?>
      <dl class="kunci-nilai">
        <dt>Barang</dt><dd><?= e(implode(', ', $item[$r['id_nota_penjual']] ?? [])) ?></dd>
        <dt>Dari toko</dt><dd><?= e((string) $r['nama_penjual']) ?> <small>(<?= e((string) $r['kota_asal']) ?>)</small></dd>
        <dt>Kepada</dt><dd><?= e($r['nama_pembeli']) ?> <small>(<?= e((string) $r['kota_tujuan']) ?>)</small></dd>
        <dt>Layanan</dt><dd><?= e($r['nama_opsi_pengiriman'] ?? '—') ?> <small>· <?= e($r['nama_jenis_pengiriman'] ?? '—') ?> · ongkir <?= rp($r['ongkos_kirim'] ?? 0) ?> · estimasi <?= (int) $r['lama_pengiriman'] === 0 ? 'hari yang sama' : e(hari_teks((int) $r['lama_pengiriman'])) ?></small></dd>
        <?php if ($sk !== SK_BELUM): ?><dt>Diserahkan</dt><dd><?= e(tgl($r['tanggal_pengiriman'])) ?></dd><?php endif; ?>
        <?php if ($final): ?><dt>Tiba pada</dt><dd><?= e(tgl($r['tanggal_sampai'], true)) ?></dd><?php endif; ?>
      </dl>
    </div>
    <div class="nota-bawah">
      <?php if ($final): ?>
        <small>Pengiriman selesai. Koin pembeli dan tingkat toko sudah diperbarui.</small>
      <?php else: ?>
        <form method="post" class="baris" style="align-items:flex-end"><?= csrf_field() ?><input type="hidden" name="npj" value="<?= e($r['id_nota_penjual']) ?>">
          <label class="field" style="margin:0;min-width:210px"><span class="field-label">Ubah status ke</span>
            <select name="sk"><?php foreach (URUTAN_KIRIM as $kode): ?><option value="<?= e($kode) ?>" <?= $kode === $sk ? 'selected' : '' ?>><?= e($LABEL[$kode]) ?></option><?php endforeach; ?></select></label>
          <button class="btn btn-ghost" type="submit" name="aksi" value="set">Simpan</button>
          <button class="btn btn-primary" type="submit" name="aksi" value="lanjut"><?= ikon('truck', 15) ?> <?= e(AKSI_LANJUT[$sk] ?? 'Lanjut') ?></button>
        </form>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
  <?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
