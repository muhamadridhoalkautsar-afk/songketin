<?php
/**
 * Songketin — menetapkan Super Admin pertama (hanya dari terminal).
 *
 * Database bawaan belum memiliki akun super_admin. Super Admin pertama harus ditetapkan dari server, karena hanya
 * Super Admin yang dapat menambah admin lewat situs. Skrip ini hanya mengubah/menambah BARIS data di tabel users;
 * tidak mengubah struktur database.
 *
 *   php tools/super_admin.php --promosi=ridho                       # jadikan akun yang sudah ada sebagai Super Admin
 *   php tools/super_admin.php --buat=pengelola --email=a@b.c --sandi=rahasia   # buat akun Super Admin baru
 *
 * Folder tools/ ditutup dari web oleh router.php/.htaccess; hapus folder ini di server sungguhan setelah dipakai.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dapat dijalankan dari terminal.');
}
require_once __DIR__ . '/../includes/bootstrap.php';

$o = getopt('', ['promosi:', 'buat:', 'email:', 'sandi:']);
if (isset($o['promosi'])) {
    $u = row('SELECT id_user, username, role FROM users WHERE LOWER(username) = LOWER(?)', [$o['promosi']]);
    if (!$u) {
        exit("Akun \"{$o['promosi']}\" tidak ditemukan.\n");
    }
    if (!in_array($u['role'], ['admin', 'super_admin'], true)) {
        exit("Akun \"{$u['username']}\" berperan {$u['role']}. Hanya akun admin yang dapat dipromosikan; buat akun baru dengan --buat.\n");
    }
    q('UPDATE users SET role = ? WHERE id_user = ?', ['super_admin', $u['id_user']]);
    exit("Akun \"{$u['username']}\" sekarang Super Admin.\n");
}
if (isset($o['buat'], $o['email'], $o['sandi'])) {
    $user = mb_strtolower($o['buat']);
    if (!preg_match('/^[a-z0-9_.]{4,30}$/', $user) || !filter_var($o['email'], FILTER_VALIDATE_EMAIL) || $o['sandi'] === '') {
        exit("Nama pengguna (4–30 huruf kecil/angka/titik/garis bawah), email, dan sandi harus valid.\n");
    }
    if (val('SELECT 1 FROM users WHERE LOWER(username) = ? OR LOWER(email) = ?', [$user, mb_strtolower($o['email'])])) {
        exit("Nama pengguna atau email itu sudah dipakai.\n");
    }
    $id = next_id('users', 'id_user', 'USR');
    q('INSERT INTO users (id_user, username, email, password, role) VALUES (?, ?, ?, ?, ?)', [$id, $user, mb_strtolower($o['email']), sandi_hash($o['sandi']), 'super_admin']);
    exit("Super Admin \"$user\" dibuat ($id).\n");
}
echo "Pemakaian:\n  php tools/super_admin.php --promosi=<username admin>\n  php tools/super_admin.php --buat=<username> --email=<email> --sandi=<sandi>\n";
