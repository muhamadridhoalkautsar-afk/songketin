<?php
/** Dimuat paling awal oleh setiap halaman: konfigurasi, sesi, database, autentikasi, tata letak. */

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/status.php';
if (!defined('BASE_URL')) {
    define('BASE_URL', detect_base_url());
}
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('SONGKETINSESS');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $https,
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

set_exception_handler(function (Throwable $e) {
    error_log('[songketin] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (function_exists('is_ajax') && is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'pesan' => DEBUG ? $e->getMessage() : 'Terjadi kesalahan di server.']));
    }
    echo '<!doctype html><meta charset="utf-8"><title>Terjadi kesalahan</title>'
        . '<body style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 20px;line-height:1.6">'
        . '<h1 style="font-family:Georgia,serif">Benang kusut sebentar…</h1>'
        . '<p>Terjadi kesalahan di server. Silakan coba lagi, atau kembali ke <a href="' . e(url()) . '">beranda</a>.</p>'
        . (DEBUG ? '<pre style="white-space:pre-wrap;background:#f6efe2;padding:12px;border-radius:8px">' . e((string) $e) . '</pre>' : '')
        . '</body>';
    exit;
});

require_once __DIR__ . '/auth.php';

// Baris referensi status refund/banding (data saja) dipastikan ada; dicek sekali per sesi.
if (empty($_SESSION['_ref_ok'])) {
    try {
        siapkan_referensi();
        $_SESSION['_ref_ok'] = 1;
    } catch (Throwable $e) {
        error_log('[songketin] siapkan_referensi: ' . $e->getMessage());
    }
}
require_once __DIR__ . '/wilayah.php';
require_once __DIR__ . '/layout.php';
