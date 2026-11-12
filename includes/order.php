<?php
/** Pesanan: membuat, membayar, membatalkan, mengubah status; tingkat; pengambilan data nota. */
require_once __DIR__ . '/bukti.php';
require_once __DIR__ . '/koin.php';
require_once __DIR__ . '/refund.php';

// ---------------------------------------------------------------- membuat pesanan
/**
 * Buat pesanan dari keranjang. $hit = hasil hitung_checkout(); $idJenisBayar = jenis pembayaran yang dipilih di checkout
 * (kolom nota_pembeli.id_jenis_pembayaran wajib terisi). Pembayaran Tunai (COD) langsung dikonfirmasi dan diproses.
 */
function buat_pesanan(array $kr, array $pembeli, array $hit, string $idJenisBayar, bool $tunai): string
{
    return transaksi(function () use ($kr, $pembeli, $hit, $idJenisBayar, $tunai) {
        $idNota = next_id('nota_pembeli', 'id_nota_pembeli', 'NPB');
        foreach ($kr['grup'] as $g) {
            foreach ($g['items'] as $it) { // stok dikurangi atomik: gagal bila stok sudah diambil pembeli lain
                $st = q('UPDATE produk SET stok = stok - ? WHERE id_produk = ? AND stok >= ?', [$it['qty'], $it['id_produk'], $it['qty']]);
                if ($st->rowCount() !== 1) {
                    throw new RuntimeException('Stok “' . $it['nama_produk'] . '” tidak mencukupi lagi. Perbarui keranjang Anda.');
                }
            }
        }
        if ($hit['koin'] > 0 && $hit['koin'] > koin_saldo($pembeli['id_pembeli'])) {
            throw new RuntimeException('Saldo koin tidak mencukupi.');
        }
        $idKoin = koin_buat();
        q('INSERT INTO nota_pembeli (id_nota_pembeli, id_pembeli, id_diskon_ongkir, id_koin, tanggal_pembelian, total_harga_beli, total_barang_beli,
                jumlah_koin_dipakai, total_potongan, id_jenis_pembayaran, status_pembayaran, foto_bukti_pembayaran, catatan_pembayaran_admin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$idNota, $pembeli['id_pembeli'], $hit['id_diskon_ongkir'], $idKoin, date('Y-m-d H:i:s'), $hit['total'], $hit['qty'],
                $hit['koin'], $hit['total_potongan'], $idJenisBayar, $tunai ? DB_MENUNGGU : DB_BELUM, '', null]);
        foreach ($hit['sellers'] as $s) {
            $idKrm = next_id('pengiriman', 'id_pengiriman', 'KRM');
            q('INSERT INTO pengiriman (id_pengiriman, id_opsi_pengiriman, ongkos_kirim, kota_asal, kota_tujuan, lama_pengiriman, tanggal_pengiriman) VALUES (?,?,?,?,?,?,?)',
                [$idKrm, $hit['opsi']['id_opsi_pengiriman'], $s['ongkir'], $s['kota_asal'], $s['kota_tujuan'], $s['hari'], date('Y-m-d')]);
            $idNpj = next_id('nota_penjual', 'id_nota_penjual', 'NPJ');
            q('INSERT INTO nota_penjual (id_nota_penjual, id_nota_pembeli, id_pengiriman, id_status_pengiriman, total_harga_jual, total_barang_jual, tanggal_sampai) VALUES (?,?,?,?,?,?,?)',
                [$idNpj, $idNota, $idKrm, SK_BELUM, $s['jual'], $s['qty'], null]);
            foreach ($s['items'] as $it) {
                q('INSERT INTO rincian_pesanan (id_nota_penjual, id_produk, id_status_pesanan, qty, sub_harga) VALUES (?,?,?,?,?)',
                    [$idNpj, $it['id_produk'], $tunai ? SP_DIPROSES : SP_MENUNGGU, $it['qty'], $it['subtotal']]);
            }
        }
        return $idNota;
    });
}

