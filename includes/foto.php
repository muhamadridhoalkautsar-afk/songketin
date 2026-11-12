<?php
/**
 * Unggahan gambar: validasi, simpan di folder server, dan kelola path relatif yang disimpan di database.
 *
 * Aturan: gambar disimpan sebagai berkas di uploads/{folder}/; database hanya menyimpan path relatif
 * (mis. "uploads/produk/PRD00001_9f3a1c2b7d10.jpg"). Nama berkas selalu unik. Berkas lama dihapus saat diganti/dihapus.
 *   uploads/produk  → foto_produk.foto_produk          (publik)
 *   uploads/qr      → jenis_pembayaran.foto_qr          (publik: pembeli perlu melihat QR tujuan bayar)
 *   uploads/ulasan  → nota_penjual.foto_ulasan          (publik)
 *   uploads/bukti   → nota_pembeli.foto_bukti_pembayaran (pribadi: lewat api/bukti.php)
 *   uploads/refund  → foto bukti pengajuan refund/banding (pribadi: lewat api/bukti.php)
 */

const FOTO_TIPE = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
const FOTO_MAKS_PIKSEL = 16000000; // ± 4000×4000; menjaga memori saat gambar didekode
const FOTO_FOLDER = ['produk', 'qr', 'ulasan', 'bukti', 'refund'];
const FOTO_PRIBADI = ['bukti', 'refund'];

/** Pesan galat bila unggahan tidak layak; null bila boleh disimpan. Berkas kosong dianggap galat hanya bila $wajib. */
function foto_cek(?array $f, bool $wajib = false, int $minPx = 100): ?string
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $wajib ? 'Pilih berkas foto terlebih dahulu.' : null;
    }
    if (is_array($f['error']) || ($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > MAX_UPLOAD_BYTES) {
        return 'Ukuran foto maksimal ' . (int) (MAX_UPLOAD_BYTES / 1048576) . ' MB.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return 'Foto gagal diunggah. Coba pilih berkasnya lagi.';
    }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !isset(FOTO_TIPE[$info[2]])) {
        return 'Format foto harus JPG, PNG, atau WebP.';
    }
    if ($info[0] < $minPx || $info[1] < $minPx) {
        return 'Foto terlalu kecil (minimal ' . $minPx . ' × ' . $minPx . ' piksel).';
    }
    if ($info[0] * $info[1] > FOTO_MAKS_PIKSEL) {
        return 'Dimensi foto terlalu besar (maksimal sekitar 4000 × 4000 piksel).';
    }
    return null;
}

function foto_ada_unggahan(?array $f): bool
{
    return $f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Simpan foto yang sudah lolos foto_cek(). Mengembalikan path relatif untuk disimpan di database, atau null bila gagal.
 * Bila GD tersedia gambar dikecilkan (maks 1200 px) dan disimpan sebagai JPEG, kecuali folder 'qr' (dibiarkan apa adanya agar kode tetap terbaca).
 */
function foto_simpan(array $f, string $folder, string $prefix): ?string
{
    if (!in_array($folder, FOTO_FOLDER, true) || !preg_match('/^[A-Za-z0-9]{1,20}$/', $prefix)) {
        return null;
    }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !isset(FOTO_TIPE[$info[2]])) {
        return null;
    }
    $dir = ROOT_PATH . '/uploads/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return null;
    }
    $dasar = $prefix . '_' . bin2hex(random_bytes(6));
    if ($folder !== 'qr' && function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $skala = min(1, 1600 / max($w, $h));
            $nw = max(1, (int) round($w * $skala));
            $nh = max(1, (int) round($h * $skala));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // latar putih untuk PNG/WebP transparan
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = imagejpeg($dst, $dir . '/' . $dasar . '.jpg', 86);
            imagedestroy($src);
            imagedestroy($dst);
            if ($ok) {
                return 'uploads/' . $folder . '/' . $dasar . '.jpg';
            }
        }
    }
    $nama = $dasar . '.' . FOTO_TIPE[$info[2]];
    return move_uploaded_file($f['tmp_name'], $dir . '/' . $nama) ? 'uploads/' . $folder . '/' . $nama : null;
}

/** Path relatif yang sah (di dalam uploads/{folder}/, tanpa naik direktori)? */
function foto_path_valid(?string $path, ?string $folder = null): bool
{
    if ($path === null || $path === '' || strpos($path, '..') !== false) {
        return false;
    }
    $f = $folder !== null ? preg_quote($folder, '#') : '(?:' . implode('|', FOTO_FOLDER) . ')';
    return (bool) preg_match('#^uploads/' . $f . '/[A-Za-z0-9_.-]+\.(?:jpe?g|png|webp)$#', $path);
}

/** Alamat berkas di disk untuk path relatif, atau null bila path tidak sah / berkas tidak ada. */
function foto_berkas(?string $path): ?string
{
    if (!foto_path_valid($path)) {
        return null;
    }
    $f = ROOT_PATH . '/' . $path;
    return is_file($f) ? $f : null;
}

/** Hapus berkas lama (aman: hanya di dalam uploads/). */
function foto_hapus(?string $path): void
{
    if ($f = foto_berkas($path)) {
        @unlink($f);
    }
}

/** URL publik sebuah foto (folder publik). Null bila tidak ada berkasnya. */
function foto_url(?string $path): ?string
{
    $f = foto_berkas($path);
    if (!$f) {
        return null;
    }
    return BASE_URL . '/' . $path . '?v=' . filemtime($f);
}

function foto_mime(string $berkas): string
{
    return ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($berkas, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

// ---------------------------------------------------------------- foto produk
/** Foto produk: unggahan penjual (path dari database) bila berkasnya ada; selain itu kain hasil gambar otomatis. */
function foto_produk_url(string $idProduk, ?string $path = null): string
{
    return foto_url($path) ?? url('api/swatch.php?id=' . rawurlencode($idProduk));
}
