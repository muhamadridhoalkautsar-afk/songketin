<?php
/** Pemesanan: alamat, pengiriman, diskon ongkir, koin, cara pembayaran → buat pesanan. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');
$pb = profil_pembeli();
$kr = cart_load();
foreach ($kr['peringatan'] as $w) {
    flash('warn', $w);
}
if (!$kr['grup']) {
    flash('warn', 'Keranjang Anda masih kosong.');
    redirect('pembeli/keranjang.php');
}
if (empty($pb['id_desa'])) {
    flash('warn', 'Lengkapi alamat pengiriman Anda terlebih dahulu.');
    redirect('pembeli/profil.php');
}

// Pratinjau tiap opsi pengiriman (jumlah ongkir seluruh toko + estimasi terlama) untuk alamat ini.
$opsiSemua = opsi_pengiriman_semua();
$pratinjau = [];
foreach ($opsiSemua as $o) {
    $total = 0;
    $hari = 0;
    $ok = true;
    foreach ($kr['grup'] as $g) {
        $r = hitung_ongkir($o, zona_kirim($g['desa_penjual'], $pb['id_desa']), $g['qty']);
        if (!$r) {
            $ok = false;
            break;
        }
        $total += $r[0];
        $hari = max($hari, $r[1]);
    }
    $pratinjau[$o['id_opsi_pengiriman']] = ['ok' => $ok, 'ongkir' => $total, 'hari' => $hari];
}
$pertama = '';
foreach ($opsiSemua as $o) {
    if ($pratinjau[$o['id_opsi_pengiriman']]['ok']) {
        $pertama = $o['id_opsi_pengiriman'];
        break;
    }
}

$sumber = is_post() ? $_POST : $_GET;
$pilih = [
    'opsi' => isset($sumber['opsi']) && is_string($sumber['opsi']) ? $sumber['opsi'] : $pertama,
    'diskon_ongkir' => (string) ($sumber['diskon_ongkir'] ?? ''),
    'koin' => !empty($sumber['koin']) ? '1' : '',
    'jenis' => isset($sumber['jenis']) && is_string($sumber['jenis']) ? $sumber['jenis'] : '',
];
$daftarBayar = jenis_bayar_semua();

if (is_post() && post('aksi') === 'pesan') {
    csrf_check();
    $hit = hitung_checkout($kr, $pb, $pilih);
    $jb = $pilih['jenis'] !== '' ? jenis_bayar_info($pilih['jenis']) : null;
    if (!$hit['siap']) {
        flash('error', $hit['galat'][0] ?? 'Pilih opsi pengiriman yang tersedia terlebih dahulu.');
    } elseif (!$jb) {
        flash('error', 'Pilih cara pembayaran terlebih dahulu.');
    } else {
        try {
            $id = buat_pesanan($kr, $pb, $hit, $jb['id_jenis_pembayaran'], $jb['tunai']);
            $_SESSION['cart'] = [];
            if ($jb['tunai']) {
                flash('ok', 'Pesanan ' . $id . ' dibuat dan dikonfirmasi. Siapkan pembayaran tunai saat barang tiba.');
            } else {
                flash('ok', 'Pesanan ' . $id . ' dibuat. Selesaikan pembayaran dan unggah buktinya.');
            }
            redirect('pembeli/bayar.php?id=' . $id);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('pembeli/keranjang.php');
        }
    }
}

$hit = hitung_checkout($kr, $pb, $pilih);
$dOng = diskon_ongkir_semua();
$tingkat = tingkat_pembeli_row($pb['id_tingkat_pembeli']);

page_start('Pemesanan', ['menu' => 'keranjang'], );
?>
<h1>Pemesanan</h1>
<form method="post" id="form-checkout" data-hitung="<?= e(url('api/hitung.php')) ?>">
<?= csrf_field() ?><input type="hidden" name="aksi" value="pesan">
<div class="dua-kolom">
  <div>
    <section class="card mb">
      <div class="card-head"><h3>1. Alamat pengiriman</h3><a class="btn btn-ghost btn-sm" href="<?= e(url('pembeli/profil.php')) ?>"><?= ikon('edit', 14) ?> Ubah</a></div>
      <strong><?= e($pb['nama_pembeli']) ?></strong>
      <p class="muted" style="margin:2px 0 0"><?= e(alamat_teks($pb['detail_alamat'], $pb['id_desa'])) ?></p>
    </section>

    <section class="card mb">
      <h3>2. Barang yang dipesan</h3>
      <?php foreach ($kr['grup'] as $g): ?>
        <div class="toko-kepala"><?= ikon('home', 16) ?> <?= e($g['nama_penjual']) ?></div>
        <?php foreach ($g['items'] as $it): ?>
          <div class="item-baris" style="grid-template-columns:56px minmax(0,1fr) auto;padding:8px 0">
            <img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" style="width:56px;height:56px"><div><?= e($it['nama_produk']) ?><br><small><?= (int) $it['qty'] ?> × <?= rp($it['harga_akhir']) ?></small></div><div class="tebal mono"><?= rp($it['subtotal']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <a class="kecil" href="<?= e(url('pembeli/keranjang.php')) ?>">Ubah isi keranjang</a>
    </section>

    <section class="card mb">
      <h3>3. Pengiriman</h3>
      <p class="kecil">Ongkos kirim dihitung dari kota toko ke alamat Anda, jumlah barang, dan layanan yang dipilih.</p>
      <?php $jenisSekarang = null; ?>
      <div class="pilih-kartu">
      <?php foreach ($opsiSemua as $o): $pv = $pratinjau[$o['id_opsi_pengiriman']];
          if ($jenisSekarang !== $o['id_jenis_pengiriman']) { $jenisSekarang = $o['id_jenis_pengiriman']; echo '<div class="seksi-judul" style="margin:8px 0 0">' . e($o['nama_jenis_pengiriman']) . '</div>'; } ?>
        <label class="opsi" <?= $pv['ok'] ? '' : 'style="opacity:.55;cursor:not-allowed"' ?>>
          <input type="radio" name="opsi" value="<?= e($o['id_opsi_pengiriman']) ?>" <?= $pilih['opsi'] === $o['id_opsi_pengiriman'] ? 'checked' : '' ?> <?= $pv['ok'] ? '' : 'disabled' ?>>
          <span style="flex:1"><b><?= e($o['nama_opsi_pengiriman']) ?></b>
            <small><?= $pv['ok'] ? 'Estimasi tiba ' . e(hari_teks($pv['hari'])) : 'Tidak melayani tujuan ini (hanya dalam satu kota)' ?></small></span>
          <span class="tebal mono"><?= $pv['ok'] ? rp($pv['ongkir']) : '—' ?></span>
        </label>
      <?php endforeach; ?>
      </div>
    </section>

    <section class="card mb">
      <h3>4. Diskon ongkir &amp; koin</h3>
      <div class="field-label" style="margin-bottom:8px">Diskon ongkos kirim</div>
      <div class="pilih-kartu mb">
        <label class="opsi"><input type="radio" name="diskon_ongkir" value="" <?= $pilih['diskon_ongkir'] === '' ? 'checked' : '' ?>><span><b>Tanpa diskon ongkir</b></span></label>
        <?php foreach ($dOng as $d): ?>
          <label class="opsi"><input type="radio" name="diskon_ongkir" value="<?= e($d['id_diskon_ongkir']) ?>" <?= $pilih['diskon_ongkir'] === $d['id_diskon_ongkir'] ? 'checked' : '' ?>>
            <span><b><?= e($d['nama_diskon_ongkir']) ?></b><small>Potongan hingga <?= rp($d['besaran_potongan_ongkir']) ?> · minimal ongkir <?= rp($d['minimal_ongkir']) ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field-label" style="margin-bottom:8px">Koin Songketin</div>
      <?php $bisaKoin = $hit['maks_koin'] > 0; ?>
      <label class="opsi" <?= $bisaKoin ? '' : 'style="opacity:.55;cursor:not-allowed"' ?>>
        <input type="checkbox" name="koin" value="1" <?= $pilih['koin'] !== '' && $bisaKoin ? 'checked' : '' ?> <?= $bisaKoin ? '' : 'disabled' ?>>
        <span><b>Pakai koin</b>
          <small>Saldo <?= angka($hit['saldo_koin']) ?> koin · 1 koin = <?= rp(NILAI_KOIN) ?>.
            <?= $bisaKoin ? 'Dipakai ' . angka($hit['maks_koin']) . ' koin (' . rp($hit['maks_koin'] * NILAI_KOIN) . ') — maksimal ' . KOIN_MAKS_PERSEN . '% dari harga barang.' : ($hit['saldo_koin'] > 0 ? 'Belum bisa dipakai pada pesanan ini.' : 'Anda belum punya koin. Koin didapat dari belanja yang sudah diterima.') ?></small></span></label>
      <p class="kecil mt">Tingkat Anda: <b><?= e($tingkat['nama_tingkat_pembeli']) ?></b><?= (float) $tingkat['besar_diskon'] > 0 ? ' — diskon ' . rtrim(rtrim(number_format((float) $tingkat['besar_diskon'], 2, ',', ''), '0'), ',') . '% otomatis dihitung.' : '. Kumpulkan koin untuk naik tingkat.' ?></p>
    </section>

    <section class="card mb">
      <h3>5. Cara pembayaran</h3>
      <?php if (!$daftarBayar): ?>
        <div class="alert alert-warn"><span>Admin belum menyiapkan cara pembayaran, sehingga pesanan belum bisa dibuat.</span></div>
      <?php else: $metodeSekarang = null; ?>
      <div class="pilih-kartu">
        <?php foreach ($daftarBayar as $j):
            if ($metodeSekarang !== $j['id_metode_pembayaran']) { $metodeSekarang = $j['id_metode_pembayaran']; echo '<div class="seksi-judul" style="margin:8px 0 0">' . e($j['nama_sifat_pembayaran'] . ' · ' . $j['nama_metode_pembayaran']) . '</div>'; } ?>
          <label class="opsi">
            <input type="radio" name="jenis" value="<?= e($j['id_jenis_pembayaran']) ?>" <?= $pilih['jenis'] === $j['id_jenis_pembayaran'] ? 'checked' : '' ?> required>
            <span><b><?= e($j['nama_jenis_pembayaran']) ?></b>
              <small><?= $j['tunai'] ? 'Bayar tunai ke kurir saat pesanan tiba; pesanan langsung diproses.' : 'Bayar sekarang, lalu unggah bukti pembayaran. Admin memverifikasinya.' ?></small></span></label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="card sticky">
    <h3>Ringkasan pesanan</h3>
    <div id="ringkasan"><?= ringkasan_checkout_html($hit) ?></div>
    <button class="btn btn-gold btn-lg btn-block mt" id="btn-pesan" type="submit" <?= $hit['siap'] && $daftarBayar ? '' : 'disabled' ?>>Buat pesanan</button>
    <p class="kecil mt">Pesanan dibuat sesuai cara pembayaran yang Anda pilih. Pembayaran non-tunai dilanjutkan dengan unggah bukti.</p>
  </aside>
</div>
</form>
<?php page_end(['js/checkout.js']);
