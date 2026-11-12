<?php
/** Kerangka halaman: <head>, loader, navigasi (atas untuk pembeli, samping untuk penjual/admin), footer. */

require_once __DIR__ . '/loader.php';

function ikon(string $nama, int $ukuran = 20): string
{
    static $p = [
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/>',
        'plus' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
        'orders' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>',
        'chart' => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'truck' => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'star' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.9L12 17.8 5.8 21.1 7 14.2 2 9.3l6.9-1z"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/>',
        'card' => '<rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/>',
        'trash' => '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 6V4a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v2"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'coin' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.7c.4-1 1.4-1.4 2.5-1.4 1.4 0 2.5.7 2.5 1.8 0 2.4-5 1.2-5 3.8 0 1.1 1.1 1.9 2.5 1.9 1.1 0 2.1-.5 2.5-1.5"/>',
        'back' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'star' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eyeoff' => '<path d="M17.9 17.9A10.9 10.9 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.1-5.9M9.9 4.2A9.1 9.1 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.2 3.2M14.1 14.1a3 3 0 1 1-4.2-4.2"/><path d="M1 1l22 22"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L21 2M16 7l3 3M14 9l2 2"/>',
    ];
    return '<svg class="ikon" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($p[$nama] ?? '') . '</svg>';
}

function logo_svg(int $s = 34): string
{
    return '<svg class="logo" width="' . $s . '" height="' . $s . '" viewBox="0 0 40 40" aria-hidden="true">'
        . '<rect width="40" height="40" rx="10" fill="#6b1a28"/>'
        . '<path d="M6 12h28M6 20h28M6 28h28" stroke="#3f0d17" stroke-width="2.4"/>'
        . '<path d="M10 6v28M20 6v28M30 6v28" stroke="#3f0d17" stroke-width="2.4"/>'
        . '<path d="M20 8l9 12-9 12-9-12z" fill="#e3b23c"/><path d="M20 14l4.6 6-4.6 6-4.6-6z" fill="#6b1a28"/><circle cx="20" cy="20" r="1.8" fill="#e3b23c"/></svg>';
}

function menu_for(string $role): array
{
    switch ($role) {
        case 'pembeli':
            return [['katalog', 'pembeli/index.php', 'Katalog', 'home'], ['pesanan', 'pembeli/pesanan.php', 'Pesanan Saya', 'orders'],
                ['refund', 'pembeli/refund.php', 'Refund', 'back'], ['profil', 'pembeli/profil.php', 'Profil', 'user']];
        case 'penjual':
            return [['ringkasan', 'penjual/index.php', 'Ringkasan', 'home'], ['produk', 'penjual/produk.php', 'Produk Saya', 'box'],
                ['tambah', 'penjual/produk_form.php', 'Tambah Produk', 'plus'], ['pesanan', 'penjual/pesanan.php', 'Pesanan Masuk', 'orders'],
                ['ulasan', 'penjual/ulasan.php', 'Ulasan', 'star'], ['refund', 'penjual/refund.php', 'Refund', 'back'], ['laporan', 'penjual/laporan.php', 'Laporan', 'chart'],
                ['profil', 'penjual/profil.php', 'Profil Toko', 'user']];
        case 'admin':
        case 'super_admin':
            if ($role === 'super_admin') {   // Super Admin hanya mengelola akun
                return [['akun', 'admin/akun.php', 'Kelola Akun', 'users']];
            }
            return array_merge([['ringkasan', 'admin/index.php', 'Ringkasan', 'home']], [['pesanan', 'admin/pesanan.php', 'Pesanan', 'orders'], ['pengiriman', 'admin/pengiriman.php', 'Pengiriman', 'truck'],
                ['laporan', 'admin/laporan.php', 'Barang Terjual', 'chart'], ['refund', 'admin/refund.php', 'Banding Refund', 'back'], ['master', 'admin/master.php', 'Data Master', 'box']]);
    }
    return [];
}

function cart_count(): int
{
    return array_sum(array_map('intval', $_SESSION['cart'] ?? []));
}

/**
 * Buka halaman. Opsi: 'menu' (kunci menu aktif), 'judul' (judul di header dashboard),
 * 'layout' (public|shop|dash — otomatis menurut peran), 'wilayah' (true bila memakai alamat bertingkat), 'body' (kelas tambahan).
 */
