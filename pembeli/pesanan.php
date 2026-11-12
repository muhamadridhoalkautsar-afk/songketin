<?php
/** Pesanan Saya: daftar pesanan, detail + pelacakan, konfirmasi barang diterima, pembatalan. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');
$pb = profil_pembeli();

/** Nota milik pembeli ini, atau tampilkan 404 (pembeli lain tidak boleh tahu nota itu ada). */
function nota_milik(string $id, array $pb): array
{
    $n = $id !== '' ? nota_lengkap($id) : null;
    if (!$n || $n['id_pembeli'] !== $pb['id_pembeli']) {
        abort404();
    }
    return $n;
}

// ---------------------------------------------------------------- aksi
if (is_post()) {
    csrf_check();
    $n = nota_milik(post('id'), $pb);
    $aksi = post('aksi');
    if ($aksi === 'terima') {
        $toko = refund_toko($n, post('npj'));
        if (!$toko) {
            flash('error', 'Pesanan toko itu tidak ditemukan.');
        } elseif (!bisa_konfirmasi_terima($toko)) {
            flash('warn', 'Pesanan baru bisa dikonfirmasi setelah tiba di kota Anda.');
        } elseif (refund_menahan_kirim($toko['id_nota_penjual']) || refund_status_toko($toko) !== null) {
            flash('warn', 'Pesanan ini sedang dalam proses refund.');
        } else {
            $dapat = intdiv(max(0, (int) round((float) $toko['total_harga_jual'])), RUPIAH_PER_KOIN_DIDAPAT);
            selesaikan_nota_penjual($toko['id_nota_penjual']);
            flash('ok', 'Terima kasih! Pesanan dari ' . $toko['nama_penjual'] . ' ditandai diterima' . ($dapat > 0 ? ' dan Anda mendapat ' . angka($dapat) . ' koin.' : '.'));
        }
    } elseif ($aksi === 'ulasan') {
        // Tulis ulasan baru, atau ubah ulasan yang sudah ada (rating, komentar, foto).
        $toko = refund_toko($n, post('npj'));
        $rating = (float) post('rating');
        $komentar = post('komentar');
        $galatU = null;
        if (!$toko || $toko['id_status_pengiriman'] !== SK_DITERIMA) {
            $galatU = 'Ulasan hanya bisa ditulis setelah pesanan diterima.';
        } elseif (refund_status_toko($toko) !== null) {
            $galatU = 'Pesanan ini sedang atau sudah dalam proses refund, ulasan tidak bisa diubah.';
        } elseif ($rating < 1 || $rating > 5 || floor($rating) != $rating) {
            $galatU = 'Pilih rating 1 sampai 5 bintang.';
        } elseif (mb_strlen($komentar) > 500) {
            $galatU = 'Komentar maksimal 500 karakter.';
        } else {
            $galatU = foto_cek($_FILES['foto'] ?? null, false, 100);
        }
        if ($galatU) {
            flash('error', $galatU);
        } else {
            $ubah = $toko['rating'] !== null;
            $lama = (string) ($toko['foto_ulasan'] ?? '');
            $path = $lama;
            if (foto_ada_unggahan($_FILES['foto'] ?? null)) {
                $baru = foto_simpan($_FILES['foto'], 'ulasan', $toko['id_nota_penjual']);
                if ($baru === null) {
                    flash('warn', 'Foto ulasan gagal disimpan' . ($ubah ? '; foto lama tetap dipakai.' : '; ulasan dikirim tanpa foto.'));
                } else {
                    $path = $baru;
                }
            } elseif ($ubah && post('hapus_foto') === '1') {
                $path = '';
            }
            q('UPDATE nota_penjual SET rating = ?, komentar = ?, foto_ulasan = ? WHERE id_nota_penjual = ?',
                [$rating, $komentar !== '' ? $komentar : null, $path !== '' ? $path : null, $toko['id_nota_penjual']]);
            if ($lama !== '' && $lama !== $path) {
                foto_hapus($lama); // foto lama dibersihkan setelah diganti atau dihapus
            }
            flash('ok', $ubah ? 'Ulasan Anda diperbarui.' : 'Terima kasih, ulasan Anda tersimpan.');
        }
    } elseif ($aksi === 'batal') {
        if ($n['status_pembayaran'] === BAYAR_LUNAS) {
            flash('warn', 'Pesanan ini sudah lunas. Gunakan “Ajukan refund” per toko pada halaman status pesanan.');
        } else {
            [$ok, $pesan] = batalkan_nota($n['id_nota_pembeli']);
            flash($ok ? 'ok' : 'error', $pesan);
        }
    }
    redirect('pembeli/pesanan.php?id=' . rawurlencode($n['id_nota_pembeli']));
}

