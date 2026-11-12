<?php
/**
 * Koin Songketin — dipetakan ke tabel `koin` + kolom nota_pembeli.id_koin / jumlah_koin_dipakai.
 *
 * Setiap pesanan (nota_pembeli) punya satu baris `koin` (nota_pembeli.id_koin) yang menyimpan koin yang DIDAPAT dari pesanan itu
 * (koin.total_koin). Koin yang DIPAKAI pada pesanan dicatat di nota_pembeli.jumlah_koin_dipakai.
 *   saldo pembeli = Σ koin didapat (semua pesanannya) − Σ koin dipakai yang masih berlaku
 * Koin didapat saat toko pada pesanan itu sudah menerima barang (status pengiriman Diterima) dan tidak direfund:
 * tiap RUPIAH_PER_KOIN_DIDAPAT belanja = 1 koin. Nilai total_koin selalu dihitung ulang dari data transaksi (koin_sinkron),
 * jadi tetap konsisten setelah pembatalan atau refund.
 */

/** Koin yang dipakai pada satu pesanan dan masih berlaku (pesanan batal → 0; sebagian dikembalikan sebanding refund yang disetujui). */
function koin_dipakai_berlaku(array $nota, array $tokoNota): int
{
    $dipakai = (int) $nota['jumlah_koin_dipakai'];
    if ($dipakai <= 0) {
        return 0;
    }
    $semua = 0.0;
    $kembali = 0.0;
    foreach ($tokoNota as $t) {
        $nilai = (float) $t['total_harga_jual'];
        $semua += $nilai;
        if ($t['id_status_pengiriman'] === SK_BATAL || in_array($t['status_rincian'], SP_REFUND_BERHASIL, true)) {
            $kembali += $nilai;
        }
    }
    if ($semua <= 0) {
        return 0;
    }
    return max(0, $dipakai - (int) floor($dipakai * min(1.0, $kembali / $semua)));
}

/** Data toko semua pesanan seorang pembeli: [id_nota_pembeli => [baris toko + status_rincian]]. */
function koin_data_toko(string $idPembeli): array
{
    $hasil = [];
    foreach (rows('SELECT np.id_nota_penjual, np.id_nota_pembeli, np.total_harga_jual, np.id_status_pengiriman,
                          (SELECT MIN(rp.id_status_pesanan) FROM rincian_pesanan rp WHERE rp.id_nota_penjual = np.id_nota_penjual) AS status_rincian
                     FROM nota_penjual np JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
                    WHERE nb.id_pembeli = ?', [$idPembeli]) as $r) {
        $hasil[$r['id_nota_pembeli']][] = $r;
    }
    return $hasil;
}

/** Koin yang didapat dari satu pesanan (dari toko yang sudah diterima dan tidak direfund). */
function koin_didapat_nota(array $tokoNota): int
{
    $n = 0;
    foreach ($tokoNota as $t) {
        if ($t['id_status_pengiriman'] === SK_DITERIMA && !in_array($t['status_rincian'], SP_REFUND_BERHASIL, true)) {
            $n += intdiv(max(0, (int) round((float) $t['total_harga_jual'])), RUPIAH_PER_KOIN_DIDAPAT);
        }
    }
    return $n;
}

/** [didapat total, dipakai berlaku, saldo] seorang pembeli. */
function koin_ringkasan(string $idPembeli): array
{
    $toko = koin_data_toko($idPembeli);
    $didapat = $dipakai = 0;
    foreach (rows('SELECT id_nota_pembeli, jumlah_koin_dipakai FROM nota_pembeli WHERE id_pembeli = ?', [$idPembeli]) as $nb) {
        $t = $toko[$nb['id_nota_pembeli']] ?? [];
        $didapat += koin_didapat_nota($t);
        $dipakai += koin_dipakai_berlaku($nb, $t);
    }
    return ['didapat' => $didapat, 'dipakai' => $dipakai, 'saldo' => max(0, $didapat - $dipakai)];
}

function koin_saldo(string $idPembeli): int
{
    return koin_ringkasan($idPembeli)['saldo'];
}

/** Buat baris koin untuk pesanan baru; mengembalikan id_koin. */
function koin_buat(): string
{
    $id = next_id('koin', 'id_koin', 'KOI', 5);
    q('INSERT INTO koin (id_koin, nama_diskon_koin, total_koin) VALUES (?, ?, 0)', [$id, 'Koin Songketin']);
    return $id;
}

/**
 * Hitung ulang koin yang didapat seluruh pesanan pembeli (koin.total_koin) lalu perbarui tingkat pembeli.
 * Dipanggil setelah barang diterima, dibatalkan, atau direfund.
 */
function koin_sinkron(string $idPembeli): void
{
    $toko = koin_data_toko($idPembeli);
    foreach (rows('SELECT id_nota_pembeli, id_koin FROM nota_pembeli WHERE id_pembeli = ? AND id_koin IS NOT NULL', [$idPembeli]) as $nb) {
        q('UPDATE koin SET total_koin = ? WHERE id_koin = ?', [koin_didapat_nota($toko[$nb['id_nota_pembeli']] ?? []), $nb['id_koin']]);
    }
    perbarui_tingkat_pembeli($idPembeli);
}

/** Tingkat pembeli mengikuti total koin yang pernah didapat (di luar yang direfund). */
function perbarui_tingkat_pembeli(string $idPembeli): void
{
    $p = row('SELECT id_tingkat_pembeli FROM pembeli WHERE id_pembeli = ?', [$idPembeli]);
    if (!$p) {
        return;
    }
    $total = koin_ringkasan($idPembeli)['didapat'];
    $t = row('SELECT id_tingkat_pembeli FROM tingkat_pembeli WHERE akumulasi_koin <= ? ORDER BY akumulasi_koin DESC LIMIT 1', [$total]);
    if ($t && $t['id_tingkat_pembeli'] !== $p['id_tingkat_pembeli']) {
        q('UPDATE pembeli SET id_tingkat_pembeli = ? WHERE id_pembeli = ?', [$t['id_tingkat_pembeli'], $idPembeli]);
    }
}
