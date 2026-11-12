<?php
/** Hitung ulang ringkasan checkout (dipanggil JavaScript saat pilihan berubah). Selalu dihitung di server. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/views.php';

if (!logged_in() || user()['role'] !== 'pembeli') {
    json_out(['ok' => false, 'pesan' => 'Silakan masuk sebagai pembeli.'], 401);
}
if (!is_post()) {
    json_out(['ok' => false], 405);
}
csrf_check();
$kr = cart_load();
$hit = hitung_checkout($kr, profil_pembeli(), [
    'opsi' => post('opsi'), 'diskon_ongkir' => post('diskon_ongkir'), 'koin' => post('koin'),
]);
json_out(['ok' => true, 'html' => ringkasan_checkout_html($hit), 'siap' => $hit['siap'], 'total' => $hit['total']]);
