<?php
/** Cetak struk pembelian / pembayaran sebagai PDF. Dibuka pemilik nota atau admin. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/order.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/struk.php';

$u = require_role('pembeli', 'admin');
$n = nota_lengkap(get('id'));
if (!$n) {
    abort404();
}
if ($u['role'] === 'pembeli' && $n['id_user'] !== $u['id']) {
    abort404(); // pembeli lain tidak boleh tahu nota ini ada
}
struk_pdf($n)->kirim('struk-' . $n['id_nota_pembeli'] . '.pdf', get('unduh') === '1');
