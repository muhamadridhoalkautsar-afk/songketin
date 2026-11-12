<?php
/**
 * Beranda Songketin (halaman publik). Navigasi mengikuti status masuk pengguna; koleksi yang ditampilkan
 * diambil dari tabel produk (bukan contoh tetap). Belanja dan berjualan ada di dashboard tiap peran.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/foto.php';

$me = user();
$urlMarket = $me ? url(home_for($me['role'])) : url('login.php');
$urlMasuk = url('login.php');
$urlDaftar = url('login.php?tab=daftar');

// Koleksi: produk tersedia terbaru (maks. 2), lengkap dengan asal daerah, motif, benang, dan jenis produksi dari tabel referensi.
$koleksi = rows('SELECT p.id_produk, p.nama_produk, p.deskripsi, p.warna, p.harga_produk, p.besaran_potongan_harga, p.foto_produk,
                        ad.nama_asal_daerah, pk.nama_pola_kain, jb.nama_benang, jp.nama_jenis_produksi, kt.nama_kategori_produk, pj.nama_penjual
                   FROM produk p
                   JOIN kategori_produk kt ON kt.id_kategori_produk = p.id_kategori_produk
                   JOIN penjual pj ON pj.id_penjual = p.id_penjual
                   LEFT JOIN asal_daerah ad ON ad.id_asal_daerah = p.id_asal_daerah
                   LEFT JOIN pola_kain pk ON pk.id_pola_kain = p.id_pola_kain
                   LEFT JOIN jenis_benang jb ON jb.id_jenis_benang = p.id_jenis_benang
                   LEFT JOIN jenis_produksi jp ON jp.id_jenis_produksi = p.id_jenis_produksi
                  WHERE p.stok > 0 ORDER BY p.id_produk DESC LIMIT 2');
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Songketin — Marketplace Songket Nusantara</title>
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(url('style.css')) ?>">
    <style>
        .home-header nav .btn-small{white-space:nowrap}
        .home-alert{margin:0 auto;max-width:1320px;padding:12px 18px;border-radius:8px;background:#f3e9d8;color:#4b2c14;font-size:14px}
        .home-kosong{padding:34px 22px;border:1px dashed var(--line);border-radius:8px;text-align:center;color:var(--muted)}
        .fabric-photo.produk{background-size:cover;background-position:center}
    </style>
</head>

<body>
    <link rel="preload" as="image" href="<?= e(url('images/songket-hero.png')) ?>" />

    <header class="home-header container">
        <a href="<?= e(url('index.php')) ?>" class="brand">songketin<span class="brand-dot">.</span></a>
        <nav aria-label="Navigasi utama">
            <a href="<?= e(url('index.php')) ?>" aria-current="page">Home</a>
            <a href="#asal-songket">Kenali Songket</a>
            <a href="<?= e($urlMarket) ?>"><?= $me ? 'Dashboard' : 'Marketplace' ?></a>
            <?php if ($me): ?>
                <a class="btn btn-primary btn-small" href="<?= e(url('logout.php')) ?>">Keluar</a>
            <?php else: ?>
                <a href="<?= e($urlMasuk) ?>">Login</a>
                <a class="btn btn-primary btn-small" href="<?= e($urlDaftar) ?>">Register</a>
            <?php endif; ?>
        </nav>
    </header>
    <?php if ($flashHtml = flash_render()): ?><div class="container"><?= $flashHtml ?></div><?php endif; ?>

    <main>
        <section class="home-hero container">
            <div>
                <span class="eyebrow">DITENUN DENGAN HATI. DIPILIH DENGAN CERITA.</span>
                <h1>Warisan indah.<br />Untuk cerita<br /><em>Anda.</em></h1>
                <p>Dari kilau benang emas hingga detail motif yang memikat. Kenali songket pilihan Nusantara, temukan kain yang dekat dengan gaya Anda, dan terhubung dengan para penjualnya.</p>
                <div class="home-actions">
                    <a class="btn btn-primary" href="<?= e($urlMarket) ?>">Temukan koleksi Anda ↗</a>
                    <a class="link-arrow" href="#cerita">Jelajahi cerita ↓</a>
                </div>
                <div class="home-caption">PALEMBANG · MINANGKABAU · LOMBOK · BALI</div>
            </div>
            <figure>
                <img src="<?= e(url('images/songket-hero.png')) ?>" alt="Ilustrasi kain songket marun dengan motif emas" />
                <figcaption>
                    <span>THE NUSANTARA EDIT / 01</span>
                    <strong>Keanggunan<br />dalam setiap helai.</strong>
                    <small>Ilustrasi songket marun &amp; emas</small>
                </figcaption>
                <div class="home-seal" aria-hidden="true">
                    ✳<small>RAGAM<br />NUSANTARA</small>
                </div>
            </figure>
        </section>

        <div class="heritage-ribbon">
            <span>TENUN &amp; CERITA</span>
            <i>✳</i><span>WARNA &amp; KARAKTER</span>
            <i>✳</i><span>PILIHAN NUSANTARA</span>
            <i>✳</i><span>SONGKETIN</span>
        </div>

        <section class="home-intro">
            <div class="container">
                <span class="eyebrow">TENTANG WEBSITE</span>
                <h2>Lebih dekat dengan setiap tenunan.</h2>
                <div class="home-steps">
                    <article>
                        <b>01 · Home</b>
                        <h3>Kenali pilihan Anda</h3>
                        <p>Temukan gambaran singkat setiap koleksi, mulai dari warna, karakter motif, hingga ide penggunaannya.</p>
                    </article>
                    <article>
                        <b>02 · Index</b>
                        <h3>Belanja dan berjualan</h3>
                        <p>Setelah register dan login, pembeli dapat memesan produk dan mengajukan refund bila perlu. Penjual dapat mengelola produk, pesanan, dan pengajuan refund dari tokonya.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="container origin-guide" id="asal-songket">
            <span class="eyebrow">MENGENAL SONGKET</span>
            <h2>Dari mana cerita ini bermula?</h2>
            <p class="origin-intro">Songket adalah kain tenun dengan hiasan benang tambahan yang membentuk motif di atas tenunan dasar. Bahan dan susunan motif berbeda menurut tradisi daerah dan pilihan perajinnya.</p>

            <div class="origin-grid">
                <article>
                    <small>01 / DAERAH TENUN</small>
                    <h3>Palembang</h3>
                    <strong>Sumatera Selatan</strong>
                    <p>Dikenal dengan hiasan benang emas atau perak yang memberi kesan berkilau. Lepus dan Bungo Pacik merupakan nama ragam songket Palembang.</p>
                    <dl>
                        <dt>Penggunaan umum</dt>
                        <dd>Busana adat, acara pernikahan, dan pelengkap busana formal.</dd>
                    </dl>
                    <a href="https://kebudayaan.kemdikbud.go.id/ditwdb/songket-palembang-2/" target="_blank" rel="noreferrer">Baca referensi daerah ↗</a>
                </article>

                <article>
                    <small>02 / DAERAH TENUN</small>
                    <h3>Pandai Sikek</h3>
                    <strong>Tanah Datar, Sumatera Barat</strong>
                    <p>Pandai Sikek merupakan salah satu pusat kerajinan songket Minangkabau. Ragam motifnya menjadi bagian dari tradisi tenun yang diwariskan antargenerasi.</p>
                    <dl>
                        <dt>Penggunaan umum</dt>
                        <dd>Busana adat Minangkabau dan koleksi kain tradisional.</dd>
                    </dl>
                    <a href="https://jadesta.kemenpar.go.id/desa/pandai_sikek" target="_blank" rel="noreferrer">Baca referensi daerah ↗</a>
                </article>

                <article>
                    <small>03 / DAERAH TENUN</small>
                    <h3>Sukarara</h3>
                    <strong>Jonggat, Lombok Tengah, NTB</strong>
                    <p>Desa Sukarara dikenal sebagai sentra tenun tradisional Sasak. Subahnale termasuk motif yang dijumpai dalam ragam songket dari daerah ini.</p>
                    <dl>
                        <dt>Penggunaan umum</dt>
                        <dd>Kain busana, selendang, dan cendera mata tekstil.</dd>
                    </dl>
                    <a href="https://jadesta.kemenpar.go.id/desa/sentra_tenun" target="_blank" rel="noreferrer">Baca referensi daerah ↗</a>
                </article>

                <article>
                    <small>04 / DAERAH TENUN</small>
                    <h3>Sidemen</h3>
                    <strong>Karangasem, Bali</strong>
                    <p>Sidemen memiliki tradisi pembuatan songket dan endek. Songket dibedakan dari endek melalui teknik penghiasan kain; keduanya bukan nama untuk kain yang sama.</p>
                    <dl>
                        <dt>Penggunaan umum</dt>
                        <dd>Busana adat Bali dan pelengkap acara khusus.</dd>
                    </dl>
                    <a href="https://diskominfos.baliprov.go.id/untaian-filosofi-pada-kain-songket-sidemen/" target="_blank" rel="noreferrer">Baca referensi daerah ↗</a>
                </article>
            </div>

            <p class="home-disclaimer">Bahan dasar dapat berupa katun atau sutra, dengan benang hias yang berbeda. Penjelasan daerah adalah informasi umum; bahan, motif, dan asal setiap barang perlu mengikuti keterangan penjual.</p>
        </section>

        <section class="container home-collection" id="cerita">
            <span class="eyebrow">CATATAN KOLEKSI</span>
            <h2>Kain pilihan. Cerita yang berbeda.</h2>
            <?php if (!$koleksi): ?>
            <div class="home-kosong">Belum ada produk yang dijual saat ini. Penjual yang sudah terdaftar dapat menambahkan produk dari dashboard-nya.</div>
            <?php else: ?>
            <p class="home-disclaimer">Koleksi di bawah ini adalah produk terbaru dari para penjual. Spesifikasi lengkap ada di halaman produk; tanyakan detailnya kepada penjual bila perlu.</p>
            <div class="home-story-grid">
                <?php foreach ($koleksi as $n => $p): $fotoP = foto_produk_url($p['id_produk'], $p['foto_produk']); ?>
                <article>
                    <div class="fabric-photo produk" role="img" aria-label="Foto <?= e($p['nama_produk']) ?>" style="background-image:url('<?= e($fotoP) ?>')"></div>
                    <div class="home-story-copy">
                        <small class="story-number"><?= str_pad((string) ($n + 1), 2, '0', STR_PAD_LEFT) ?> / KOLEKSI</small>
                        <span class="eyebrow"><?= e(trim(($p['nama_asal_daerah'] ?: 'Nusantara') . ' · ' . $p['nama_kategori_produk'])) ?></span>
                        <h3><?= e($p['nama_produk']) ?></h3>
                        <p><?= e(mb_strimwidth((string) $p['deskripsi'], 0, 190, '…')) ?: 'Dijual oleh ' . e($p['nama_penjual']) . '.' ?></p>
                        <dl class="songket-facts">
                            <dt>Asal</dt><dd><?= e($p['nama_asal_daerah'] ?: '—') ?></dd>
                            <dt>Motif</dt><dd><?= e($p['nama_pola_kain'] ?: '—') ?></dd>
                            <dt>Benang</dt><dd><?= e($p['nama_benang'] ?: '—') ?></dd>
                            <dt>Cara produksi</dt><dd><?= e($p['nama_jenis_produksi'] ?: '—') ?></dd>
                            <dt>Harga</dt><dd><?= e(rp(harga_akhir($p))) ?></dd>
                        </dl>
                        <a href="<?= e($me && $me['role'] === 'pembeli' ? url('pembeli/produk.php?id=' . rawurlencode($p['id_produk'])) : $urlMarket) ?>">Lihat di marketplace ↗</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="home-editorial container">
            <div class="editorial-photo">
                <img src="<?= e(url('images/songket-hero.png')) ?>" alt="Ilustrasi detail benang emas pada kain songket" loading="lazy" />
            </div>
            <div>
                <span class="eyebrow">SEBELUM ANDA MEMILIH</span>
                <h2>Bukan sekadar warna.<br /><em>Temukan yang terasa Anda.</em></h2>
                <p>Pilihan terbaik dimulai dari detail kecil. Perhatikan bahan, ukuran, dan motif agar songket selaras dengan kebutuhan Anda.</p>
                <details open="">
                    <summary>01 · Sesuaikan dengan momen</summary>
                    <p>Untuk tampilan menonjol, jelajahi kontras marun atau hitam dengan emas. Untuk kesan lembut, lihat koleksi sage, champagne, atau gading.</p>
                </details>
                <details>
                    <summary>02 · Kenali bahan dan ukuran</summary>
                    <p>Baca spesifikasi benang dan ukuran pada halaman produk. Kain utama dan selendang memiliki fungsi berbeda; tanyakan detail kepada penjual sebelum membeli.</p>
                </details>
                <details>
                    <summary>03 · Perhatikan detail produk</summary>
                    <p>Bandingkan motif, warna, harga, dan ketersediaan. Foto produk berasal dari unggahan penjual; konfirmasi detail barang kepada penjual bila perlu.</p>
                </details>
            </div>
        </section>

        <section class="container home-join">
            <span class="eyebrow">UNTUK PEMBELI DAN PENJUAL</span>
            <h2>Dari pencinta kain,<br />untuk penjaga cerita.</h2>
            <p>Temukan kain istimewa atau buka toko Anda sendiri. Daftar langsung dengan email, lalu mulai perjalanan di Songketin.</p>
            <a class="btn btn-primary" href="<?= e($urlDaftar) ?>">Daftar sekarang →</a>
            <a class="text-button" href="<?= e($urlMasuk) ?>">Sudah punya akun? Login</a>
        </section>
    </main>

    <footer class="container home-footer">
        <span>© Songketin · Marketplace Songket Nusantara</span>
    </footer>
</body>

</html>
