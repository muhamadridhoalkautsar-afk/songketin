<?php
/** Keranjang (disimpan di sesi) + perhitungan ongkos kirim dan total checkout. */
require_once __DIR__ . '/koin.php';

// ---------------------------------------------------------------- keranjang
function cart_add(string $id, int $qty): array
{
    $p = row('SELECT id_produk, nama_produk, stok FROM produk WHERE id_produk = ?', [$id]);
    if (!$p) {
        return [false, 'Produk ini sedang tidak tersedia.'];
    }
    if ((int) $p['stok'] < 1) {
        return [false, 'Maaf, stok produk ini habis.'];
    }
    $baru = (int) ($_SESSION['cart'][$id] ?? 0) + max(1, $qty);
    if ($baru > (int) $p['stok']) {
        $_SESSION['cart'][$id] = (int) $p['stok'];
        return [true, 'Jumlah disesuaikan dengan stok tersisa (' . (int) $p['stok'] . ').'];
    }
    $_SESSION['cart'][$id] = $baru;
    return [true, '“' . $p['nama_produk'] . '” masuk ke keranjang.'];
}

function cart_set(string $id, int $qty): void
{
    if ($qty < 1) {
        unset($_SESSION['cart'][$id]);
    } elseif (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] = $qty;
    }
}

function cart_remove(string $id): void
{
    unset($_SESSION['cart'][$id]);
}

/**
 * Isi keranjang dari database, dikelompokkan per toko. Produk yang hilang/habis dibuang,
 * jumlah dipangkas sesuai stok; peringatannya dikembalikan di 'peringatan'.
 */
function cart_load(): array
{
    $ids = array_keys($_SESSION['cart'] ?? []);
    $out = ['grup' => [], 'jumlah' => 0, 'sub' => 0, 'peringatan' => []];
    if (!$ids) {
        return $out;
    }
    $rs = rows(
        'SELECT p.*, pj.nama_penjual, pj.id_desa AS desa_penjual
           FROM produk p JOIN penjual pj ON pj.id_penjual = p.id_penjual
          WHERE p.id_produk IN (' . in_marks($ids) . ')',
        $ids
    );
    $peta = [];
    foreach ($rs as $r) {
        $peta[$r['id_produk']] = $r;
    }
    foreach ($ids as $id) {
        $p = $peta[$id] ?? null;
        $qty = (int) $_SESSION['cart'][$id];
        if (!$p || (int) $p['stok'] < 1) {
            unset($_SESSION['cart'][$id]);
            $out['peringatan'][] = ($p['nama_produk'] ?? 'Sebuah produk') . ' tidak lagi tersedia dan dikeluarkan dari keranjang.';
            continue;
        }
        if ($qty > (int) $p['stok']) {
            $qty = (int) $p['stok'];
            $_SESSION['cart'][$id] = $qty;
            $out['peringatan'][] = 'Jumlah “' . $p['nama_produk'] . '” disesuaikan dengan stok (' . $qty . ').';
        }
        $harga = harga_akhir($p);
        $item = $p + ['qty' => $qty, 'harga_akhir' => $harga, 'subtotal' => $harga * $qty];
        $g = &$out['grup'][$p['id_penjual']];
        $g['id_penjual'] = $p['id_penjual'];
        $g['nama_penjual'] = $p['nama_penjual'];
        $g['desa_penjual'] = $p['desa_penjual'];
        $g['items'][] = $item;
        $g['sub'] = ($g['sub'] ?? 0) + $item['subtotal'];
        $g['qty'] = ($g['qty'] ?? 0) + $qty;
        unset($g);
        $out['jumlah'] += $qty;
        $out['sub'] += $item['subtotal'];
    }
    return $out;
}

