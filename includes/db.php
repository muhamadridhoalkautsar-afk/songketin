<?php
/** Koneksi database (PDO) + pembantu query. Semua query memakai prepared statement. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
        http_response_code(500);
        $pesan = DEBUG ? e($e->getMessage()) : 'Periksa pengaturan koneksi di config/config.php, lalu pastikan database songketin sudah diimpor (database/songketin.sql).';
        exit('<!doctype html><meta charset="utf-8"><title>Database belum siap</title>'
            . '<body style="font-family:system-ui;max-width:560px;margin:15vh auto;padding:0 20px;line-height:1.6">'
            . '<h1 style="font-family:Georgia,serif">Database belum siap</h1><p>' . $pesan . '</p>'
            . '</body>');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

/** Tanda tanya sebanyak $n untuk klausa IN (...) */
function in_marks(array $arr): string
{
    return implode(',', array_fill(0, max(1, count($arr)), '?'));
}

/**
 * ID berformat tetap, mis. next_id('produk','id_produk','PRD') → PRD00017.
 * Panggil di dalam transaksi bila ada risiko dua proses bersamaan.
 */
function next_id(string $table, string $col, string $prefix, int $digits = 5): string
{
    $max = val("SELECT MAX($col) FROM $table WHERE $col LIKE ?", [$prefix . '%']);
    $n = $max ? (int) substr($max, strlen($prefix)) : 0;
    return $prefix . str_pad((string) ($n + 1), $digits, '0', STR_PAD_LEFT);
}

/** Jalankan $fn dalam transaksi; commit bila sukses, rollback bila ada exception. */
function transaksi(callable $fn)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $hasil = $fn();
        $pdo->commit();
        return $hasil;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
