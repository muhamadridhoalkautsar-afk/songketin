<?php
/** Gambar kain otomatis untuk sebuah produk: /api/swatch.php?id=PRD00001 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/swatch.php';

$id = get('id');
$p = $id === '' ? null : row(
    'SELECT p.id_produk, p.warna, k.nama_pola_kain, b.nama_benang
       FROM produk p
       LEFT JOIN pola_kain k ON k.id_pola_kain = p.id_pola_kain
       LEFT JOIN jenis_benang b ON b.id_jenis_benang = p.id_jenis_benang
      WHERE p.id_produk = ?',
    [$id]
);
if (!$p && get('pratinjau') === '1') { // pratinjau gambar otomatis di formulir produk penjual
    $pola = get('pola') !== '' ? row('SELECT nama_pola_kain FROM pola_kain WHERE id_pola_kain = ?', [get('pola')]) : null;
    $benang = get('benang') !== '' ? row('SELECT nama_benang FROM jenis_benang WHERE id_jenis_benang = ?', [get('benang')]) : null;
    $p = ['id_produk' => 'pratinjau', 'warna' => mb_substr(get('warna'), 0, 50), 'nama_pola_kain' => $pola['nama_pola_kain'] ?? '', 'nama_benang' => $benang['nama_benang'] ?? 'Emas'];
}
if (!$p) {
    $p = ['id_produk' => 'x', 'warna' => 'Marun', 'nama_pola_kain' => '', 'nama_benang' => 'Emas'];
}
$svg = swatch_svg($p);
$etag = '"' . md5($svg) . '"';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
echo $svg;
