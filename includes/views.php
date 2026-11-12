<?php
/** Potongan tampilan yang dipakai berulang: kartu produk, ringkasan checkout, ilustrasi kosong. */

require_once __DIR__ . '/foto.php';

function kartu_produk(array $p): string
{
    $harga = harga_akhir($p);
    $pot = (float) ($p['besaran_potongan_harga'] ?? 0);
    $stok = (int) $p['stok'];
    $detail = url('pembeli/produk.php?id=' . rawurlencode($p['id_produk']));
    $o = '<article class="produk">'
        . '<a class="produk-gambar" href="' . e($detail) . '"><img loading="lazy" src="' . e(foto_produk_url($p['id_produk'], $p['foto_produk'] ?? null)) . '" alt="' . e($p['nama_produk']) . '">';
    if ($pot > 0) {
        $o .= '<span class="lencana-diskon">-' . persen_diskon((float) $p['harga_produk'], $pot) . '%</span>';
    }
    if ($stok <= 0) {
        $o .= '<span class="lencana-stok">Habis</span>';
    } elseif ($stok <= 5) {
        $o .= '<span class="lencana-stok">Sisa ' . $stok . '</span>';
    }
    $o .= '</a><div class="produk-isi">'
        . '<span class="produk-daerah">' . e($p['nama_asal_daerah'] ?? $p['nama_kategori_produk'] ?? '') . '</span>'
        . '<h3 class="produk-nama"><a href="' . e($detail) . '">' . e($p['nama_produk']) . '</a></h3>'
        . '<span class="produk-toko">' . e($p['nama_penjual'] ?? '') . '</span>'
        . (array_key_exists('jumlah_ulasan', $p) ? rating_ringkas_html(isset($p['rata_rating']) ? (float) $p['rata_rating'] : null, (int) $p['jumlah_ulasan']) : '')
        . '<div class="harga"><strong>' . rp($harga) . '</strong>' . ($pot > 0 ? '<s>' . rp($p['harga_produk']) . '</s>' : '') . '</div></div>';
    if ($stok > 0) {
        $o .= '<form class="produk-aksi" method="post" action="' . e(url('pembeli/keranjang.php')) . '" data-ajax-keranjang>'
            . csrf_field() . '<input type="hidden" name="aksi" value="tambah"><input type="hidden" name="id" value="' . e($p['id_produk']) . '"><input type="hidden" name="qty" value="1">'
            . '<a class="btn btn-ghost" href="' . e($detail) . '">Detail</a>'
            . '<button class="btn btn-primary" type="submit">' . ikon('cart', 16) . ' Keranjang</button></form>';
    } else {
        $o .= '<div class="produk-aksi"><a class="btn btn-ghost" href="' . e($detail) . '">Lihat detail</a></div>';
    }
    return $o . '</article>';
}

// ================================================================ ULASAN
// Ulasan disimpan pada nota_penjual (rating, komentar, foto_ulasan): satu ulasan per toko per pesanan.
// Ulasan sebuah produk = ulasan nota toko yang memuat produk tersebut.

/** Bintang statis, mis. ★★★★☆ — $r boleh pecahan. */
function bintang_html(float $r, string $kelas = 'bintang'): string
{
    $n = max(0, min(5, (int) round($r)));
    return '<span class="' . e($kelas) . '" role="img" aria-label="' . e(rtrim(rtrim(number_format($r, 1, '.', ''), '0'), '.')) . ' dari 5">'
        . str_repeat('★', $n) . str_repeat('☆', 5 - $n) . '</span>';
}

/** Dua ekspresi SELECT (rata_rating, jumlah_ulasan) untuk produk beralias $p — taruh di daftar kolom. */
function sql_rating_produk(string $p = 'p'): string
{
    $dari = "FROM nota_penjual npr JOIN rincian_pesanan rpr ON rpr.id_nota_penjual = npr.id_nota_penjual
              WHERE rpr.id_produk = $p.id_produk AND npr.rating IS NOT NULL";
    return "(SELECT AVG(npr.rating) $dari) AS rata_rating, (SELECT COUNT(DISTINCT npr.id_nota_penjual) $dari) AS jumlah_ulasan";
}

/** Ringkasan bintang di kartu produk. */
function rating_ringkas_html(?float $rata, int $jumlah): string
{
    if ($jumlah <= 0 || $rata === null) {
        return '<span class="rating-ringkas kosong">Belum ada ulasan</span>';
    }
    return '<span class="rating-ringkas"><span class="bintang" aria-hidden="true">★</span> <b>' . number_format($rata, 1, ',', '') . '</b> <small>(' . $jumlah . ' ulasan)</small></span>';
}

