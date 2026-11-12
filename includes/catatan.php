<?php
/**
 * Catatan pesanan yang disimpan di kolom teks yang SUDAH ADA: nota_pembeli.catatan_pembayaran_admin.
 *
 * Struktur database tidak punya tabel/kolom khusus untuk alasan refund & banding, sehingga kolom teks itu dipakai sebagai
 * "amplop" JSON kecil yang memuat dua hal:
 *   bayar  : catatan admin untuk pembayaran (mis. alasan bukti ditolak) — teks biasa
 *   refund : riwayat pengajuan refund/banding per toko  {id_nota_penjual: [peristiwa, …]}
 * Nilai lama yang berupa teks biasa (bukan amplop) tetap dibaca sebagai catatan pembayaran.
 * Status refund-nya sendiri ada di rincian_pesanan.id_status_pesanan (lihat includes/status.php); amplop hanya menyimpan alasan dan riwayat.
 */

const CATATAN_PENANDA = '_sk';

/** Urai nilai kolom menjadi ['bayar' => string, 'refund' => array]. */
function catatan_urai(?string $mentah): array
{
    $kosong = ['bayar' => '', 'refund' => []];
    $mentah = (string) $mentah;
    if ($mentah === '') {
        return $kosong;
    }
    if ($mentah[0] === '{') {
        $d = json_decode($mentah, true);
        if (is_array($d) && isset($d[CATATAN_PENANDA])) {
            return ['bayar' => (string) ($d['bayar'] ?? ''), 'refund' => is_array($d['refund'] ?? null) ? $d['refund'] : []];
        }
    }
    return ['bayar' => $mentah, 'refund' => []];   // catatan teks biasa
}

/** Gabungkan kembali menjadi nilai kolom (teks biasa bila tidak ada riwayat refund). */
function catatan_rakit(array $c): ?string
{
    $refund = array_filter($c['refund'] ?? [], fn($e) => !empty($e));
    $bayar = trim((string) ($c['bayar'] ?? ''));
    if (!$refund) {
        return $bayar !== '' ? $bayar : null;
    }
    return json_encode([CATATAN_PENANDA => 1, 'bayar' => $bayar, 'refund' => $refund], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function catatan_baca(string $idNota): array
{
    return catatan_urai(val('SELECT catatan_pembayaran_admin FROM nota_pembeli WHERE id_nota_pembeli = ?', [$idNota]));
}

/** Ubah catatan secara aman (baris dikunci). $fn menerima array catatan dan mengembalikan array baru. Panggil di dalam transaksi. */
function catatan_ubah(string $idNota, callable $fn): void
{
    $mentah = val('SELECT catatan_pembayaran_admin FROM nota_pembeli WHERE id_nota_pembeli = ? FOR UPDATE', [$idNota]);
    $baru = $fn(catatan_urai($mentah));
    q('UPDATE nota_pembeli SET catatan_pembayaran_admin = ? WHERE id_nota_pembeli = ?', [catatan_rakit($baru), $idNota]);
}

/** Catatan admin untuk pembayaran ('' bila tidak ada). */
function catatan_bayar(string $idNota): string
{
    return catatan_baca($idNota)['bayar'];
}

function catatan_set_bayar(string $idNota, string $teks): void
{
    catatan_ubah($idNota, function (array $c) use ($teks) {
        $c['bayar'] = trim($teks);
        return $c;
    });
}

/** Riwayat refund sebuah toko (urut waktu). */
function catatan_riwayat_refund(string $idNota, string $idNpj): array
{
    return catatan_baca($idNota)['refund'][$idNpj] ?? [];
}

/** Tambahkan satu peristiwa ke riwayat refund toko. */
function catatan_tambah_peristiwa(string $idNota, string $idNpj, array $ev): void
{
    $ev = ['t' => date('Y-m-d H:i:s')] + $ev;
    catatan_ubah($idNota, function (array $c) use ($idNpj, $ev) {
        $c['refund'][$idNpj][] = $ev;
        return $c;
    });
}
