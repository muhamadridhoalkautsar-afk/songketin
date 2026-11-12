<?php
/**
 * Router untuk server bawaan PHP (tanpa Apache):
 *   php -S localhost:8000 router.php
 * Meniru perilaku .htaccess: folder internal ditutup, alamat tak dikenal → 404.php.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = __DIR__ . $path;
if (preg_match('#^/(includes|config|database|tools)(/|$)#', $path) || preg_match('#\.(sql|md|log|lock|ini|sqlite)$#', $path)
    || preg_match('#^/uploads/(bukti|refund)(/|$)#', $path) || preg_match('#^/uploads/.*\.(php|phtml|phar|php[0-9])$#i', $path)) { // bukti pembayaran & refund hanya lewat api/bukti.php (ada pemeriksaan hak akses)
    require __DIR__ . '/404.php';
    return true;
}
if (is_dir($file)) {
    $idx = rtrim($file, '/') . '/index.php';
    if (is_file($idx)) {
        $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = $idx;
        chdir(dirname($idx));
        require $idx;
        return true;
    }
}
if (is_file($file)) {
    return false; // berkas nyata (statis atau .php) dilayani apa adanya
}
require __DIR__ . '/404.php';
return true;
