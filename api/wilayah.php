<?php
/** Daftar anak wilayah: /api/wilayah.php?level=kabupaten&induk=16 → [{id,nama},…] (dipakai alamat bertingkat) */
require_once __DIR__ . '/../includes/bootstrap.php';

$level = get('level');
$induk = get('induk');
header('Content-Type: application/json; charset=utf-8');
if (!isset(WILAYAH[$level]) || $level === 'negara' || $induk === '' || strlen($induk) > 10) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'data' => []]));
}
header('Cache-Control: no-cache'); // admin bisa mengubah data wilayah kapan saja
echo json_encode(['ok' => true, 'data' => wilayah_anak($level, $induk)], JSON_UNESCAPED_UNICODE);
