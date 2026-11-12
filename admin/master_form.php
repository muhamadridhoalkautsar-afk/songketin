<?php
/** Dashboard admin — Data Master: formulir tambah / ubah satu data. Aturan isian ada di includes/master.php. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/master.php';
require_once __DIR__ . '/../includes/wilayah.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('admin');

$kunci = get('d');
$c = $kunci !== '' ? master_cfg($kunci) : null;
if (!$c) {
    abort404();
}
$idUrl = get('id');
$lama = $idUrl !== '' ? master_ambil($kunci, $idUrl) : null;
if ($idUrl !== '' && !$lama) {
    abort404();
}
$baru = $lama === null;
if ($baru && !empty($c['hanya_ubah'])) {
    abort404(); // status hanya boleh diubah namanya
}
$induk = $baru && isset($c['induk']) ? get('induk') : '';
$galat = [];
$f = null;

// ---------------------------------------------------------------- simpan
if (is_post()) {
    csrf_check();
    $f = master_isian_dari_post($c);
    [$galat, $nilai, $idBaru] = master_validasi($c, $f, $baru ? null : $lama[$c['pk']]);
    if (!$galat) {
        $fotoLama = [];
        $fotoBaru = [];
        try {
            [$nilai, $fotoLama] = master_siapkan_foto($c, $idBaru, $nilai, $baru ? null : $lama);
            foreach ($c['kolom'] as $k) {
                if ($k['tipe'] === 'foto' && !empty($nilai[$k['k']]) && (!$lama || $nilai[$k['k']] !== ($lama[$k['k']] ?? ''))) {
                    $fotoBaru[] = $nilai[$k['k']];
                }
            }
            transaksi(fn() => master_simpan($c, $idBaru, $nilai, $baru));
            foreach ($fotoLama as $path) {
                foto_hapus($path);
            }
        } catch (PDOException | RuntimeException $e) {
            foreach ($fotoBaru as $path) {
                foto_hapus($path); // jangan tinggalkan berkas yatim bila data gagal tersimpan
            }
            $galat[] = 'Data tidak tersimpan: ' . ($e instanceof RuntimeException ? $e->getMessage() : ($e->getCode() === '23000' ? 'nilai ini bertabrakan dengan data lain.' : 'terjadi galat database.'));
        }
    }
    if (!$galat) {
        $namaSimpan = (string) $nilai[$c['nama']];
        flash('ok', $baru ? $c['judul'] . ' “' . $namaSimpan . '” ditambahkan.' : $c['judul'] . ' “' . $namaSimpan . '” diperbarui.');
        $parent = isset($c['induk']) ? (string) ($nilai[$c['induk']['k']] ?? '') : '';
        $tujuan = array_filter(['d' => $kunci, 'induk' => $parent], fn($v) => $v !== '');
        redirect('admin/master.php?' . http_build_query($tujuan));
    }
}

if ($f === null) {
    $f = $baru ? master_isian_kosong($c, $induk) : master_isian_dari_baris($c, $lama);
}

$aksiUrl = url('admin/master_form.php?' . http_build_query(array_filter(['d' => $kunci, 'id' => $baru ? '' : $lama[$c['pk']], 'induk' => $induk], fn($v) => $v !== '')));
$kembali = url('admin/master.php?' . http_build_query(array_filter(['d' => $kunci, 'induk' => $baru ? $induk : (string) ($lama[$c['induk']['k'] ?? ''] ?? '')], fn($v) => $v !== '')));
$judulHalaman = ($baru ? 'Tambah ' : 'Ubah ') . mb_strtolower($c['judul']);

page_start($judulHalaman, ['menu' => 'master', 'judul' => $c['judul'], 'wilayah' => !empty($c['wilayah'])]);
?>
<div class="krumb"><a href="<?= e(url('admin/master.php')) ?>">Data Master</a> &rsaquo; <a href="<?= e($kembali) ?>"><?= e($c['judul']) ?></a> &rsaquo; <?= $baru ? 'Tambah' : 'Ubah' ?></div>
<h1><?= e(ucfirst($judulHalaman)) ?></h1>

<?php if ($galat): ?>
<div class="alert alert-error" role="alert"><span><b>Periksa kembali isian Anda:</b>
  <ul class="daftar-galat"><?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul></span></div>
<?php endif; ?>
<?php if (!empty($c['catatan'])): ?><div class="alert"><span><?= e($c['catatan']) ?></span></div><?php endif; ?>

<form method="post" action="<?= e($aksiUrl) ?>" class="card master-form" autocomplete="off" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <?php if ($c['id']['mode'] === 'manual' && $baru): ?>
    <label class="field"><span class="field-label">Kode <b class="req">*</b></span>
      <input type="text" name="kode" value="<?= e($f['kode']) ?>" maxlength="10" required autocapitalize="characters" spellcheck="false">
      <small class="hint"><?= e($c['id']['petunjuk']) ?></small></label>
  <?php elseif ($baru): ?>
    <p class="muted kecil">Kode dibuat otomatis setelah disimpan.</p>
  <?php else: ?>
    <div class="field"><span class="field-label">Kode</span><span class="mono kode-kecil"><?= e((string) $lama[$c['pk']]) ?></span></div>
  <?php endif; ?>

  <?php if (isset($c['induk'])): $ind = $c['induk']; ?>
    <?php if (!empty($c['wilayah'])): ?>
      <div class="field-label"><?= e($ind['label']) ?> <b class="req">*</b></div>
      <p class="hint" style="margin:0 0 8px">Pilih berjenjang dari atas. Data di bawahnya akan ikut mengikuti induk yang dipilih.</p>
      <?= wilayah_fields(master_rantai_induk($ind['ent'], (string) $f[$ind['k']]), $ind['ent']) ?>
    <?php else: ?>
      <label class="field"><span class="field-label"><?= e($ind['label']) ?> <b class="req">*</b></span>
        <select name="<?= e($ind['k']) ?>" required>
          <option value="">— pilih <?= e(mb_strtolower($ind['label'])) ?> —</option>
          <?php foreach (master_pilihan_induk($c) as $id => $nm): ?>
            <option value="<?= e((string) $id) ?>" <?= $f[$ind['k']] === (string) $id ? 'selected' : '' ?>><?= e((string) $nm) ?></option>
          <?php endforeach; ?>
        </select></label>
    <?php endif; ?>
  <?php endif; ?>

  <?php foreach ($c['kolom'] as $k): $nm = $k['k']; $v = (string) ($f[$nm] ?? ''); ?>
    <?php if ($k['tipe'] === 'rel'): ?>
      <label class="field"><span class="field-label"><?= e($k['label']) ?><?= $k['wajib'] ? ' <b class="req">*</b>' : '' ?></span>
        <select name="<?= e($nm) ?>"<?= $k['wajib'] ? ' required' : '' ?>>
          <option value=""><?= $k['wajib'] ? '— pilih —' : '— tidak ada —' ?></option>
          <?php foreach (master_pilihan_rel($k) as $id => $teks): ?>
            <option value="<?= e((string) $id) ?>" <?= $v === (string) $id ? 'selected' : '' ?>><?= e((string) $teks) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($k['petunjuk'])): ?><small class="hint"><?= e($k['petunjuk']) ?></small><?php endif; ?></label>
    <?php elseif ($k['tipe'] === 'foto'): ?>
      <div class="field"><span class="field-label"><?= e($k['label']) ?></span>
        <?php if ($v !== '' && foto_url($v)): ?><img src="<?= e(foto_url($v)) ?>" alt="Foto saat ini" style="max-width:160px;max-height:160px;border-radius:10px;border:1px solid var(--garis,#e2d4b6);margin-bottom:8px;display:block"><?php endif; ?>
        <input type="file" name="<?= e($nm) ?>" accept="image/jpeg,image/png,image/webp">
        <?php if (!empty($k['petunjuk'])): ?><small class="hint"><?= e($k['petunjuk']) ?><?= $v !== '' ? ' Pilih berkas baru untuk mengganti foto saat ini.' : '' ?></small><?php endif; ?></div>
    <?php elseif (in_array($k['tipe'], ['uang', 'angka'], true)): ?>
      <label class="field"><span class="field-label"><?= e($k['label']) ?><?= $k['wajib'] ? ' <b class="req">*</b>' : '' ?></span>
        <input type="text" inputmode="numeric" name="<?= e($nm) ?>" value="<?= e($v) ?>"<?= $k['wajib'] ? ' required' : '' ?> placeholder="0">
        <?php if (!empty($k['petunjuk'])): ?><small class="hint"><?= e($k['petunjuk']) ?></small><?php endif; ?></label>
    <?php elseif ($k['tipe'] === 'persen'): ?>
      <label class="field"><span class="field-label"><?= e($k['label']) ?><?= $k['wajib'] ? ' <b class="req">*</b>' : '' ?></span>
        <input type="text" inputmode="decimal" name="<?= e($nm) ?>" value="<?= e($v) ?>"<?= $k['wajib'] ? ' required' : '' ?> placeholder="0">
        <?php if (!empty($k['petunjuk'])): ?><small class="hint"><?= e($k['petunjuk']) ?></small><?php endif; ?></label>
    <?php else: ?>
      <label class="field"><span class="field-label"><?= e($k['label']) ?><?= $k['wajib'] ? ' <b class="req">*</b>' : '' ?></span>
        <input type="text" name="<?= e($nm) ?>" value="<?= e($v) ?>" maxlength="<?= (int) $k['maks'] ?>"<?= $k['wajib'] ? ' required' : '' ?>>
        <?php if (!empty($k['petunjuk'])): ?><small class="hint"><?= e($k['petunjuk']) ?></small><?php endif; ?></label>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="baris rata mt">
    <a class="btn btn-ghost" href="<?= e($kembali) ?>">Batal</a>
    <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> <?= $baru ? 'Simpan data baru' : 'Simpan perubahan' ?></button>
  </div>
</form>
<?php page_end();