// ---------------------------------------------------------------- membaca nota
/** Nota pembeli lengkap: pembeli, pembayaran, dan setiap toko (pengiriman + barang). status_pembayaran = label turunan (BAYAR_*). */
function nota_lengkap(string $id): ?array
{
    $n = row('SELECT nb.*, pb.nama_pembeli, pb.id_desa, pb.detail_alamat, pb.id_user,
                     jp.nama_jenis_pembayaran, jp.rekening_jenis_pembayaran, jp.foto_qr, mp.id_metode_pembayaran, mp.nama_metode_pembayaran,
                     sp.id_sifat_pembayaran, sp.nama_sifat_pembayaran
                FROM nota_pembeli nb
                JOIN pembeli pb ON pb.id_pembeli = nb.id_pembeli
                LEFT JOIN jenis_pembayaran jp ON jp.id_jenis_pembayaran = nb.id_jenis_pembayaran
                LEFT JOIN metode_pembayaran mp ON mp.id_metode_pembayaran = jp.id_metode_pembayaran
                LEFT JOIN sifat_pembayaran sp ON sp.id_sifat_pembayaran = mp.id_sifat_pembayaran
               WHERE nb.id_nota_pembeli = ?', [$id]);
    if (!$n) {
        return null;
    }
    $n['alamat'] = alamat_teks($n['detail_alamat'], $n['id_desa']);
    $n['toko'] = rows('SELECT np.*, pg.id_opsi_pengiriman, pg.ongkos_kirim, pg.kota_asal, pg.kota_tujuan, pg.lama_pengiriman, pg.tanggal_pengiriman,
                              op.nama_opsi_pengiriman, jk.nama_jenis_pengiriman, sk.keterangan_status_pengiriman
                         FROM nota_penjual np
                         LEFT JOIN pengiriman pg ON pg.id_pengiriman = np.id_pengiriman
                         LEFT JOIN opsi_pengiriman op ON op.id_opsi_pengiriman = pg.id_opsi_pengiriman
                         LEFT JOIN jenis_pengiriman jk ON jk.id_jenis_pengiriman = op.id_jenis_pengiriman
                         LEFT JOIN status_pengiriman sk ON sk.id_status_pengiriman = np.id_status_pengiriman
                        WHERE np.id_nota_pembeli = ? ORDER BY np.id_nota_penjual', [$id]);
    $semuaBatal = count($n['toko']) > 0;
    foreach ($n['toko'] as &$t) {
        $t['items'] = rows('SELECT rp.id_produk, rp.id_status_pesanan, rp.qty, rp.sub_harga, p.nama_produk, p.foto_produk, p.id_penjual, pj.nama_penjual, st.keterangan_status_pesanan
                              FROM rincian_pesanan rp
                              JOIN produk p ON p.id_produk = rp.id_produk
                              JOIN penjual pj ON pj.id_penjual = p.id_penjual
                              LEFT JOIN status_pesanan st ON st.id_status_pesanan = rp.id_status_pesanan
                             WHERE rp.id_nota_penjual = ? ORDER BY p.nama_produk', [$t['id_nota_penjual']]);
        $t['id_penjual'] = $t['items'][0]['id_penjual'] ?? null;
        $t['nama_penjual'] = $t['items'][0]['nama_penjual'] ?? '-';
        $t['id_status_pesanan'] = $t['items'][0]['id_status_pesanan'] ?? SP_MENUNGGU;
        $t['status_pesanan'] = $t['items'][0]['keterangan_status_pesanan'] ?? '';
        if (($t['id_status_pengiriman'] ?? '') !== SK_BATAL) {
            $semuaBatal = false;
        }
    }
    unset($t);
    $c = catatan_urai($n['catatan_pembayaran_admin']);
    $n['catatan_bayar'] = $c['bayar'];
    unset($n['catatan_pembayaran_admin']);
    $n['status_bayar_db'] = $n['status_pembayaran'];
    $n['tunai'] = $n['id_jenis_pembayaran'] && sifat_tunai((string) $n['id_sifat_pembayaran'], (string) $n['nama_sifat_pembayaran']);
    $n['ditolak'] = $n['status_bayar_db'] === DB_DITOLAK;
    $n['dibatalkan'] = $semuaBatal;
    if ($semuaBatal) {
        $n['status_pembayaran'] = BAYAR_BATAL;
    } elseif ($n['status_bayar_db'] === DB_SUKSES) {
        $n['status_pembayaran'] = BAYAR_LUNAS;
    } elseif ($n['status_bayar_db'] === DB_DITOLAK) {
        $n['status_pembayaran'] = BAYAR_DITOLAK;
    } elseif ($n['status_bayar_db'] === DB_MENUNGGU) {
        $n['status_pembayaran'] = $n['tunai'] ? BAYAR_COD : BAYAR_VERIF;
    } else {
        $n['status_pembayaran'] = BAYAR_BELUM;
    }
    $n['catatan_tolak'] = $n['ditolak'] ? $n['catatan_bayar'] : '';
    return $n;
}

/** Rincian biaya yang diturunkan dari angka tersimpan pada nota. */
function rincian_biaya(array $n): array
{
    $sub = $ongkir = 0;
    foreach ($n['toko'] as $t) {
        $ongkir += (float) $t['ongkos_kirim'];
        foreach ($t['items'] as $it) {
            $sub += (float) $it['sub_harga'];
        }
    }
    $koinRp = (int) $n['jumlah_koin_dipakai'] * NILAI_KOIN;
    $dOng = 0;
    if ($n['id_diskon_ongkir']) {
        $do = row('SELECT besaran_potongan_ongkir FROM diskon_ongkir WHERE id_diskon_ongkir = ?', [$n['id_diskon_ongkir']]);
        $dOng = $do ? min((float) $do['besaran_potongan_ongkir'], $ongkir) : 0;
    }
    $tingkat = max(0, (float) $n['total_potongan'] - $koinRp - $dOng);
    return ['sub' => $sub, 'tingkat' => $tingkat, 'koin_rp' => $koinRp, 'ongkir' => $ongkir,
        'diskon_ongkir' => $dOng, 'total' => (float) $n['total_harga_beli']];
}

function sudah_dibayar(array $n): bool
{
    return in_array($n['status_pembayaran'], [BAYAR_LUNAS, BAYAR_COD], true);
}

/** Bisa dibatalkan langsung (tanpa refund) selama belum dibayar online dan belum ada paket yang diserahkan ke kurir. */
function bisa_dibatalkan(array $n): bool
{
    if ($n['status_pembayaran'] === BAYAR_BATAL) {
        return false;
    }
    foreach ($n['toko'] as $t) {
        if ($t['id_status_pengiriman'] !== SK_BELUM || refund_status_toko($t) !== null) {
            return false;
        }
    }
    return true;
}

// ---------------------------------------------------------------- pembayaran
/** Sifat pembayaran tunai (bayar ke kurir): SF02 atau yang bernama "Tunai" — tetap berlaku walau namanya diubah admin. */
function sifat_tunai(string $idSifat, string $nama): bool
{
    return $idSifat === 'SF02' || mb_strtolower(trim($nama)) === 'tunai';
}

/** Semua jenis pembayaran beserta metode & sifatnya, urut sifat → metode → jenis (untuk daftar pilihan di checkout/bayar). */
function jenis_bayar_semua(): array
{
    $r = rows('SELECT jp.*, mp.nama_metode_pembayaran, mp.id_sifat_pembayaran, sp.nama_sifat_pembayaran
                 FROM jenis_pembayaran jp
                 JOIN metode_pembayaran mp ON mp.id_metode_pembayaran = jp.id_metode_pembayaran
                 JOIN sifat_pembayaran sp ON sp.id_sifat_pembayaran = mp.id_sifat_pembayaran
                ORDER BY sp.id_sifat_pembayaran, mp.id_metode_pembayaran, jp.nama_jenis_pembayaran');
    foreach ($r as &$j) {
        $j['tunai'] = sifat_tunai((string) $j['id_sifat_pembayaran'], (string) $j['nama_sifat_pembayaran']);
    }
    return $r;
}

/** Satu jenis pembayaran dengan metode & sifatnya; null bila tidak ada. */
function jenis_bayar_info(string $idJenis): ?array
{
    foreach (jenis_bayar_semua() as $j) {
        if ($j['id_jenis_pembayaran'] === $idJenis) {
            return $j;
        }
    }
    return null;
}

/** Rantai sifat → metode → jenis yang sah? Mengembalikan baris jenis (dengan sifat) atau null. */
function jenis_bayar_valid(string $idSifat, string $idMetode, string $idJenis): ?array
{
    if ($idSifat === '' || $idMetode === '' || $idJenis === '') {
        return null;
    }
    $jp = row('SELECT jp.id_jenis_pembayaran, jp.id_metode_pembayaran, mp.id_sifat_pembayaran, sp.nama_sifat_pembayaran
                 FROM jenis_pembayaran jp
                 JOIN metode_pembayaran mp ON mp.id_metode_pembayaran = jp.id_metode_pembayaran
                 JOIN sifat_pembayaran sp ON sp.id_sifat_pembayaran = mp.id_sifat_pembayaran
                WHERE jp.id_jenis_pembayaran = ?', [$idJenis]);
    if (!$jp || $jp['id_metode_pembayaran'] !== $idMetode || $jp['id_sifat_pembayaran'] !== $idSifat) {
        return null;
    }
    $jp['tunai'] = sifat_tunai($jp['id_sifat_pembayaran'], $jp['nama_sifat_pembayaran']);
    return $jp;
}

/**
 * Pembeli memilih sifat → metode → jenis pembayaran untuk pesanan yang belum dibayar (atau yang pembayarannya ditolak).
 * Non-tunai: bukti pembayaran harus sudah tersimpan oleh pemanggil; status menjadi 'menunggu' (verifikasi admin).
 * Tunai (COD): status 'menunggu' dan pesanan langsung diproses.
 */
function bayar_nota(string $idNota, string $idSifat, string $idMetode, string $idJenis): array
{
    $n = nota_lengkap($idNota);
    if (!$n) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if (!in_array($n['status_pembayaran'], [BAYAR_BELUM, BAYAR_DITOLAK], true)) {
        return [false, 'Pesanan ini sudah dibayar atau dibatalkan.'];
    }
    $jp = jenis_bayar_valid($idSifat, $idMetode, $idJenis);
    if (!$jp) {
        return [false, 'Pilih sifat pembayaran, metode, lalu jenis pembayaran secara berurutan.'];
    }
    $tunai = $jp['tunai'];
    transaksi(function () use ($idNota, $idJenis, $tunai) {
        q('UPDATE nota_pembeli SET id_jenis_pembayaran = ?, status_pembayaran = ? WHERE id_nota_pembeli = ?', [$idJenis, DB_MENUNGGU, $idNota]);
        catatan_set_bayar($idNota, '');   // pembayaran baru: catatan penolakan lama tidak berlaku lagi
        if ($tunai) {
            set_rincian_semua($idNota, SP_DIPROSES, SP_MENUNGGU);
        }
    });
    return [true, $tunai ? 'Pesanan dikonfirmasi. Siapkan pembayaran tunai saat barang tiba.' : 'Pembayaran tercatat dan menunggu verifikasi admin.'];
}

/** Ubah status semua rincian pada satu nota pembeli (opsional: hanya yang sedang berstatus $dari). */
function set_rincian_semua(string $idNota, string $ke, ?string $dari = null): void
{
    $sql = 'UPDATE rincian_pesanan SET id_status_pesanan = ? WHERE id_nota_penjual IN (SELECT id_nota_penjual FROM nota_penjual WHERE id_nota_pembeli = ?)';
    $par = [$ke, $idNota];
    if ($dari !== null) {
        $sql .= ' AND id_status_pesanan = ?';
        $par[] = $dari;
    } else {   // status refund/banding tidak boleh tertimpa
        $sql .= ' AND id_status_pesanan NOT IN (' . in_marks(SP_REFUND_SEMUA) . ')';
        array_push($par, ...SP_REFUND_SEMUA);
    }
    q($sql, $par);
}

function verifikasi_pembayaran(string $idNota): array
{
    $n = nota_lengkap($idNota);
    if (!$n || $n['status_pembayaran'] !== BAYAR_VERIF) {
        return [false, 'Hanya pembayaran berstatus “Menunggu Verifikasi” yang bisa diverifikasi.'];
    }
    transaksi(function () use ($idNota) {
        q('UPDATE nota_pembeli SET status_pembayaran = ? WHERE id_nota_pembeli = ?', [DB_SUKSES, $idNota]);
        catatan_set_bayar($idNota, '');
        set_rincian_semua($idNota, SP_DIPROSES, SP_MENUNGGU);
    });
    return [true, 'Pembayaran ' . $idNota . ' diverifikasi: Lunas.'];
}

/** Pembayaran ditolak: status 'ditolak' supaya pembeli bisa membayar ulang; catatan admin tampil di sisi pembeli. */
function tolak_pembayaran(string $idNota, string $catatan = ''): array
{
    $catatan = trim($catatan);
    if (mb_strlen($catatan) > 500) {
        return [false, 'Catatan penolakan maksimal 500 karakter.'];
    }
    $n = nota_lengkap($idNota);
    if (!$n || $n['status_pembayaran'] !== BAYAR_VERIF) {
        return [false, 'Hanya pembayaran berstatus “Menunggu Verifikasi” yang bisa ditolak.'];
    }
    transaksi(function () use ($idNota, $catatan) {
        q('UPDATE nota_pembeli SET status_pembayaran = ? WHERE id_nota_pembeli = ?', [DB_DITOLAK, $idNota]);
        catatan_set_bayar($idNota, $catatan);
    });
    hapus_bukti($idNota); // bukti yang ditolak tidak lagi dipakai; pembeli mengunggah yang baru saat membayar ulang
    return [true, 'Pembayaran ditolak' . ($catatan !== '' ? ' dengan catatan untuk pembeli' : '') . '; pembeli diminta membayar ulang.'];
}

// ---------------------------------------------------------------- pembatalan
/**
 * Batalkan pesanan yang belum diserahkan ke kurir. Pembeli hanya boleh membatalkan pesanan yang belum dibayar online
 * (yang sudah lunas memakai refund); admin boleh membatalkan pesanan lunas — dananya dicatat pada riwayat sebagai harus dikembalikan.
 */
function batalkan_nota(string $idNota, bool $olehAdmin = false): array
{
    $n = nota_lengkap($idNota);
    if (!$n) {
        return [false, 'Pesanan tidak ditemukan.'];
    }
    if (!bisa_dibatalkan($n)) {
        return [false, 'Pesanan tidak dapat dibatalkan karena sudah dikirim, sudah dibatalkan, atau sedang dalam proses refund.'];
    }
    $lunas = $n['status_pembayaran'] === BAYAR_LUNAS;
    if ($lunas && !$olehAdmin) {
        return [false, 'Pesanan sudah lunas. Gunakan “Ajukan refund” pada halaman status pesanan.'];
    }
    transaksi(function () use ($n, $lunas) {
        proses_batal_nota($n);
        if ($lunas) {
            foreach ($n['toko'] as $t) {
                catatan_tambah_peristiwa($n['id_nota_pembeli'], $t['id_nota_penjual'], ['aksi' => 'setuju', 'oleh' => 'admin',
                    'teks' => 'Pesanan dibatalkan admin. Dana ' . rp(refund_bagian_toko($n, $t)) . ' untuk toko ini harus dikembalikan ke pembeli.']);
            }
        }
    });
    if (!$lunas) {
        hapus_bukti($idNota);
    }
    koin_sinkron($n['id_pembeli']);
    return [true, 'Pesanan ' . $idNota . ' dibatalkan. Stok dan koin dikembalikan.' . ($lunas ? ' Dana ' . rp($n['total_harga_beli']) . ' dicatat sebagai harus dikembalikan ke pembeli.' : '')];
}

/** Isi pembatalan satu nota (stok, status). Koin kembali otomatis karena pesanan batal tidak dihitung. Panggil di dalam transaksi. */
function proses_batal_nota(array $n): void
{
    foreach ($n['toko'] as $t) {
        if ($t['id_status_pengiriman'] === SK_BATAL) {
            continue;
        }
        foreach ($t['items'] as $it) {
            q('UPDATE produk SET stok = stok + ? WHERE id_produk = ?', [$it['qty'], $it['id_produk']]);
        }
        q('UPDATE nota_penjual SET id_status_pengiriman = ? WHERE id_nota_penjual = ?', [SK_BATAL, $t['id_nota_penjual']]);
    }
    set_rincian_semua($n['id_nota_pembeli'], SP_BATAL);
}

// ---------------------------------------------------------------- status pesanan & pengiriman
function nota_penjual_row(string $idNpj): ?array
{
    $r = row('SELECT np.*, nb.status_pembayaran AS status_bayar_db, nb.id_pembeli, nb.jumlah_koin_dipakai,
                     (SELECT p.id_penjual FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual ORDER BY p.id_produk LIMIT 1) AS id_penjual,
                     (SELECT MIN(rp.id_status_pesanan) FROM rincian_pesanan rp WHERE rp.id_nota_penjual = np.id_nota_penjual) AS id_status_pesanan,
                     ' . sql_cod('nb') . ' AS tunai
                FROM nota_penjual np JOIN nota_pembeli nb ON nb.id_nota_pembeli = np.id_nota_pembeli
               WHERE np.id_nota_penjual = ?', [$idNpj]);
    if ($r) {   // label turunan untuk pemeriksaan: lunas atau COD terkonfirmasi
        $r['terbayar'] = $r['status_bayar_db'] === DB_SUKSES || ($r['status_bayar_db'] === DB_MENUNGGU && (int) $r['tunai'] === 1);
    }
    return $r;
}

/** Ubah status pesanan satu toko — kecuali bila toko itu sedang/sudah punya status refund (status refund tidak boleh tertimpa). */
function status_rincian_npj(string $idNpj, string $ke): void
{
    q('UPDATE rincian_pesanan SET id_status_pesanan = ? WHERE id_nota_penjual = ? AND id_status_pesanan NOT IN (' . in_marks(SP_REFUND_SEMUA) . ')',
        array_merge([$ke, $idNpj], SP_REFUND_SEMUA));
}

function set_status_pengiriman(string $idNpj, string $sk): array
{
    $np = nota_penjual_row($idNpj);
    $ada = val('SELECT 1 FROM status_pengiriman WHERE id_status_pengiriman = ?', [$sk]);
    if (!$np || !$ada) {
        return [false, 'Data pengiriman tidak ditemukan.'];
    }
    if (in_array($np['id_status_pengiriman'], [SK_DITERIMA, SK_BATAL], true)) {
        return [false, 'Pesanan ini sudah final (diterima atau dibatalkan).'];
    }
    if ($sk === SK_BATAL) {
        return [false, 'Untuk membatalkan, gunakan tombol “Batalkan pesanan”.'];
    }
    if ($sk !== SK_BELUM && !$np['terbayar']) {
        return [false, 'Pembayaran belum terverifikasi, pesanan belum bisa dikirim.'];
    }
    if ($sk !== SK_BELUM && refund_menahan_kirim($idNpj)) {
        return [false, 'Pembeli mengajukan refund untuk pesanan ini. Putuskan refund-nya dulu sebelum pesanan dikirim.'];
    }
    if ($sk === SK_DITERIMA) {
        selesaikan_nota_penjual($idNpj);
        return [true, 'Pesanan ditandai diterima dan selesai.'];
    }
    transaksi(function () use ($np, $idNpj, $sk) {
        q('UPDATE nota_penjual SET id_status_pengiriman = ? WHERE id_nota_penjual = ?', [$sk, $idNpj]);
        if ($np['id_status_pengiriman'] === SK_BELUM && $sk !== SK_BELUM) {
            q('UPDATE pengiriman SET tanggal_pengiriman = ? WHERE id_pengiriman = ?', [date('Y-m-d'), $np['id_pengiriman']]);
        }
        status_rincian_npj($idNpj, $sk === SK_BELUM ? SP_DIKEMAS : SP_DIKIRIM);
    });
    return [true, 'Status pengiriman diperbarui.'];
}

function set_status_pesanan(string $idNpj, string $sp): array
{
    $np = nota_penjual_row($idNpj);
    if (!$np || !in_array($sp, [SP_DIPROSES, SP_DIKEMAS, SP_DIKIRIM, SP_SELESAI], true)) {
        return [false, 'Status pesanan tidak valid.'];
    }
    if (in_array($np['id_status_pengiriman'], [SK_DITERIMA, SK_BATAL], true)) {
        return [false, 'Pesanan ini sudah final (diterima atau dibatalkan).'];
    }
    if (!$np['terbayar']) {
        return [false, 'Pembayaran belum terverifikasi, status pesanan belum bisa diubah.'];
    }
    if ($sp === SP_SELESAI) {
        if (refund_menahan_kirim($idNpj)) {
            return [false, 'Pembeli mengajukan refund untuk pesanan ini. Putuskan refund-nya dulu sebelum pesanan ditandai selesai.'];
        }
        selesaikan_nota_penjual($idNpj);
        return [true, 'Pesanan ditandai selesai.'];
    }
    if ($sp === SP_DIKIRIM) {
        return set_status_pengiriman($idNpj, $np['id_status_pengiriman'] === SK_BELUM ? SK_KURIR : $np['id_status_pengiriman']);
    }
    if ($np['id_status_pengiriman'] !== SK_BELUM) {
        return [false, 'Pesanan sudah dalam pengiriman; ubah lewat status pengiriman.'];
    }
    if (refund_menahan_kirim($idNpj)) {
        return [false, 'Pembeli mengajukan refund untuk pesanan ini. Putuskan refund-nya dulu.'];
    }
    status_rincian_npj($idNpj, $sp);
    return [true, 'Status pesanan diperbarui.'];
}

/** Barang diterima: tutup pesanan, tandai tanggal sampai, hitung ulang koin, perbarui tingkat pembeli & penjual. */
function selesaikan_nota_penjual(string $idNpj): void
{
    $np = nota_penjual_row($idNpj);
    if (!$np || in_array($np['id_status_pengiriman'], [SK_DITERIMA, SK_BATAL], true)) {
        return;
    }
    transaksi(function () use ($np, $idNpj) {
        q('UPDATE nota_penjual SET id_status_pengiriman = ?, tanggal_sampai = ? WHERE id_nota_penjual = ?', [SK_DITERIMA, date('Y-m-d H:i:s'), $idNpj]);
        status_rincian_npj($idNpj, SP_SELESAI);
        $belum = (int) val('SELECT COUNT(*) FROM nota_penjual WHERE id_nota_pembeli = ? AND id_status_pengiriman NOT IN (?, ?)', [$np['id_nota_pembeli'], SK_DITERIMA, SK_BATAL]);
        if ($belum === 0 && $np['status_bayar_db'] === DB_MENUNGGU && (int) $np['tunai'] === 1) {   // COD: tunai diterima kurir saat barang tiba
            q('UPDATE nota_pembeli SET status_pembayaran = ? WHERE id_nota_pembeli = ?', [DB_SUKSES, $np['id_nota_pembeli']]);
        }
    });
    koin_sinkron($np['id_pembeli']);   // koin didapat dihitung dari toko yang sudah diterima; tingkat pembeli ikut diperbarui
    if ($np['id_penjual']) {
        perbarui_tingkat_penjual($np['id_penjual']);
    }
}

// ---------------------------------------------------------------- tingkat penjual
/** Total penjualan yang sudah diterima pembeli (di luar yang direfund) — dasar kenaikan tingkat penjual. */
function omzet_selesai_penjual(string $idPenjual): float
{
    return (float) val('SELECT COALESCE(SUM(np.total_harga_jual), 0) FROM nota_penjual np
                         WHERE np.id_status_pengiriman = ?
                           AND EXISTS (SELECT 1 FROM rincian_pesanan rp JOIN produk p ON p.id_produk = rp.id_produk WHERE rp.id_nota_penjual = np.id_nota_penjual AND p.id_penjual = ?)
                           AND NOT EXISTS (SELECT 1 FROM rincian_pesanan rx WHERE rx.id_nota_penjual = np.id_nota_penjual AND ' . sql_status_refund_berhasil('rx') . ')',
        [SK_DITERIMA, $idPenjual]);
}

function perbarui_tingkat_penjual(string $idPenjual): void
{
    $t = row('SELECT id_tingkat_penjual FROM tingkat_penjual WHERE minimal_transaksi <= ? ORDER BY minimal_transaksi DESC LIMIT 1', [omzet_selesai_penjual($idPenjual)]);
    if ($t) {
        q('UPDATE penjual SET id_tingkat_penjual = ? WHERE id_penjual = ?', [$t['id_tingkat_penjual'], $idPenjual]);
    }
}

// ---------------------------------------------------------------- bantu tampilan
/** Langkah pelacakan untuk satu toko: tiap elemen [label, state] dengan state selesai|kini|''. */
function langkah_lacak(array $n, array $t): array
{
    if ($n['status_pembayaran'] === BAYAR_BATAL || $t['id_status_pengiriman'] === SK_BATAL) {
        return [['Dibatalkan', 'kini']];
    }
    // “kini” = langkah yang sedang ditunggu; langkah sebelumnya sudah selesai.
    $urut = ['Dipesan', 'Dibayar', 'Dikemas', 'Dikirim', 'Diterima'];
    $kini = 1;                                   // menunggu pembayaran
    if (sudah_dibayar($n)) {
        $kini = 2;                               // penjual sedang memproses & mengemas
        if ($t['id_status_pesanan'] === SP_DIKEMAS) {
            $kini = 3;                           // sudah dikemas, menunggu kurir
        }
        if (in_array($t['id_status_pengiriman'], [SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR], true)) {
            $kini = 3;                           // dalam pengiriman
        }
        if ($t['id_status_pengiriman'] === SK_DITERIMA) {
            $kini = 4;                           // diterima — semua langkah selesai
        }
    }
    $o = [];
    foreach ($urut as $i => $lab) {
        $o[] = [$lab, $i < $kini || ($i === 4 && $kini === 4) ? 'selesai' : ($i === $kini ? 'kini' : '')];
    }
    return $o;
}

function lacak_html(array $langkah): string
{
    $o = '<ol class="lacak">';
    foreach ($langkah as [$lab, $st]) {
        $o .= '<li class="' . e($st) . '">' . e($lab) . '</li>';
    }
    return $o . '</ol>';
}

/**
 * Status satu nota untuk daftar pesanan → [label, jenis badge, kunci tab].
 * $n butuh: status_pembayaran (label turunan), jml_toko, jml_terima, jml_kirim, jml_batal.
 */
function status_ringkas(array $n): array
{
    switch ($n['status_pembayaran']) {
        case BAYAR_BATAL:
            return ['Dibatalkan', 'bad', 'batal'];
        case BAYAR_DITOLAK:
            return ['Pembayaran Ditolak', 'bad', 'bayar'];
        case BAYAR_BELUM:
            return ['Belum Dibayar', 'warn', 'bayar'];
        case BAYAR_VERIF:
            return ['Menunggu Verifikasi', 'info', 'bayar'];
    }
    $toko = (int) $n['jml_toko'] - (int) ($n['jml_batal'] ?? 0);   // toko yang dibatalkan/direfund-batal tidak ditunggu lagi
    $terima = (int) $n['jml_terima'];
    if ($toko > 0 && $terima >= $toko) {
        return ['Selesai', 'ok', 'selesai'];
    }
    if ((int) $n['jml_kirim'] > 0 || $terima > 0) {
        return ['Dalam Pengiriman', 'info', 'kirim'];
    }
    return ['Diproses', 'info', 'proses'];
}

/** Sama seperti status_ringkas, tetapi dihitung dari hasil nota_lengkap(). */
function status_nota_lengkap(array $n): array
{
    $terima = $kirim = $batal = 0;
    foreach ($n['toko'] as $t) {
        if ($t['id_status_pengiriman'] === SK_DITERIMA) {
            $terima++;
        } elseif ($t['id_status_pengiriman'] === SK_BATAL) {
            $batal++;
        } elseif (in_array($t['id_status_pengiriman'], [SK_KURIR, SK_JALAN, SK_TIBA, SK_ANTAR], true)) {
            $kirim++;
        }
    }
    return status_ringkas($n + ['jml_toko' => count($n['toko']), 'jml_terima' => $terima, 'jml_kirim' => $kirim, 'jml_batal' => $batal]);
}

/** Pembeli boleh menekan “Pesanan diterima” setelah paket tiba di kota tujuan atau sedang diantar. */
function bisa_konfirmasi_terima(array $t): bool
{
    return in_array($t['id_status_pengiriman'], [SK_TIBA, SK_ANTAR], true);
}
