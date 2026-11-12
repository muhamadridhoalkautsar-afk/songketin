<?php
/**
 * Refund & banding refund.
 *
 * Pemetaan ke struktur database yang ada (tidak ada tabel/kolom baru):
 *   • STATUS refund tiap toko = rincian_pesanan.id_status_pesanan  (SP07 Refund Diajukan, SP08 Refund Disetujui, SP09 Refund Ditolak,
 *     SP10 Banding Diajukan, SP11 Banding Disetujui, SP12 Banding Ditolak — baris data pada tabel status_pesanan)
 *   • ALASAN, bukti, keputusan, dan riwayat = amplop JSON di nota_pembeli.catatan_pembayaran_admin (lihat includes/catatan.php)
 *   • FOTO pendukung = berkas di uploads/refund/, path-nya ada di dalam riwayat
 *
 * Alur:
 *   1. Pembeli mengajukan refund untuk satu toko pada pesanannya (alasan + tujuan dana + foto bila perlu)            → SP07
 *   2. Penjual toko itu memeriksa lalu MENYETUJUI atau MENOLAK dengan alasan                                       → SP08 / SP09
 *   3. Bila ditolak, pembeli boleh mengajukan BANDING ke admin (alasan + foto opsional)                           → SP10
 *   4. Admin memeriksa banding lalu MENYETUJUI atau MENOLAK dengan alasan; keputusan admin final                   → SP11 / SP12
 * Bila refund disetujui: jenis 'batal' (sebelum dikirim) membatalkan pesanan toko itu dan mengembalikan stok;
 * jenis 'barang' (setelah diterima, maksimal REFUND_BATAS_HARI hari) menandai penjualan toko itu direfund.
 * Koin dan tingkat pembeli/penjual dihitung ulang otomatis.
 */
require_once __DIR__ . '/bukti.php';
require_once __DIR__ . '/catatan.php';
require_once __DIR__ . '/koin.php';

const RJ_BATAL = 'batal', RJ_BARANG = 'barang';

// ---------------------------------------------------------------- aturan dasar
function refund_batas_hari(): int
{
    return max(1, (int) REFUND_BATAS_HARI);
}

function refund_alasan(string $jenis): array
{
    if ($jenis === RJ_BATAL) {
        return ['Salah memesan barang', 'Ingin mengganti produk, ukuran, atau warna', 'Alamat pengiriman salah', 'Berubah pikiran', 'Lainnya'];
    }
    return ['Barang rusak atau cacat', 'Tidak sesuai foto atau deskripsi', 'Warna atau motif berbeda', 'Barang tidak lengkap', 'Salah barang yang dikirim', 'Lainnya'];
}

function refund_jenis_teks(string $jenis): string
{
    return $jenis === RJ_BATAL ? 'Batal sebelum dikirim' : 'Barang sudah diterima';
}

/** [teks, jenis badge] untuk status refund (SP07–SP12). */
function refund_label(string $sp): array
{
    return [
        SP_RF_AJU => ['Refund Diajukan', 'warn'], SP_RF_SETUJU => ['Refund Disetujui', 'ok'], SP_RF_TOLAK => ['Refund Ditolak', 'bad'],
        SP_BD_AJU => ['Banding Diajukan', 'info'], SP_BD_SETUJU => ['Banding Disetujui', 'ok'], SP_BD_TOLAK => ['Banding Ditolak', 'bad'],
    ][$sp] ?? [$sp, 'muted'];
}

function refund_badge(string $sp): string
{
    [$teks, $jenis] = refund_label($sp);
    return badge($teks, $jenis);
}

/** Status refund sebuah toko (SP07–SP12) atau null bila tidak ada refund. $t = baris toko dari nota_lengkap(). */
function refund_status_toko(array $t): ?string
{
    return in_array($t['id_status_pesanan'] ?? '', SP_REFUND_SEMUA, true) ? $t['id_status_pesanan'] : null;
}

