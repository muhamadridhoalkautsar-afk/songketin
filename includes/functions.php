<?php
/** Fungsi bantu umum: escape, URL, format, flash, CSRF, paginasi. */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function detect_base_url(): string
{
    $root = str_replace('\\', '/', ROOT_PATH);
    $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    if ($docroot !== '' && strpos($root, $docroot) === 0) {
        return rtrim(substr($root, strlen($docroot)), '/');
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $name = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script !== '' && strpos($script, $root) === 0) {
        $rel = substr($script, strlen($root));
        if ($rel !== '' && substr($name, -strlen($rel)) === $rel) {
            return rtrim(substr($name, 0, -strlen($rel)), '/');
        }
    }
    return '';
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $f = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($f) ? filemtime($f) : 1;
    return BASE_URL . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post(string $k, string $d = ''): string
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

function get(string $k, string $d = ''): string
{
    $v = $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $d;
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------- format
function rp($n): string
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function angka($n): string
{
    return number_format((float) $n, 0, ',', '.');
}

function bulan_id(int $m): string
{
    static $b = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $b[$m] ?? '';
}

function tgl(?string $dt, bool $jam = false): string
{
    if (!$dt) {
        return '—';
    }
    $t = strtotime($dt);
    if (!$t) {
        return '—';
    }
    $s = date('j', $t) . ' ' . substr(bulan_id((int) date('n', $t)), 0, 3) . ' ' . date('Y', $t);
    return $jam ? $s . ', ' . date('H.i', $t) . ' WIB' : $s;
}

function persen_diskon(float $harga, float $potongan): int
{
    return $harga > 0 && $potongan > 0 ? (int) round($potongan / $harga * 100) : 0;
}

/** Ambil angka bulat dari isian (boleh berisi titik/koma/"Rp"). */
function angka_dari(string $s): int
{
    return (int) preg_replace('/\D+/', '', $s);
}

function badge(string $teks, ?string $jenis = null): string
{
    static $peta = [
        'Lunas' => 'ok', 'Selesai' => 'ok', 'Diterima' => 'ok', 'Tersedia' => 'ok',
        'Menunggu Verifikasi' => 'warn', 'Menunggu Pembayaran' => 'warn', 'Belum Dibayar' => 'warn', 'Bayar di Tempat' => 'info',
        'Diproses' => 'info', 'Dikemas' => 'info', 'Dikirim' => 'info', 'Diserahkan ke Kurir' => 'info',
        'Dalam Perjalanan' => 'info', 'Tiba di Kota Tujuan' => 'info', 'Sedang Diantar' => 'info', 'Belum Dikirim' => 'muted',
        'Dibatalkan' => 'bad', 'Habis' => 'bad', 'Nonaktif' => 'muted',
    ];
    $j = $jenis ?? ($peta[$teks] ?? 'muted');
    return '<span class="badge badge-' . e($j) . '">' . e($teks) . '</span>';
}

// ---------------------------------------------------------------- flash
function flash(string $jenis, string $pesan): void
{
    $_SESSION['_flash'][] = ['t' => $jenis, 'm' => $pesan];
}

function flash_render(): string
{
    $out = '';
    foreach ($_SESSION['_flash'] ?? [] as $f) {
        $out .= '<div class="alert alert-' . e($f['t']) . '" role="alert"><span>' . e($f['m']) . '</span>'
            . '<button type="button" class="alert-x" aria-label="Tutup" data-tutup>&times;</button></div>';
    }
    unset($_SESSION['_flash']);
    return $out;
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Dipanggil di awal setiap handler POST. */
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (is_ajax()) {
            json_out(['ok' => false, 'pesan' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.'], 419);
        }
        flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

// ---------------------------------------------------------------- halaman
function abort404(): void
{
    require ROOT_PATH . '/404.php';
    exit;
}

function abort403(string $pesan = 'Anda tidak berhak membuka halaman ini.'): void
{
    http_response_code(403);
    exit('<!doctype html><meta charset="utf-8"><title>Akses ditolak</title><body style="font-family:system-ui;max-width:520px;margin:15vh auto;padding:0 20px">'
        . '<h1 style="font-family:Georgia,serif">Akses ditolak</h1><p>' . e($pesan) . '</p><p><a href="' . e(url()) . '">Kembali</a></p></body>');
}

/** Hitung paginasi: kembalikan [halaman, totalHalaman, offset]. */
function paginasi(int $total, int $perPage, int $halaman): array
{
    $totalHal = max(1, (int) ceil($total / $perPage));
    $halaman = min(max(1, $halaman), $totalHal);
    return [$halaman, $totalHal, ($halaman - 1) * $perPage];
}

function paginasi_html(int $halaman, int $totalHal, array $query = []): string
{
    if ($totalHal <= 1) {
        return '';
    }
    $link = function (int $p, string $label, string $kelas = '') use ($query) {
        $query['hal'] = $p;
        return '<a class="pg ' . $kelas . '" href="?' . e(http_build_query($query)) . '">' . $label . '</a>';
    };
    $o = '<nav class="paginasi" aria-label="Halaman">';
    if ($halaman > 1) {
        $o .= $link($halaman - 1, '&laquo;');
    }
    for ($p = max(1, $halaman - 2); $p <= min($totalHal, $halaman + 2); $p++) {
        $o .= $link($p, (string) $p, $p === $halaman ? 'aktif' : '');
    }
    if ($halaman < $totalHal) {
        $o .= $link($halaman + 1, '&raquo;');
    }
    return $o . '</nav>';
}

function harga_akhir(array $p): float
{
    return max(0.0, (float) $p['harga_produk'] - (float) ($p['besaran_potongan_harga'] ?? 0));
}
