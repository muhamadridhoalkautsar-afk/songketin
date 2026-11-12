<?php
/**
 * Bukti pembayaran (nota_pembeli.foto_bukti_pembayaran) dan foto pendukung refund/banding.
 * Berkas disimpan di uploads/bukti dan uploads/refund (folder pribadi), database hanya menyimpan path relatif.
 * Berkas hanya disajikan lewat api/bukti.php, yang memeriksa hak akses.
 */
require_once __DIR__ . '/foto.php';

/** Path bukti pembayaran sebuah nota (dari database), atau null bila belum diunggah / berkas hilang. */
function bukti_path(string $idNota): ?string
{
    $p = val('SELECT foto_bukti_pembayaran FROM nota_pembeli WHERE id_nota_pembeli = ?', [$idNota]);
    return foto_path_valid((string) $p, 'bukti') && foto_berkas($p) ? $p : null;
}

function bukti_ada(string $idNota): bool
{
    return bukti_path($idNota) !== null;
}

/** Hapus bukti pembayaran (berkas + kolom). */
function hapus_bukti(string $idNota): void
{
    $p = val('SELECT foto_bukti_pembayaran FROM nota_pembeli WHERE id_nota_pembeli = ?', [$idNota]);
    if ($p !== null && $p !== '') {
        foto_hapus((string) $p);
        q('UPDATE nota_pembeli SET foto_bukti_pembayaran = ? WHERE id_nota_pembeli = ?', ['', $idNota]);
    }
}

/** Pesan galat untuk unggahan bukti; null bila layak disimpan. */
function cek_bukti(?array $f): ?string
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return 'Unggah bukti pembayaran (tangkapan layar atau foto struk) terlebih dahulu.';
    }
    return foto_cek($f, true, 100);
}

/** Simpan bukti yang sudah lolos cek_bukti(); menggantikan bukti lama (berkasnya dihapus). */
function simpan_bukti(string $idNota, array $f): bool
{
    if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $idNota)) {
        return false;
    }
    $lama = (string) val('SELECT foto_bukti_pembayaran FROM nota_pembeli WHERE id_nota_pembeli = ?', [$idNota]);
    $baru = foto_simpan($f, 'bukti', $idNota);
    if ($baru === null) {
        return false;
    }
    q('UPDATE nota_pembeli SET foto_bukti_pembayaran = ? WHERE id_nota_pembeli = ?', [$baru, $idNota]);
    foto_hapus($lama);
    return true;
}

function bukti_mime(string $berkas): string
{
    return foto_mime($berkas);
}
