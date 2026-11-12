<?php
/**
 * Data master yang dikelola admin: wilayah, pembayaran, pengiriman, produk, tingkat, promo, status.
 * Satu daftar konfigurasi (master_daftar) menggerakkan halaman admin/master.php dan admin/master_form.php,
 * sehingga semua tabel referensi punya perilaku tambah / ubah / hapus yang sama dan aman terhadap relasi.
 */

require_once __DIR__ . '/foto.php';

const MASTER_GRUP = [
    'Wilayah' => ['negara', 'provinsi', 'kabupaten', 'kecamatan', 'desa'],
    'Pembayaran' => ['sifat', 'metode', 'jenis_bayar'],
    'Pengiriman' => ['jenis_kirim', 'opsi_kirim'],
    'Produk' => ['kategori', 'daerah', 'motif', 'benang', 'jenis_produksi'],
    'Keanggotaan' => ['tingkat_pembeli', 'tingkat_penjual'],
    'Promo' => ['diskon_ongkir', 'diskon_harga'],
    'Status' => ['status_pesanan', 'status_kirim'],
];

/** Konfigurasi semua data master, diindeks dengan kunci pendek (dipakai di alamat ?d=…). */
function master_daftar(): array
{
    static $d = null;
    if ($d !== null) {
        return $d;
    }
    $teks = fn(string $k, string $label, bool $wajib = true, int $maks = 100, string $petunjuk = '') => ['k' => $k, 'label' => $label, 'tipe' => 'teks', 'wajib' => $wajib, 'maks' => $maks, 'petunjuk' => $petunjuk];
    $uang = fn(string $k, string $label, string $petunjuk = '') => ['k' => $k, 'label' => $label, 'tipe' => 'uang', 'wajib' => true, 'petunjuk' => $petunjuk];
    $persen = fn(string $k, string $label, string $petunjuk = '') => ['k' => $k, 'label' => $label, 'tipe' => 'persen', 'wajib' => true, 'petunjuk' => $petunjuk];
    $angka = fn(string $k, string $label, string $petunjuk = '') => ['k' => $k, 'label' => $label, 'tipe' => 'angka', 'wajib' => true, 'petunjuk' => $petunjuk];
    $auto = fn(string $awalan, int $lebar = 2) => ['mode' => 'auto', 'awalan' => $awalan, 'lebar' => $lebar];
    $manual = fn(string $petunjuk) => ['mode' => 'manual', 'maks' => 10, 'petunjuk' => $petunjuk];

    $d = [
        // ------------------------------------------------------------ wilayah
        'negara' => ['grup' => 'Wilayah', 'judul' => 'Negara', 'tabel' => 'negara', 'pk' => 'id_negara', 'nama' => 'nama_negara', 'wilayah' => true,
            'id' => $manual('Kode singkat, mis. ID.'), 'kolom' => [$teks('nama_negara', 'Nama negara')], 'dipakai' => []],
        'provinsi' => ['grup' => 'Wilayah', 'judul' => 'Provinsi', 'tabel' => 'provinsi', 'pk' => 'id_provinsi', 'nama' => 'nama_provinsi', 'wilayah' => true,
            'induk' => ['k' => 'id_negara', 'ent' => 'negara', 'label' => 'Negara'],
            'id' => $manual('Kode 2 digit, mis. 16 (Sumatera Selatan). Digit pertama menentukan kelompok pulau untuk ongkos kirim.'),
            'kolom' => [$teks('nama_provinsi', 'Nama provinsi')], 'dipakai' => []],
        'kabupaten' => ['grup' => 'Wilayah', 'judul' => 'Kabupaten / Kota', 'tabel' => 'kabupaten', 'pk' => 'id_kabupaten', 'nama' => 'nama_kabupaten', 'wilayah' => true,
            'induk' => ['k' => 'id_provinsi', 'ent' => 'provinsi', 'label' => 'Provinsi'],
            'id' => $manual('Kode 4 digit, diawali kode provinsi, mis. 1671.'),
            'kolom' => [$teks('nama_kabupaten', 'Nama kabupaten / kota', true, 100, 'Awali dengan “Kab.” atau “Kota” bila perlu.')], 'dipakai' => []],
        'kecamatan' => ['grup' => 'Wilayah', 'judul' => 'Kecamatan', 'tabel' => 'kecamatan', 'pk' => 'id_kecamatan', 'nama' => 'nama_kecamatan', 'wilayah' => true,
            'induk' => ['k' => 'id_kabupaten', 'ent' => 'kabupaten', 'label' => 'Kabupaten / Kota'],
            'id' => $manual('Kode 6 digit, diawali kode kabupaten/kota.'),
            'kolom' => [$teks('nama_kecamatan', 'Nama kecamatan')], 'dipakai' => []],
        'desa' => ['grup' => 'Wilayah', 'judul' => 'Desa / Kelurahan', 'tabel' => 'desa', 'pk' => 'id_desa', 'nama' => 'nama_desa', 'wilayah' => true,
            'induk' => ['k' => 'id_kecamatan', 'ent' => 'kecamatan', 'label' => 'Kecamatan'],
            'id' => $manual('Kode maksimal 10 karakter, diawali kode kecamatan.'),
            'kolom' => [$teks('nama_desa', 'Nama desa / kelurahan')],
            'dipakai' => [['pembeli', 'id_desa', 'akun pembeli'], ['penjual', 'id_desa', 'toko']]],

        // ------------------------------------------------------------ pembayaran
        'sifat' => ['grup' => 'Pembayaran', 'judul' => 'Sifat pembayaran', 'tabel' => 'sifat_pembayaran', 'pk' => 'id_sifat_pembayaran', 'nama' => 'nama_sifat_pembayaran',
            'id' => $auto('SF'), 'kolom' => [$teks('nama_sifat_pembayaran', 'Nama sifat pembayaran', true, 100, 'Mis. Non-Tunai atau Tunai. Sifat bernomor SF02 (Tunai) diperlakukan sebagai bayar di tempat.')],
            'dilindungi' => ['SF01', 'SF02'], 'dipakai' => []],
        'metode' => ['grup' => 'Pembayaran', 'judul' => 'Metode pembayaran', 'tabel' => 'metode_pembayaran', 'pk' => 'id_metode_pembayaran', 'nama' => 'nama_metode_pembayaran',
            'induk' => ['k' => 'id_sifat_pembayaran', 'ent' => 'sifat', 'label' => 'Sifat pembayaran'],
            'id' => $auto('MP'), 'kolom' => [$teks('nama_metode_pembayaran', 'Nama metode pembayaran', true, 100, 'Mis. Transfer Bank, Dompet Digital, QRIS.')], 'dipakai' => []],
        'jenis_bayar' => ['grup' => 'Pembayaran', 'judul' => 'Jenis pembayaran', 'tabel' => 'jenis_pembayaran', 'pk' => 'id_jenis_pembayaran', 'nama' => 'nama_jenis_pembayaran',
            'induk' => ['k' => 'id_metode_pembayaran', 'ent' => 'metode', 'label' => 'Metode pembayaran'],
            'id' => $auto('JP'),
            'kolom' => [$teks('nama_jenis_pembayaran', 'Nama jenis pembayaran', true, 100, 'Mis. Bank BCA, DANA, QRIS Songketin.'),
                $teks('rekening_jenis_pembayaran', 'Rekening / nomor tujuan', false, 100, 'Ditampilkan ke pembeli saat membayar. Kosongkan bila tidak perlu.'),
                ['k' => 'foto_qr', 'label' => 'Foto QR / logo tujuan bayar', 'tipe' => 'foto', 'wajib' => false, 'folder' => 'qr', 'prefix' => 'QR', 'min' => 100,
                    'petunjuk' => 'Opsional. JPG/PNG/WebP, maksimal 2 MB. Disimpan sebagai berkas di server; database hanya menyimpan path-nya.']],
            'dipakai' => [['nota_pembeli', 'id_jenis_pembayaran', 'pesanan']]],

        // ------------------------------------------------------------ pengiriman
        'jenis_kirim' => ['grup' => 'Pengiriman', 'judul' => 'Jenis pengiriman', 'tabel' => 'jenis_pengiriman', 'pk' => 'id_jenis_pengiriman', 'nama' => 'nama_jenis_pengiriman',
            'id' => $auto('JK'), 'kolom' => [$teks('nama_jenis_pengiriman', 'Nama jenis pengiriman', true, 100, 'Mis. Reguler, Ekspres, Hemat (Kargo).')],
            'catatan' => 'Ongkos kirim dihitung dari tarif bawaan aplikasi per jenis. Jenis baru memakai tarif Reguler.', 'dipakai' => []],
        'opsi_kirim' => ['grup' => 'Pengiriman', 'judul' => 'Opsi pengiriman', 'tabel' => 'opsi_pengiriman', 'pk' => 'id_opsi_pengiriman', 'nama' => 'nama_opsi_pengiriman',
            'induk' => ['k' => 'id_jenis_pengiriman', 'ent' => 'jenis_kirim', 'label' => 'Jenis pengiriman'],
            'id' => $auto('OK'), 'kolom' => [$teks('nama_opsi_pengiriman', 'Nama opsi / ekspedisi', true, 100, 'Mis. JNE Reguler, SiCepat BEST.')],
            'dipakai' => [['pengiriman', 'id_opsi_pengiriman', 'pengiriman']]],

        // ------------------------------------------------------------ produk
        'kategori' => ['grup' => 'Produk', 'judul' => 'Kategori produk', 'tabel' => 'kategori_produk', 'pk' => 'id_kategori_produk', 'nama' => 'nama_kategori_produk',
            'id' => $auto('KT'), 'kolom' => [$teks('nama_kategori_produk', 'Nama kategori')], 'dipakai' => [['produk', 'id_kategori_produk', 'produk']]],
        'daerah' => ['grup' => 'Produk', 'judul' => 'Asal daerah', 'tabel' => 'asal_daerah', 'pk' => 'id_asal_daerah', 'nama' => 'nama_asal_daerah',
            'id' => $auto('AD'), 'kolom' => [$teks('nama_asal_daerah', 'Nama asal daerah', true, 100, 'Mis. Palembang, Sumatera Selatan.')], 'dipakai' => [['produk', 'id_asal_daerah', 'produk']]],
        'motif' => ['grup' => 'Produk', 'judul' => 'Motif / pola kain', 'tabel' => 'pola_kain', 'pk' => 'id_pola_kain', 'nama' => 'nama_pola_kain',
            'id' => $auto('PL'), 'kolom' => [$teks('nama_pola_kain', 'Nama motif')], 'dipakai' => [['produk', 'id_pola_kain', 'produk']]],
        'benang' => ['grup' => 'Produk', 'judul' => 'Jenis benang', 'tabel' => 'jenis_benang', 'pk' => 'id_jenis_benang', 'nama' => 'nama_benang',
            'id' => $auto('BN'), 'kolom' => [$teks('nama_benang', 'Nama benang'), $teks('kualitas_benang', 'Kualitas', false, 50, 'Mis. Premium, Standar.')],
            'dipakai' => [['produk', 'id_jenis_benang', 'produk']]],

        'jenis_produksi' => ['grup' => 'Produk', 'judul' => 'Jenis produksi', 'tabel' => 'jenis_produksi', 'pk' => 'id_jenis_produksi', 'nama' => 'nama_jenis_produksi',
            'id' => $auto('JPR', 2), 'kolom' => [$teks('nama_jenis_produksi', 'Nama jenis produksi', true, 50, 'Mis. Tenun Tangan, Tenun Mesin.')],
            'dipakai' => [['produk', 'id_jenis_produksi', 'produk']]],

        // ------------------------------------------------------------ keanggotaan
        'tingkat_pembeli' => ['grup' => 'Keanggotaan', 'judul' => 'Tingkat pembeli', 'tabel' => 'tingkat_pembeli', 'pk' => 'id_tingkat_pembeli', 'nama' => 'nama_tingkat_pembeli',
            'id' => $auto('TB'), 'urut' => 't.akumulasi_koin',
            'kolom' => [$teks('nama_tingkat_pembeli', 'Nama tingkat', true, 50), $angka('akumulasi_koin', 'Koin yang dibutuhkan', 'Total koin terkumpul agar pembeli naik ke tingkat ini.'),
                $persen('besar_diskon', 'Diskon (%)', 'Potongan otomatis dari harga barang bagi pembeli di tingkat ini.')],
            'minimal' => 1, 'catatan' => 'Pembeli baru otomatis masuk tingkat dengan koin paling sedikit.', 'dipakai' => [['pembeli', 'id_tingkat_pembeli', 'akun pembeli']]],
        'tingkat_penjual' => ['grup' => 'Keanggotaan', 'judul' => 'Tingkat penjual', 'tabel' => 'tingkat_penjual', 'pk' => 'id_tingkat_penjual', 'nama' => 'nama_tingkat_penjual',
            'id' => $auto('TJ'), 'urut' => 't.minimal_transaksi',
            'kolom' => [$teks('nama_tingkat_penjual', 'Nama tingkat', true, 50), $persen('persen_biaya_admin', 'Biaya layanan (%)', 'Dipotong dari penjualan toko pada tingkat ini.'),
                $uang('minimal_transaksi', 'Omzet minimal (Rp)', 'Total penjualan selesai agar toko naik ke tingkat ini.')],
            'minimal' => 1, 'catatan' => 'Toko baru otomatis masuk tingkat dengan omzet minimal paling kecil.', 'dipakai' => [['penjual', 'id_tingkat_penjual', 'toko']]],

        // ------------------------------------------------------------ promo
        'diskon_ongkir' => ['grup' => 'Promo', 'judul' => 'Diskon ongkir', 'tabel' => 'diskon_ongkir', 'pk' => 'id_diskon_ongkir', 'nama' => 'nama_diskon_ongkir',
            'id' => $auto('DO'), 'urut' => 't.besaran_potongan_ongkir',
            'kolom' => [$teks('nama_diskon_ongkir', 'Nama promo'), $uang('besaran_potongan_ongkir', 'Potongan ongkir (Rp)'), $uang('minimal_ongkir', 'Minimal ongkir (Rp)', 'Promo berlaku bila total ongkir mencapai nilai ini.')],
            'dipakai' => [['nota_pembeli', 'id_diskon_ongkir', 'pesanan']]],
        'diskon_harga' => ['grup' => 'Promo', 'judul' => 'Diskon harga produk', 'tabel' => 'diskon_harga', 'pk' => 'id_diskon_harga', 'nama' => 'nama_diskon_harga',
            'id' => $auto('DH'), 'urut' => 't.besaran_potongan_harga',
            'kolom' => [$teks('nama_diskon_harga', 'Nama diskon', true, 100, 'Mis. Promo Akhir Tahun.'), $uang('besaran_potongan_harga', 'Potongan harga (Rp)', 'Penjual memilih diskon ini pada produknya; potongan dikurangkan dari harga produk.')],
            'dilindungi' => ['DH01'], 'dipakai' => [['produk', 'id_diskon_harga', 'produk']]],

        // ------------------------------------------------------------ status (nama saja; alurnya tertanam di aplikasi)
        'status_pesanan' => ['grup' => 'Status', 'judul' => 'Status pesanan', 'tabel' => 'status_pesanan', 'pk' => 'id_status_pesanan', 'nama' => 'keterangan_status_pesanan',
            'id' => $auto('SP'), 'hanya_ubah' => true, 'kolom' => [$teks('keterangan_status_pesanan', 'Nama status')],
            'catatan' => 'Alur pesanan bergantung pada status-status ini, sehingga yang bisa diubah hanya namanya.', 'dipakai' => [['rincian_pesanan', 'id_status_pesanan', 'barang pesanan']]],
        'status_kirim' => ['grup' => 'Status', 'judul' => 'Status pengiriman', 'tabel' => 'status_pengiriman', 'pk' => 'id_status_pengiriman', 'nama' => 'keterangan_status_pengiriman',
            'id' => $auto('SK'), 'hanya_ubah' => true, 'kolom' => [$teks('keterangan_status_pengiriman', 'Nama status')],
            'catatan' => 'Tahapan pengiriman bergantung pada status-status ini, sehingga yang bisa diubah hanya namanya.', 'dipakai' => [['nota_penjual', 'id_status_pengiriman', 'pengiriman']]],
    ];
    foreach ($d as $kunci => &$c) {
        $c['kunci'] = $kunci;
        $c['urut'] = $c['urut'] ?? ('t.' . $c['nama']);
        $c['lingkup'] = $c['lingkup'] ?? ($c['induk']['k'] ?? null);
    }
    unset($c);
    return $d;
}

