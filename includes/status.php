<?php
/**
 * Status pesanan, pembayaran, pengiriman, dan refund — dipetakan ke struktur database yang sudah ada.
 *
 *  • nota_pembeli.status_pembayaran  enum('sukses','ditolak','menunggu','')  → status pembayaran (DB_*)
 *      '' = belum dibayar · 'menunggu' = bukti terkirim, menunggu verifikasi admin (atau COD menunggu tunai) · 'sukses' = lunas · 'ditolak' = bukti ditolak
 *      Label lengkap (BAYAR_*) diturunkan: COD = 'menunggu' + sifat Tunai; Dibatalkan = semua toko berstatus pengiriman SK07.
 *  • rincian_pesanan.id_status_pesanan → tabel status_pesanan (SP01–SP06 bawaan; SP07–SP12 untuk alur refund & banding)
 *  • nota_penjual.id_status_pengiriman → tabel status_pengiriman (SK01–SK07 bawaan)
 */

// ---- nilai kolom status_pembayaran di database
const DB_BELUM = '', DB_MENUNGGU = 'menunggu', DB_SUKSES = 'sukses', DB_DITOLAK = 'ditolak';

// ---- label status pembayaran yang dipakai di tampilan (diturunkan dari kolom + sifat pembayaran + status pengiriman)
const BAYAR_BELUM = 'Belum Dibayar', BAYAR_VERIF = 'Menunggu Verifikasi', BAYAR_LUNAS = 'Lunas', BAYAR_COD = 'Bayar di Tempat',
    BAYAR_BATAL = 'Dibatalkan', BAYAR_DITOLAK = 'Pembayaran Ditolak';

// ---- status pesanan (rincian_pesanan)
const SP_MENUNGGU = 'SP01', SP_DIPROSES = 'SP02', SP_DIKEMAS = 'SP03', SP_DIKIRIM = 'SP04', SP_SELESAI = 'SP05', SP_BATAL = 'SP06';
const SP_RF_AJU = 'SP07', SP_RF_SETUJU = 'SP08', SP_RF_TOLAK = 'SP09', SP_BD_AJU = 'SP10', SP_BD_SETUJU = 'SP11', SP_BD_TOLAK = 'SP12';

// ---- status pengiriman (nota_penjual)
const SK_BELUM = 'SK01', SK_KURIR = 'SK02', SK_JALAN = 'SK03', SK_TIBA = 'SK04', SK_ANTAR = 'SK05', SK_DITERIMA = 'SK06', SK_BATAL = 'SK07';

/** Status refund/banding yang ditambahkan ke tabel status_pesanan bila belum ada (baris data; struktur tabel tidak disentuh). */
const STATUS_REFUND = [
    SP_RF_AJU => 'Refund Diajukan',
    SP_RF_SETUJU => 'Refund Disetujui',
    SP_RF_TOLAK => 'Refund Ditolak',
    SP_BD_AJU => 'Banding Diajukan',
    SP_BD_SETUJU => 'Banding Disetujui',
    SP_BD_TOLAK => 'Banding Ditolak',
];
/** Status yang berarti dana sudah dikembalikan (penjualan toko itu tidak dihitung). */
const SP_REFUND_BERHASIL = [SP_RF_SETUJU, SP_BD_SETUJU];
/** Status refund yang masih berjalan (menunggu keputusan). */
const SP_REFUND_PROSES = [SP_RF_AJU, SP_BD_AJU];
/** Semua status yang terkait refund. */
const SP_REFUND_SEMUA = [SP_RF_AJU, SP_RF_SETUJU, SP_RF_TOLAK, SP_BD_AJU, SP_BD_SETUJU, SP_BD_TOLAK];

/**
 * Pastikan baris referensi minimum tersedia. Dipanggil dari bootstrap (sekali per sesi). Hanya INSERT IGNORE baris
 * data (tidak ada perubahan struktur tabel):
 *  - status refund/banding (SP07–SP12) di status_pesanan;
 *  - bila tabelnya kosong: satu diskon_harga “Tanpa Diskon” (produk.id_diskon_harga wajib terisi),
 *    satu jenis_pembayaran “Bayar di Tempat (COD)” (nota wajib punya jenis pembayaran) dan dua jenis_produksi.
 * Bank/dompet/QRIS tujuan transfer tidak dibuat otomatis: admin mengisinya sendiri di Data Master.
 */
