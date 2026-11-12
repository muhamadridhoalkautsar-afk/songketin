<?php
/** Hirarki alamat: negara > provinsi > kabupaten/kota > kecamatan > desa/kelurahan. */

const WILAYAH = [
    'negara' => ['tabel' => 'negara', 'id' => 'id_negara', 'nama' => 'nama_negara', 'induk' => null],
    'provinsi' => ['tabel' => 'provinsi', 'id' => 'id_provinsi', 'nama' => 'nama_provinsi', 'induk' => 'id_negara'],
    'kabupaten' => ['tabel' => 'kabupaten', 'id' => 'id_kabupaten', 'nama' => 'nama_kabupaten', 'induk' => 'id_provinsi'],
    'kecamatan' => ['tabel' => 'kecamatan', 'id' => 'id_kecamatan', 'nama' => 'nama_kecamatan', 'induk' => 'id_kabupaten'],
    'desa' => ['tabel' => 'desa', 'id' => 'id_desa', 'nama' => 'nama_desa', 'induk' => 'id_kecamatan'],
];

/** Daftar anak langsung dari $induk pada tingkat $level, urut abjad. */
function wilayah_anak(string $level, ?string $induk = null): array
{
    $w = WILAYAH[$level] ?? null;
    if (!$w) {
        return [];
    }
    if ($w['induk'] === null) {
        return rows("SELECT {$w['id']} AS id, {$w['nama']} AS nama FROM {$w['tabel']} ORDER BY {$w['nama']}");
    }
    if ($induk === null || $induk === '') {
        return [];
    }
    return rows("SELECT {$w['id']} AS id, {$w['nama']} AS nama FROM {$w['tabel']} WHERE {$w['induk']} = ? ORDER BY {$w['nama']}", [$induk]);
}

/** Benar hanya bila kelima pilihan membentuk satu rantai yang sah (desa ∈ kecamatan ∈ kabupaten ∈ provinsi ∈ negara). */
function wilayah_valid(string $negara, string $prov, string $kab, string $kec, string $desa): bool
{
    if ($negara === '' || $prov === '' || $kab === '' || $kec === '' || $desa === '') {
        return false;
    }
    return (bool) val(
        'SELECT 1 FROM desa d
           JOIN kecamatan k ON k.id_kecamatan = d.id_kecamatan
           JOIN kabupaten b ON b.id_kabupaten = k.id_kabupaten
           JOIN provinsi p ON p.id_provinsi = b.id_provinsi
          WHERE d.id_desa = ? AND k.id_kecamatan = ? AND b.id_kabupaten = ? AND p.id_provinsi = ? AND p.id_negara = ?',
        [$desa, $kec, $kab, $prov, $negara]
    );
}

/** Rantai lengkap dari id desa: ['desa'=>['id','nama'], 'kecamatan'=>…, 'kabupaten'=>…, 'provinsi'=>…, 'negara'=>…] atau null. */
function wilayah_rantai(?string $idDesa): ?array
{
    if (!$idDesa) {
        return null;
    }
    static $cache = [];
    if (array_key_exists($idDesa, $cache)) {
        return $cache[$idDesa];
    }
    $r = row(
        'SELECT d.id_desa, d.nama_desa, k.id_kecamatan, k.nama_kecamatan, b.id_kabupaten, b.nama_kabupaten,
                p.id_provinsi, p.nama_provinsi, n.id_negara, n.nama_negara
           FROM desa d
           JOIN kecamatan k ON k.id_kecamatan = d.id_kecamatan
           JOIN kabupaten b ON b.id_kabupaten = k.id_kabupaten
           JOIN provinsi p ON p.id_provinsi = b.id_provinsi
           JOIN negara n ON n.id_negara = p.id_negara
          WHERE d.id_desa = ?',
        [$idDesa]
    );
    if (!$r) {
        return $cache[$idDesa] = null;
    }
    return $cache[$idDesa] = [
        'desa' => ['id' => $r['id_desa'], 'nama' => $r['nama_desa']],
        'kecamatan' => ['id' => $r['id_kecamatan'], 'nama' => $r['nama_kecamatan']],
        'kabupaten' => ['id' => $r['id_kabupaten'], 'nama' => $r['nama_kabupaten']],
        'provinsi' => ['id' => $r['id_provinsi'], 'nama' => $r['nama_provinsi']],
        'negara' => ['id' => $r['id_negara'], 'nama' => $r['nama_negara']],
    ];
}

/** Alamat satu baris: "Jl. X No. 1, Desa A, Kec. B, Kab. C, Provinsi D". */
function alamat_teks(?string $detail, ?string $idDesa): string
{
    $r = wilayah_rantai($idDesa);
    $bagian = [];
    if ($detail !== null && trim($detail) !== '') {
        $bagian[] = trim($detail);
    }
    if ($r) {
        $bagian[] = $r['desa']['nama'];
        $bagian[] = 'Kec. ' . $r['kecamatan']['nama'];
        $bagian[] = $r['kabupaten']['nama'];
        $bagian[] = $r['provinsi']['nama'];
    }
    return implode(', ', $bagian);
}

/** Kota (kabupaten/kota) untuk kolom pengiriman.kota_asal / kota_tujuan. */
function kota_dari_desa(?string $idDesa): string
{
    $r = wilayah_rantai($idDesa);
    return $r ? $r['kabupaten']['nama'] . ', ' . $r['provinsi']['nama'] : '';
}

/**
 * Lima pilihan alamat bertingkat. Pilihan induk harus dipilih dulu agar tingkat berikutnya terbuka.
 * $sel = ['negara'=>id, 'provinsi'=>id, ...] untuk mengisi awal (mis. halaman profil); $sampai = tingkat terakhir yang ditampilkan.
 */
function wilayah_fields(array $sel = [], string $sampai = 'desa'): string
{
    $label = ['negara' => 'Negara', 'provinsi' => 'Provinsi', 'kabupaten' => 'Kabupaten / Kota', 'kecamatan' => 'Kecamatan', 'desa' => 'Desa / Kelurahan'];
    $o = '<div class="wilayah" data-wilayah data-api="' . e(url('api/wilayah.php')) . '">';
    $induk = null;
    foreach (WILAYAH as $level => $w) {
        $pilihan = $sel[$level] ?? '';
        $opsi = $level === 'negara' || ($induk !== null && $induk !== '') ? wilayah_anak($level, $induk) : [];
        $tersedia = $level === 'negara' || !empty($opsi);
        $o .= '<label class="field"><span class="field-label">' . $label[$level] . ' <b class="req">*</b></span>'
            . '<select name="' . $level . '" data-level="' . $level . '" required' . ($tersedia ? '' : ' disabled') . '>'
            . '<option value="">' . ($level === 'negara' ? '— pilih negara —' : '— pilih ' . strtolower($label[$level]) . ' —') . '</option>';
        foreach ($opsi as $x) {
            $o .= '<option value="' . e($x['id']) . '"' . ($x['id'] === $pilihan ? ' selected' : '') . '>' . e($x['nama']) . '</option>';
        }
        $o .= '</select></label>';
        $induk = $pilihan;
        if ($level === $sampai) { // kotak bertingkat boleh berhenti di tingkat tertentu (mis. memilih induk kecamatan)
            break;
        }
    }
    return $o . '</div>';
}