function page_start(string $title, array $o = []): void
{
    $u = user();
    $layout = $o['layout'] ?? ($u ? ($u['role'] === 'pembeli' ? 'shop' : 'dash') : 'public');
    $aktif = $o['menu'] ?? '';
    $GLOBALS['_layout'] = $layout;
    $GLOBALS['_opt'] = $o;
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#6b1a28">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(asset('fonts/fraunces-latin-700-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tenun.css')) ?>">
<noscript><style>#loader{display:none!important}</style></noscript>
<script>/* Masih di halaman yang sama (filter, tab, simpan, ubah status…) → tanpa animasi menenun. */(function(){try{var n=function(p){return p.replace(/index\.php$/,'');},r=document.referrer;if(r){var u=new URL(r);if(u.origin===location.origin&&n(u.pathname)===n(location.pathname))document.documentElement.className+=' satu-halaman';}}catch(e){}})();</script>
<script>window.SK={base:<?= json_encode(BASE_URL) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
</head>
<body class="layout-<?= e($layout) ?> <?= e($o['body'] ?? '') ?>">
<?= loader_html() ?>
<a class="lompat" href="#isi">Lewati ke konten</a>
<?php if ($layout === 'shop' && $u): ?>
<header class="topbar">
  <div class="topbar-in container">
    <a class="brand" href="<?= e(url('pembeli/index.php')) ?>"><?= logo_svg(34) ?><span><?= APP_NAME ?></span></a>
    <form class="search" action="<?= e(url('pembeli/index.php')) ?>" method="get" role="search">
      <?= ikon('search', 18) ?><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Cari songket, selendang, sarung…" aria-label="Cari produk">
    </form>
    <nav class="topnav" aria-label="Menu utama">
      <?php foreach (menu_for('pembeli') as [$k, $href, $label, $ik]): ?>
        <a class="<?= $aktif === $k ? 'aktif' : '' ?>" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="cart-link <?= $aktif === 'keranjang' ? 'aktif' : '' ?>" href="<?= e(url('pembeli/keranjang.php')) ?>" aria-label="Keranjang belanja">
        <?= ikon('cart', 22) ?><span class="cart-count" data-cart-count<?= cart_count() ? '' : ' hidden' ?>><?= cart_count() ?></span>
      </a>
    </nav>
    <div class="usermenu">
      <span class="user-chip" title="<?= e($u['username']) ?>"><?= ikon('user', 16) ?><?= e(mb_strimwidth($u['nama'], 0, 18, '…')) ?></span>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>"><?= ikon('logout', 16) ?> Keluar</a>
    </div>
  </div>
  <div class="tumpal" aria-hidden="true"></div>
</header>
<main id="isi" class="container main-shop">
<?php elseif ($layout === 'dash' && $u): ?>
<div class="dash">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url(home_for($u['role']))) ?>"><?= logo_svg(36) ?><span><?= APP_NAME ?></span></a>
    <div class="peran"><?= $u['role'] === 'super_admin' ? 'Pusat Super Admin' : ($u['role'] === 'admin' ? 'Pusat Admin' : 'Ruang Penjual') ?></div>
    <nav class="side-nav" aria-label="Menu dashboard">
      <?php foreach (menu_for($u['role']) as [$k, $href, $label, $ik]): ?>
        <a class="<?= $aktif === $k ? 'aktif' : '' ?>" href="<?= e(url($href)) ?>"><?= ikon($ik, 19) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-bawah">
      <div class="side-user"><span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nama'], 0, 1))) ?></span>
        <div><strong><?= e(mb_strimwidth($u['nama'], 0, 22, '…')) ?></strong><small>@<?= e($u['username']) ?></small></div></div>
      <a class="btn btn-ghost-light btn-sm btn-block" href="<?= e(url('logout.php')) ?>"><?= ikon('logout', 16) ?> Keluar</a>
    </div>
    <div class="tumpal tumpal-v" aria-hidden="true"></div>
  </aside>
  <div class="sidebar-scrim" data-sidebar-tutup></div>
  <div class="dash-main">
    <header class="dash-top">
      <button type="button" class="btn-ikon menu-btn" data-sidebar-buka aria-label="Buka menu"><?= ikon('menu', 22) ?></button>
      <h1 class="dash-judul"><?= e($o['judul'] ?? $title) ?></h1>
    </header>
    <main id="isi" class="dash-content">
<?php else: ?>
<main id="isi" class="main-public">
<?php endif; ?>
<?= ($o['flash'] ?? true) ? flash_render() : '' ?>
<?php
}

function page_end(array $scripts = []): void
{
    $layout = $GLOBALS['_layout'] ?? 'public';
    $o = $GLOBALS['_opt'] ?? [];
    ?>
</main>
<?php if ($layout === 'dash'): ?>
  </div>
</div>
<?php elseif ($layout === 'shop'): ?>
<footer class="footer">
  <div class="tumpal tumpal-terbalik" aria-hidden="true"></div>
  <div class="container footer-in">
    <div><strong class="footer-brand"><?= APP_NAME ?></strong><p><?= e(APP_TAGLINE) ?>. Songket, tenun ikat, dan lurik langsung dari para penenun.</p></div>
    <small>&copy; <?= date('Y') ?> <?= APP_NAME ?>. </small>
  </div>
</footer>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($o['wilayah'])): ?><script src="<?= e(asset('js/wilayah.js')) ?>"></script><?php endif; ?>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset($s)) ?>"></script><?php endforeach; ?>
</body>
</html>
<?php
}
