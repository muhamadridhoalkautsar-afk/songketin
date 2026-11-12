<?php
/** Penghapusan data master yang aman: memeriksa pemakaian di data lain, dan menghapus turunannya bila diminta. */

/**
 * Himpunan baris yang ikut terhapus bila $id dihapus: [[entitas, klausa WHERE, parameter], …], induk lebih dulu.
 * Klausa memakai subkueri bersarang sehingga satu perintah cukup untuk satu tingkat (aman untuk puluhan ribu desa).
 */
function master_subpohon(string $d, string $id): array
{
    $keluar = [];
    $bangun = function (string $ent, string $where, array $par) use (&$bangun, &$keluar) {
        $keluar[] = [$ent, $where, $par];
        $c = master_cfg($ent);
        foreach (master_anak($ent) as $kk => $cc) {
            $bangun($kk, $cc['induk']['k'] . ' IN (SELECT ' . $c['pk'] . ' FROM ' . $c['tabel'] . ' WHERE ' . $where . ')', $par);
        }
    };
    $c = master_cfg($d);
    $bangun($d, $c['pk'] . ' = ?', [$id]);
    return $keluar;
}

/** Jumlah baris turunan per entitas (tanpa induknya sendiri): [entitas => jumlah]. */
function master_hitung_turunan(array $subpohon): array
{
    $hasil = [];
    foreach (array_slice($subpohon, 1) as [$ent, $where, $par]) {
        $n = (int) val('SELECT COUNT(*) FROM ' . master_cfg($ent)['tabel'] . ' WHERE ' . $where, $par);
        if ($n > 0) {
            $hasil[$ent] = $n;
        }
    }
    return $hasil;
}

/** Pemakaian oleh data lain (produk, akun, pesanan, …) di seluruh subpohon: [label => jumlah]. */
function master_pemakaian(array $subpohon): array
{
    $hasil = [];
    foreach ($subpohon as [$ent, $where, $par]) {
        $c = master_cfg($ent);
        foreach ($c['dipakai'] as [$tabel, $kolom, $label]) {
            $n = (int) val('SELECT COUNT(*) FROM ' . $tabel . ' WHERE ' . $kolom . ' IN (SELECT ' . $c['pk'] . ' FROM ' . $c['tabel'] . ' WHERE ' . $where . ')', $par);
            if ($n > 0) {
                $hasil[$label] = ($hasil[$label] ?? 0) + $n;
            }
        }
    }
    return $hasil;
}

/** Kalimat "3 produk, 2 toko" dari hasil master_pemakaian. */
function master_pemakaian_teks(array $pemakaian): string
{
    $o = [];
    foreach ($pemakaian as $label => $n) {
        $o[] = angka($n) . ' ' . $label;
    }
    return implode(', ', $o);
}

/** Hapus satu data master. $semuaTurunan = true menghapus juga seluruh turunannya. Mengembalikan [ok, pesan]. */
function master_hapus(string $d, string $id, bool $semuaTurunan): array
{
    $c = master_cfg($d);
    $baris = master_ambil($d, $id);
    if (!$c || !$baris) {
        return [false, 'Data tidak ditemukan (mungkin sudah dihapus).'];
    }
    $nama = (string) $baris[$c['nama']];
    if (!empty($c['hanya_ubah'])) {
        return [false, $c['judul'] . ' hanya bisa diubah namanya, tidak bisa dihapus.'];
    }
    if (in_array($id, $c['dilindungi'] ?? [], true)) {
        return [false, '“' . $nama . '” dipakai langsung oleh alur aplikasi sehingga tidak bisa dihapus. Anda tetap bisa mengubah namanya.'];
    }
    if (isset($c['minimal']) && master_hitung($d) <= $c['minimal']) {
        return [false, 'Minimal harus ada ' . $c['minimal'] . ' ' . mb_strtolower($c['judul']) . ', jadi data terakhir ini tidak bisa dihapus.'];
    }
    $sub = master_subpohon($d, $id);
    $pakai = master_pemakaian($sub);
    if ($pakai) {
        return [false, '“' . $nama . '” tidak bisa dihapus karena masih dipakai oleh ' . master_pemakaian_teks($pakai) . '.' . (count($sub) > 1 ? ' (termasuk data di bawahnya)' : '')];
    }
    $turunan = master_hitung_turunan($sub);
    if ($turunan && !$semuaTurunan) {
        return [false, '“' . $nama . '” masih punya data di bawahnya. Hapus lewat tombol hapus di daftar dan setujui penghapusan beserta isinya.'];
    }
    // berkas foto milik baris yang akan terhapus (mis. foto QR) dibersihkan setelah penghapusan berhasil
    $fotoHapus = [];
    foreach ($sub as [$ent, $where, $par]) {
        foreach (master_cfg($ent)['kolom'] as $k) {
            if ($k['tipe'] === 'foto') {
                foreach (rows('SELECT ' . $k['k'] . ' AS f FROM ' . master_cfg($ent)['tabel'] . ' WHERE ' . $where, $par) as $r) {
                    $fotoHapus[] = (string) $r['f'];
                }
            }
        }
    }
    try {
        transaksi(function () use ($sub) {
            foreach (array_reverse($sub) as [$ent, $where, $par]) { // dari tingkat terdalam ke atas
                q('DELETE FROM ' . master_cfg($ent)['tabel'] . ' WHERE ' . $where, $par);
            }
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return [false, '“' . $nama . '” masih terhubung dengan data lain sehingga tidak bisa dihapus.'];
        }
        throw $e;
    }
    foreach ($fotoHapus as $path) {
        foto_hapus($path);
    }
    $tambah = '';
    if ($turunan) {
        $o = [];
        foreach ($turunan as $ent => $n) {
            $o[] = angka($n) . ' ' . mb_strtolower(master_cfg($ent)['judul']);
        }
        $tambah = ' beserta ' . implode(', ', $o);
    }
    return [true, $c['judul'] . ' “' . $nama . '”' . $tambah . ' dihapus.'];
}