/** Pengiriman toko ditahan selama refund 'batal' belum diputuskan (toko belum diserahkan ke kurir). */
function refund_menahan_kirim(string $idNpj): bool
{
    return (bool) val('SELECT 1 FROM nota_penjual np JOIN rincian_pesanan rp ON rp.id_nota_penjual = np.id_nota_penjual
                        WHERE np.id_nota_penjual = ? AND np.id_status_pengiriman = ? AND rp.id_status_pesanan IN (?, ?) LIMIT 1',
        [$idNpj, SK_BELUM, SP_RF_AJU, SP_BD_AJU]);
}

/** Batas akhir (cap waktu) pengajuan refund barang: akhir hari ke-N sejak barang diterima. Null bila belum diterima. */
function refund_batas_akhir(array $t): ?int
{
    if (($t['id_status_pengiriman'] ?? '') !== SK_DITERIMA || empty($t['tanggal_sampai'])) {
        return null;
    }
    $d = new DateTimeImmutable(date('Y-m-d', strtotime((string) $t['tanggal_sampai'])));
    return $d->modify('+' . refund_batas_hari() . ' days')->setTime(23, 59, 59)->getTimestamp();
}

/** Nilai satu toko pada nota: barang + ongkos kirim (rupiah utuh). */
function refund_nilai_toko(array $t): int
{
    $s = (int) round((float) ($t['ongkos_kirim'] ?? 0));
    foreach ($t['items'] ?? [] as $it) {
        $s += (int) round((float) $it['sub_harga']);
    }
    return $s;
}

/** Dana maksimal yang bisa direfund untuk satu toko: bagian sebanding dari total yang dibayar pembeli (sudah memperhitungkan semua potongan). */
function refund_bagian_toko(array $n, array $t): int
{
    $bayar = (int) round((float) $n['total_harga_beli']);
    $semua = 0;
    foreach ($n['toko'] as $x) {
        $semua += refund_nilai_toko($x);
    }
    $g = refund_nilai_toko($t);
    return ($semua <= 0 || $g >= $semua) ? $bayar : intdiv($bayar * $g, $semua);
}

/** [boleh, pesan, jenis] — bisakah pembeli mengajukan refund untuk toko $t pada nota $n? */
function refund_cek_boleh(array $n, array $t): array
{
    $sp = refund_status_toko($t);
    if ($sp !== null) {
        return [false, 'Toko ini sudah punya pengajuan refund (' . refund_label($sp)[0] . ').', null];
    }
    $sk = $t['id_status_pengiriman'];
    if ($sk === SK_BATAL) {
        return [false, 'Pesanan toko ini sudah dibatalkan.', null];
    }
    $lunas = $n['status_pembayaran'] === BAYAR_LUNAS;
    if ($sk === SK_BELUM) {
        if (!$lunas) {
            return [false, 'Pesanan yang belum dibayar atau bayar di tempat cukup dibatalkan lewat tombol “Batalkan pesanan”.', null];
        }
        return [true, '', RJ_BATAL];
    }
    if ($sk === SK_DITERIMA) {
        $batas = refund_batas_akhir($t);
        if ($batas === null || time() > $batas) {
            return [false, 'Batas pengajuan refund (' . refund_batas_hari() . ' hari sejak barang diterima) sudah lewat.', null];
        }
        if (!$lunas && $n['status_pembayaran'] !== BAYAR_COD) {
            return [false, 'Pembayaran pesanan ini belum lunas.', null];
        }
        return [true, '', RJ_BARANG];
    }
    return [false, 'Refund bisa diajukan sebelum pesanan diserahkan ke kurir, atau setelah barang diterima.', null];
}

// ---------------------------------------------------------------- membaca
/** Peristiwa terakhir bertipe $aksi pada riwayat (atau null). */
function refund_peristiwa_terakhir(array $riwayat, string $aksi): ?array
{
    for ($i = count($riwayat) - 1; $i >= 0; $i--) {
        if (($riwayat[$i]['aksi'] ?? '') === $aksi) {
            return $riwayat[$i];
        }
    }
    return null;
}