// ================================================================ DETAIL
if (get('id') !== '') {
    $n = nota_milik(get('id'), $pb);
    $idNota = $n['id_nota_pembeli'];
    $st = $n['status_pembayaran'];
    [$labelSt, $jenisSt] = status_nota_lengkap($n);
    $perluBayar = in_array($st, [BAYAR_BELUM, BAYAR_DITOLAK], true);
    $bisaBatal = bisa_dibatalkan($n) && $st !== BAYAR_LUNAS;

    page_start('Pesanan ' . $idNota, ['menu' => 'pesanan']);
    ?>
<div class="krumb"><a href="<?= e(url('pembeli/pesanan.php')) ?>">Pesanan Saya</a> &rsaquo; <?= e($idNota) ?></div>
<div class="nota-kepala">
  <div><h1>Pesanan <?= e($idNota) ?></h1><span class="muted">Dipesan <?= e(tgl($n['tanggal_pembelian'], true)) ?></span></div>
  <?= badge($labelSt, $jenisSt) ?>
</div>

<div class="dua-kolom mt">
  <div>
    <?php foreach ($n['toko'] as $t):
        $spToko = refund_status_toko($t);
        [$bolehRefund, $pesanRefund] = $spToko === null ? refund_cek_boleh($n, $t) : [false, ''];
        $urlRefund = url('pembeli/refund.php?npj=' . rawurlencode($t['id_nota_penjual'])); ?>
    <section class="card mb">
      <div class="card-head"><h3><?= ikon('home', 18) ?> <?= e($t['nama_penjual']) ?></h3><div class="baris"><?= badge((string) $t['keterangan_status_pengiriman']) ?><?= $spToko ? refund_badge($spToko) : '' ?></div></div>
      <?= lacak_html(langkah_lacak($n, $t)) ?>
      <?php foreach ($t['items'] as $it): ?>
        <div class="item-baris ringkas">
          <a href="<?= e(url('pembeli/produk.php?id=' . rawurlencode($it['id_produk']))) ?>"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" style="width:64px;height:64px" loading="lazy"></a>
          <div><a class="tebal" href="<?= e(url('pembeli/produk.php?id=' . rawurlencode($it['id_produk']))) ?>"><?= e($it['nama_produk']) ?></a><br><small><?= (int) $it['qty'] ?> × <?= rp((float) $it['sub_harga'] / max(1, (int) $it['qty'])) ?></small></div>
          <div class="tebal mono"><?= rp($it['sub_harga']) ?></div>
        </div>
      <?php endforeach; ?>
      <dl class="kunci-nilai mt">
        <dt>Pengiriman</dt><dd><?= e($t['nama_opsi_pengiriman'] ?? '—') ?> <small>(<?= e($t['nama_jenis_pengiriman'] ?? '—') ?>)</small></dd>
        <dt>Rute</dt><dd><?= e((string) $t['kota_asal']) ?> &rarr; <?= e((string) $t['kota_tujuan']) ?></dd>
        <dt><?= $t['id_status_pengiriman'] === SK_DITERIMA ? 'Tiba pada' : 'Estimasi' ?></dt>
        <dd><?php
            if ($t['id_status_pengiriman'] === SK_DITERIMA) {
                echo e(tgl($t['tanggal_sampai'], true));
            } elseif ($t['id_status_pengiriman'] === SK_BATAL) {
                echo '—';
            } else {
                echo (int) $t['lama_pengiriman'] === 0 ? 'Hari yang sama' : 'Sekitar ' . e(hari_teks((int) $t['lama_pengiriman']));
            } ?></dd>
        <dt>Ongkos kirim</dt><dd><?= rp($t['ongkos_kirim']) ?></dd>
      </dl>
      <?php if (bisa_konfirmasi_terima($t) && $spToko === null): ?>
        <form method="post" class="mt" data-konfirmasi="Pastikan paket sudah Anda terima dengan baik. Lanjutkan?">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="terima"><input type="hidden" name="id" value="<?= e($idNota) ?>"><input type="hidden" name="npj" value="<?= e($t['id_nota_penjual']) ?>">
          <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Pesanan diterima</button>
          <small style="margin-left:8px">Tekan setelah paket sampai di tangan Anda.</small>
        </form>
      <?php endif; ?>

      <?php if ($spToko !== null || $bolehRefund || ($t['id_status_pengiriman'] === SK_DITERIMA && $pesanRefund !== '')): ?>
        <div class="refund-baris">
          <?php if ($spToko !== null): ?>
            <span class="kecil">Refund:</span> <?= refund_badge($spToko) ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($urlRefund) ?>"><?= $spToko === SP_RF_TOLAK ? 'Lihat alasan &amp; ajukan banding' : 'Lihat rincian' ?></a>
          <?php elseif ($bolehRefund): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($urlRefund) ?>"><?= ikon('back', 14) ?> Ajukan refund</a>
            <small><?= $t['id_status_pengiriman'] === SK_DITERIMA ? 'Barang rusak atau tidak sesuai? Bisa diajukan sampai ' . e(tgl(date('Y-m-d H:i:s', (int) refund_batas_akhir($t)))) . '.' : 'Pesanan sudah lunas dan belum dikirim; dana dikembalikan bila penjual menyetujui.' ?></small>
          <?php else: ?>
            <small class="muted"><?= e($pesanRefund) ?></small>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($t['id_status_pengiriman'] === SK_DITERIMA && $spToko === null): ?>
        <div class="ulasan-kotak">
        <?php if ($t['rating'] !== null): ?>
          <div class="ulasan-judul"><b>Ulasan Anda</b> <?= bintang_html((float) $t['rating']) ?></div>
          <?php if ($t['komentar']): ?><p class="ulasan-isi"><?= e($t['komentar']) ?></p><?php endif; ?>
          <?php if ($t['foto_ulasan'] && foto_url($t['foto_ulasan'])): ?><img class="ulasan-foto" src="<?= e(foto_url($t['foto_ulasan'])) ?>" alt="Foto ulasan Anda" loading="lazy"><?php endif; ?>
          <details class="mt"><summary class="link-ubah">Ubah ulasan</summary><div class="mt"><?= ulasan_form_html($idNota, $t['id_nota_penjual'], $t) ?></div></details>
        <?php else: ?>
          <div class="ulasan-judul"><b>Bagaimana pesanan dari <?= e($t['nama_penjual']) ?>?</b></div>
          <p class="muted" style="margin:2px 0 10px"><small>Ulasan Anda berlaku untuk semua barang dari toko ini pada pesanan ini dan tampil di halaman produknya.</small></p>
          <?= ulasan_form_html($idNota, $t['id_nota_penjual']) ?>
        <?php endif; ?>
        </div>
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
    <div class="ringkas-baris"><span>Status</span><span><?= badge($st, $st === BAYAR_DITOLAK ? 'bad' : null) ?></span></div>
    <?php if ($st === BAYAR_DITOLAK): ?>
      <div class="alert alert-warn kecil catatan-tolak" role="alert"><span><b>Pembayaran Anda ditolak admin.</b>
        <?= $n['catatan_tolak'] !== null && $n['catatan_tolak'] !== '' ? '<br><b>Catatan admin:</b> ' . nl2br(e($n['catatan_tolak'])) : '<br>Admin tidak menambahkan catatan.' ?>
        <br>Silakan bayar ulang dengan bukti yang benar.</span></div>
    <?php endif; ?>
    <?php if ($n['id_jenis_pembayaran']): ?>
      <div class="ringkas-baris kecil"><span>Cara bayar</span><span><?= e((string) $n['nama_sifat_pembayaran']) ?> · <?= e((string) $n['nama_metode_pembayaran']) ?> · <?= e((string) $n['nama_jenis_pembayaran']) ?></span></div>
    <?php endif; ?>
    <hr>
    <?= rincian_biaya_html($n) ?>
    <div class="mt" style="display:grid;gap:10px">
      <?php if ($perluBayar): ?>
        <a class="btn btn-gold btn-lg btn-block" href="<?= e(url('pembeli/bayar.php?id=' . rawurlencode($idNota))) ?>"><?= $st === BAYAR_DITOLAK ? 'Bayar ulang' : 'Bayar sekarang' ?></a>
      <?php elseif ($st === BAYAR_VERIF): ?>
        <a class="btn btn-ghost btn-block" href="<?= e(url('pembeli/bayar.php?id=' . rawurlencode($idNota))) ?>">Lihat instruksi pembayaran</a>
      <?php endif; ?>
      <a class="btn btn-primary btn-block" href="<?= e(url_struk($idNota, true)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('download', 16) ?> Unduh struk (PDF)</a>
      <a class="btn btn-ghost btn-block" href="<?= e(url_struk($idNota)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('file', 16) ?> Lihat &amp; cetak struk</a>
    </div>
    <?php if ($bisaBatal): ?>
      <form method="post" class="mt tengah" data-konfirmasi="Batalkan pesanan ini? Stok dan koin akan dikembalikan.">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= e($idNota) ?>"><button class="link-hapus" type="submit">Batalkan pesanan</button>
      </form>
    <?php endif; ?>
  </aside>
</div>
<?php
    page_end(['js/ulasan.js']);
    exit;
}

