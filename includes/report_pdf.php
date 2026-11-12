<?php
/** Laporan penjualan & keuangan dalam PDF (A4) memakai MiniPDF — untuk penjual dan admin. */

require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/report.php';

/**
 * Gambar tabel dengan teks yang dibungkus dan pindah halaman otomatis.
 * $kolom = [['j'=>judul, 'w'=>lebar, 'a'=>'L'|'R'], …]; $baris = array of array string; $jumlah = baris total (opsional).
 * $halamanBaru() menambah halaman dan mengembalikan y awal halaman itu.
 */
function pdf_tabel(MiniPDF $pdf, float $x0, float &$y, array $kolom, array $baris, float $batas, callable $halamanBaru, ?array $jumlah = null): void
{
    $pad = 5;
    $lh = 11;
    $lebar = array_sum(array_column($kolom, 'w'));
    $kepala = function () use ($pdf, $x0, &$y, $kolom, $lebar, $pad) {
        $pdf->rect($x0, $y, $lebar, 20, '#f0e5cf');
        $pdf->font('B', 7.8);
        $x = $x0;
        foreach ($kolom as $k) {
            $pdf->text(($k['a'] ?? 'L') === 'R' ? $x + $k['w'] - $pad : $x + $pad, $y + 13.5, mb_strtoupper($k['j']), '#3f0d17', $k['a'] ?? 'L');
            $x += $k['w'];
        }
        $y += 20;
    };
    $kepala();
    foreach ($baris as $i => $b) {
        $pdf->font('', 8.5);
        $sel = [];
        $maks = 1;
        foreach ($kolom as $j => $k) {
            $l = $pdf->wrap((string) ($b[$j] ?? ''), $k['w'] - 2 * $pad);
            if (count($l) > 3) {
                $l = array_slice($l, 0, 3);
                $l[2] = rtrim(mb_substr($l[2], 0, max(1, mb_strlen($l[2]) - 1)), ' .,') . '…';
            }
            $sel[$j] = $l;
            $maks = max($maks, count($l));
        }
        $tinggi = $maks * $lh + 8;
        if ($y + $tinggi > $batas) {
            $y = $halamanBaru();
            $kepala();
            $pdf->font('', 8.5);
        }
        if ($i % 2 === 1) {
            $pdf->rect($x0, $y, $lebar, $tinggi, '#fbf6ea');
        }
        $x = $x0;
        foreach ($kolom as $j => $k) {
            $yy = $y + 12.5;
            foreach ($sel[$j] as $l) {
                $pdf->text(($k['a'] ?? 'L') === 'R' ? $x + $k['w'] - $pad : $x + $pad, $yy, $l, '#2a1a14', $k['a'] ?? 'L');
                $yy += $lh;
            }
            $x += $k['w'];
        }
        $pdf->line($x0, $y + $tinggi, $x0 + $lebar, $y + $tinggi, '#efe4cc', 0.5);
        $y += $tinggi;
    }
    if ($jumlah) {
        if ($y + 22 > $batas) {
            $y = $halamanBaru();
        }
        $pdf->rect($x0, $y, $lebar, 22, '#f0e5cf');
        $pdf->font('B', 8.5);
        $x = $x0;
        foreach ($kolom as $j => $k) {
            if (($jumlah[$j] ?? '') !== '') {
                $pdf->text(($k['a'] ?? 'L') === 'R' ? $x + $k['w'] - $pad : $x + $pad, $y + 14.5, (string) $jumlah[$j], '#3f0d17', $k['a'] ?? 'L');
            }
            $x += $k['w'];
        }
        $y += 22;
    }
    $y += 8;
}

/**
 * $meta: judul, subjudul, periode, tampil_penjual (bool), nama_berkas.
 * $d: hasil laporan_data().
 */