/**
 * Formulir tulis / ubah ulasan untuk satu nota toko. Unggah foto sama dengan produk & bukti pembayaran:
 * JPG/PNG/WebP, maksimal 2 MB, pratinjau di browser (assets/js/ulasan.js), disimpan sebagai path relatif.
 * $ada = baris nota_penjual yang sudah berisi ulasan (mode ubah), atau null (mode tulis).
 */
function ulasan_form_html(string $idNota, string $idNpj, ?array $ada = null): string
{
    $rating = $ada ? (int) round((float) $ada['rating']) : 0;
    $fotoAda = $ada && !empty($ada['foto_ulasan']) && foto_url($ada['foto_ulasan']);
    $o = '<form method="post" enctype="multipart/form-data" class="form-ulasan" novalidate>'
        . csrf_field() . '<input type="hidden" name="aksi" value="ulasan"><input type="hidden" name="id" value="' . e($idNota) . '"><input type="hidden" name="npj" value="' . e($idNpj) . '">'
        . '<fieldset class="field rating-field"><legend class="field-label">Rating <b class="req">*</b></legend><div class="rating-bintang">';
    for ($b = 5; $b >= 1; $b--) {
        $o .= '<input type="radio" name="rating" id="rt-' . e($idNpj) . '-' . $b . '" value="' . $b . '"' . ($rating === $b ? ' checked' : '') . ' required>'
            . '<label for="rt-' . e($idNpj) . '-' . $b . '" title="' . $b . ' bintang"><span aria-hidden="true">★</span><span class="sr-only">' . $b . ' bintang</span></label>';
    }
    $o .= '</div></fieldset>'
        . '<label class="field"><span class="field-label">Komentar</span><textarea name="komentar" rows="3" maxlength="500" placeholder="Bagaimana kainnya? Sesuai foto? Ceritakan pengalaman Anda.">' . e((string) ($ada['komentar'] ?? '')) . '</textarea>'
        . '<span class="hint">Maksimal 500 karakter.</span></label>'
        . '<div class="field"><span class="field-label">Foto ulasan (opsional)</span>';
    if ($fotoAda) {
        $o .= '<img class="ulasan-foto-lama" src="' . e(foto_url($ada['foto_ulasan'])) . '" alt="Foto ulasan saat ini">'
            . '<label class="cek"><input type="checkbox" name="hapus_foto" value="1"> Hapus foto ini</label>';
    }
    $o .= '<input type="file" name="foto" accept="image/jpeg,image/png,image/webp">'
        . '<span class="hint">JPG, PNG, atau WebP, maksimal ' . (int) (MAX_UPLOAD_BYTES / 1048576) . ' MB.' . ($fotoAda ? ' Memilih foto baru akan mengganti foto saat ini.' : '') . '</span>'
        . '<img class="ulasan-pratinjau" alt="Pratinjau foto ulasan" hidden></div>'
        . '<button class="btn btn-primary btn-sm" type="submit">' . ($ada ? 'Simpan perubahan' : 'Kirim ulasan') . '</button></form>';
    return $o;
}

/** Satu ulasan sebagai kartu (dipakai halaman produk dan halaman ulasan penjual). */
function ulasan_item_html(array $u): string
{
    $o = '<div class="ulasan-item"><div class="ulasan-kepala"><b>' . e((string) ($u['nama_pembeli'] ?? '')) . '</b> ' . bintang_html((float) $u['rating'])
        . (!empty($u['tanggal_sampai']) ? ' <small class="muted">' . e(tgl($u['tanggal_sampai'])) . '</small>' : '') . '</div>';
    if (!empty($u['komentar'])) {
        $o .= '<p class="ulasan-isi">' . e($u['komentar']) . '</p>';
    }
    if (!empty($u['foto_ulasan']) && foto_url($u['foto_ulasan'])) {
        $o .= '<a href="' . e(foto_url($u['foto_ulasan'])) . '" target="_blank" rel="noopener"><img class="ulasan-foto" src="' . e(foto_url($u['foto_ulasan'])) . '" alt="Foto ulasan" loading="lazy"></a>';
    }
    return $o . '</div>';
}