// ================================================================ DAFTAR
$semua = rows(
    'SELECT nb.id_nota_pembeli, nb.tanggal_pembelian, nb.total_harga_beli, nb.total_barang_beli, (' . sql_status_bayar('nb') . ') AS status_pembayaran,
            (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli) AS jml_toko,
            (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli AND np.id_status_pengiriman = ?) AS jml_terima,
            (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli AND np.id_status_pengiriman = ?) AS jml_batal,
            (SELECT COUNT(*) FROM nota_penjual np WHERE np.id_nota_pembeli = nb.id_nota_pembeli AND np.id_status_pengiriman IN (?, ?, ?, ?)) AS jml_kirim,
            (SELECT MIN(rp.id_status_pesanan) FROM rincian_pesanan rp JOIN nota_penjual nq ON nq.id_nota_penjual = rp.id_nota_penjual
              WHERE nq.id_nota_pembeli = nb.id_nota_pembeli AND rp.id_status_pesanan IN (' . in_marks(SP_REFUND_SEMUA) . ')) AS refund_sp
       FROM nota_pembeli nb WHERE nb.id_pembeli = ?
      ORDER BY nb.tanggal_pembelian DESC, nb.id_nota_pembeli DESC',
    array_merge([SK_DITERIMA, SK_BATAL, SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR], SP_REFUND_SEMUA, [$pb['id_pembeli']])
);
$tabs = ['' => 'Semua', 'bayar' => 'Perlu dibayar', 'proses' => 'Diproses', 'kirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Dibatalkan', 'refund' => 'Refund'];
$tab = isset($tabs[get('status')]) ? get('status') : '';
$hitung = array_fill_keys(array_keys($tabs), 0);
$tampilkan = [];
foreach ($semua as $r) {
    $s = status_ringkas($r);
    $r['_label'] = $s[0];
    $r['_jenis'] = $s[1];
    $r['_refund'] = $r['refund_sp'];
    $hitung[''] ++;
    $hitung[$s[2]]++;
    if ($r['_refund']) {
        $hitung['refund']++;
    }
    if ($tab === '' || $tab === $s[2] || ($tab === 'refund' && $r['_refund'])) {
        $tampilkan[] = $r;
    }
}
const PER_HALAMAN_PESANAN = 6;
[$halaman, $totalHal, $offset] = paginasi(count($tampilkan), PER_HALAMAN_PESANAN, (int) get('hal', '1'));
$tampilkan = array_slice($tampilkan, $offset, PER_HALAMAN_PESANAN);

