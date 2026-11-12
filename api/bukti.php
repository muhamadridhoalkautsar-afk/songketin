<?php
/**
 * Gambar pribadi (tidak bisa dibuka lewat URL berkas langsung):
 *   /api/bukti.php?id=NPB00001                       → bukti pembayaran (admin dan pembeli pemilik pesanan)
 *   /api/bukti.php?jenis=refund&id=NPJ00001&n=<no>   → foto pada riwayat refund/banding toko tersebut
 *                                                       (admin, pembeli pemilik, dan penjual toko itu)
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';

$u = user();
if (!$u) {
    http_response_code(403);
    exit;
}
$id = get('id');
$berkas = null;
$boleh = false;
$ada = false;

if (get('jenis') === 'refund') {
    $r = refund_ambil($id);
    $ada = (bool) $r;
    $ev = $r['riwayat'][(int) get('n', '-1')] ?? null;
    if ($r && $ev && !empty($ev['bukti']) && foto_path_valid($ev['bukti'], 'refund')) {
        $berkas = foto_berkas($ev['bukti']);
        $boleh = is_admin_role($u['role'])
            || ($u['role'] === 'pembeli' && $r['id_user'] === $u['id'])
            || ($u['role'] === 'penjual' && $r['id_penjual'] === ($u['ref'] ?? null));
    }
} else {
    $n = $id !== '' ? nota_lengkap($id) : null;
    $ada = (bool) $n;
    $berkas = $n ? foto_berkas(bukti_path($id)) : null;
    $boleh = $n && (is_admin_role($u['role']) || ($u['role'] === 'pembeli' && $n['id_user'] === $u['id']));
}
if (!$ada || !$berkas) {
    http_response_code(404);
    exit;
}
if (!$boleh) {
    http_response_code(403);
    exit;
}
header('Content-Type: ' . foto_mime($berkas));
header('Content-Length: ' . filesize($berkas));
header('Cache-Control: private, no-cache');
header('X-Content-Type-Options: nosniff');
readfile($berkas);