function master_cfg(string $d): ?array
{
    return master_daftar()[$d] ?? null;
}

/** Entitas master yang menjadi anak langsung dari $d (mis. provinsi → kabupaten). */
function master_anak(string $d): array
{
    $hasil = [];
    foreach (master_daftar() as $k => $c) {
        if (($c['induk']['ent'] ?? null) === $d) {
            $hasil[$k] = $c;
        }
    }
    return $hasil;
}

function master_hitung(string $d): int
{
    $c = master_cfg($d);
    return $c ? (int) val('SELECT COUNT(*) FROM ' . $c['tabel']) : 0;
}

function master_ambil(string $d, string $id): ?array
{
    $c = master_cfg($d);
    return $c && $id !== '' ? row('SELECT * FROM ' . $c['tabel'] . ' WHERE ' . $c['pk'] . ' = ?', [$id]) : null;
}

// ---------------------------------------------------------------- nilai
/** ID baru bernomor urut untuk entitas dengan id otomatis, mis. KT08. */
function master_id_baru(array $c): string
{
    $aw = $c['id']['awalan'];
    $maks = 0;
    foreach (rows('SELECT ' . $c['pk'] . ' AS id FROM ' . $c['tabel'] . ' WHERE ' . $c['pk'] . ' LIKE ?', [$aw . '%']) as $r) {
        if (preg_match('/^' . preg_quote($aw, '/') . '(\d+)$/', $r['id'], $m)) {
            $maks = max($maks, (int) $m[1]);
        }
    }
    return $aw . str_pad((string) ($maks + 1), $c['id']['lebar'], '0', STR_PAD_LEFT);
}

