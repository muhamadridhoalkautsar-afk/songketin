<?php
/**
 * Pengaturan aplikasi.
 *
 * Koneksi database TIDAK didefinisikan di sini: berkas config/config.php (milik pengguna, tidak diubah) dimuat apa adanya
 * dan variabel yang ia buat ($host, $user, $pass, $db) dipakai ulang oleh lapisan PDO di includes/db.php.
 */
require_once __DIR__ . '/../config/config.php';

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));
}

// ---- koneksi (diturunkan dari config/config.php)
$__host = (string) ($host ?? 'localhost');
$__port = 3306;
if (preg_match('/^(.+):(\d{2,5})$/', $__host, $__m)) {   // mis. "127.0.0.1:3307"
    $__host = $__m[1];
    $__port = (int) $__m[2];
}
define('DB_HOST', $__host);
define('DB_PORT', $__port);
define('DB_USER', (string) ($user ?? 'root'));
define('DB_PASS', (string) ($pass ?? ''));
define('DB_NAME', (string) ($db ?? 'songketin'));
unset($__host, $__port, $__m);

// ---- identitas
const APP_NAME = 'Songketin';
const APP_TAGLINE = 'Marketplace Tenun Nusantara';

// ---- galat: rinci hanya bila dibuka dari komputer sendiri (localhost) atau SONGKETIN_DEBUG=1
define('DEBUG', getenv('SONGKETIN_DEBUG') === '1' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true));
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Jakarta');

// ---- aturan bisnis
const MAX_UPLOAD_BYTES = 2 * 1024 * 1024;   // foto: maksimal 2 MB
const PER_PAGE = 12;                         // produk per halaman katalog
const REFUND_BATAS_HARI = 7;                // refund barang diterima: maksimal 7 hari sejak diterima
const NILAI_KOIN = 100;                     // 1 koin = Rp100 potongan
const RUPIAH_PER_KOIN_DIDAPAT = 10000;      // tiap Rp10.000 belanja yang diterima = 1 koin
const KOIN_MAKS_PERSEN = 50;                // koin boleh memotong paling banyak 50% harga barang
