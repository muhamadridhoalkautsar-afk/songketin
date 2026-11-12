<?php
/**
 * Laporan penjualan & keuangan — dipakai dashboard penjual dan admin.
 * Agregasi dikerjakan di PHP (bukan fungsi tanggal/GROUP BY khusus MySQL) supaya SQL tetap portabel.
 *
 * Rumus per baris barang (satu produk di satu nota toko):
 *   kotor    = sub_harga                              (harga setelah potongan produk × qty)
 *   jual     = kotor                                    (sub_harga rincian sudah memuat diskon harga produk)
 *   admin    = jual × persen_biaya_admin tingkat penjual / 100
 *   bersih   = jual − admin
 * Hanya nota yang sudah dibayar (Lunas / Bayar di Tempat), tidak dibatalkan, dan tidak direfund yang dihitung sebagai penjualan.
 * “Terealisasi” = nota toko sudah diterima pembeli; sisanya “dalam proses”.
 */

function tanggal_valid(string $s): bool
{
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** [dari, sampai] (Y-m-d) dari query string; bawaan: enam bulan terakhir sampai hari ini. */
function rentang_laporan(): array
{
    $sampai = tanggal_valid(get('sampai')) ? get('sampai') : date('Y-m-d');
    $dari = tanggal_valid(get('dari')) ? get('dari') : date('Y-m-d', mktime(0, 0, 0, (int) date('n') - 5, 1, (int) date('Y')));
    return $dari > $sampai ? [$sampai, $dari] : [$dari, $sampai];
}

/** Pilihan periode cepat: label => [dari, sampai]. */
function periode_pintas(): array
{
    $hari = date('Y-m-d');
    $y = (int) date('Y');
    $m = (int) date('n');
    return [
        'Bulan ini' => [date('Y-m-01'), $hari],
        '3 bulan' => [date('Y-m-d', mktime(0, 0, 0, $m - 2, 1, $y)), $hari],
        '6 bulan' => [date('Y-m-d', mktime(0, 0, 0, $m - 5, 1, $y)), $hari],
        'Tahun ini' => [$y . '-01-01', $hari],
        'Semua' => ['2000-01-01', $hari],
    ];
}

function periode_pintas_html(string $dari, string $sampai, array $query = []): string
{
    $o = '<nav class="tab-status no-print" aria-label="Periode cepat">';
    foreach (periode_pintas() as $label => [$d, $s]) {
        $o .= '<a class="chip-link ' . ($d === $dari && $s === $sampai ? 'aktif' : '') . '" href="?' . e(http_build_query(['dari' => $d, 'sampai' => $s] + $query)) . '">' . e($label) . '</a>';
    }
    return $o . '</nav>';
}

function periode_teks(string $dari, string $sampai): string
{
    return $dari === '2000-01-01' ? 'Semua waktu s.d. ' . tgl($sampai) : tgl($dari) . ' – ' . tgl($sampai);
}

/** Rupiah ringkas untuk label grafik: 1,2 jt / 850 rb. */
function rp_singkat(float $n): string
{
    $n = round($n);
    if ($n >= 1e9) {
        return rtrim(rtrim(number_format($n / 1e9, 1, ',', ''), '0'), ',') . ' M';
    }
    if ($n >= 1e6) {
        return rtrim(rtrim(number_format($n / 1e6, 1, ',', ''), '0'), ',') . ' jt';
    }
    if ($n >= 1e3) {
        return number_format($n / 1e3, 0, ',', '.') . ' rb';
    }
    return (string) (int) $n;
}

/** Tambahkan satu baris barang ke rekap per penjual / per kategori ($grup dikelompokkan menurut $id). */
function laporan_tambah_grup(array &$grup, string $id, string $nama, string $idNota, array $r): void
{
    if (!isset($grup[$id])) {
        $grup[$id] = ['id' => $id, 'nama' => $nama, 'nota' => 0, 'qty' => 0, 'kotor' => 0.0, 'admin' => 0.0, 'bersih' => 0.0, '_n' => []];
    }
    if (!isset($grup[$id]['_n'][$idNota])) {
        $grup[$id]['_n'][$idNota] = 1;
        $grup[$id]['nota']++;
    }
    $grup[$id]['qty'] += (int) $r['qty'];
    foreach (['kotor', 'admin', 'bersih'] as $k) {
        $grup[$id][$k] += $r[$k];
    }
}

/** Urutkan rekap dari omzet terbesar dan buang penanda internal. */
function laporan_urut_grup(array $grup): array
{
    foreach ($grup as &$g) {
        unset($g['_n']);
    }
    unset($g);
    usort($grup, fn($a, $b) => [$b['kotor'], $b['qty']] <=> [$a['kotor'], $a['qty']]);
    return $grup;
}

/**
 * Ambil & hitung data laporan.
 * Hasil: ['baris'=>[…], 'nota'=>[id=>…], 'produk'=>[…urut terlaris], 'penjual'=>[…urut omzet], 'kategori'=>[…urut omzet], 'bulan'=>['2026-05'=>…], 'tot'=>[…]]
 */
function laporan_data(string $dari, string $sampai, ?string $idPenjual = null, ?string $idKategori = null): array
{
    $sql = 'SELECT rp.id_nota_penjual, rp.id_produk, rp.qty, rp.sub_harga, np.id_nota_pembeli, np.id_status_pengiriman, np.total_harga_jual, np.tanggal_sampai,
                   (SELECT SUM(x.sub_harga) FROM rincian_pesanan x WHERE x.id_nota_penjual = rp.id_nota_penjual) AS sub_nota,
                   nb.tanggal_pembelian, nb.status_pembayaran, p.nama_produk, p.foto_produk, p.id_penjual, p.id_kategori_produk,
                   kt.nama_kategori_produk, pj.nama_penjual, COALESCE(tj.persen_biaya_admin, 0) AS persen_admin, pb.nama_pembeli
              FROM rincian_pesanan rp
              JOIN nota_penjual np ON np.id_nota_penjual = rp.id_nota_penjual
              JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
              JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
              JOIN produk p ON p.id_produk = rp.id_produk
              JOIN kategori_produk kt ON kt.id_kategori_produk = p.id_kategori_produk
              JOIN penjual pj ON pj.id_penjual = p.id_penjual
              LEFT JOIN tingkat_penjual tj ON tj.id_tingkat_penjual = pj.id_tingkat_penjual
             WHERE ' . sql_nota_terhitung('nb') . ' AND COALESCE(np.id_status_pengiriman, \'\') <> \'' . SK_BATAL . '\'
               AND nb.tanggal_pembelian >= ? AND nb.tanggal_pembelian < ?
               AND COALESCE(rp.id_status_pesanan, \'\') NOT IN (\'' . implode("','", SP_REFUND_BERHASIL) . '\')';
    $par = [$dari . ' 00:00:00', date('Y-m-d', strtotime($sampai . ' +1 day')) . ' 00:00:00'];
    if ($idPenjual) {
        $sql .= ' AND p.id_penjual = ?';
        $par[] = $idPenjual;
    }
    if ($idKategori) {
        $sql .= ' AND p.id_kategori_produk = ?';
        $par[] = $idKategori;
    }
    $sql .= ' ORDER BY nb.tanggal_pembelian DESC, rp.id_nota_penjual, p.nama_produk';

    $baris = rows($sql, $par);
    $nota = $produk = $bulan = $penjual = $kategori = [];
    $tot = ['nota' => 0, 'qty' => 0, 'kotor' => 0.0, 'jual' => 0.0, 'admin' => 0.0, 'bersih' => 0.0, 'bersih_selesai' => 0.0, 'bersih_proses' => 0.0];

    foreach ($baris as &$r) {
        // toko dengan refund yang disetujui (SP08/SP11) atau pesanan batal sudah dikeluarkan di query
        $kotor = (float) $r['sub_harga'];
        $jual = $kotor;
        $admin = $jual * (float) $r['persen_admin'] / 100;
        $r['kotor'] = $kotor;
        $r['jual'] = $jual;
        $r['admin'] = $admin;
        $r['bersih'] = $jual - $admin;
        $r['selesai'] = $r['id_status_pengiriman'] === SK_DITERIMA;
        $r['ym'] = substr((string) $r['tanggal_pembelian'], 0, 7);

        $idn = $r['id_nota_penjual'];
        if (!isset($nota[$idn])) {
            $nota[$idn] = ['id' => $idn, 'id_nota_pembeli' => $r['id_nota_pembeli'], 'tanggal' => $r['tanggal_pembelian'], 'pembeli' => $r['nama_pembeli'],
                'penjual' => $r['nama_penjual'], 'selesai' => $r['selesai'], 'status_kirim' => $r['id_status_pengiriman'], 'qty' => 0, 'kotor' => 0.0,
                'jual' => 0.0, 'admin' => 0.0, 'bersih' => 0.0, 'item' => []];
            $tot['nota']++;
        }
        foreach (['kotor', 'jual', 'admin', 'bersih'] as $k) {
            $nota[$idn][$k] += $r[$k];
            $tot[$k] += $r[$k];
        }
        $nota[$idn]['qty'] += (int) $r['qty'];
        $nota[$idn]['item'][] = $r['nama_produk'] . ' ×' . (int) $r['qty'];
        $tot['qty'] += (int) $r['qty'];
        $tot[$r['selesai'] ? 'bersih_selesai' : 'bersih_proses'] += $r['bersih'];

        $kp = $r['id_produk'];
        if (!isset($produk[$kp])) {
            $produk[$kp] = ['id' => $kp, 'foto' => $r['foto_produk'], 'nama' => $r['nama_produk'], 'kategori' => $r['nama_kategori_produk'], 'penjual' => $r['nama_penjual'], 'qty' => 0, 'omzet' => 0.0, 'bersih' => 0.0];
        }
        $produk[$kp]['qty'] += (int) $r['qty'];
        $produk[$kp]['omzet'] += $kotor;
        $produk[$kp]['bersih'] += $r['bersih'];

        laporan_tambah_grup($penjual, (string) $r['id_penjual'], (string) $r['nama_penjual'], $idn, $r);
        laporan_tambah_grup($kategori, (string) $r['id_kategori_produk'], (string) $r['nama_kategori_produk'], $idn, $r);

        $ym = $r['ym'];
        if (!isset($bulan[$ym])) {
            $bulan[$ym] = ['ym' => $ym, 'nota' => 0, 'qty' => 0, 'kotor' => 0.0, 'jual' => 0.0, 'admin' => 0.0, 'bersih' => 0.0, '_n' => []];
        }
        if (!isset($bulan[$ym]['_n'][$idn])) {
            $bulan[$ym]['_n'][$idn] = 1;
            $bulan[$ym]['nota']++;
        }
        $bulan[$ym]['qty'] += (int) $r['qty'];
        foreach (['kotor', 'jual', 'admin', 'bersih'] as $k) {
            $bulan[$ym][$k] += $r[$k];
        }
    }
    unset($r);

    uasort($produk, fn($a, $b) => [$b['qty'], $b['omzet']] <=> [$a['qty'], $a['omzet']]);
    $penjual = laporan_urut_grup($penjual);
    $kategori = laporan_urut_grup($kategori);
    return ['baris' => $baris, 'nota' => $nota, 'produk' => array_values($produk), 'penjual' => $penjual, 'kategori' => $kategori,
        'bulan' => bulan_berurutan($bulan, $dari, $sampai), 'tot' => $tot];
}

/**
 * Lengkapi bulan kosong dalam rentang supaya grafik tidak bolong.
 * Rentang sampai 12 bulan ditampilkan utuh; rentang yang lebih panjang (mis. “Semua”) dimulai dari bulan pertama yang punya penjualan.
 * Bulan yang punya penjualan tidak pernah dibuang, jadi jumlah di tabel bulanan selalu sama dengan total laporan.
 */
function bulan_berurutan(array $bulan, string $dari, string $sampai): array
{
    $indeks = fn(string $s): int => (int) substr($s, 0, 4) * 12 + (int) substr($s, 5, 2) - 1;
    $akhir = $indeks($sampai);
    $awal = $indeks($dari);
    if ($akhir - $awal > 11) {
        $awal = $bulan ? max($awal, $indeks(min(array_keys($bulan)))) : $akhir - 5;
    }
    $o = [];
    for ($i = $awal; $i <= $akhir; $i++) {
        $ym = sprintf('%04d-%02d', intdiv($i, 12), $i % 12 + 1);
        $o[$ym] = $bulan[$ym] ?? ['ym' => $ym, 'nota' => 0, 'qty' => 0, 'kotor' => 0.0, 'jual' => 0.0, 'admin' => 0.0, 'bersih' => 0.0];
        unset($o[$ym]['_n']);
    }
    return $o;
}

function nama_bulan_singkat(string $ym): string
{
    return substr(bulan_id((int) substr($ym, 5, 2)), 0, 3) . ' ' . substr($ym, 2, 2);
}

/** Grafik batang per bulan memakai komponen .grafik (tinggi batang dianimasikan oleh app.js). */
function grafik_bulanan_html(array $bulan, string $kunci, string $deskripsi): string
{
    $bulan = array_slice($bulan, -12, null, true); // grafik cukup 12 bulan terakhir; tabel tetap memuat semuanya
    $maks = 0.0;
    foreach ($bulan as $b) {
        $maks = max($maks, (float) $b[$kunci]);
    }
    $o = '<div class="grafik" role="img" aria-label="' . e($deskripsi) . '">';
    foreach ($bulan as $b) {
        $v = (float) $b[$kunci];
        $h = $v > 0 && $maks > 0 ? max(4, (int) round($v / $maks * 70)) : 0;
        $o .= '<div class="batang"><b>' . ($v > 0 ? e(rp_singkat($v)) : '–') . '</b><i data-h="' . $h . '%" title="' . e(bulan_id((int) substr($b['ym'], 5, 2)) . ' ' . substr($b['ym'], 0, 4) . ': ' . rp($v)) . '"></i><span>' . e(nama_bulan_singkat($b['ym'])) . '</span></div>';
    }
    return $o . '</div>';
}

/** Tabel ringkasan per bulan (juga berfungsi sebagai alternatif teks untuk grafik). */
function tabel_bulanan_html(array $bulan, bool $tampilAdmin = true): string
{
    $o = '<div class="tabel-bungkus"><table class="tabel"><thead><tr><th>Bulan</th><th class="angka">Pesanan</th><th class="angka">Barang</th><th class="angka">Penjualan kotor</th>'
        . ($tampilAdmin ? '<th class="angka">Biaya layanan</th>' : '') . '<th class="angka">Pendapatan bersih</th></tr></thead><tbody>';
    $t = ['nota' => 0, 'qty' => 0, 'kotor' => 0, 'admin' => 0, 'bersih' => 0];
    foreach (array_reverse($bulan, true) as $b) {
        foreach ($t as $k => $_) {
            $t[$k] += $b[$k];
        }
        $o .= '<tr><td>' . e(bulan_id((int) substr($b['ym'], 5, 2)) . ' ' . substr($b['ym'], 0, 4)) . '</td><td class="angka">' . angka($b['nota']) . '</td><td class="angka">' . angka($b['qty']) . '</td>'
            . '<td class="angka">' . rp($b['kotor']) . '</td>'
            . ($tampilAdmin ? '<td class="angka">' . ($b['admin'] > 0 ? '− ' . rp($b['admin']) : '–') . '</td>' : '') . '<td class="angka tebal">' . rp($b['bersih']) . '</td></tr>';
    }
    return $o . '</tbody><tfoot><tr><td>Jumlah</td><td class="angka">' . angka($t['nota']) . '</td><td class="angka">' . angka($t['qty']) . '</td><td class="angka">' . rp($t['kotor']) . '</td>'
        . ($tampilAdmin ? '<td class="angka">− ' . rp($t['admin']) . '</td>' : '') . '<td class="angka">' . rp($t['bersih']) . '</td></tr></tfoot></table></div>';
}

/** Badge status satu nota toko untuk tabel laporan. */
function badge_status_kirim(string $sk): string
{
    $nama = ['SK01' => 'Belum Dikirim', 'SK02' => 'Diserahkan ke Kurir', 'SK03' => 'Dalam Perjalanan', 'SK04' => 'Tiba di Kota Tujuan', 'SK05' => 'Sedang Diantar', 'SK06' => 'Diterima', 'SK07' => 'Dibatalkan'][$sk] ?? $sk;
    return badge($nama);
}
