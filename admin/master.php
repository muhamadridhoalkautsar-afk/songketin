<?php
/**
 * Dashboard admin — Data Master: wilayah, pembayaran, pengiriman, produk, tingkat, promo, status.
 * Tanpa ?d= tampil daftar semua data master; dengan ?d=… tampil isi satu data master (cari, tambah, ubah, hapus).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/master_hapus.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('admin');

// ---------------------------------------------------------------- hapus
if (is_post()) {
    csrf_check();
    $dh = post('d');
    if (!master_cfg($dh)) {
        abort404();
    }
    [$ok, $pesan] = master_hapus($dh, post('id'), post('semua') === '1');
    flash($ok ? 'ok' : 'error', $pesan);
    redirect('admin/master.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$kunci = get('d');
$c = $kunci !== '' ? master_cfg($kunci) : null;
if ($kunci !== '' && !$c) {
    abort404();
}

// ================================================================ ringkasan semua data master
if (!$c) {
    $deskripsi = [
        'Wilayah' => 'Negara → provinsi → kabupaten/kota → kecamatan → desa/kelurahan untuk alamat pembeli dan toko.',
        'Pembayaran' => 'Sifat → metode → jenis pembayaran yang dipilih pembeli saat membayar.',
        'Pengiriman' => 'Jenis pengiriman dan opsi ekspedisinya.',
        'Produk' => 'Pilihan yang dipakai penjual saat mengisi produk.',
        'Keanggotaan' => 'Tingkat pembeli (diskon), tingkat penjual (biaya layanan), dan jenis koin beserta nilai potongannya.',
        'Promo' => 'Diskon ongkir dan diskon harga produk (dipilih penjual pada produknya).',
        'Status' => 'Nama status pesanan dan pengiriman (alurnya tetap).',
    ];
    page_start('Data Master', ['menu' => 'master', 'judul' => 'Data Master']);
    ?>
<p class="muted">Kelola semua data referensi Songketin. Perubahan langsung dipakai di formulir pendaftaran, katalog, pembayaran, dan pengiriman.</p>
<?php foreach (MASTER_GRUP as $grup => $daftar): ?>
  <section class="mb">
    <h2 class="master-judul"><?= e($grup) ?></h2>
    <p class="muted kecil" style="margin:-4px 0 12px"><?= e($deskripsi[$grup] ?? '') ?></p>
    <div class="master-grid">
      <?php foreach ($daftar as $k): $cc = master_cfg($k); ?>
        <a class="master-kartu" href="<?= e(url('admin/master.php?d=' . $k)) ?>">
          <span class="master-nama"><?= e($cc['judul']) ?></span>
          <span class="master-jumlah"><?= angka(master_hitung($k)) ?> <small>data</small></span>
          <span class="master-aksi"><?= !empty($cc['hanya_ubah']) ? 'Ubah nama' : 'Kelola' ?> &rsaquo;</span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
<?php
    page_end();
    exit;
}

// ================================================================ isi satu data master
$q = trim(get('q'));
$indukId = isset($c['induk']) ? trim(get('induk')) : '';
$indukRow = null;
$indukCfg = null;
if ($indukId !== '') {
    $indukCfg = master_cfg($c['induk']['ent']);
    $indukRow = master_ambil($c['induk']['ent'], $indukId);
    if (!$indukRow) {
        $indukId = '';
    }
}
$perHal = 12;
[$baris, $total] = master_baris($c, $q, $indukId, (int) get('hal', '1'), $perHal);
[$halaman, $totalHal] = paginasi($total, $perHal, (int) get('hal', '1'));
$anak = master_anak($kunci);
$bisaTambah = empty($c['hanya_ubah']);
$kueri = array_filter(['d' => $kunci, 'induk' => $indukId, 'q' => $q], fn($v) => $v !== '');
$tambahUrl = url('admin/master_form.php?' . http_build_query(array_filter(['d' => $kunci, 'induk' => $indukId])));
$saudara = MASTER_GRUP[$c['grup']];
// Kolom yang tampil sebagai sel sendiri: tanpa kolom nama (sudah di kolom kedua) dan tanpa relasi pertama (ditampilkan di bawah nama).
$kolomRelPertama = ($c['kolom'][0]['tipe'] ?? '') === 'rel' ? $c['kolom'][0]['k'] : null;
$kolomTabel = array_values(array_filter($c['kolom'], fn($k) => $k['k'] !== $c['nama'] && $k['k'] !== $kolomRelPertama));

page_start($c['judul'], ['menu' => 'master', 'judul' => $c['judul']]);
?>
<div class="krumb"><a href="<?= e(url('admin/master.php')) ?>">Data Master</a> &rsaquo; <?= e($c['grup']) ?> &rsaquo; <?= e($c['judul']) ?></div>

<?php if (count($saudara) > 1): ?>
<nav class="tab-status" aria-label="Data master <?= e($c['grup']) ?>">
  <?php foreach ($saudara as $k): ?><a class="chip-link <?= $k === $kunci ? 'aktif' : '' ?>" href="<?= e(url('admin/master.php?d=' . $k)) ?>"><?= e(master_cfg($k)['judul']) ?></a><?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!empty($c['catatan'])): ?><div class="alert alert-info"><span><?= e($c['catatan']) ?></span></div><?php endif; ?>

<?php if ($indukRow): ?>
  <div class="card card-flat" style="padding:12px 16px;margin-bottom:14px">
    <div class="baris rata">
      <span>Menampilkan <b><?= e(mb_strtolower($c['judul'])) ?></b> di <?= e(mb_strtolower($c['induk']['label'])) ?> <b><?= e((string) $indukRow[$indukCfg['nama']]) ?></b> <small class="mono muted"><?= e($indukId) ?></small></span>
      <span class="baris">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/master.php?d=' . $kunci)) ?>">Tampilkan semua</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/master.php?d=' . $c['induk']['ent'])) ?>">&lsaquo; <?= e($indukCfg['judul']) ?></a>
      </span>
    </div>
  </div>
<?php endif; ?>

<form class="card card-flat filter-kartu" method="get">
  <input type="hidden" name="d" value="<?= e($kunci) ?>">
  <?php if ($indukId !== ''): ?><input type="hidden" name="induk" value="<?= e($indukId) ?>"><?php endif; ?>
  <div class="filter" style="margin-bottom:0">
    <label class="field" style="flex:1 1 260px"><span class="field-label">Cari <?= e(mb_strtolower($c['judul'])) ?></span><input type="search" name="q" value="<?= e($q) ?>" placeholder="nama atau kode"></label>
    <div class="baris" style="margin-bottom:14px">
      <button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Cari</button>
      <?php if ($q !== ''): ?><a class="btn btn-ghost" href="<?= e(url('admin/master.php?' . http_build_query(array_filter(['d' => $kunci, 'induk' => $indukId])))) ?>">Hapus pencarian</a><?php endif; ?>
      <?php if ($bisaTambah): ?><a class="btn btn-primary" href="<?= e($tambahUrl) ?>"><?= ikon('plus', 16) ?> Tambah <?= e(mb_strtolower($c['judul'])) ?></a><?php endif; ?>
    </div>
  </div>
</form>

<?php if (!$baris): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Belum ada data</h3><p><?= $q !== '' ? 'Tidak ada yang cocok dengan pencarian Anda.' : 'Belum ada ' . e(mb_strtolower($c['judul'])) . ' di sini.' ?></p>
    <?php if ($bisaTambah && $q === ''): ?><a class="btn btn-primary" href="<?= e($tambahUrl) ?>"><?= ikon('plus', 16) ?> Tambah <?= e(mb_strtolower($c['judul'])) ?></a><?php endif; ?></div>
<?php else: ?>
<div class="tabel-bungkus">
<table class="tabel">
  <thead><tr><th>Kode</th><th>Nama</th>
    <?php foreach ($kolomTabel as $k): ?><th<?= in_array($k['tipe'], ['uang', 'angka', 'persen'], true) ? ' class="angka"' : '' ?>><?= e($k['label']) ?></th><?php endforeach; ?>
    <?php if ($anak): ?><th>Isi</th><?php endif; ?>
    <th class="angka">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($baris as $b):
      $id = (string) $b[$c['pk']];
      $adaAnak = 0;
      foreach ($anak as $kk => $cc) {
          $adaAnak += (int) ($b['anak_' . $kk] ?? 0);
      }
      $lindung = in_array($id, $c['dilindungi'] ?? [], true);
      $editUrl = url('admin/master_form.php?d=' . $kunci . '&id=' . rawurlencode($id) . ($indukId !== '' ? '&induk=' . rawurlencode($indukId) : ''));
      $jejak = master_jejak($b);
  ?>
    <tr>
      <td class="mono nowrap"><?= e($id) ?></td>
      <td><a class="tebal" href="<?= e($editUrl) ?>"><?= e((string) $b[$c['nama']]) ?></a><?php if ($jejak !== '' && $indukId === ''): ?><br><small class="muted"><?= e($jejak) ?></small><?php endif; ?>
        <?php if (isset($c['kolom'][0]) && $c['kolom'][0]['tipe'] === 'rel'): ?><br><small class="muted"><?= e((string) ($b['rel_' . $c['kolom'][0]['k']] ?? '')) ?></small><?php endif; ?></td>
      <?php foreach ($kolomTabel as $k): ?>
        <td<?= in_array($k['tipe'], ['uang', 'angka', 'persen'], true) ? ' class="angka"' : '' ?>><?= e(master_tampil($k, $b)) ?></td>
      <?php endforeach; ?>
      <?php if ($anak): ?>
        <td class="nowrap">
          <?php foreach ($anak as $kk => $cc): ?>
            <a class="link-kecil" href="<?= e(url('admin/master.php?d=' . $kk . '&induk=' . rawurlencode($id))) ?>"><?= angka($b['anak_' . $kk]) ?> <?= e(mb_strtolower($cc['judul'])) ?> &rsaquo;</a><br>
          <?php endforeach; ?>
        </td>
      <?php endif; ?>
      <td class="angka aksi-sel">
        <div class="aksi-baris">
          <a class="btn btn-ghost btn-sm" href="<?= e($editUrl) ?>"><?= ikon('edit', 14) ?> Ubah</a>
          <?php if ($bisaTambah && !$lindung): ?>
          <form method="post" data-konfirmasi="<?= e($adaAnak > 0
              ? 'Hapus “' . $b[$c['nama']] . '” beserta SEMUA data di bawahnya (' . $adaAnak . ' ' . implode('/', array_map(fn($x) => mb_strtolower($x['judul']), $anak)) . ' dan turunannya)? Tindakan ini tidak bisa dibatalkan.'
              : 'Hapus “' . $b[$c['nama']] . '”? Tindakan ini tidak bisa dibatalkan.') ?>">
            <?= csrf_field() ?><input type="hidden" name="d" value="<?= e($kunci) ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><?php if ($adaAnak > 0): ?><input type="hidden" name="semua" value="1"><?php endif; ?>
            <button class="btn btn-danger btn-sm" type="submit" title="Hapus" aria-label="Hapus <?= e((string) $b[$c['nama']]) ?>"><?= ikon('trash', 14) ?></button>
          </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<p class="muted kecil"><?= angka($total) ?> data<?= $total > $perHal ? ' · halaman ' . $halaman . ' dari ' . $totalHal : '' ?></p>
<?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