// ---------------------------------------------------------------- ongkos kirim
const TARIF_KIRIM = [ // dasar, tambahan per zona (0 = satu kota … 3 = beda pulau), per barang tambahan, estimasi hari per zona
    'JK01' => ['dasar' => 12000, 'zona' => [0, 6000, 14000, 28000], 'item' => 4000, 'hari' => [2, 3, 5, 7]],
    'JK02' => ['dasar' => 24000, 'zona' => [0, 9000, 20000, 38000], 'item' => 6000, 'hari' => [1, 2, 3, 4]],
    'JK03' => ['dasar' => 9000, 'zona' => [0, 4000, 10000, 20000], 'item' => 2500, 'hari' => [3, 5, 8, 12]],
    'JK04' => ['dasar' => 20000, 'zona' => [0], 'item' => 5000, 'hari' => [0]], // hanya dalam satu kabupaten/kota
];
const FAKTOR_OPSI = ['OK01' => 1.00, 'OK02' => 0.97, 'OK03' => 0.95, 'OK04' => 1.00, 'OK05' => 1.04, 'OK06' => 1.00, 'OK07' => 0.94, 'OK08' => 1.00, 'OK09' => 1.06];

function kelompok_pulau(string $idProvinsi): string
{
    return $idProvinsi === '21' ? '1' : substr($idProvinsi, 0, 1); // Kepri digabung dengan Sumatera
}

/** 0 = satu kabupaten/kota, 1 = satu provinsi, 2 = satu pulau, 3 = beda pulau. */
function zona_kirim(?string $desaAsal, ?string $desaTujuan): int
{
    $a = wilayah_rantai($desaAsal);
    $b = wilayah_rantai($desaTujuan);
    if (!$a || !$b) {
        return 2;
    }
    if ($a['kabupaten']['id'] === $b['kabupaten']['id']) {
        return 0;
    }
    if ($a['provinsi']['id'] === $b['provinsi']['id']) {
        return 1;
    }
    return kelompok_pulau($a['provinsi']['id']) === kelompok_pulau($b['provinsi']['id']) ? 2 : 3;
}

/** [ongkir, hari] untuk satu pengiriman, atau null bila opsi tidak melayani zona itu. */
function hitung_ongkir(array $opsi, int $zona, int $qty): ?array
{
    $t = TARIF_KIRIM[$opsi['id_jenis_pengiriman']] ?? TARIF_KIRIM['JK01']; // jenis baru buatan admin memakai tarif Reguler
    if (!$t || !isset($t['zona'][$zona])) {
        return null;
    }
    $mentah = ($t['dasar'] + $t['zona'][$zona] + min(5, max(0, $qty - 1)) * $t['item']) * (FAKTOR_OPSI[$opsi['id_opsi_pengiriman']] ?? 1);
    return [(int) (round($mentah / 500) * 500), (int) $t['hari'][$zona]];
}