function siapkan_referensi(): void
{
    $ada = (int) val('SELECT COUNT(*) FROM status_pesanan WHERE id_status_pesanan IN (' . in_marks(array_keys(STATUS_REFUND)) . ')', array_keys(STATUS_REFUND));
    if ($ada < count(STATUS_REFUND)) {
        foreach (STATUS_REFUND as $id => $nama) {
            q('INSERT IGNORE INTO status_pesanan (id_status_pesanan, keterangan_status_pesanan) VALUES (?, ?)', [$id, $nama]);
        }
    }
    if (!val('SELECT 1 FROM diskon_harga LIMIT 1')) {
        q('INSERT IGNORE INTO diskon_harga (id_diskon_harga, nama_diskon_harga, besaran_potongan_harga) VALUES (?, ?, 0)', ['DH01', 'Tanpa Diskon']);
    }
    if (!val('SELECT 1 FROM jenis_pembayaran LIMIT 1') && val("SELECT 1 FROM metode_pembayaran WHERE id_metode_pembayaran = 'MP04'")) {
        q("INSERT IGNORE INTO jenis_pembayaran (id_jenis_pembayaran, id_metode_pembayaran, nama_jenis_pembayaran, rekening_jenis_pembayaran, foto_qr) VALUES ('JP01', 'MP04', 'Bayar di Tempat (COD)', NULL, '')");
    }
    if (!val('SELECT 1 FROM jenis_produksi LIMIT 1')) {
        q("INSERT IGNORE INTO jenis_produksi (id_jenis_produksi, nama_jenis_produksi) VALUES ('JPR01', 'Tenun Tangan'), ('JPR02', 'Tenun Mesin')");
    }
}

// ---------------------------------------------------------------- fragmen SQL untuk status turunan
/** Kondisi SQL: sifat pembayaran nota $a adalah Tunai (COD). */
function sql_cod(string $a = 'nb'): string
{
    return "EXISTS (SELECT 1 FROM jenis_pembayaran jpc JOIN metode_pembayaran mpc ON mpc.id_metode_pembayaran = jpc.id_metode_pembayaran
                     JOIN sifat_pembayaran spc ON spc.id_sifat_pembayaran = mpc.id_sifat_pembayaran
                    WHERE jpc.id_jenis_pembayaran = $a.id_jenis_pembayaran AND (spc.id_sifat_pembayaran = 'SF02' OR LOWER(spc.nama_sifat_pembayaran) = 'tunai'))";
}

/** Kondisi SQL: semua toko pada nota $a dibatalkan (SK07) dan nota punya minimal satu toko. */
function sql_dibatalkan(string $a = 'nb'): string
{
    return "(EXISTS (SELECT 1 FROM nota_penjual xb WHERE xb.id_nota_pembeli = $a.id_nota_pembeli)
             AND NOT EXISTS (SELECT 1 FROM nota_penjual xc WHERE xc.id_nota_pembeli = $a.id_nota_pembeli AND COALESCE(xc.id_status_pengiriman, '') <> '" . SK_BATAL . "'))";
}

/** Ekspresi SQL label status pembayaran lengkap (BAYAR_*) untuk nota $a. */
function sql_status_bayar(string $a = 'nb'): string
{
    return 'CASE WHEN ' . sql_dibatalkan($a) . " THEN '" . BAYAR_BATAL . "'
                 WHEN $a.status_pembayaran = '" . DB_SUKSES . "' THEN '" . BAYAR_LUNAS . "'
                 WHEN $a.status_pembayaran = '" . DB_DITOLAK . "' THEN '" . BAYAR_DITOLAK . "'
                 WHEN $a.status_pembayaran = '" . DB_MENUNGGU . "' THEN (CASE WHEN " . sql_cod($a) . " THEN '" . BAYAR_COD . "' ELSE '" . BAYAR_VERIF . "' END)
                 ELSE '" . BAYAR_BELUM . "' END";
}

/** Kondisi SQL: nota $a sudah dibayar atau COD yang sudah dikonfirmasi (dihitung sebagai penjualan) dan tidak dibatalkan. */
function sql_nota_terhitung(string $a = 'nb'): string
{
    return "(($a.status_pembayaran = '" . DB_SUKSES . "' OR ($a.status_pembayaran = '" . DB_MENUNGGU . "' AND " . sql_cod($a) . "))) AND NOT " . sql_dibatalkan($a);
}

/** Kondisi SQL pada rincian_pesanan alias $r: status refund berhasil. */
function sql_status_refund_berhasil(string $r = 'rp'): string
{
    return "$r.id_status_pesanan IN ('" . implode("','", SP_REFUND_BERHASIL) . "')";
}
