<?php
/**
 * Autentikasi & otorisasi: sesi pengguna, penjaga halaman per peran, kata sandi (teks biasa).
 *
 * Peran (users.role): pembeli, penjual, admin, super_admin.
 * Pembagian tugas: super_admin HANYA mengelola akun (semua peran, termasuk menambah admin & hak akses);
 * admin mengurus operasional (data master, pesanan, pengiriman, laporan, refund/banding). Hak keduanya tidak tumpang tindih.
 */

const PERAN_ADMIN = ['admin', 'super_admin'];

function folder_peran(string $role): string
{
    return in_array($role, PERAN_ADMIN, true) ? 'admin' : $role;
}

function home_for(string $role): string
{
    return ['pembeli' => 'pembeli/index.php', 'penjual' => 'penjual/index.php', 'admin' => 'admin/index.php', 'super_admin' => 'admin/akun.php'][$role] ?? 'index.php';
}

function label_peran(string $role): string
{
    return ['pembeli' => 'Pembeli', 'penjual' => 'Penjual', 'admin' => 'Admin', 'super_admin' => 'Super Admin'][$role] ?? $role;
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function logged_in(): bool
{
    return !empty($_SESSION['user']);
}

/** Peran yang menjalankan operasional (bukan pengelola akun): hanya 'admin'. */
function is_admin_role(?string $role): bool
{
    return $role === 'admin';
}

function is_super_admin(): bool
{
    return (user()['role'] ?? '') === 'super_admin';
}

/** Tingkat terendah (syarat paling kecil) untuk anggota baru; admin bisa mengubah daftar tingkat. */
function tingkat_awal(string $tabel, string $kolomId, string $kolomSyarat, string $cadangan): string
{
    return (string) (val("SELECT $kolomId FROM $tabel ORDER BY $kolomSyarat, $kolomId LIMIT 1") ?: $cadangan);
}

/**
 * Akun lama (mis. hasil input langsung ke database) bisa tidak punya baris profil pembeli/penjual.
 * Profil dasar dibuat saat masuk supaya akun itu tetap bisa dipakai; alamat dilengkapi pengguna di halaman profil.
 */
function pastikan_profil(array $u): void
{
    if ($u['role'] === 'pembeli' && !val('SELECT 1 FROM pembeli WHERE id_user = ?', [$u['id_user']])) {
        q('INSERT INTO pembeli (id_pembeli, id_user, nama_pembeli, id_desa, detail_alamat, id_tingkat_pembeli) VALUES (?,?,?,?,?,?)',
            [next_id('pembeli', 'id_pembeli', 'PMB'), $u['id_user'], $u['username'], null, null, tingkat_awal('tingkat_pembeli', 'id_tingkat_pembeli', 'akumulasi_koin', 'TB01')]);
        flash('warn', 'Profil Anda belum lengkap. Isi nama dan alamat di halaman Profil sebelum berbelanja.');
    } elseif ($u['role'] === 'penjual' && !val('SELECT 1 FROM penjual WHERE id_user = ?', [$u['id_user']])) {
        q('INSERT INTO penjual (id_penjual, id_user, nama_penjual, id_desa, alamat_lengkap_penjual, no_telepon, no_rekening, jenis_pembayaran, id_tingkat_penjual) VALUES (?,?,?,?,?,?,?,?,?)',
            [next_id('penjual', 'id_penjual', 'PNJ'), $u['id_user'], $u['username'], null, null, null, null, null, tingkat_awal('tingkat_penjual', 'id_tingkat_penjual', 'minimal_transaksi', 'TJ01')]);
        flash('warn', 'Profil toko Anda belum lengkap. Isi nama toko, alamat, dan rekening di halaman Profil Toko.');
    }
}

/** Simpan identitas ke sesi setelah kata sandi benar. */
function login_user(array $u): void
{
    pastikan_profil($u);
    session_regenerate_id(true);
    $nama = $u['username'];
    $ref = null;
    if ($u['role'] === 'pembeli') {
        $p = row('SELECT id_pembeli, nama_pembeli FROM pembeli WHERE id_user = ?', [$u['id_user']]);
        $nama = $p['nama_pembeli'] ?? $nama;
        $ref = $p['id_pembeli'] ?? null;
    } elseif ($u['role'] === 'penjual') {
        $p = row('SELECT id_penjual, nama_penjual FROM penjual WHERE id_user = ?', [$u['id_user']]);
        $nama = $p['nama_penjual'] ?? $nama;
        $ref = $p['id_penjual'] ?? null;
    }
    $_SESSION['user'] = [
        'id' => $u['id_user'], 'username' => $u['username'], 'email' => $u['email'],
        'role' => $u['role'], 'nama' => $nama, 'ref' => $ref,
    ];
    unset($_SESSION['gagal']);
}

function logout_user(bool $hancurkan = false): void
{
    unset($_SESSION['user'], $_SESSION['cart']);
    if ($hancurkan) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $c = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $c['path'], $c['domain'], $c['secure'], $c['httponly']);
        }
        session_destroy();
    } else {
        session_regenerate_id(true);
    }
}

