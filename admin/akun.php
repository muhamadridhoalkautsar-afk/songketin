<?php
/**
 * Super Admin — Kelola Akun (satu-satunya tugas Super Admin): semua peran (pembeli, penjual, admin, super admin);
 * daftar, cari, tambah, ubah, atur ulang kata sandi, ubah hak akses admin, hapus.
 * Operasional (data master, pesanan, pengiriman, laporan, refund) adalah tugas Admin, bukan Super Admin.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/akun.php';
require_once __DIR__ . '/../includes/koin.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_super_admin();

// ---------------------------------------------------------------- aksi
if (is_post()) {
    csrf_check();
    $id = post('id');
    $target = $id !== '' ? row('SELECT id_user, username, role FROM users WHERE id_user = ?', [$id]) : null;
    if (!$target) {
        flash('error', 'Akun tidak ditemukan.');
    } elseif (post('aksi') === 'peran') {
        [$ok, $pesan] = ubah_peran_admin($target['id_user'], post('peran'), $u['id']);
        flash($ok ? 'ok' : 'error', $pesan);
        if ($ok && $target['id_user'] === $u['id']) {
            $_SESSION['user']['role'] = post('peran');
        }
    } elseif (post('aksi') === 'hapus') {
        [$ok, $pesan] = hapus_akun($target['id_user'], $u['id']);
        flash($ok ? 'ok' : 'error', $pesan);
    } elseif (post('aksi') === 'reset') {
        $baru = reset_sandi($target['id_user']);
        flash('ok', 'Kata sandi “' . $target['username'] . '” diatur ulang menjadi: ' . $baru . ' — catat dan berikan kepada pemiliknya; sandi ini hanya ditampilkan sekali.');
    }
    redirect('admin/akun.php' . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// ---------------------------------------------------------------- daftar
$q = trim(get('q'));
$peran = in_array(get('peran'), ['pembeli', 'penjual', 'admin', 'super_admin'], true) ? get('peran') : '';
$hitung = row("SELECT COUNT(*) AS semua,
                      COALESCE(SUM(CASE WHEN role = 'pembeli' THEN 1 ELSE 0 END), 0) AS pembeli,
                      COALESCE(SUM(CASE WHEN role = 'penjual' THEN 1 ELSE 0 END), 0) AS penjual,
                      COALESCE(SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END), 0) AS admin,
                      COALESCE(SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END), 0) AS super_admin FROM users");

$where = ['1 = 1'];
$par = [];
if ($peran !== '') {
    $where[] = 'u.role = ?';
    $par[] = $peran;
}
if ($q !== '') {
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)) . '%';
    $where[] = "(LOWER(u.username) LIKE ? ESCAPE '!' OR LOWER(u.email) LIKE ? ESCAPE '!' OR LOWER(COALESCE(pb.nama_pembeli, pj.nama_penjual, '')) LIKE ? ESCAPE '!')";
    array_push($par, $like, $like, $like);
}
$dari = ' FROM users u LEFT JOIN pembeli pb ON pb.id_user = u.id_user LEFT JOIN penjual pj ON pj.id_user = u.id_user
          LEFT JOIN tingkat_pembeli tb ON tb.id_tingkat_pembeli = pb.id_tingkat_pembeli
          LEFT JOIN tingkat_penjual tj ON tj.id_tingkat_penjual = pj.id_tingkat_penjual
          WHERE ' . implode(' AND ', $where);
$total = (int) val('SELECT COUNT(*)' . $dari, $par);
[$halaman, $totalHal, $offset] = paginasi($total, 10, (int) get('hal', '1'));
$akun = rows(
    'SELECT u.id_user, u.username, u.email, u.role, u.created_at, COALESCE(pb.nama_pembeli, pj.nama_penjual, u.username) AS nama,
            pb.id_desa AS desa_pembeli, pj.id_desa AS desa_penjual, pb.id_pembeli, tb.nama_tingkat_pembeli, tj.nama_tingkat_penjual,
            (SELECT COUNT(*) FROM produk x WHERE x.id_penjual = pj.id_penjual) AS jml_produk,
            (SELECT COUNT(*) FROM nota_pembeli y WHERE y.id_pembeli = pb.id_pembeli) AS jml_nota'
    . $dari . ' ORDER BY u.created_at DESC, u.id_user DESC LIMIT 10 OFFSET ' . (int) $offset,
    $par
);
$kueri = array_filter(['q' => $q, 'peran' => $peran], fn($v) => $v !== '');
$tabs = ['' => ['Semua', $hitung['semua']], 'pembeli' => ['Pembeli', $hitung['pembeli']], 'penjual' => ['Penjual', $hitung['penjual']], 'admin' => ['Admin', $hitung['admin']], 'super_admin' => ['Super Admin', $hitung['super_admin']]];
$jenisPeran = ['pembeli' => 'info', 'penjual' => 'ok', 'admin' => 'warn', 'super_admin' => 'bad'];

page_start('Kelola Akun', ['menu' => 'akun', 'judul' => 'Kelola Akun']);
?>
<div class="baris rata mb">
  <nav class="tab-status" aria-label="Filter peran" style="margin:0">
    <?php foreach ($tabs as $k => [$label, $n]): ?>
      <a class="chip-link <?= $peran === $k ? 'aktif' : '' ?>" href="?<?= e(http_build_query(array_filter(['peran' => $k, 'q' => $q], fn($v) => $v !== ''))) ?>"><?= e($label) ?> <small><?= (int) $n ?></small></a>
    <?php endforeach; ?>
  </nav>
  <span class="baris">
    <a class="btn btn-ghost" href="<?= e(url('admin/akun_form.php?peran=admin')) ?>"><?= ikon('plus', 16) ?> Tambah admin</a>
    <a class="btn btn-primary" href="<?= e(url('admin/akun_form.php')) ?>"><?= ikon('plus', 16) ?> Tambah akun</a>
  </span>
</div>

<form class="card card-flat filter-kartu" method="get">
  <?php if ($peran !== ''): ?><input type="hidden" name="peran" value="<?= e($peran) ?>"><?php endif; ?>
  <div class="filter" style="margin-bottom:0">
    <label class="field" style="flex:1 1 280px"><span class="field-label">Cari akun</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="nama, nama pengguna, atau email"></label>
    <div class="baris" style="margin-bottom:14px"><button class="btn btn-primary" type="submit"><?= ikon('search', 16) ?> Cari</button><?php if ($kueri): ?><a class="btn btn-ghost" href="<?= e(url('admin/akun.php')) ?>">Atur ulang</a><?php endif; ?></div>
  </div>
</form>

<?php if (!$akun): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Tidak ada akun yang cocok</h3><p>Coba kata kunci lain atau pilih peran yang berbeda.</p></div>
<?php else: ?>
<div class="tabel-bungkus">
<table class="tabel">
  <thead><tr><th>Akun</th><th>Peran</th><th>Keterangan</th><th>Terdaftar</th><th class="angka">Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($akun as $a): $saya = $a['id_user'] === $u['id']; ?>
    <tr>
      <td>
        <div class="thumb-baris"><span class="avatar-kecil" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($a['nama'], 0, 1))) ?></span>
          <div style="min-width:0"><a class="tebal" href="<?= e(url('admin/akun_form.php?id=' . rawurlencode($a['id_user']))) ?>"><?= e($a['nama']) ?></a><?= $saya ? ' <span class="badge badge-muted">Anda</span>' : '' ?>
            <small>@<?= e($a['username']) ?> · <?= e($a['email']) ?></small></div></div>
      </td>
      <td><?= badge(label_peran($a['role']), $jenisPeran[$a['role']] ?? 'muted') ?>
        <?php if (in_array($a['role'], PERAN_ADMIN, true)): $terakhir = $a['role'] === 'super_admin' && (int) $hitung['super_admin'] <= 1; ?>
          <form method="post" class="baris" style="margin-top:6px" data-konfirmasi="Ubah hak akses “<?= e($a['username']) ?>”?"><?= csrf_field() ?><input type="hidden" name="aksi" value="peran"><input type="hidden" name="id" value="<?= e($a['id_user']) ?>">
            <select name="peran" aria-label="Hak akses <?= e($a['username']) ?>" <?= $terakhir ? 'disabled title="Harus tersisa minimal satu Super Admin"' : '' ?>>
              <option value="admin" <?= $a['role'] === 'admin' ? 'selected' : '' ?>>Admin</option><option value="super_admin" <?= $a['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin</option></select>
            <button class="btn btn-ghost btn-sm" type="submit" <?= $terakhir ? 'disabled' : '' ?>>Simpan</button></form>
        <?php endif; ?></td>
      <td>
        <?php if ($a['role'] === 'pembeli'): ?>
          <?= e((string) $a['nama_tingkat_pembeli']) ?> · <?= angka($a['id_pembeli'] ? koin_ringkasan($a['id_pembeli'])['saldo'] : 0) ?> koin<br><small><?= angka($a['jml_nota']) ?> pesanan · <?= e(kota_dari_desa($a['desa_pembeli'])) ?></small>
        <?php elseif ($a['role'] === 'penjual'): ?>
          Tingkat <?= e((string) $a['nama_tingkat_penjual']) ?> · <?= angka($a['jml_produk']) ?> produk<br><small><?= e(kota_dari_desa($a['desa_penjual'])) ?></small>
        <?php endif; ?>
      </td>
      <td class="nowrap"><?= e(tgl($a['created_at'])) ?></td>
      <td class="angka aksi-sel">
        <div class="aksi-baris">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/akun_form.php?id=' . rawurlencode($a['id_user']))) ?>"><?= ikon('edit', 14) ?> Ubah</a>
          <form method="post" data-konfirmasi="Atur ulang kata sandi “<?= e($a['username']) ?>”? Kata sandi lamanya tidak berlaku lagi."><?= csrf_field() ?><input type="hidden" name="aksi" value="reset"><input type="hidden" name="id" value="<?= e($a['id_user']) ?>">
            <button class="btn btn-ghost btn-sm" type="submit" title="Atur ulang kata sandi" aria-label="Atur ulang kata sandi <?= e($a['username']) ?>"><?= ikon('key', 15) ?></button></form>
          <?php if (!$saya): ?>
          <form method="post" data-konfirmasi="Hapus akun “<?= e($a['username']) ?>” secara permanen? Akun yang punya riwayat pesanan tidak akan dihapus."><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= e($a['id_user']) ?>">
            <button class="btn btn-danger btn-sm" type="submit" title="Hapus akun" aria-label="Hapus akun <?= e($a['username']) ?>"><?= ikon('trash', 14) ?></button></form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= paginasi_html($halaman, $totalHal, $kueri) ?>
<?php endif; ?>
<?php page_end();
