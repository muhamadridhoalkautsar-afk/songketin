<?php
/** Pembayaran: pilih sifat → metode → jenis, lalu bayar. Setelah itu halaman ini menampilkan status pembayarannya. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/views.php';

$u = require_role('pembeli');
$pb = profil_pembeli();
$id = get('id');
$n = $id !== '' ? nota_lengkap($id) : null;
if (!$n || $n['id_pembeli'] !== $pb['id_pembeli']) {
    abort404(); // nota milik pembeli lain dianggap tidak ada
}
$idNota = $n['id_nota_pembeli'];
$balik = 'pembeli/bayar.php?id=' . rawurlencode($idNota);

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    if ($aksi === 'bayar') {
        $sifatId = post('sifat');
        $sifatNama = (string) val('SELECT nama_sifat_pembayaran FROM sifat_pembayaran WHERE id_sifat_pembayaran = ?', [$sifatId]);
        $wajibBukti = $sifatNama !== '' && !sifat_tunai($sifatId, $sifatNama); // Non-Tunai: bukti pembayaran wajib dikirim
        $galatBukti = $wajibBukti ? cek_bukti($_FILES['bukti'] ?? null) : null;
        if ($galatBukti) {
            flash('error', $galatBukti);
        } else {
            [$ok, $pesan] = bayar_nota($idNota, $sifatId, post('metode'), post('jenis'));
            $jenisFlash = $ok ? 'ok' : 'error';
            if ($ok && $wajibBukti && !simpan_bukti($idNota, $_FILES['bukti'])) {
                $jenisFlash = 'warn';
                $pesan .= ' Namun bukti pembayaran gagal disimpan; unggah ulang di bagian bawah.';
            }
            flash($jenisFlash, $pesan);
        }
    } elseif ($aksi === 'bukti') {
        $galatBukti = cek_bukti($_FILES['bukti'] ?? null);
        if ($n['status_pembayaran'] !== BAYAR_VERIF) {
            flash('warn', 'Bukti pembayaran hanya bisa diganti selama pembayaran menunggu verifikasi admin.');
        } elseif ($galatBukti) {
            flash('error', $galatBukti);
        } elseif (simpan_bukti($idNota, $_FILES['bukti'])) {
            flash('ok', 'Bukti pembayaran diganti. Admin akan memeriksanya kembali.');
        } else {
            flash('error', 'Bukti pembayaran gagal disimpan. Coba unggah lagi.');
        }
    } elseif ($aksi === 'batal') {
        if ($n['status_pembayaran'] === BAYAR_LUNAS) {
            flash('warn', 'Pesanan ini sudah lunas. Gunakan “Ajukan refund” di halaman status pesanan untuk membatalkannya.');
        } else {
            [$ok, $pesan] = batalkan_nota($idNota);
            flash($ok ? 'ok' : 'error', $pesan);
        }
    }
    redirect($balik);
}

$st = $n['status_pembayaran'];
$perluBayar = in_array($st, [BAYAR_BELUM, BAYAR_DITOLAK], true);
$totalTeks = rp($n['total_harga_beli']);
$sifat = rows('SELECT * FROM sifat_pembayaran ORDER BY id_sifat_pembayaran');
$metode = rows('SELECT * FROM metode_pembayaran ORDER BY id_metode_pembayaran');
$jenis = rows('SELECT * FROM jenis_pembayaran ORDER BY id_jenis_pembayaran');
$idSifatNow = (string) ($n['id_sifat_pembayaran'] ?? '');   // pilihan awal = jenis yang dipilih saat checkout (kolom wajib di nota)
$idMetodeNow = (string) ($n['id_metode_pembayaran'] ?? '');
$idJenisNow = (string) ($n['id_jenis_pembayaran'] ?? '');
$petunjuk = [
    'MP01' => 'Transfer dari m-banking atau ATM ke rekening yang tertera.',
    'MP02' => 'Kirim ke nomor dompet digital yang tertera.',
    'MP03' => 'Pindai kode QRIS dari aplikasi pembayaran apa saja.',
    'MP04' => 'Bayar tunai kepada kurir saat pesanan tiba.',
];
$bisaBatal = bisa_dibatalkan($n) && $st !== BAYAR_LUNAS;
$adaBukti = bukti_ada($idNota);
$pakaiBukti = $n['id_jenis_pembayaran'] && !sifat_tunai('', (string) $n['nama_sifat_pembayaran']);
$urlBukti = url('api/bukti.php?id=' . rawurlencode($idNota));
$unggahBukti = static fn(string $label) => '<form method="post" enctype="multipart/form-data" action="' . e(url($balik)) . '" class="bukti-unggah" style="margin-top:8px">'
    . csrf_field() . '<input type="hidden" name="aksi" value="bukti">'
    . '<input type="file" name="bukti" accept="image/jpeg,image/png,image/webp" required aria-label="Berkas bukti pembayaran">'
    . '<button class="btn btn-ghost btn-sm" type="submit">' . ikon('plus', 14) . ' ' . e($label) . '</button></form>';

page_start('Pembayaran', ['menu' => 'pesanan']);
?>
<div class="krumb"><a href="<?= e(url('pembeli/pesanan.php')) ?>">Pesanan Saya</a> &rsaquo; <a href="<?= e(url('pembeli/pesanan.php?id=' . rawurlencode($idNota))) ?>"><?= e($idNota) ?></a> &rsaquo; Pembayaran</div>
<h1>Pembayaran</h1>

<div class="dua-kolom">
  <div>
<?php if ($perluBayar): ?>
    <?php if (!empty($n['ditolak'])): ?>
    <div class="alert alert-warn" role="alert"><span><b>Pembayaran Anda sebelumnya ditolak admin.</b><?= $n['catatan_tolak'] !== null && $n['catatan_tolak'] !== '' ? '<br>Catatan admin: ' . e($n['catatan_tolak']) : '' ?><br>Silakan pilih cara bayar dan unggah bukti pembayaran yang benar.</span></div>
    <?php endif; ?>
    <form method="post" id="form-bayar" action="<?= e(url($balik)) ?>" data-total="<?= e($totalTeks) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="bayar">

      <section class="langkah" id="l-sifat">
        <h3><span class="no-langkah">1</span> Pilih sifat pembayaran</h3>
        <div class="pilih-kartu">
          <?php foreach ($sifat as $s): $tunai = sifat_tunai($s['id_sifat_pembayaran'], $s['nama_sifat_pembayaran']); ?>
            <label class="opsi besar" <?= $tunai ? 'data-tunai' : '' ?>>
              <input type="radio" name="sifat" value="<?= e($s['id_sifat_pembayaran']) ?>" <?= $idSifatNow === $s['id_sifat_pembayaran'] ? 'checked' : '' ?> required>
              <span><b><?= e($s['nama_sifat_pembayaran']) ?></b>
                <small><?= $tunai ? 'Bayar langsung ke kurir saat pesanan tiba. Pesanan segera diproses.' : 'Bayar sekarang lewat transfer, dompet digital, atau QRIS. Admin memverifikasi pembayaran Anda.' ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="langkah" id="l-metode">
        <h3><span class="no-langkah">2</span> Pilih metode pembayaran</h3>
        <div class="pilih-kartu">
          <?php foreach ($metode as $m): ?>
            <label class="opsi" data-sifat="<?= e($m['id_sifat_pembayaran']) ?>">
              <input type="radio" name="metode" value="<?= e($m['id_metode_pembayaran']) ?>" <?= $idMetodeNow === $m['id_metode_pembayaran'] ? 'checked' : '' ?> required>
              <span><b><?= e($m['nama_metode_pembayaran']) ?></b><?php if (isset($petunjuk[$m['id_metode_pembayaran']])): ?><small><?= e($petunjuk[$m['id_metode_pembayaran']]) ?></small><?php endif; ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="kecil" data-kosong>Pilih sifat pembayaran dulu untuk melihat metodenya.</p>
      </section>

      <section class="langkah" id="l-jenis">
        <h3><span class="no-langkah">3</span> Pilih jenis pembayaran</h3>
        <div class="pilih-kartu">
          <?php foreach ($jenis as $j): ?>
            <label class="opsi" data-metode="<?= e($j['id_metode_pembayaran']) ?>" data-rekening="<?= e((string) $j['rekening_jenis_pembayaran']) ?>" data-qr="<?= e((string) foto_url($j['foto_qr'] ?? '')) ?>">
              <input type="radio" name="jenis" value="<?= e($j['id_jenis_pembayaran']) ?>" <?= $idJenisNow === $j['id_jenis_pembayaran'] ? 'checked' : '' ?> required>
              <span><b><?= e($j['nama_jenis_pembayaran']) ?></b></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="kecil" data-kosong>Pilih metode pembayaran dulu untuk melihat pilihannya.</p>
      </section>

      <section class="langkah" id="l-bukti" hidden>
        <h3><span class="no-langkah">4</span> Unggah bukti pembayaran</h3>
        <div class="bukti-unggah">
          <p class="kecil" style="margin:0">Unggah tangkapan layar atau foto struk transfer, dompet digital, atau QRIS. Admin memakainya untuk memverifikasi pembayaran Anda. Format JPG, PNG, atau WebP, maksimal <?= (int) (MAX_UPLOAD_BYTES / 1048576) ?> MB.</p>
          <label class="field"><span class="field-label">Berkas bukti <b class="req">*</b></span>
            <input type="file" name="bukti" id="input-bukti" accept="image/jpeg,image/png,image/webp" disabled></label>
          <img id="pratinjau-bukti" alt="Pratinjau bukti pembayaran" hidden style="max-width:220px;border-radius:10px;border:1px solid #ead9b6">
        </div>
      </section>

      <div class="rekening" id="info-bayar" hidden aria-live="polite">
        <div class="kecil" id="info-judul"></div>
        <div class="nomor"><span id="info-nomor"></span><button type="button" class="btn btn-ghost btn-sm" id="btn-salin"><?= ikon('file', 14) ?> Salin</button></div>
        <div class="kecil" id="info-catatan"></div>
        <img id="info-qr" alt="Kode QR tujuan pembayaran" hidden style="max-width:220px;margin-top:8px;border-radius:10px;border:1px solid #ead9b6">
      </div>

      <div class="total-bayar"><span>Total yang dibayar</span><strong class="mono"><?= e($totalTeks) ?></strong></div>
      <button class="btn btn-gold btn-lg btn-block" id="btn-bayar" type="submit">Bayar sekarang</button>
    </form>
    <?php if ($bisaBatal): ?>
    <form method="post" action="<?= e(url($balik)) ?>" class="mt tengah" data-konfirmasi="Batalkan pesanan ini? Stok dan koin akan dikembalikan.">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><button class="link-hapus" type="submit">Batalkan pesanan</button>
    </form>
    <?php endif; ?>

<?php else:
    $kelas = $st === BAYAR_VERIF ? 'menunggu' : ($st === BAYAR_BATAL ? 'batal' : '');
    $judul = [
        BAYAR_VERIF => 'Menunggu verifikasi admin',
        BAYAR_COD => 'Pesanan dikonfirmasi',
        BAYAR_LUNAS => 'Pembayaran lunas',
        BAYAR_BATAL => 'Pesanan dibatalkan',
    ][$st] ?? $st;
    $isi = [
        BAYAR_VERIF => 'Pembayaran Anda sudah tercatat. Admin akan memeriksanya; pantau statusnya di Pesanan Saya.',
        BAYAR_COD => 'Siapkan uang tunai sebesar ' . $totalTeks . ' saat pesanan tiba. Pesanan Anda sedang diproses.',
        BAYAR_LUNAS => 'Terima kasih! Pembayaran sudah diverifikasi dan pesanan Anda sedang diproses penjual.',
        BAYAR_BATAL => 'Pesanan ini dibatalkan. Stok dan koin yang terpakai sudah dikembalikan.',
    ][$st] ?? ''; ?>
    <section class="card mb">
      <div class="status-bayar <?= e($kelas) ?>">
        <span class="lingkar"><?= ikon($st === BAYAR_BATAL ? 'x' : ($st === BAYAR_VERIF ? 'card' : 'check'), 28) ?></span>
        <div><h2><?= e($judul) ?></h2><p class="muted"><?= e($isi) ?></p></div>
      </div>
      <?php if ($n['id_jenis_pembayaran']): ?>
      <dl class="kunci-nilai mt">
        <dt>Status</dt><dd><?= badge($st) ?></dd>
        <dt>Sifat</dt><dd><?= e($n['nama_sifat_pembayaran']) ?></dd>
        <dt>Metode</dt><dd><?= e($n['nama_metode_pembayaran']) ?></dd>
        <dt>Jenis</dt><dd><?= e($n['nama_jenis_pembayaran']) ?></dd>
        <?php if ($n['rekening_jenis_pembayaran']): ?><dt>Tujuan bayar</dt><dd><?= e($n['rekening_jenis_pembayaran']) ?></dd><?php endif; ?>
        <dt>Jumlah</dt><dd class="tebal"><?= e($totalTeks) ?></dd>
      </dl>
      <?php endif; ?>
      <div class="baris mt">
        <a class="btn btn-primary" href="<?= e(url_struk($idNota, true)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('download', 16) ?> Unduh struk (PDF)</a>
        <a class="btn btn-ghost" href="<?= e(url_struk($idNota)) ?>" target="_blank" rel="noopener" data-tanpa-loader><?= ikon('file', 16) ?> Lihat struk</a>
        <a class="btn btn-ghost" href="<?= e(url('pembeli/pesanan.php?id=' . rawurlencode($idNota))) ?>"><?= ikon('truck', 16) ?> Lacak pesanan</a>
      </div>
    </section>
    <?php if ($pakaiBukti): ?>
    <section class="card mb">
      <h3>Bukti pembayaran</h3>
      <?php if ($adaBukti): ?>
        <div class="bukti-kartu">
          <a href="<?= e($urlBukti) ?>" target="_blank" rel="noopener" data-tanpa-loader><img src="<?= e($urlBukti) ?>" alt="Bukti pembayaran <?= e($idNota) ?>"></a>
          <div class="bukti-unggah">
            <p class="kecil" style="margin:0">Bukti sudah kami terima.<?= $st === BAYAR_VERIF ? ' Anda masih bisa menggantinya selama admin belum memverifikasi.' : '' ?></p>
            <?php if ($st === BAYAR_VERIF) echo $unggahBukti('Ganti bukti'); ?>
          </div>
        </div>
      <?php else: ?>
        <div class="bukti-kosong">Bukti pembayaran belum diunggah.</div>
        <?php if ($st === BAYAR_VERIF) echo $unggahBukti('Unggah bukti'); ?>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($bisaBatal): ?>
    <form method="post" action="<?= e(url($balik)) ?>" class="tengah" data-konfirmasi="Batalkan pesanan ini? Stok dan koin akan dikembalikan.">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><button class="link-hapus" type="submit">Batalkan pesanan</button>
    </form>
    <?php endif; ?>
<?php endif; ?>
  </div>

  <aside class="card sticky">
    <h3>Ringkasan pesanan</h3>
    <div class="ringkas-baris kecil"><span>No. pesanan</span><b class="mono"><?= e($idNota) ?></b></div>
    <div class="ringkas-baris kecil"><span>Tanggal</span><span><?= e(tgl($n['tanggal_pembelian'], true)) ?></span></div>
    <div class="ringkas-baris kecil"><span>Status</span><span><?= badge($st) ?></span></div>
    <hr>
    <?php foreach ($n['toko'] as $t): ?>
      <div class="toko-kepala"><?= ikon('home', 16) ?> <?= e($t['nama_penjual']) ?></div>
      <?php foreach ($t['items'] as $it): ?>
        <div class="item-ringkas"><span><?= e($it['nama_produk']) ?> <small>× <?= (int) $it['qty'] ?></small></span><span class="mono"><?= rp($it['sub_harga']) ?></span></div>
      <?php endforeach; ?>
      <div class="ringkas-baris kecil"><span><?= e($t['nama_opsi_pengiriman'] ?? 'Pengiriman') ?></span><span><?= rp($t['ongkos_kirim']) ?></span></div>
    <?php endforeach; ?>
    <hr>
    <?= rincian_biaya_html($n) ?>
  </aside>
</div>
<?php page_end($perluBayar ? ['js/bayar.js'] : []);