/** Data pengajuan terbaru sebuah toko: ['jenis','alasan','keterangan','tujuan','jumlah','bukti', 'banding'=>…, 'riwayat'=>[…]] atau null. */
function refund_data(string $idNota, string $idNpj): ?array
{
    $r = catatan_riwayat_refund($idNota, $idNpj);
    $aju = refund_peristiwa_terakhir($r, 'ajukan');
    if (!$aju) {
        return null;
    }
    return $aju + ['banding' => refund_peristiwa_terakhir($r, 'banding'), 'riwayat' => $r];
}

const REFUND_SELECT = 'SELECT np.id_nota_penjual, np.id_nota_pembeli, np.total_harga_jual, np.id_status_pengiriman, np.tanggal_sampai,
        nb.id_pembeli, nb.total_harga_beli, nb.tanggal_pembelian, nb.catatan_pembayaran_admin, pb.nama_pembeli, pb.id_user,
        (SELECT MIN(rp.id_status_pesanan) FROM rincian_pesanan rp WHERE rp.id_nota_penjual = np.id_nota_penjual) AS id_status_pesanan,
        (SELECT p.id_penjual FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual ORDER BY p.id_produk LIMIT 1) AS id_penjual,
        (SELECT pj.nama_penjual FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk JOIN penjual pj ON pj.id_penjual = p.id_penjual
          WHERE rp.id_nota_penjual = np.id_nota_penjual ORDER BY p.id_produk LIMIT 1) AS nama_toko
   FROM nota_penjual np
   JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
   JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli';

/** Satu refund (per toko) lengkap dengan riwayatnya. */
function refund_ambil(string $idNpj): ?array
{
    $r = $idNpj === '' ? null : row(REFUND_SELECT . ' WHERE np.id_nota_penjual = ?', [$idNpj]);
    return $r ? refund_lengkapi($r) : null;
}

function refund_lengkapi(array $r): ?array
{
    if (!in_array($r['id_status_pesanan'], SP_REFUND_SEMUA, true)) {
        return null;
    }
    $c = catatan_urai($r['catatan_pembayaran_admin']);
    $riwayat = $c['refund'][$r['id_nota_penjual']] ?? [];
    $aju = refund_peristiwa_terakhir($riwayat, 'ajukan') ?? [];
    $r['riwayat'] = $riwayat;
    $r['jenis'] = $aju['jenis'] ?? RJ_BARANG;
    $r['alasan'] = $aju['alasan'] ?? '';
    $r['keterangan'] = $aju['keterangan'] ?? '';
    $r['tujuan'] = $aju['tujuan'] ?? '';
    $r['jumlah'] = (int) ($aju['jumlah'] ?? 0);
    $r['bukti'] = $aju['bukti'] ?? null;
    $r['tanggal_ajuan'] = $aju['t'] ?? null;
    $r['banding'] = refund_peristiwa_terakhir($riwayat, 'banding');
    unset($r['catatan_pembayaran_admin']);
    return $r;
}

/**
 * Daftar refund. $filter: 'penjual' => id_penjual, 'status' => [SP…], 'pembeli' => id_pembeli.
 * Hanya toko yang berstatus refund yang dikembalikan; terbaru lebih dulu.
 */
