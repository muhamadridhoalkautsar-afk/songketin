<?php
/** Keranjang belanja (disimpan di sesi). Menerima aksi: tambah, beli, ubah, hapus, kosongkan. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $id = post('id');
    $qty = max(1, (int) post('qty', '1'));
    if (in_array($aksi, ['tambah', 'beli'], true)) {
        [$ok, $pesan] = cart_add($id, $qty);
        if (is_ajax()) {
            json_out(['ok' => $ok, 'pesan' => $pesan, 'jumlah' => cart_count()]);
        }
        if ($ok && $aksi === 'beli') {
            redirect('pembeli/checkout.php');
        }
        flash($ok ? 'ok' : 'error', $pesan);
        $balik = $_SERVER['HTTP_REFERER'] ?? '';
        redirect($ok && $balik !== '' ? $balik : 'pembeli/keranjang.php');
    }
    if ($aksi === 'ubah') {
        cart_set($id, (int) post('qty', '1'));
    } elseif ($aksi === 'hapus') {
        cart_remove($id);
        flash('info', 'Barang dikeluarkan dari keranjang.');
    } elseif ($aksi === 'kosongkan') {
        $_SESSION['cart'] = [];
        flash('info', 'Keranjang dikosongkan.');
    }
    redirect('pembeli/keranjang.php');
}

$kr = cart_load();
foreach ($kr['peringatan'] as $w) {
    flash('warn', $w);
}
$hemat = 0;
foreach ($kr['grup'] as $g) {
    foreach ($g['items'] as $it) {
        $hemat += (float) $it['besaran_potongan_harga'] * $it['qty'];
    }
}

page_start('Keranjang', ['menu' => 'keranjang']);
?>
<h1>Keranjang belanja</h1>
<?php if (!$kr['grup']): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?><h3>Keranjang Anda masih kosong</h3><p>Belum ada kain yang dipilih. Yuk, jelajahi katalog tenun.</p><a class="btn btn-primary btn-lg" href="<?= e(url('pembeli/index.php')) ?>">Mulai belanja</a></div>
<?php else: ?>
<div class="dua-kolom">
  <div>
    <?php foreach ($kr['grup'] as $g): ?>
    <section class="card mb">
      <div class="toko-kepala"><?= ikon('home', 18) ?> <?= e($g['nama_penjual']) ?></div>
      <?php foreach ($g['items'] as $it): ?>
      <div class="item-baris">
        <a href="<?= e(url('pembeli/produk.php?id=' . rawurlencode($it['id_produk']))) ?>"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" loading="lazy"></a>
        <div>
          <a class="tebal" href="<?= e(url('pembeli/produk.php?id=' . rawurlencode($it['id_produk']))) ?>"><?= e($it['nama_produk']) ?></a>
          <div class="harga" style="padding-top:2px"><strong style="font-size:1rem"><?= rp($it['harga_akhir']) ?></strong><?php if ((float) $it['besaran_potongan_harga'] > 0): ?><s><?= rp($it['harga_produk']) ?></s><?php endif; ?></div>
          <div class="baris" style="margin-top:8px">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="ubah"><input type="hidden" name="id" value="<?= e($it['id_produk']) ?>">
              <div class="qty"><button type="button" data-qty="-1" aria-label="Kurangi">−</button><input type="number" name="qty" value="<?= (int) $it['qty'] ?>" min="1" max="<?= (int) $it['stok'] ?>" data-auto-submit aria-label="Jumlah"><button type="button" data-qty="1" aria-label="Tambah">+</button></div></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= e($it['id_produk']) ?>"><button class="link-hapus" type="submit"><?= ikon('trash', 15) ?> Hapus</button></form>
            <?php if ((int) $it['stok'] <= 5): ?><span class="badge badge-warn">Sisa <?= (int) $it['stok'] ?></span><?php endif; ?>
          </div>
        </div>
        <div class="tebal mono kanan"><?= rp($it['subtotal']) ?></div>
      </div>
      <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
    <form method="post" data-konfirmasi="Kosongkan seluruh keranjang?"><?= csrf_field() ?><input type="hidden" name="aksi" value="kosongkan"><button class="link-hapus" type="submit">Kosongkan keranjang</button></form>
  </div>

  <aside class="card sticky">
    <h3>Ringkasan belanja</h3>
    <div class="ringkas-baris"><span>Barang (<?= (int) $kr['jumlah'] ?>)</span><span><?= rp($kr['sub']) ?></span></div>
    <?php if ($hemat > 0): ?><div class="ringkas-baris minus"><span>Hemat dari potongan produk</span><span><?= rp($hemat) ?></span></div><?php endif; ?>
    <div class="ringkas-baris total"><span>Subtotal</span><span><?= rp($kr['sub']) ?></span></div>
    <p class="kecil">Ongkos kirim, diskon ongkir, dan koin dihitung di langkah pemesanan.</p>
    <a class="btn btn-primary btn-lg btn-block" href="<?= e(url('pembeli/checkout.php')) ?>">Lanjut ke pemesanan</a>
    <a class="btn btn-ghost btn-block mt" href="<?= e(url('pembeli/index.php')) ?>">Tambah barang lain</a>
  </aside>
</div>
<?php endif; ?>
<?php page_end();