page_start('Pesanan Saya', ['menu' => 'pesanan']);
?>
<h1>Pesanan Saya</h1>
<nav class="tab-status" aria-label="Filter status">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="chip-link <?= $tab === $k ? 'aktif' : '' ?>" href="?<?= e($k === '' ? '' : http_build_query(['status' => $k])) ?>"><?= e($label) ?> <small><?= (int) $hitung[$k] ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if (!$tampilkan): ?>
  <div class="card kosong"><?= ilustrasi_kosong() ?>
    <h3><?= $hitung[''] ? 'Tidak ada pesanan di tab ini' : 'Belum ada pesanan' ?></h3>
    <p><?= $hitung[''] ? 'Coba pilih tab lain.' : 'Pesanan Anda akan muncul di sini setelah Anda berbelanja.' ?></p>
    <a class="btn btn-primary btn-lg" href="<?= e(url('pembeli/index.php')) ?>">Mulai belanja</a>
  </div>
<?php else: foreach ($tampilkan as $r):
    $n = nota_lengkap($r['id_nota_pembeli']);
    $item = [];
    foreach ($n['toko'] as $t) {
        foreach ($t['items'] as $it) {
            $item[] = $it + ['toko' => $t['nama_penjual']];
        }
    } ?>
  <article class="nota-kartu">
    <div class="nota-atas">
      <div class="kiri"><span class="id"><?= e($n['id_nota_pembeli']) ?></span><span class="kecil"><?= e(tgl($n['tanggal_pembelian'], true)) ?></span></div>
      <div class="baris"><?= badge($r['_label'], $r['_jenis']) ?><?= $r['_refund'] ? refund_badge($r['_refund']) : '' ?></div>
    </div>
    <div class="nota-isi">
      <?php foreach (array_slice($item, 0, 2) as $it): ?>
        <div class="thumb-baris"><img src="<?= e(foto_produk_url($it['id_produk'], $it['foto_produk'] ?? null)) ?>" alt="" loading="lazy">
          <div style="min-width:0"><div class="nama"><?= e($it['nama_produk']) ?></div><small><?= (int) $it['qty'] ?> barang · <?= e($it['toko']) ?></small></div></div>
      <?php endforeach; ?>
      <?php if (count($item) > 2): ?><small>+ <?= count($item) - 2 ?> barang lainnya</small><?php endif; ?>
      <?php if ($n['status_pembayaran'] === BAYAR_DITOLAK): ?>
        <div class="alert alert-warn kecil catatan-tolak" role="alert"><span><b>Pembayaran ditolak admin.</b>
          <?= $n['catatan_tolak'] !== null && $n['catatan_tolak'] !== '' ? 'Catatan: ' . e(mb_strimwidth((string) $n['catatan_tolak'], 0, 140, '…')) : 'Tanpa catatan.' ?> Silakan bayar ulang.</span></div>
      <?php endif; ?>
    </div>
    <div class="nota-bawah">
      <div><small>Total belanja</small><div class="tebal mono" style="font-size:1.1rem"><?= rp($n['total_harga_beli']) ?></div></div>
      <div class="baris">
        <?php if (in_array($n['status_pembayaran'], [BAYAR_BELUM, BAYAR_DITOLAK], true)): ?><a class="btn btn-gold btn-sm" href="<?= e(url('pembeli/bayar.php?id=' . rawurlencode($n['id_nota_pembeli']))) ?>">Bayar<?= $n['status_pembayaran'] === BAYAR_DITOLAK ? ' ulang' : ' sekarang' ?></a><?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url_struk($n['id_nota_pembeli'], true)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('download', 14) ?> Struk PDF</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('pembeli/pesanan.php?id=' . rawurlencode($n['id_nota_pembeli']))) ?>">Lihat detail</a>
      </div>
    </div>
  </article>
<?php endforeach; ?>
  <?= paginasi_html($halaman, $totalHal, array_filter(['status' => $tab], fn($v) => $v !== '')) ?>
<?php endif; ?>
<?php page_end();