/**
 * Penjaga halaman (diperiksa di server pada setiap permintaan, bukan hanya menyembunyikan tombol).
 * Tanpa login → halaman masuk; peran salah → ke dashboard sendiri. Peran dicocokkan persis (super_admin tidak otomatis boleh membuka halaman admin).
 * Mengembalikan data pengguna.
 */
function require_role(string ...$roles): array
{
    $u = user();
    if (!$u) {
        $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warn', 'Silakan masuk dulu untuk membuka halaman itu.');
        redirect('login.php');
    }
    $r = row('SELECT role FROM users WHERE id_user = ?', [$u['id']]);   // peran dibaca ulang dari database setiap kali
    if (!$r) {
        logout_user();
        flash('warn', 'Sesi Anda berakhir. Silakan masuk lagi.');
        redirect('login.php');
    }
    if ($r['role'] !== $u['role']) {   // peran diubah super admin saat sesi berjalan
        $_SESSION['user']['role'] = $r['role'];
        $u['role'] = $r['role'];
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        flash('warn', 'Halaman itu bukan untuk peran Anda.');
        redirect(home_for($u['role']));
    }
    return $u;
}

/** Halaman khusus super admin (mis. menambah admin). */
function require_super_admin(): array
{
    return require_role('super_admin');
}

function profil_pembeli(): array
{
    static $p = null;
    if ($p === null) {
        $p = row('SELECT * FROM pembeli WHERE id_user = ?', [user()['id']]);
        if (!$p) {
            abort403('Profil pembeli tidak ditemukan.');
        }
    }
    return $p;
}

function profil_penjual(): array
{
    static $p = null;
    if ($p === null) {
        $p = row('SELECT * FROM penjual WHERE id_user = ?', [user()['id']]);
        if (!$p) {
            abort403('Profil penjual tidak ditemukan.');
        }
    }
    return $p;
}

// ---------------------------------------------------------------- pembatasan percobaan masuk
function login_terblokir(): int
{
    $g = $_SESSION['gagal'] ?? null;
    if ($g && $g['n'] >= 5) {
        $sisa = 300 - (time() - $g['t']);
        if ($sisa > 0) {
            return $sisa;
        }
        unset($_SESSION['gagal']);
    }
    return 0;
}

function login_gagal(): void
{
    $g = $_SESSION['gagal'] ?? ['n' => 0, 't' => time()];
    $g['n']++;
    $g['t'] = time();
    $_SESSION['gagal'] = $g;
}

/** Tujuan setelah masuk: halaman yang tadi dituju (bila milik perannya) atau dashboard. */
function tujuan_setelah_masuk(array $u): string
{
    $next = $_SESSION['next'] ?? '';
    unset($_SESSION['next']);
    $awal = BASE_URL . '/' . folder_peran($u['role']) . '/';
    if ($next !== '' && strpos($next, $awal) === 0 && strpos($next, "\n") === false && strpos($next, "\r") === false) {
        return $next;
    }
    return home_for($u['role']);
}

// ---------------------------------------------------------------- kata sandi (disimpan apa adanya, tanpa enkripsi — atas permintaan pemilik proyek)
/** Nilai yang disimpan di users.password: kata sandi apa adanya. */
function sandi_hash(string $sandi): string
{
    return $sandi;
}

/** Apakah nilai tersimpan berupa hash bcrypt/argon peninggalan versi lama? */
function sandi_adalah_hash(string $tersimpan): bool
{
    return strlen($tersimpan) >= 60 && (bool) preg_match('/^\$(2[aby]|argon2id?)\$/', $tersimpan);
}

/** Cocokkan kata sandi yang diketik dengan yang tersimpan (teks biasa; hash lama masih dikenali agar akunnya tetap bisa masuk). */
function sandi_cocok(string $diketik, string $tersimpan): bool
{
    if (sandi_adalah_hash($tersimpan)) {
        return password_verify($diketik, $tersimpan);
    }
    return $tersimpan !== '' && hash_equals($tersimpan, $diketik);
}

/** Hash peninggalan versi lama disimpan ulang sebagai teks biasa saat pemiliknya berhasil masuk. */
function sandi_perlu_hash_ulang(string $tersimpan): bool
{
    return sandi_adalah_hash($tersimpan);
}