/** Isian formulir yang kosong (untuk tambah) dengan induk terisi bila diketahui. */
function master_isian_kosong(array $c, string $induk = ''): array
{
    $f = ['kode' => ''];
    foreach ($c['kolom'] as $k) {
        $f[$k['k']] = '';
    }
    if (isset($c['induk'])) {
        $f[$c['induk']['k']] = $induk;
    }
    return $f;
}

function master_isian_dari_baris(array $c, array $r): array
{
    $f = ['kode' => (string) $r[$c['pk']]];
    foreach ($c['kolom'] as $k) {
        $v = $r[$k['k']];
        if ($k['tipe'] === 'foto') {
            $f[$k['k']] = (string) $v;
            continue;
        }
        $f[$k['k']] = $v === null ? '' : (in_array($k['tipe'], ['uang', 'angka'], true) ? (string) (int) $v : ($k['tipe'] === 'persen' ? rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') : (string) $v));
    }
    if (isset($c['induk'])) {
        $f[$c['induk']['k']] = (string) $r[$c['induk']['k']];
    }
    return $f;
}

function master_isian_dari_post(array $c): array
{
    $f = ['kode' => trim(post('kode'))];
    foreach ($c['kolom'] as $k) {
        $f[$k['k']] = in_array($k['tipe'], ['teks'], true) ? trim(post($k['k'])) : trim(post($k['k']));
    }
    if (isset($c['induk'])) {
        // Wilayah: pilihan induk datang dari kotak bertingkat (nama = tingkatnya); lainnya dari satu kotak pilihan.
        $f[$c['induk']['k']] = trim(post(!empty($c['wilayah']) ? $c['induk']['ent'] : $c['induk']['k']));
    }
    return $f;
}

/** Rantai pilihan wilayah dari satu induk ke atas, untuk mengisi kotak bertingkat: ['negara'=>…, 'provinsi'=>…]. */
function master_rantai_induk(string $levelInduk, string $idInduk): array
{
    $urut = array_keys(WILAYAH);
    $sel = [];
    $i = array_search($levelInduk, $urut, true);
    $id = $idInduk;
    while ($i !== false && $i >= 0 && $id !== '') {
        $lv = $urut[$i];
        $sel[$lv] = $id;
        $w = WILAYAH[$lv];
        if ($w['induk'] === null) {
            break;
        }
        $id = (string) val('SELECT ' . $w['induk'] . ' FROM ' . $w['tabel'] . ' WHERE ' . $w['id'] . ' = ?', [$id]);
        $i--;
    }
    return $sel;
}

/** Opsi isi kotak pilihan untuk induk non-wilayah / kolom relasi: [id => teks]. */
function master_pilihan_induk(array $c): array
{
    $p = master_cfg($c['induk']['ent']);
    $hasil = [];
    foreach (rows('SELECT t.' . $p['pk'] . ' AS id, t.' . $p['nama'] . ' AS nama FROM ' . $p['tabel'] . ' t ORDER BY ' . $p['urut']) as $r) {
        $hasil[$r['id']] = $r['nama'];
    }
    return $hasil;
}

function master_pilihan_rel(array $kol): array
{
    $hasil = [];
    foreach (rows('SELECT ' . $kol['nilai'] . ' AS id, ' . $kol['teks'] . ' AS nama FROM ' . $kol['tabel'] . ' ORDER BY ' . $kol['teks']) as $r) {
        $hasil[$r['id']] = $r['nama'];
    }
    return $hasil;
}

// ---------------------------------------------------------------- validasi & simpan
/**
 * Validasi isian. $idLama = id yang sedang diubah (null saat tambah).
 * Mengembalikan [daftar galat, nilai siap simpan (kolom => nilai), id]
 */
function master_validasi(array $c, array $f, ?string $idLama): array
{
    $g = [];
    $nilai = [];
    // ---- id
    $id = $idLama;
    if ($idLama === null) {
        if ($c['id']['mode'] === 'auto') {
            $id = master_id_baru($c);
        } else {
            $id = $f['kode'];
            if (!preg_match('/^[A-Za-z0-9._-]{1,10}$/', $id)) {
                $g[] = 'Kode wajib diisi: 1–10 karakter (huruf, angka, titik, minus, atau garis bawah).';
            } elseif (val('SELECT 1 FROM ' . $c['tabel'] . ' WHERE ' . $c['pk'] . ' = ?', [$id])) {
                $g[] = 'Kode “' . $id . '” sudah dipakai oleh data lain.';
            }
        }
    }
    // ---- induk
    if (isset($c['induk'])) {
        $p = master_cfg($c['induk']['ent']);
        $v = $f[$c['induk']['k']] ?? '';
        if ($v === '') {
            $g[] = $c['induk']['label'] . ' wajib dipilih.';
        } elseif (!val('SELECT 1 FROM ' . $p['tabel'] . ' WHERE ' . $p['pk'] . ' = ?', [$v])) {
            $g[] = $c['induk']['label'] . ' yang dipilih tidak ditemukan.';
        } else {
            $nilai[$c['induk']['k']] = $v;
        }
    }
    // ---- kolom
    foreach ($c['kolom'] as $k) {
        $v = (string) ($f[$k['k']] ?? '');
        $label = $k['label'];
        if ($k['tipe'] === 'foto') {
            // Berkas diperiksa di sini, disimpan oleh master_simpan_foto() setelah semua isian lolos.
            if ($err = foto_cek($_FILES[$k['k']] ?? null, false, (int) ($k['min'] ?? 100))) {
                $g[] = $label . ': ' . $err;
            }
            continue;
        }
        if ($v === '') {
            if ($k['wajib']) {
                $g[] = $label . ' wajib diisi.';
            }
            $nilai[$k['k']] = null;
            continue;
        }
        switch ($k['tipe']) {
            case 'teks':
                if (mb_strlen($v) > $k['maks']) {
                    $g[] = $label . ' maksimal ' . $k['maks'] . ' karakter.';
                }
                $nilai[$k['k']] = $v;
                break;
            case 'angka':
            case 'uang':
                if (!preg_match('/^[\d.\s]+$/', $v) || preg_replace('/\D+/', '', $v) === '') {
                    $g[] = $label . ' harus berupa angka bulat (0 atau lebih).';
                    break;
                }
                $n = angka_dari($v);
                $batas = $k['tipe'] === 'angka' ? 2147483647 : 9999999999;
                if ($n > $batas) {
                    $g[] = $label . ' terlalu besar.';
                }
                if (isset($k['min']) && $n < $k['min']) {
                    $g[] = $label . ' minimal ' . $k['min'] . '.';
                }
                $nilai[$k['k']] = $n;
                break;
            case 'persen':
                $x = str_replace(',', '.', $v);
                if (!is_numeric($x) || (float) $x < 0 || (float) $x > 100) {
                    $g[] = $label . ' harus berupa angka 0–100.';
                    break;
                }
                $nilai[$k['k']] = round((float) $x, 2);
                break;
            case 'rel':
                if (!val('SELECT 1 FROM ' . $k['tabel'] . ' WHERE ' . $k['nilai'] . ' = ?', [$v])) {
                    $g[] = $label . ' yang dipilih tidak ditemukan.';
                } elseif (!empty($k['unik']) && val('SELECT 1 FROM ' . $c['tabel'] . ' WHERE ' . $k['k'] . ' = ?' . ($idLama !== null ? ' AND ' . $c['pk'] . ' <> ?' : ''), $idLama !== null ? [$v, $idLama] : [$v])) {
                    $g[] = $label . ': pilihan itu sudah dipakai oleh data lain.';
                }
                $nilai[$k['k']] = $v;
                break;
        }
    }
    // ---- nama unik dalam lingkupnya
    $nm = $nilai[$c['nama']] ?? null;
    if (is_string($nm) && $nm !== '') {
        $sql = 'SELECT 1 FROM ' . $c['tabel'] . ' WHERE LOWER(' . $c['nama'] . ') = ?';
        $par = [mb_strtolower($nm)];
        if ($c['lingkup'] !== null && isset($nilai[$c['lingkup']])) {
            $sql .= ' AND ' . $c['lingkup'] . ' = ?';
            $par[] = $nilai[$c['lingkup']];
        }
        if ($idLama !== null) {
            $sql .= ' AND ' . $c['pk'] . ' <> ?';
            $par[] = $idLama;
        }
        if (val($sql, $par)) {
            $g[] = 'Sudah ada data bernama “' . $nm . '”' . ($c['lingkup'] !== null ? ' pada induk yang sama' : '') . '.';
        }
    }
    return [$g, $nilai, (string) $id];
}

/**
 * Simpan unggahan foto kolom bertipe 'foto' (bila ada) dan kembalikan nilai kolomnya; kolom foto yang tidak diunggah
 * dipertahankan (data lama) atau '' (kolom NOT NULL) untuk data baru. Berkas lama dihapus setelah penyimpanan berhasil.
 * @return array [nilai yang diperbarui, daftar path lama yang harus dihapus setelah commit]
 */
function master_siapkan_foto(array $c, string $id, array $nilai, ?array $lama): array
{
    $hapus = [];
    foreach ($c['kolom'] as $k) {
        if ($k['tipe'] !== 'foto') {
            continue;
        }
        if (foto_ada_unggahan($_FILES[$k['k']] ?? null)) {
            $path = foto_simpan($_FILES[$k['k']], $k['folder'], $k['prefix'] . preg_replace('/[^A-Za-z0-9]/', '', $id));
            if ($path === null) {
                throw new RuntimeException('Foto gagal disimpan di server.');
            }
            $nilai[$k['k']] = $path;
            if ($lama && !empty($lama[$k['k']])) {
                $hapus[] = (string) $lama[$k['k']];
            }
        } elseif ($lama === null) {
            $nilai[$k['k']] = '';
        }
    }
    return [$nilai, $hapus];
}

function master_simpan(array $c, string $id, array $nilai, bool $baru): void
{
    if ($baru) {
        $kol = array_merge([$c['pk']], array_keys($nilai));
        q('INSERT INTO ' . $c['tabel'] . ' (' . implode(', ', $kol) . ') VALUES (' . in_marks($kol) . ')', array_merge([$id], array_values($nilai)));
        return;
    }
    $set = implode(', ', array_map(fn($k) => $k . ' = ?', array_keys($nilai)));
    if ($c['tabel'] === 'diskon_harga') {
        // produk.besaran_potongan_harga menyalin besaran diskon yang dipilih; ikut diperbarui supaya harga katalog konsisten
        $baruBesar = (float) ($nilai['besaran_potongan_harga'] ?? 0);
        if (val('SELECT 1 FROM produk WHERE id_diskon_harga = ? AND harga_produk - ? < 500 LIMIT 1', [$id, $baruBesar])) {
            throw new RuntimeException('Potongan ini membuat harga akhir sebagian produk kurang dari Rp500. Pilih potongan yang lebih kecil.');
        }
        q('UPDATE produk SET besaran_potongan_harga = ? WHERE id_diskon_harga = ?', [$baruBesar, $id]);
    }
    q('UPDATE ' . $c['tabel'] . ' SET ' . $set . ' WHERE ' . $c['pk'] . ' = ?', array_merge(array_values($nilai), [$id]));
}

// ---------------------------------------------------------------- daftar
/** Potongan FROM + kolom nama leluhur (n1 = induk langsung, n2 = induknya lagi, …) untuk tampilan jejak. */
function master_sql_leluhur(array $c): array
{
    $join = '';
    $kolom = '';
    $alias = 't';
    $kolomInduk = null;
    $cur = $c;
    $n = 0;
    while (isset($cur['induk']) && $n < 4) {
        $p = master_cfg($cur['induk']['ent']);
        $n++;
        $a = 'a' . $n;
        $join .= ' LEFT JOIN ' . $p['tabel'] . ' ' . $a . ' ON ' . $a . '.' . $p['pk'] . ' = ' . $alias . '.' . $cur['induk']['k'];
        $kolom .= ', ' . $a . '.' . $p['nama'] . ' AS n' . $n;
        $alias = $a;
        $cur = $p;
    }
    return [$join, $kolom];
}

/** Baris untuk satu halaman daftar. Mengembalikan [baris, total]. */
function master_baris(array $c, string $q, string $induk, int $halaman, int $perHalaman): array
{
    $where = ['1 = 1'];
    $par = [];
    if ($induk !== '' && isset($c['induk'])) {
        $where[] = 't.' . $c['induk']['k'] . ' = ?';
        $par[] = $induk;
    }
    if ($q !== '') {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)) . '%';
        $where[] = "(LOWER(t.{$c['nama']}) LIKE ? ESCAPE '!' OR LOWER(t.{$c['pk']}) LIKE ? ESCAPE '!')";
        array_push($par, $like, $like);
    }
    $w = implode(' AND ', $where);
    $total = (int) val('SELECT COUNT(*) FROM ' . $c['tabel'] . ' t WHERE ' . $w, $par);
    [$hal, $totalHal, $offset] = paginasi($total, $perHalaman, $halaman);
    [$join, $kolomLeluhur] = master_sql_leluhur($c);
    $anak = '';
    foreach (master_anak($c['kunci']) as $kk => $cc) {
        $anak .= ', (SELECT COUNT(*) FROM ' . $cc['tabel'] . ' z WHERE z.' . $cc['induk']['k'] . ' = t.' . $c['pk'] . ') AS anak_' . $kk;
    }
    $baris = rows('SELECT t.*' . $kolomLeluhur . $anak . ' FROM ' . $c['tabel'] . ' t' . $join . ' WHERE ' . $w . ' ORDER BY ' . $c['urut'] . ', t.' . $c['pk'] . ' LIMIT ' . (int) $perHalaman . ' OFFSET ' . (int) $offset, $par);
    // nama untuk kolom relasi
    foreach ($c['kolom'] as $k) {
        if ($k['tipe'] === 'rel' && $baris) {
            $ids = array_values(array_unique(array_column($baris, $k['k'])));
            $peta = [];
            foreach (rows('SELECT ' . $k['nilai'] . ' AS id, ' . $k['teks'] . ' AS nama FROM ' . $k['tabel'] . ' WHERE ' . $k['nilai'] . ' IN (' . in_marks($ids) . ')', $ids) as $r) {
                $peta[$r['id']] = $r['nama'];
            }
            foreach ($baris as &$b) {
                $b['rel_' . $k['k']] = $peta[$b[$k['k']]] ?? '';
            }
            unset($b);
        }
    }
    return [$baris, $total];
}

/** Nilai satu kolom untuk ditampilkan di tabel. */
function master_tampil(array $k, array $baris): string
{
    $v = $baris[$k['k']] ?? null;
    if ($v === null || $v === '') {
        return '–';
    }
    switch ($k['tipe']) {
        case 'uang':
            return rp($v);
        case 'angka':
            return angka($v);
        case 'foto':
            return 'Ada';
        case 'persen':
            return rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . '%';
        case 'rel':
            return (string) ($baris['rel_' . $k['k']] ?? $v);
        default:
            return (string) $v;
    }
}

/** Jejak leluhur "Indonesia › Sumatera Selatan › Kota Palembang" dari kolom n1..nN hasil master_baris. */
function master_jejak(array $baris): string
{
    $bagian = [];
    for ($i = 1; $i <= 4; $i++) {
        if (isset($baris['n' . $i]) && $baris['n' . $i] !== null) {
            array_unshift($bagian, $baris['n' . $i]);
        }
    }
    return implode(' › ', $bagian);
}