function laporan_pdf(array $meta, array $d): MiniPDF
{
    $pdf = new MiniPDF($meta['judul'] . ' — ' . $meta['subjudul']);
    $W = $pdf->w;
    $H = $pdf->h;
    $M = 40;
    $isi = $W - 2 * $M;
    $tot = $d['tot'];
    $lihatPenjual = !empty($meta['tampil_penjual']);

    $kepala = function (bool $penuh) use ($pdf, $W, $M, $meta) {
        $t = $penuh ? 86 : 42;
        $pdf->rect(0, 0, $W, $t, '#6b1a28');
        $pdf->font('B', $penuh ? 22 : 14);
        $pdf->text($M, $penuh ? 46 : 27, 'SONGKETIN', '#fff3d3');
        if ($penuh) {
            $pdf->font('', 9);
            $pdf->text($M, 64, 'Tenun Nusantara, ditenun untuk Anda', '#e3b23c');
        }
        $pdf->font('B', $penuh ? 12.5 : 9.5);
        $pdf->text($W - $M, $penuh ? 42 : 25, mb_strtoupper($meta['judul']), '#ffffff', 'R');
        $pdf->font('', $penuh ? 9.5 : 8);
        $pdf->text($W - $M, $penuh ? 58 : 36, $meta['subjudul'], '#e3b23c', 'R');
        for ($x = 0; $x < $W; $x += 16) {
            $pdf->triangle($x, $t + 11, $x + 8, $t + 1, $x + 16, $t + 11, '#e3b23c');
        }
        return $t + 32;
    };
    $pdf->footer = function (MiniPDF $p, int $i, int $total) use ($W, $H, $M, $meta) {
        $p->line($M, $H - 48, $W - $M, $H - 48, '#e2d4b6', 0.6);
        $p->font('', 8);
        $p->text($M, $H - 34, 'Songketin — ' . $meta['judul'] . ' · dicetak ' . date('d/m/Y H.i') . ' WIB', '#8c7b6c');
        $p->text($W - $M, $H - 34, 'Halaman ' . $i . ' dari ' . $total, '#8c7b6c', 'R');
        for ($x = 0; $x < $W; $x += 12) {
            $p->triangle($x, $H, $x + 6, $H - 7, $x + 12, $H, $x % 24 === 0 ? '#6b1a28' : '#e3b23c');
        }
    };

    $pdf->addPage();
    $y = $kepala(true);
    $batas = $H - 64;
    $halamanBaru = function () use ($pdf, &$y, $kepala) {
        $pdf->addPage();
        return $y = $kepala(false);
    };
    $judulBagian = function (string $t) use ($pdf, $M, &$y, $batas, $halamanBaru) {
        if ($y + 70 > $batas) {
            $y = $halamanBaru();
        }
        $pdf->font('B', 11);
        $pdf->text($M, $y + 4, $t, '#3f0d17');
        $pdf->line($M, $y + 10, $M + 28, $y + 10, '#c8962e', 1.6);
        $y += 24;
    };

    // ---- identitas laporan
    $pdf->font('B', 8);
    $pdf->text($M, $y, mb_strtoupper($lihatPenjual && !empty($meta['semua']) ? 'Cakupan' : 'Toko'), '#8c7b6c');
    $pdf->font('B', 12);
    $pdf->text($M, $y + 16, $meta['subjudul'], '#3f0d17');
    $pdf->font('B', 8);
    $pdf->text($W - $M, $y, 'PERIODE', '#8c7b6c', 'R');
    $pdf->font('B', 12);
    $pdf->text($W - $M, $y + 16, $meta['periode'], '#3f0d17', 'R');
    $y += 36;

    // ---- kotak ringkasan
    $kotak = [
        ['Penjualan kotor', rp($tot['kotor']), '#2a1a14'],
        ['Barang terjual', angka($tot['qty']) . ' pcs', '#2a1a14'],
        [$lihatPenjual ? 'Biaya layanan (platform)' : 'Biaya layanan admin', '- ' . rp($tot['admin']), '#2a1a14'],
        [$lihatPenjual ? 'Bersih untuk penjual' : 'Pendapatan bersih', rp($tot['bersih']), '#1e5a3b'],
    ];
    $kotak2 = [
        ['Jumlah pesanan', angka($tot['nota']), '#2a1a14'],
        ['Rata-rata per pesanan', $tot['nota'] > 0 ? rp($tot['kotor'] / $tot['nota']) : '-', '#2a1a14'],
        ['Sudah diterima pembeli', rp($tot['bersih_selesai']), '#1e5a3b'],
        ['Masih dalam proses', rp($tot['bersih_proses']), '#8a5a00'],
    ];
    $gap = 8;
    $lb = ($isi - 3 * $gap) / 4;
    foreach ([$kotak, $kotak2] as $baris) {
        foreach ($baris as $i => [$lab, $nilai, $warna]) {
            $x = $M + $i * ($lb + $gap);
            $pdf->rect($x, $y, $lb, 46, '#fffaf0', '#e2d4b6', 0.7, 5);
            $pdf->font('', 7.8);
            $pdf->text($x + 9, $y + 15, $lab, '#8c7b6c');
            $pdf->font('B', 11.5);
            $pdf->text($x + 9, $y + 35, $nilai, $warna);
        }
        $y += 54;
    }
    $pdf->font('', 7.8);
    foreach ($pdf->wrap('Catatan: hanya pesanan berstatus Lunas atau Bayar di Tempat yang dihitung. Bersih = penjualan (setelah diskon harga produk) dikurangi biaya layanan menurut tingkat penjual.', $isi) as $l) {
        $pdf->text($M, $y + 4, $l, '#8c7b6c');
        $y += 10;
    }
    $y += 10;

    // ---- per bulan
    $judulBagian('Ringkasan per bulan');
    $kol = [['j' => 'Bulan', 'w' => 86], ['j' => 'Pesanan', 'w' => 48, 'a' => 'R'], ['j' => 'Barang', 'w' => 46, 'a' => 'R'], ['j' => 'Kotor', 'w' => 110, 'a' => 'R'],
        ['j' => 'Biaya layanan', 'w' => 110, 'a' => 'R'], ['j' => 'Bersih', 'w' => 115, 'a' => 'R']];
    $br = [];
    foreach (array_reverse($d['bulan'], true) as $b) {
        $br[] = [bulan_id((int) substr($b['ym'], 5, 2)) . ' ' . substr($b['ym'], 0, 4), angka($b['nota']), angka($b['qty']), rp($b['kotor']),
            $b['admin'] > 0 ? '- ' . rp($b['admin']) : '-', rp($b['bersih'])];
    }
    pdf_tabel($pdf, $M, $y, $kol, $br, $batas, $halamanBaru,
        ['Jumlah', angka($tot['nota']), angka($tot['qty']), rp($tot['kotor']), '- ' . rp($tot['admin']), rp($tot['bersih'])]);

    // ---- rekap per toko & per kategori (laporan admin)
    if ($lihatPenjual) {
        foreach ([['Rekap per toko', 'Toko', $d['penjual']], ['Rekap per kategori', 'Kategori', $d['kategori']]] as [$judulGrup, $kolNama, $grup]) {
            if (!$grup) {
                continue;
            }
            $judulBagian($judulGrup);
            $kol = [['j' => 'No', 'w' => 24], ['j' => $kolNama, 'w' => 130], ['j' => 'Pesanan', 'w' => 48, 'a' => 'R'], ['j' => 'Barang', 'w' => 44, 'a' => 'R'],
                ['j' => 'Kotor', 'w' => 90, 'a' => 'R'], ['j' => 'Biaya', 'w' => 90, 'a' => 'R'], ['j' => 'Bersih', 'w' => 89, 'a' => 'R']];
            $br = [];
            foreach ($grup as $i => $g) {
                $br[] = [$i + 1, $g['nama'], angka($g['nota']), angka($g['qty']), rp($g['kotor']), $g['admin'] > 0 ? '- ' . rp($g['admin']) : '-', rp($g['bersih'])];
            }
            pdf_tabel($pdf, $M, $y, $kol, $br, $batas, $halamanBaru, ['', 'Jumlah', angka($tot['nota']), angka($tot['qty']), rp($tot['kotor']), '- ' . rp($tot['admin']), rp($tot['bersih'])]);
        }
    }

    // ---- per produk
    $judulBagian('Penjualan per produk');
    $kol = $lihatPenjual
        ? [['j' => 'No', 'w' => 26], ['j' => 'Produk', 'w' => 176], ['j' => 'Penjual', 'w' => 104], ['j' => 'Terjual', 'w' => 45, 'a' => 'R'], ['j' => 'Omzet', 'w' => 80, 'a' => 'R'], ['j' => 'Bersih', 'w' => 84, 'a' => 'R']]
        : [['j' => 'No', 'w' => 26], ['j' => 'Produk', 'w' => 186], ['j' => 'Kategori', 'w' => 96], ['j' => 'Terjual', 'w' => 45, 'a' => 'R'], ['j' => 'Omzet', 'w' => 80, 'a' => 'R'], ['j' => 'Bersih', 'w' => 82, 'a' => 'R']];
    $br = [];
    foreach ($d['produk'] as $i => $p) {
        $br[] = $lihatPenjual
            ? [$i + 1, $p['nama'], $p['penjual'], angka($p['qty']), rp($p['omzet']), rp($p['bersih'])]
            : [$i + 1, $p['nama'], $p['kategori'], angka($p['qty']), rp($p['omzet']), rp($p['bersih'])];
    }
    if (!$br) {
        $pdf->font('', 9);
        $pdf->text($M, $y + 4, 'Belum ada penjualan pada periode ini.', '#8c7b6c');
        $y += 26;
    } else {
        pdf_tabel($pdf, $M, $y, $kol, $br, $batas, $halamanBaru,
            ['', 'Jumlah', '', angka($tot['qty']), rp($tot['kotor']), rp($tot['bersih'])]);
    }

    // ---- rincian pesanan
    $judulBagian('Rincian pesanan');
    $kol = $lihatPenjual
        ? [['j' => 'Tanggal', 'w' => 56], ['j' => 'No. nota', 'w' => 58], ['j' => 'Pembeli', 'w' => 78], ['j' => 'Penjual', 'w' => 74], ['j' => 'Barang', 'w' => 140], ['j' => 'Bersih', 'w' => 109, 'a' => 'R']]
        : [['j' => 'Tanggal', 'w' => 56], ['j' => 'No. nota', 'w' => 64], ['j' => 'Pembeli', 'w' => 88], ['j' => 'Barang', 'w' => 175], ['j' => 'Status', 'w' => 48], ['j' => 'Bersih', 'w' => 84, 'a' => 'R']];
    $br = [];
    foreach ($d['nota'] as $n) {
        $tglPendek = date('d/m/y', strtotime($n['tanggal']));
        $br[] = $lihatPenjual
            ? [$tglPendek, $n['id_nota_pembeli'], $n['pembeli'], $n['penjual'], implode(', ', $n['item']), rp($n['bersih'])]
            : [$tglPendek, $n['id_nota_pembeli'], $n['pembeli'], implode(', ', $n['item']), $n['selesai'] ? 'Selesai' : 'Proses', rp($n['bersih'])];
    }
    if (!$br) {
        $pdf->font('', 9);
        $pdf->text($M, $y + 4, 'Belum ada pesanan pada periode ini.', '#8c7b6c');
        $y += 26;
    } else {
        pdf_tabel($pdf, $M, $y, $kol, $br, $batas, $halamanBaru);
    }
    return $pdf;
}