function opsi_pengiriman_semua(): array
{
    return rows('SELECT o.id_opsi_pengiriman, o.id_jenis_pengiriman, o.nama_opsi_pengiriman, j.nama_jenis_pengiriman
                   FROM opsi_pengiriman o JOIN jenis_pengiriman j ON j.id_jenis_pengiriman = o.id_jenis_pengiriman
                  ORDER BY o.id_jenis_pengiriman, o.id_opsi_pengiriman');
}

function hari_teks(int $h): string
{
    return $h === 0 ? 'Hari ini' : ($h === 1 ? '1 hari' : $h . ' hari');
}

// ---------------------------------------------------------------- diskon
function diskon_ongkir_semua(): array
{
    return rows('SELECT * FROM diskon_ongkir ORDER BY besaran_potongan_ongkir');
}

function tingkat_pembeli_row(?string $id): array
{
    return ($id !== null ? row('SELECT * FROM tingkat_pembeli WHERE id_tingkat_pembeli = ?', [$id]) : null)
        ?? row('SELECT * FROM tingkat_pembeli ORDER BY akumulasi_koin LIMIT 1')
        ?? ['id_tingkat_pembeli' => 'TB01', 'nama_tingkat_pembeli' => 'Perunggu', 'akumulasi_koin' => 0, 'besar_diskon' => 0];
}

// ---------------------------------------------------------------- total checkout
/**
 * Hitung semua angka pesanan. $pilih = ['opsi'=>id, 'diskon_ongkir'=>id, 'koin'=>'1' bila pembeli memakai koin].
 * Selalu dihitung ulang di server — angka dari browser tidak dipercaya.
 * Potongan harga produk sudah tercermin pada harga_akhir (produk.besaran_potongan_harga).
 */
function hitung_checkout(array $kr, array $pembeli, array $pilih): array
{
    $hasil = ['sellers' => [], 'galat' => [], 'sub' => 0, 'qty' => 0, 'tingkat' => 0, 'ongkir' => 0,
        'diskon_ongkir' => 0, 'koin' => 0, 'koin_rp' => 0, 'total_potongan' => 0, 'total' => 0, 'siap' => false,
        'id_diskon_ongkir' => null, 'saldo_koin' => 0, 'maks_koin' => 0, 'pakai_koin' => false];
    $tk = tingkat_pembeli_row($pembeli['id_tingkat_pembeli'] ?? null);
    $pct = (float) $tk['besar_diskon'];
    $opsi = !empty($pilih['opsi']) ? row('SELECT * FROM opsi_pengiriman WHERE id_opsi_pengiriman = ?', [$pilih['opsi']]) : null;
    $tujuanKota = kota_dari_desa($pembeli['id_desa'] ?? null);

    foreach ($kr['grup'] as $idP => $g) {
        $tingkat = (int) round($g['sub'] * $pct / 100);
        $zona = zona_kirim($g['desa_penjual'], $pembeli['id_desa'] ?? null);
        $ong = $opsi ? hitung_ongkir($opsi, $zona, $g['qty']) : null;
        if ($opsi && !$ong) {
            $hasil['galat'][] = $opsi['nama_opsi_pengiriman'] . ' tidak melayani pengiriman dari ' . $g['nama_penjual'] . ' ke alamat Anda.';
        }
        $hasil['sellers'][$idP] = $g + [
            'tingkat' => $tingkat, 'jual' => (int) $g['sub'], 'zona' => $zona,
            'ongkir' => $ong[0] ?? 0, 'hari' => $ong[1] ?? 0,
            'kota_asal' => kota_dari_desa($g['desa_penjual']), 'kota_tujuan' => $tujuanKota,
        ];
        $hasil['sub'] += $g['sub'];
        $hasil['qty'] += $g['qty'];
        $hasil['tingkat'] += $tingkat;
        $hasil['ongkir'] += $ong[0] ?? 0;
    }

    $do = !empty($pilih['diskon_ongkir']) ? row('SELECT * FROM diskon_ongkir WHERE id_diskon_ongkir = ?', [$pilih['diskon_ongkir']]) : null;
    if ($do && $opsi && !$hasil['galat']) {
        if ($hasil['ongkir'] >= (float) $do['minimal_ongkir']) {
            $hasil['diskon_ongkir'] = (int) min((float) $do['besaran_potongan_ongkir'], $hasil['ongkir']);
            $hasil['id_diskon_ongkir'] = $do['id_diskon_ongkir'];
        } else {
            $hasil['galat'][] = 'Ongkir belum mencapai minimal Rp' . angka($do['minimal_ongkir']) . ' untuk memakai “' . $do['nama_diskon_ongkir'] . '”.';
        }
    }

    // Koin: potongan koin paling banyak KOIN_MAKS_PERSEN dari harga barang (setelah diskon tingkat).
    $sesudah = $hasil['sub'] - $hasil['tingkat'];
    $hasil['saldo_koin'] = koin_saldo((string) ($pembeli['id_pembeli'] ?? ''));
    $hasil['maks_koin'] = (int) min($hasil['saldo_koin'], intdiv((int) floor($sesudah * KOIN_MAKS_PERSEN / 100), NILAI_KOIN));
    if (!empty($pilih['koin']) && $hasil['maks_koin'] > 0) {
        $hasil['pakai_koin'] = true;
        $hasil['koin'] = $hasil['maks_koin'];
        $hasil['koin_rp'] = $hasil['koin'] * NILAI_KOIN;
    }
    $hasil['total_potongan'] = $hasil['tingkat'] + $hasil['koin_rp'] + $hasil['diskon_ongkir'];
    $hasil['total'] = max(0, $hasil['sub'] + $hasil['ongkir'] - $hasil['total_potongan']);
    $hasil['hemat_produk'] = 0;
    foreach ($kr['grup'] as $g) {
        foreach ($g['items'] as $it) {
            $hasil['hemat_produk'] += (int) ((float) $it['besaran_potongan_harga'] * $it['qty']);
        }
    }
    $hasil['siap'] = $opsi && !$hasil['galat'] && $hasil['qty'] > 0 && !empty($pembeli['id_desa']);
    $hasil['opsi'] = $opsi;
    return $hasil;
}