function ilustrasi_kosong(): string
{
    return '<svg viewBox="0 0 160 110" aria-hidden="true"><rect x="22" y="14" width="116" height="70" rx="6" fill="#f0e5cf" stroke="#c8962e" stroke-width="2"/>'
        . '<path d="M22 34H138M22 54H138M22 74H138" stroke="#e4d3b0" stroke-width="2"/><path d="M52 14V84M80 14V84M108 14V84" stroke="#e4d3b0" stroke-width="2"/>'
        . '<path d="M80 30L98 49 80 68 62 49Z" fill="#6b1a28"/><path d="M80 38L90 49 80 60 70 49Z" fill="#e3b23c"/>'
        . '<path d="M30 92Q44 100 58 92T86 92 114 92 134 92" fill="none" stroke="#c8962e" stroke-width="2.4" stroke-linecap="round"/></svg>';
}

/** Ringkasan biaya pada halaman checkout (juga dikirim sebagai potongan HTML oleh api/hitung.php). */
function ringkasan_checkout_html(array $h): string
{
    $o = '<div class="ringkas-baris"><span>Subtotal barang (' . (int) $h['qty'] . ')</span><span>' . rp($h['sub']) . '</span></div>';
    if ($h['hemat_produk'] > 0) {
        $o .= '<div class="ringkas-baris kecil"><span>Sudah termasuk potongan harga produk</span><span>' . rp($h['hemat_produk']) . '</span></div>';
    }
    if ($h['tingkat'] > 0) {
        $o .= '<div class="ringkas-baris minus"><span>Diskon tingkat pembeli</span><span>− ' . rp($h['tingkat']) . '</span></div>';
    }
    if ($h['koin_rp'] > 0) {
        $o .= '<div class="ringkas-baris minus"><span>' . 'Koin Songketin (' . (int) $h['koin'] . ')</span><span>− ' . rp($h['koin_rp']) . '</span></div>';
    }
    if ($h['opsi']) {
        $o .= '<div class="ringkas-baris"><span>Ongkos kirim</span><span>' . rp($h['ongkir']) . '</span></div>';
        if ($h['diskon_ongkir'] > 0) {
            $o .= '<div class="ringkas-baris minus"><span>Diskon ongkir</span><span>− ' . rp($h['diskon_ongkir']) . '</span></div>';
        }
    } else {
        $o .= '<div class="ringkas-baris"><span>Ongkos kirim</span><span class="muted">pilih pengiriman</span></div>';
    }
    $o .= '<div class="ringkas-baris total"><span>Total bayar</span><span>' . rp($h['total']) . '</span></div>';
    foreach ($h['galat'] as $g) {
        $o .= '<div class="alert alert-warn" style="margin-top:12px"><span>' . e($g) . '</span></div>';
    }
    return $o;
}

/** Rincian biaya sebuah nota yang sudah dibuat (angka diturunkan dari data tersimpan oleh rincian_biaya). */
function rincian_biaya_html(array $n): string
{
    $r = rincian_biaya($n);
    $o = '<div class="ringkas-baris"><span>Subtotal barang (' . (int) $n['total_barang_beli'] . ')</span><span>' . rp($r['sub']) . '</span></div>';
    if ($r['tingkat'] > 0) {
        $o .= '<div class="ringkas-baris minus"><span>Diskon tingkat pembeli</span><span>− ' . rp($r['tingkat']) . '</span></div>';
    }
    if ($r['koin_rp'] > 0) {
        $o .= '<div class="ringkas-baris minus"><span>' . 'Koin Songketin (' . (int) $n['jumlah_koin_dipakai'] . ')</span><span>− ' . rp($r['koin_rp']) . '</span></div>';
    }
    $o .= '<div class="ringkas-baris"><span>Ongkos kirim</span><span>' . rp($r['ongkir']) . '</span></div>';
    if ($r['diskon_ongkir'] > 0) {
        $o .= '<div class="ringkas-baris minus"><span>Diskon ongkir</span><span>− ' . rp($r['diskon_ongkir']) . '</span></div>';
    }
    return $o . '<div class="ringkas-baris total"><span>Total</span><span>' . rp($r['total']) . '</span></div>';
}

/** URL struk PDF sebuah nota. $unduh = true → langsung diunduh, false → dibuka di tab. */
function url_struk(string $idNota, bool $unduh = false): string
{
    return url('pembeli/struk.php?id=' . rawurlencode($idNota) . ($unduh ? '&unduh=1' : ''));
}
