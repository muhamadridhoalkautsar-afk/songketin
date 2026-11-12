<?php
/** Struk / nota pembelian dalam bentuk PDF (A4), dibuat dengan MiniPDF. */

require_once __DIR__ . '/pdf.php';

function struk_judul(array $n): string
{
    return in_array($n['status_pembayaran'], [BAYAR_LUNAS, BAYAR_COD, BAYAR_VERIF], true) ? 'STRUK PEMBAYARAN' : 'NOTA PEMBELIAN';
}

function struk_pdf(array $n): MiniPDF
{
    $b = rincian_biaya($n);
    $pdf = new MiniPDF('Struk ' . $n['id_nota_pembeli']);
    $W = $pdf->w;
    $H = $pdf->h;
    $M = 40;
    $judul = struk_judul($n);
    $warnaStatus = ['Lunas' => '#1e5a3b', 'Menunggu Verifikasi' => '#8a5a00', 'Belum Dibayar' => '#9b2118', 'Pembayaran Ditolak' => '#9b2118', 'Dibatalkan' => '#6a5a4b', 'Bayar di Tempat' => '#26467d'][$n['status_pembayaran']] ?? '#2a1a14';

    $kepala = function (bool $penuh) use ($pdf, $W, $M, $judul, $n) {
        $t = $penuh ? 92 : 44;
        $pdf->rect(0, 0, $W, $t, '#6b1a28');
        $pdf->font('B', $penuh ? 24 : 15);
        $pdf->text($M, $penuh ? 52 : 29, 'SONGKETIN', '#fff3d3');
        if ($penuh) {
            $pdf->font('', 9);
            $pdf->text($M, 70, 'Tenun Nusantara, ditenun untuk Anda', '#e3b23c');
        }
        $pdf->font('B', $penuh ? 14 : 10);
        $pdf->text($W - $M, $penuh ? 46 : 27, $judul, '#ffffff', 'R');
        $pdf->font('', $penuh ? 10 : 8.5);
        $pdf->text($W - $M, $penuh ? 64 : 38, 'No. ' . $n['id_nota_pembeli'], '#e3b23c', 'R');
        for ($x = 0; $x < $W; $x += 16) { // pita tumpal
            $pdf->triangle($x, $t + 11, $x + 8, $t + 1, $x + 16, $t + 11, '#e3b23c');
        }
        $pdf->rect(0, $t, $W, 1.2, '#6b1a28');
        return $t + 30;
    };

    $pdf->footer = function (MiniPDF $p, int $i, int $total) use ($W, $H, $M) {
        $p->line($M, $H - 52, $W - $M, $H - 52, '#e2d4b6', 0.6);
        $p->font('', 8);
        $p->text($M, $H - 38, 'Songketin — struk ini dibuat otomatis oleh sistem dan sah tanpa tanda tangan.', '#8c7b6c');
        $p->text($W - $M, $H - 38, 'Halaman ' . $i . ' dari ' . $total, '#8c7b6c', 'R');
        for ($x = 0; $x < $W; $x += 12) {
            $p->triangle($x, $H, $x + 6, $H - 8, $x + 12, $H, $x % 24 === 0 ? '#6b1a28' : '#e3b23c');
        }
    };

    $pdf->addPage();
    $y = $kepala(true);
    $batas = $H - 78; // batas bawah isi

    $pastikan = function (float $tinggi) use ($pdf, &$y, $batas, $kepala) {
        if ($y + $tinggi > $batas) {
            $pdf->addPage();
            $y = $kepala(false);
        }
    };

    // ---- pembeli & rincian pembayaran
    $pdf->font('B', 8);
    $pdf->text($M, $y, 'DIBELI OLEH', '#8c7b6c');
    $pdf->font('B', 12);
    $pdf->text($M, $y + 17, $n['nama_pembeli'], '#3f0d17');
    $pdf->font('', 9.5);
    $yk = $y + 33;
    foreach ($pdf->wrap($n['alamat'] ?: '-', 255) as $baris) {
        $pdf->text($M, $yk, $baris, '#5b4a3f');
        $yk += 13;
    }
    $kv = [
        ['Tanggal pembelian', tgl($n['tanggal_pembelian'], true), '#2a1a14'],
        ['Status pembayaran', $n['status_pembayaran'], $warnaStatus],
        ['Sifat pembayaran', $n['nama_sifat_pembayaran'] ?: 'Belum dipilih', '#2a1a14'],
        ['Metode', $n['nama_metode_pembayaran'] ?: 'Belum dipilih', '#2a1a14'],
        ['Jenis / tujuan', $n['nama_jenis_pembayaran'] ?: 'Belum dipilih', '#2a1a14'],
    ];
    $yr = $y;
    foreach ($kv as [$k, $v, $warna]) {
        $pdf->font('', 9);
        $pdf->text(330, $yr, $k, '#8c7b6c');
        $pdf->font('B', 9.5);
        $pdf->text($W - $M, $yr, $v, $warna, 'R');
        $yr += 16;
    }
    $y = max($yk, $yr) + 14;

    // ---- barang per toko
    $kolomJumlah = $W - $M - 205;
    $kolomHarga = $W - $M - 112;
    $kolomSub = $W - $M - 8;
    $lebarNama = $kolomJumlah - ($M + 30) - 34;
    foreach ($n['toko'] as $t) {
        $pastikan(80);
        $pdf->rect($M, $y, $W - 2 * $M, 24, '#f0e5cf');
        $pdf->rect($M, $y, 3.5, 24, '#6b1a28');
        $pdf->font('B', 10);
        $pdf->text($M + 12, $y + 16, 'Toko: ' . $t['nama_penjual'], '#3f0d17');
        $pdf->font('', 8.5);
        $kirim = ($t['nama_opsi_pengiriman'] ?: 'Pengiriman') . ' · estimasi ' . hari_teks((int) $t['lama_pengiriman']);
        $pdf->text($W - $M - 8, $y + 16, $kirim, '#5b4a3f', 'R');
        $y += 24;
        $pdf->font('B', 7.5);
        foreach ([[$M + 8, 'NO', 'L'], [$M + 30, 'PRODUK', 'L'], [$kolomJumlah, 'JML', 'R'], [$kolomHarga, 'HARGA', 'R'], [$kolomSub, 'SUBTOTAL', 'R']] as [$x, $lab, $al]) {
            $pdf->text($x, $y + 14, $lab, '#8c7b6c', $al);
        }
        $pdf->line($M, $y + 20, $W - $M, $y + 20, '#e2d4b6');
        $y += 20;
        foreach ($t['items'] as $i => $it) {
            $baris = $pdf->wrap($it['nama_produk'], $lebarNama, '', 9.5);
            $tinggi = max(1, count($baris)) * 12.5 + 9;
            $pastikan($tinggi + 4);
            $pdf->font('', 9.5);
            $pdf->text($M + 8, $y + 15, (string) ($i + 1), '#8c7b6c');
            foreach ($baris as $k => $bar) {
                $pdf->text($M + 30, $y + 15 + $k * 12.5, $bar, '#2a1a14');
            }
            $pdf->text($kolomJumlah, $y + 15, (string) $it['qty'], '#2a1a14', 'R');
            $pdf->text($kolomHarga, $y + 15, rp($it['qty'] > 0 ? $it['sub_harga'] / $it['qty'] : 0), '#2a1a14', 'R');
            $pdf->font('B', 9.5);
            $pdf->text($kolomSub, $y + 15, rp($it['sub_harga']), '#2a1a14', 'R');
            $y += $tinggi;
            $pdf->line($M, $y, $W - $M, $y, '#f0e5cf', 0.5);
        }
        $pastikan(30);
        $pdf->font('', 9);
        $pdf->text($M + 30, $y + 17, 'Ongkos kirim (' . ($t['nama_jenis_pengiriman'] ?: '-') . ')', '#5b4a3f');
        $pdf->text($kolomSub, $y + 17, rp($t['ongkos_kirim']), '#2a1a14', 'R');
        $y += 30;
    }

    // ---- ringkasan biaya
    $baris = [['Subtotal barang', rp($b['sub']), false]];
    if ($b['tingkat'] > 0) {
        $baris[] = ['Diskon tingkat pembeli', '− ' . rp($b['tingkat']), true];
    }
    if ($b['koin_rp'] > 0) {
        $baris[] = ['Koin Songketin' . ' (' . (int) $n['jumlah_koin_dipakai'] . ' koin)', '− ' . rp($b['koin_rp']), true];
    }
    $baris[] = ['Ongkos kirim', rp($b['ongkir']), false];
    if ($b['diskon_ongkir'] > 0) {
        $baris[] = ['Diskon ongkir', '− ' . rp($b['diskon_ongkir']), true];
    }
    $pastikan(34 + count($baris) * 17 + 40);
    $kiri = $W - $M - 250;
    $yy = $y + 4;
    foreach ($baris as [$lab, $nilai, $minus]) {
        $pdf->font('', 9.5);
        $pdf->text($kiri, $yy + 12, $lab, '#5b4a3f');
        $pdf->text($W - $M, $yy + 12, $nilai, $minus ? '#1e5a3b' : '#2a1a14', 'R');
        $yy += 17;
    }
    $pdf->line($kiri, $yy + 3, $W - $M, $yy + 3, '#c8962e', 1);
    $pdf->rect($kiri - 8, $yy + 8, 258, 32, '#6b1a28', null, 0.6, 6);
    $pdf->font('B', 10.5);
    $pdf->text($kiri + 4, $yy + 28, 'TOTAL BAYAR', '#f3dfa6');
    $pdf->font('B', 14);
    $pdf->text($W - $M - 6, $yy + 29, rp($b['total']), '#ffffff', 'R');

    // ---- keterangan pembayaran (di kiri ringkasan)
    $ket = [];
    switch ($n['status_pembayaran']) {
        case BAYAR_BELUM:
            $ket = ['Menunggu pembayaran.', 'Buka halaman Pesanan Saya lalu pilih sifat, metode,', 'dan jenis pembayaran untuk menyelesaikan pesanan.'];
            break;
        case BAYAR_VERIF:
            $ket = ['Pembayaran diterima dan sedang diverifikasi admin.', ($n['nama_jenis_pembayaran'] ?: '') . ' · ' . ($n['rekening_jenis_pembayaran'] ?: '')];
            break;
        case BAYAR_COD:
            $ket = ['Bayar tunai kepada kurir saat barang tiba.', 'Siapkan uang pas sebesar total bayar.'];
            break;
        case BAYAR_LUNAS:
            $ket = ['Pembayaran lunas. Terima kasih!', 'Dibayar via ' . ($n['nama_metode_pembayaran'] ?: '-') . ' (' . ($n['nama_jenis_pembayaran'] ?: '-') . ').'];
            break;
        case BAYAR_BATAL:
            $ket = ['Pesanan dibatalkan.', 'Stok dan koin telah dikembalikan.'];
            break;
    }
    foreach ($n['toko'] as $tk) { // refund yang disetujui dicatat pada struk
        if (in_array($tk['id_status_pesanan'], SP_REFUND_BERHASIL, true) && ($rf = refund_ambil($tk['id_nota_penjual']))) {
            $ket[] = refund_teks_struk($rf);
        }
    }
    $pdf->font('B', 8);
    $pdf->text($M, $y + 16, 'KETERANGAN', '#8c7b6c');
    $pdf->font('', 9.5);
    $yt = $y + 32;
    foreach ($ket as $baris) { // dibungkus agar tidak menabrak kolom ringkasan biaya di kanan
        foreach ($pdf->wrap($baris, $kiri - $M - 24) as $b2) {
            $pdf->text($M, $yt, $b2, '#2a1a14');
            $yt += 13;
        }
    }
    $y = $yy + 52;
    $pastikan(40);
    $pdf->font('I', 9);
    $pdf->text($M, $y + 10, 'Terima kasih telah berbelanja di Songketin. Semoga kain tenunnya membawa bahagia.', '#8a2434');
    return $pdf;
}