function refund_daftar(array $filter = []): array
{
    $status = $filter['status'] ?? SP_REFUND_SEMUA;
    $sql = REFUND_SELECT . ' WHERE EXISTS (SELECT 1 FROM rincian_pesanan rx WHERE rx.id_nota_penjual = np.id_nota_penjual AND rx.id_status_pesanan IN (' . in_marks($status) . '))';
    $par = array_values($status);
    if (!empty($filter['penjual'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM rincian_pesanan ry JOIN produk py ON py.id_produk = ry.id_produk WHERE ry.id_nota_penjual = np.id_nota_penjual AND py.id_penjual = ?)';
        $par[] = $filter['penjual'];
    }
    if (!empty($filter['pembeli'])) {
        $sql .= ' AND nb.id_pembeli = ?';
        $par[] = $filter['pembeli'];
    }
    $sql .= ' ORDER BY nb.tanggal_pembelian DESC, np.id_nota_penjual DESC';
    $hasil = [];
    foreach (rows($sql, $par) as $r) {
        if ($x = refund_lengkapi($r)) {
            $hasil[] = $x;
        }
    }
    return $hasil;
}

function refund_jumlah_menunggu(string $status, ?string $idPenjual = null): int
{
    $sql = 'SELECT COUNT(DISTINCT rp.id_nota_penjual) FROM rincian_pesanan rp WHERE rp.id_status_pesanan = ?';
    $par = [$status];
    if ($idPenjual) {
        $sql .= ' AND EXISTS (SELECT 1 FROM produk p WHERE p.id_produk = rp.id_produk AND p.id_penjual = ?)';
        $par[] = $idPenjual;
    }
    return (int) val($sql, $par);
}

// ---------------------------------------------------------------- validasi pengajuan
/** [galat[], data] untuk pengajuan refund atau banding. $jenis null untuk banding. */
function refund_validasi(?string $jenis, array $in, ?array $file): array
{
    $g = [];
    $d = ['alasan' => trim((string) ($in['alasan'] ?? '')), 'keterangan' => trim((string) ($in['keterangan'] ?? '')), 'tujuan' => trim((string) ($in['tujuan'] ?? ''))];
    if ($jenis !== null) {   // pengajuan baru
        if (!in_array($d['alasan'], refund_alasan($jenis), true)) {
            $g[] = 'Pilih alasan refund dari daftar.';
        }
        if (mb_strlen($d['keterangan']) > 500) {
            $g[] = 'Keterangan maksimal 500 karakter.';
        }
        if ($d['alasan'] === 'Lainnya' && mb_strlen($d['keterangan']) < 5) {
            $g[] = 'Untuk alasan “Lainnya”, jelaskan alasan Anda pada keterangan (minimal 5 karakter).';
        }
        if (mb_strlen($d['tujuan']) < 5 || mb_strlen($d['tujuan']) > 200) {
            $g[] = 'Tujuan pengembalian dana (bank/e-wallet, nomor, atas nama) harus 5–200 karakter.';
        }
        if ($jenis === RJ_BARANG && !foto_ada_unggahan($file)) {
            $g[] = 'Unggah foto barang sebagai bukti untuk refund barang yang sudah diterima.';
        }
    } else {                 // banding
        if (mb_strlen($d['alasan']) < 10 || mb_strlen($d['alasan']) > 500) {
            $g[] = 'Alasan banding harus 10–500 karakter.';
        }
    }
    if ($e = foto_cek($file, false, 100)) {
        $g[] = $e;
    }
    return [$g, $d];
}

function refund_validasi_alasan_putusan(string $alasan): ?string
{
    $alasan = trim($alasan);
    if (mb_strlen($alasan) < 5) {
        return 'Tuliskan alasan keputusan (minimal 5 karakter) agar jelas bagi pembeli.';
    }
    if (mb_strlen($alasan) > 500) {
        return 'Alasan keputusan maksimal 500 karakter.';
    }
    return null;
}

// ---------------------------------------------------------------- tindakan
function refund_set_status_toko(string $idNpj, string $sp): void
{
    q('UPDATE rincian_pesanan SET id_status_pesanan = ? WHERE id_nota_penjual = ?', [$sp, $idNpj]);
}

/** Toko dari nota_lengkap() menurut id_nota_penjual, atau null. */
function refund_toko(array $n, string $idNpj): ?array
{
    foreach ($n['toko'] as $t) {
        if ($t['id_nota_penjual'] === $idNpj) {
            return $t;
        }
    }
    return null;
}

/** Pembeli mengajukan refund untuk satu toko. Mengembalikan [ok, pesan]. */
function refund_ajukan(string $idNota, string $idNpj, string $idPembeli, array $in, ?array $file): array
{
    $n = nota_lengkap($idNota);
    $t = $n ? refund_toko($n, $idNpj) : null;
    if (!$n || !$t || $n['id_pembeli'] !== $idPembeli) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    [$boleh, $pesan, $jenis] = refund_cek_boleh($n, $t);
    if (!$boleh) {
        return [false, $pesan];
    }
    [$galat, $d] = refund_validasi($jenis, $in, $file);
    if ($galat) {
        return [false, implode(' ', $galat)];
    }
    $path = null;
    if (foto_ada_unggahan($file)) {
        $path = foto_simpan($file, 'refund', $idNpj);
        if ($path === null) {
            return [false, 'Foto gagal disimpan. Coba unggah lagi.'];
        }
    }
    $jumlah = refund_bagian_toko($n, $t);
    try {
        transaksi(function () use ($idNota, $idNpj, $jenis, $d, $path, $jumlah, $t) {
            $sebelum = (string) val('SELECT MIN(id_status_pesanan) FROM rincian_pesanan WHERE id_nota_penjual = ? FOR UPDATE', [$idNpj]);
            if (in_array($sebelum, SP_REFUND_SEMUA, true)) {
                throw new RuntimeException('Pengajuan refund untuk toko ini sudah ada.');
            }
            refund_set_status_toko($idNpj, SP_RF_AJU);
            catatan_tambah_peristiwa($idNota, $idNpj, ['aksi' => 'ajukan', 'oleh' => 'pembeli', 'jenis' => $jenis, 'alasan' => $d['alasan'],
                'keterangan' => $d['keterangan'], 'tujuan' => $d['tujuan'], 'jumlah' => $jumlah, 'bukti' => $path, 'sebelum' => $sebelum]);
        });
    } catch (RuntimeException $e) {
        foto_hapus($path);
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        foto_hapus($path);
        throw $e;
    }
    return [true, 'Pengajuan refund terkirim ke penjual (' . $t['nama_penjual'] . '). Pantau keputusannya di halaman ini.'];
}

/** Pembeli menarik pengajuan selama penjual belum memutuskan. */
function refund_tarik(string $idNota, string $idNpj, string $idPembeli): array
{
    $n = nota_lengkap($idNota);
    $t = $n ? refund_toko($n, $idNpj) : null;
    if (!$n || !$t || $n['id_pembeli'] !== $idPembeli) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if (refund_status_toko($t) !== SP_RF_AJU) {
        return [false, 'Pengajuan hanya bisa ditarik selama masih menunggu keputusan penjual.'];
    }
    transaksi(function () use ($idNota, $idNpj) {
        $aju = refund_peristiwa_terakhir(catatan_riwayat_refund($idNota, $idNpj), 'ajukan');
        $kembali = $aju['sebelum'] ?? SP_DIPROSES;
        refund_set_status_toko($idNpj, in_array($kembali, SP_REFUND_SEMUA, true) ? SP_DIPROSES : $kembali);
        catatan_tambah_peristiwa($idNota, $idNpj, ['aksi' => 'tarik', 'oleh' => 'pembeli', 'teks' => 'Pembeli menarik pengajuan refund.']);
    });
    return [true, 'Pengajuan refund ditarik.'];
}

/** Penjual menyetujui/menolak refund tokonya. */
function refund_putus_penjual(string $idNpj, string $idPenjual, bool $setuju, string $alasan): array
{
    if ($e = refund_validasi_alasan_putusan($alasan)) {
        return [false, $e];
    }
    $r = refund_ambil($idNpj);
    if (!$r || $r['id_penjual'] !== $idPenjual) {
        return [false, 'Pengajuan refund tidak ditemukan.'];
    }
    if ($r['id_status_pesanan'] !== SP_RF_AJU) {
        return [false, 'Pengajuan ini sudah diputuskan atau tidak lagi menunggu penjual.'];
    }
    $alasan = trim($alasan);
    transaksi(function () use ($r, $idNpj, $setuju, $alasan) {
        $ok = $setuju ? refund_jalankan($r, SP_RF_SETUJU) : refund_set_status_toko($idNpj, SP_RF_TOLAK);
        catatan_tambah_peristiwa($r['id_nota_pembeli'], $idNpj, ['aksi' => $setuju ? 'setuju' : 'tolak', 'oleh' => 'penjual', 'nama' => $r['nama_toko'], 'teks' => $alasan]);
    });
    refund_setelah($r);
    return [true, $setuju ? 'Refund disetujui. Pesanan dan stok diperbarui.' : 'Refund ditolak. Pembeli dapat mengajukan banding ke admin.'];
}

/** Pembeli mengajukan banding atas refund yang ditolak penjual. */
function refund_banding(string $idNota, string $idNpj, string $idPembeli, array $in, ?array $file): array
{
    $r = refund_ambil($idNpj);
    if (!$r || $r['id_nota_pembeli'] !== $idNota || $r['id_pembeli'] !== $idPembeli) {
        return [false, 'Pengajuan refund tidak ditemukan.'];
    }
    if ($r['id_status_pesanan'] !== SP_RF_TOLAK) {
        return [false, 'Banding hanya bisa diajukan setelah refund ditolak penjual, dan hanya satu kali.'];
    }
    [$galat, $d] = refund_validasi(null, $in, $file);
    if ($galat) {
        return [false, implode(' ', $galat)];
    }
    $path = null;
    if (foto_ada_unggahan($file)) {
        $path = foto_simpan($file, 'refund', $idNpj);
        if ($path === null) {
            return [false, 'Foto gagal disimpan. Coba unggah lagi.'];
        }
    }
    try {
        transaksi(function () use ($idNota, $idNpj, $d, $path) {
            $sp = (string) val('SELECT MIN(id_status_pesanan) FROM rincian_pesanan WHERE id_nota_penjual = ? FOR UPDATE', [$idNpj]);
            if ($sp !== SP_RF_TOLAK) {
                throw new RuntimeException('Banding sudah diajukan.');
            }
            refund_set_status_toko($idNpj, SP_BD_AJU);
            catatan_tambah_peristiwa($idNota, $idNpj, ['aksi' => 'banding', 'oleh' => 'pembeli', 'teks' => $d['alasan'], 'bukti' => $path]);
        });
    } catch (RuntimeException $e) {
        foto_hapus($path);
        return [false, $e->getMessage()];
    } catch (Throwable $e) {
        foto_hapus($path);
        throw $e;
    }
    return [true, 'Banding terkirim. Admin akan memeriksa dan keputusannya bersifat final.'];
}

/** Admin memutuskan banding (final). */
function refund_putus_admin(string $idNpj, bool $setuju, string $alasan, string $namaAdmin = 'Admin'): array
{
    if ($e = refund_validasi_alasan_putusan($alasan)) {
        return [false, $e];
    }
    $r = refund_ambil($idNpj);
    if (!$r) {
        return [false, 'Pengajuan banding tidak ditemukan.'];
    }
    if ($r['id_status_pesanan'] !== SP_BD_AJU) {
        return [false, 'Hanya banding yang berstatus “Banding Diajukan” yang bisa diputuskan.'];
    }
    $alasan = trim($alasan);
    transaksi(function () use ($r, $idNpj, $setuju, $alasan, $namaAdmin) {
        if ($setuju) {
            refund_jalankan($r, SP_BD_SETUJU);
        } else {
            refund_set_status_toko($idNpj, SP_BD_TOLAK);
        }
        catatan_tambah_peristiwa($r['id_nota_pembeli'], $idNpj, ['aksi' => $setuju ? 'putus_setuju' : 'putus_tolak', 'oleh' => 'admin', 'nama' => $namaAdmin, 'teks' => $alasan]);
    });
    refund_setelah($r);
    return [true, $setuju ? 'Banding disetujui: refund dijalankan dan keputusan ini final.' : 'Banding ditolak. Keputusan ini final.'];
}

/** Menjalankan refund yang disetujui (dalam transaksi): batalkan toko bila jenis 'batal', kembalikan stok, set status akhir. */
function refund_jalankan(array $r, string $statusAkhir): void
{
    if ($r['jenis'] === RJ_BATAL) {
        foreach (rows('SELECT id_produk, qty FROM rincian_pesanan WHERE id_nota_penjual = ?', [$r['id_nota_penjual']]) as $it) {
            q('UPDATE produk SET stok = stok + ? WHERE id_produk = ?', [$it['qty'], $it['id_produk']]);
        }
        q('UPDATE nota_penjual SET id_status_pengiriman = ? WHERE id_nota_penjual = ?', [SK_BATAL, $r['id_nota_penjual']]);
    }
    refund_set_status_toko($r['id_nota_penjual'], $statusAkhir);
}

/** Setelah refund diputuskan: hitung ulang koin & tingkat pembeli dan tingkat penjual. Dipanggil di luar transaksi. */
function refund_setelah(array $r): void
{
    koin_sinkron($r['id_pembeli']);
    if (!empty($r['id_penjual'])) {
        perbarui_tingkat_penjual($r['id_penjual']);
    }
}

// ---------------------------------------------------------------- tampilan
const PERAN_EVENT = ['pembeli' => 'Pembeli', 'penjual' => 'Penjual', 'admin' => 'Admin'];
const AKSI_EVENT = [
    'ajukan' => 'mengajukan refund', 'tarik' => 'menarik pengajuan', 'setuju' => 'menyetujui refund', 'tolak' => 'menolak refund',
    'banding' => 'mengajukan banding', 'putus_setuju' => 'menyetujui banding', 'putus_tolak' => 'menolak banding',
];

/** Linimasa riwayat refund. $untukApi: id yang dipakai di api/bukti.php untuk foto (id_nota_penjual). */
function refund_riwayat_html(array $riwayat, string $idNpj): string
{
    if (!$riwayat) {
        return '';
    }
    $o = '<ol class="riwayat-refund">';
    foreach ($riwayat as $i => $ev) {
        $aksi = AKSI_EVENT[$ev['aksi'] ?? ''] ?? ($ev['aksi'] ?? '');
        $oleh = PERAN_EVENT[$ev['oleh'] ?? ''] ?? '';
        $nama = !empty($ev['nama']) ? ' (' . e($ev['nama']) . ')' : '';
        $o .= '<li class="r-' . e($ev['oleh'] ?? '') . '"><div class="r-kepala"><b>' . e($oleh) . $nama . '</b> ' . e($aksi) . ' <small>' . e(tgl($ev['t'] ?? null, true)) . '</small></div>';
        if (($ev['aksi'] ?? '') === 'ajukan') {
            $o .= '<div class="r-isi"><b>Alasan:</b> ' . e($ev['alasan'] ?? '') . (!empty($ev['keterangan']) ? '<br>' . nl2br(e($ev['keterangan'])) : '')
                . '<br><small>' . e(refund_jenis_teks($ev['jenis'] ?? RJ_BARANG)) . ' · nominal maksimal ' . rp($ev['jumlah'] ?? 0) . ' · tujuan dana: ' . e($ev['tujuan'] ?? '') . '</small></div>';
        } elseif (!empty($ev['teks'])) {
            $o .= '<div class="r-isi">' . nl2br(e($ev['teks'])) . '</div>';
        }
        if (!empty($ev['bukti']) && foto_path_valid($ev['bukti'], 'refund')) {
            $u = url('api/bukti.php?jenis=refund&id=' . rawurlencode($idNpj) . '&n=' . (int) $i);
            $o .= '<a class="r-foto" href="' . e($u) . '" target="_blank" rel="noopener" data-tanpa-loader><img src="' . e($u) . '" alt="Foto pendukung" loading="lazy"></a>';
        }
        $o .= '</li>';
    }
    return $o . '</ol>';
}

/** Teks ringkas untuk struk: refund yang disetujui pada nota. */
function refund_teks_struk(array $r): string
{
    return 'Refund ' . rp($r['jumlah']) . ' untuk toko ' . $r['nama_toko'] . ' disetujui (' . refund_jenis_teks($r['jenis']) . ').';
}
