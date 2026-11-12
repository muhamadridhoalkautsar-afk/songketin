<?php
/** Katalog: lihat, cari, saring, dan urutkan barang. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');
$pb = profil_pembeli();

$q = get('q');
$f = ['kategori' => get('kategori'), 'daerah' => get('daerah'), 'pola' => get('pola'), 'benang' => get('benang')];
$min = angka_dari(get('min'));
$max = angka_dari(get('max'));
$urut = in_array(get('urut'), ['termurah', 'termahal', 'diskon', 'terlaris', 'rating'], true) ? get('urut') : 'terbaru';
$halaman = max(1, (int) get('hal', '1'));

$harga = '(p.harga_produk - COALESCE(p.besaran_potongan_harga, 0))';
$where = ['p.stok > 0'];
$par = [];
if ($q !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)) . '%';
    $where[] = "(LOWER(p.nama_produk) LIKE ? ESCAPE '!' OR LOWER(p.deskripsi) LIKE ? ESCAPE '!' OR LOWER(p.warna) LIKE ? ESCAPE '!' OR LOWER(pj.nama_penjual) LIKE ? ESCAPE '!' OR LOWER(k.nama_pola_kain) LIKE ? ESCAPE '!')";
    array_push($par, $like, $like, $like, $like, $like);
}
foreach (['kategori' => 'p.id_kategori_produk', 'daerah' => 'p.id_asal_daerah', 'pola' => 'p.id_pola_kain', 'benang' => 'p.id_jenis_benang'] as $k => $kol) {
    if ($f[$k] !== '') {
        $where[] = "$kol = ?";
        $par[] = $f[$k];
    }
}
if ($min > 0) {
    $where[] = "$harga >= ?";
    $par[] = $min;
}
if ($max > 0) {
    $where[] = "$harga <= ?";
    $par[] = $max;
}
$urutSql = [
    'terbaru' => 'p.id_produk DESC',
    'termurah' => "$harga ASC, p.id_produk",
    'termahal' => "$harga DESC, p.id_produk",
    'diskon' => 'COALESCE(p.besaran_potongan_harga, 0) / p.harga_produk DESC, p.id_produk',
    'terlaris' => '(SELECT COALESCE(SUM(rp.qty), 0) FROM rincian_pesanan rp WHERE rp.id_produk = p.id_produk) DESC, p.id_produk',
    'rating' => 'COALESCE(rata_rating, 0) DESC, jumlah_ulasan DESC, p.id_produk',
][$urut];
$dari = ' FROM produk p JOIN penjual pj ON pj.id_penjual = p.id_penjual
          JOIN kategori_produk kt ON kt.id_kategori_produk = p.id_kategori_produk
          LEFT JOIN asal_daerah ad ON ad.id_asal_daerah = p.id_asal_daerah
          LEFT JOIN pola_kain k ON k.id_pola_kain = p.id_pola_kain WHERE ' . implode(' AND ', $where);

$total = (int) val('SELECT COUNT(*)' . $dari, $par);
[$halaman, $totalHal, $offset] = paginasi($total, PER_PAGE, $halaman);
$produk = rows('SELECT p.*, pj.nama_penjual, kt.nama_kategori_produk, ad.nama_asal_daerah, ' . sql_rating_produk('p') . $dari . ' ORDER BY ' . $urutSql . ' LIMIT ' . PER_PAGE . ' OFFSET ' . $offset, $par);

$kategori = rows('SELECT * FROM kategori_produk ORDER BY nama_kategori_produk');
$daerah = rows('SELECT * FROM asal_daerah ORDER BY nama_asal_daerah');
$pola = rows('SELECT * FROM pola_kain ORDER BY nama_pola_kain');
$benang = rows('SELECT * FROM jenis_benang ORDER BY nama_benang');
$tingkat = tingkat_pembeli_row($pb['id_tingkat_pembeli']);
$koin = koin_saldo($pb['id_pembeli']);
$kueri = array_filter(['q' => $q, 'kategori' => $f['kategori'], 'daerah' => $f['daerah'], 'pola' => $f['pola'], 'benang' => $f['benang'],
    'min' => $min ?: '', 'max' => $max ?: '', 'urut' => $urut === 'terbaru' ? '' : $urut], fn($v) => $v !== '');
$adaFilter = (bool) $kueri;

function pilihan(array $rs, string $idKol, string $namaKol, string $dipilih): string
{
    $o = '';
    foreach ($rs as $r) {
        $o .= '<option value="' . e($r[$idKol]) . '"' . ($r[$idKol] === $dipilih ? ' selected' : '') . '>' . e($r[$namaKol]) . '</option>';
    }
    return $o;
}

page_start('Katalog', ['menu' => 'katalog']);
?>
<section class="katalog-hero">
  <div>
    <span class="kode-kecil">Katalog tenun</span>
    <h1>Halo, <?= e(explode(' ', $u['nama'])[0]) ?>. Mau menenun gaya apa hari ini?</h1>
    <p>Songket, tenun ikat, selendang, dan sarung langsung dari tangan para penenun Nusantara.</p>
  </div>
  <div class="hero-status">
    <span class="chip chip-emas"><?= ikon('star', 15) ?> Tingkat <?= e($tingkat['nama_tingkat_pembeli']) ?><?= (float) $tingkat['besar_diskon'] > 0 ? ' · diskon ' . rtrim(rtrim(number_format((float) $tingkat['besar_diskon'], 2, ',', ''), '0'), ',') . '%' : '' ?></span>
    <span class="chip"><?= ikon('coin', 15) ?> <?= angka($koin) ?> koin</span>
  </div>
</section>

<nav class="chips-kategori" aria-label="Kategori">
  <?php $dasar = array_diff_key($kueri, ['kategori' => 1]); ?>
  <a class="chip-link <?= $f['kategori'] === '' ? 'aktif' : '' ?>" href="?<?= e(http_build_query($dasar)) ?>">Semua</a>
  <?php foreach ($kategori as $k): ?>
    <a class="chip-link <?= $f['kategori'] === $k['id_kategori_produk'] ? 'aktif' : '' ?>" href="?<?= e(http_build_query($dasar + ['kategori' => $k['id_kategori_produk']])) ?>"><?= e($k['nama_kategori_produk']) ?></a>
  <?php endforeach; ?>
</nav>

<form class="card card-flat filter-kartu" method="get">
  <?php if ($f['kategori'] !== ''): ?><input type="hidden" name="kategori" value="<?= e($f['kategori']) ?>"><?php endif; ?>
  <div class="filter">
    <label class="field lebar"><span class="field-label">Cari</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Nama kain, warna, motif, atau toko"></label>
    <label class="field"><span class="field-label">Daerah asal</span><select name="daerah" data-auto-submit><option value="">Semua daerah</option><?= pilihan($daerah, 'id_asal_daerah', 'nama_asal_daerah', $f['daerah']) ?></select></label>
    <label class="field"><span class="field-label">Motif</span><select name="pola" data-auto-submit><option value="">Semua motif</option><?= pilihan($pola, 'id_pola_kain', 'nama_pola_kain', $f['pola']) ?></select></label>
    <label class="field"><span class="field-label">Benang</span><select name="benang" data-auto-submit><option value="">Semua benang</option><?= pilihan($benang, 'id_jenis_benang', 'nama_benang', $f['benang']) ?></select></label>
  </div>
  <div class="filter" style="margin-bottom:0">
    <label class="field"><span class="field-label">Harga minimum (Rp)</span><input type="number" name="min" min="0" step="50000" value="<?= $min ?: '' ?>" placeholder="0"></label>
    <label class="field"><span class="field-label">Harga maksimum (Rp)</span><input type="number" name="max" min="0" step="50000" value="<?= $max ?: '' ?>" placeholder="tanpa batas"></label>
    <label class="field"><span class="field-label">Urutkan</span>
      <select name="urut" data-auto-submit>
        <?php foreach (['terbaru' => 'Terbaru', 'terlaris' => 'Terlaris', 'rating' => 'Rating tertinggi', 'termurah' => 'Harga terendah', 'termahal' => 'Harga tertinggi', 'diskon' => 'Diskon terbesar'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $urut === $v ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Terapkan</button>
      <?php if ($adaFilter): ?><a class="btn btn-ghost" href="index.php">Atur ulang</a><?php endif; ?></div>
  </div>
</form>

<div class="baris rata mb"><strong><?= angka($total) ?> produk<?= $q !== '' ? ' untuk “' . e($q) . '”' : '' ?></strong><?php if ($totalHal > 1): ?><span class="kecil">Halaman <?= $halaman ?> dari <?= $totalHal ?></span><?php endif; ?></div>

<?php if (!$produk): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Belum ada kain yang cocok</h3><p>Coba kata kunci lain atau longgarkan filter.</p><a class="btn btn-primary" href="index.php">Lihat semua produk</a></div>
<?php else: ?>
  <div class="grid-produk"><?php foreach ($produk as $p) { echo kartu_produk($p); } ?></div>
  <?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
