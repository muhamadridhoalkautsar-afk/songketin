<?php
/**
 * Akun pengguna: validasi, pembuatan, perubahan, dan penghapusan.
 * Dipakai halaman daftar (index.php) dan menu “Kelola Akun” di dashboard admin, supaya aturannya selalu sama.
 */

require_once __DIR__ . '/wilayah.php';
require_once __DIR__ . '/foto.php';

/** Nama bank / dompet digital yang boleh dipakai penjual sebagai rekening penerimaan. */
function pilihan_rekening(): array
{
    return array_column(rows(
        "SELECT DISTINCT j.nama_jenis_pembayaran AS n FROM jenis_pembayaran j
           JOIN metode_pembayaran m ON m.id_metode_pembayaran = j.id_metode_pembayaran
          WHERE m.id_metode_pembayaran IN ('MP01','MP02') ORDER BY j.nama_jenis_pembayaran"
    ), 'n');
}

/** Isian formulir akun yang masih kosong. */
function akun_kosong(string $peran = 'pembeli'): array
{
    return ['peran' => $peran, 'username' => '', 'email' => '', 'nama' => '', 'detail' => '', 'telepon' => '', 'rekening' => '', 'bank' => '',
        'negara' => '', 'provinsi' => '', 'kabupaten' => '', 'kecamatan' => '', 'desa' => ''];
}

/** Ambil isian formulir akun dari $_POST. */
function akun_dari_post(string $peran): array
{
    return [
        'peran' => $peran, 'username' => mb_strtolower(post('username')), 'email' => mb_strtolower(post('email')),
        'nama' => post('nama'), 'detail' => post('detail_alamat'), 'telepon' => post('telepon'),
        'rekening' => post('no_rekening'), 'bank' => post('bank'),
        'negara' => post('negara'), 'provinsi' => post('provinsi'), 'kabupaten' => post('kabupaten'),
        'kecamatan' => post('kecamatan'), 'desa' => post('desa'),
    ];
}

/** Isian formulir dari akun yang sudah ada (untuk halaman ubah akun). Null bila akun tidak ada. */
function akun_ke_isian(string $idUser): ?array
{
    $u = row('SELECT * FROM users WHERE id_user = ?', [$idUser]);
    if (!$u) {
        return null;
    }
    $f = akun_kosong($u['role']);
    $f['username'] = $u['username'];
    $f['email'] = $u['email'];
    $p = null;
    if ($u['role'] === 'pembeli') {
        $p = row('SELECT nama_pembeli AS nama, id_desa, detail_alamat AS detail FROM pembeli WHERE id_user = ?', [$idUser]);
    } elseif ($u['role'] === 'penjual') {
        $p = row('SELECT nama_penjual AS nama, id_desa, alamat_lengkap_penjual AS detail, no_telepon AS telepon, no_rekening AS rekening, jenis_pembayaran AS bank FROM penjual WHERE id_user = ?', [$idUser]);
    }
    if ($p) {
        foreach (['nama', 'detail', 'telepon', 'rekening', 'bank'] as $k) {
            $f[$k] = (string) ($p[$k] ?? '');
        }
        $r = wilayah_rantai($p['id_desa'] ?? null);
        foreach (['negara', 'provinsi', 'kabupaten', 'kecamatan', 'desa'] as $k) {
            $f[$k] = $r[$k]['id'] ?? '';
        }
    }
    return $f;
}

/**
 * Validasi isian akun. Mengembalikan [daftar galat, isian yang sudah dinormalkan].
 * $idUser diisi saat mengubah akun (supaya username/email miliknya sendiri tidak dianggap bentrok);
 * saat mengubah, kata sandi boleh kosong (artinya tidak diganti).
 */
function validasi_akun(array $f, string $sandi, string $sandi2, ?string $idUser = null): array
{
    $g = [];
    $peran = $f['peran'];
    if (!preg_match('/^[a-z0-9_.]{4,30}$/', $f['username'])) {
        $g[] = 'Nama pengguna 4–30 karakter: huruf kecil, angka, titik, atau garis bawah.';
    }
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 100) {
        $g[] = 'Alamat email tidak valid.';
    }
    if ($idUser === null || $sandi !== '' || $sandi2 !== '') {
        if ($sandi === '') {
            $g[] = 'Kata sandi wajib diisi.';
        } elseif (mb_strlen($sandi) > 255) {
            $g[] = 'Kata sandi terlalu panjang (maksimal 255 karakter).';
        } elseif ($sandi !== $sandi2) {
            $g[] = 'Konfirmasi kata sandi tidak sama.';
        }
    }
    if (!in_array($peran, PERAN_ADMIN, true)) {
        $namaLabel = $peran === 'penjual' ? 'Nama toko' : 'Nama lengkap';
        if (mb_strlen($f['nama']) < 3 || mb_strlen($f['nama']) > 100) {
            $g[] = $namaLabel . ' harus 3–100 karakter.';
        }
        if (!wilayah_valid($f['negara'], $f['provinsi'], $f['kabupaten'], $f['kecamatan'], $f['desa'])) {
            $g[] = 'Alamat tidak sesuai hirarki. Pilih berurutan: negara, provinsi, kabupaten/kota, kecamatan, lalu desa/kelurahan.';
        }
        if (mb_strlen($f['detail']) < 5 || mb_strlen($f['detail']) > 300) {
            $g[] = 'Detail alamat (jalan, nomor, RT/RW) harus 5–300 karakter.';
        }
    }
    if ($peran === 'penjual') {
        $tel = preg_replace('/[\s\-().]/', '', $f['telepon']);
        $tel = preg_replace('/^\+?62/', '0', $tel);
        if (!preg_match('/^08\d{8,12}$/', $tel)) {
            $g[] = 'Nomor telepon tidak valid (contoh: 081234567890).';
        }
        $f['telepon'] = $tel;
        if (!preg_match('/^\d{6,30}$/', preg_replace('/[\s\-]/', '', $f['rekening']))) {
            $g[] = 'Nomor rekening hanya berisi angka (6–30 digit).';
        }
        $f['rekening'] = preg_replace('/[\s\-]/', '', $f['rekening']);
        $daftarBank = pilihan_rekening();
        if ($daftarBank ? !in_array($f['bank'], $daftarBank, true) : (mb_strlen($f['bank']) < 2 || mb_strlen($f['bank']) > 50)) {
            $g[] = $daftarBank ? 'Pilih bank / dompet digital penerima pembayaran.' : 'Isi nama bank / dompet digital penerima pembayaran (2–50 karakter).';
        }
    }
    if (!$g && val('SELECT 1 FROM users WHERE LOWER(username) = ? AND id_user <> ?', [$f['username'], $idUser ?? ''])) {
        $g[] = 'Nama pengguna sudah dipakai.';
    }
    if (!$g && val('SELECT 1 FROM users WHERE LOWER(email) = ? AND id_user <> ?', [$f['email'], $idUser ?? ''])) {
        $g[] = 'Email sudah terdaftar.';
    }
    return [$g, $f];
}

/**
 * Buat akun baru (beserta profil pembeli/penjual). Mengembalikan id_user. Kata sandi disimpan apa adanya (permintaan pemilik proyek).
 * Akun admin / super_admin hanya boleh dibuat oleh Super Admin (diperiksa di sini, bukan hanya di halaman).
 */
function buat_akun(array $f, string $sandi): string
{
    if (in_array($f['peran'], PERAN_ADMIN, true) && !is_super_admin()) {
        throw new RuntimeException('Hanya Super Admin yang dapat menambahkan akun admin.');
    }
    return transaksi(function () use ($f, $sandi) {
        $idUser = next_id('users', 'id_user', 'USR');
        q('INSERT INTO users (id_user, username, email, password, role) VALUES (?, ?, ?, ?, ?)',
            [$idUser, $f['username'], $f['email'], sandi_hash($sandi), $f['peran']]);
        if ($f['peran'] === 'pembeli') {
            q('INSERT INTO pembeli (id_pembeli, id_user, nama_pembeli, id_desa, detail_alamat, id_tingkat_pembeli) VALUES (?, ?, ?, ?, ?, ?)',
                [next_id('pembeli', 'id_pembeli', 'PMB'), $idUser, $f['nama'], $f['desa'], $f['detail'], tingkat_awal('tingkat_pembeli', 'id_tingkat_pembeli', 'akumulasi_koin', 'TB01')]);
        } elseif ($f['peran'] === 'penjual') {
            q('INSERT INTO penjual (id_penjual, id_user, nama_penjual, id_desa, alamat_lengkap_penjual, no_telepon, no_rekening, jenis_pembayaran, id_tingkat_penjual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [next_id('penjual', 'id_penjual', 'PNJ'), $idUser, $f['nama'], $f['desa'], $f['detail'], $f['telepon'], $f['rekening'], $f['bank'], tingkat_awal('tingkat_penjual', 'id_tingkat_penjual', 'minimal_transaksi', 'TJ01')]);
        }
        return $idUser;
    });
}

/** Simpan perubahan akun. Peran tidak bisa diubah. Kata sandi hanya diganti bila $sandi tidak kosong. */
function ubah_akun(string $idUser, string $peran, array $f, string $sandi): void
{
    transaksi(function () use ($idUser, $peran, $f, $sandi) {
        q('UPDATE users SET username = ?, email = ? WHERE id_user = ?', [$f['username'], $f['email'], $idUser]);
        if ($sandi !== '') {
            q('UPDATE users SET password = ? WHERE id_user = ?', [sandi_hash($sandi), $idUser]);
        }
        if ($peran === 'pembeli') {
            q('UPDATE pembeli SET nama_pembeli = ?, id_desa = ?, detail_alamat = ? WHERE id_user = ?', [$f['nama'], $f['desa'], $f['detail'], $idUser]);
        } elseif ($peran === 'penjual') {
            q('UPDATE penjual SET nama_penjual = ?, id_desa = ?, alamat_lengkap_penjual = ?, no_telepon = ?, no_rekening = ?, jenis_pembayaran = ? WHERE id_user = ?',
                [$f['nama'], $f['desa'], $f['detail'], $f['telepon'], $f['rekening'], $f['bank'], $idUser]);
        }
    });
}

/** Kata sandi sementara yang mudah diketik: huruf dan angka (tanpa 0/O/1/l yang membingungkan). */
function sandi_acak(int $panjang = 10): string
{
    $huruf = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $angka = '23456789';
    $semua = $huruf . $angka;
    $s = $huruf[random_int(0, strlen($huruf) - 1)] . $angka[random_int(0, strlen($angka) - 1)];
    while (strlen($s) < $panjang) {
        $s .= $semua[random_int(0, strlen($semua) - 1)];
    }
    return str_shuffle($s);
}

/** Atur ulang kata sandi sebuah akun ke kata sandi sementara. Mengembalikan kata sandi barunya. */
function reset_sandi(string $idUser): string
{
    $baru = sandi_acak();
    q('UPDATE users SET password = ? WHERE id_user = ?', [sandi_hash($baru), $idUser]);
    return $baru;
}

/**
 * Hapus akun yang tidak punya riwayat transaksi. Akun dengan riwayat pesanan tidak dihapus
 * supaya nota, laporan, dan struk lama tetap utuh (gunakan “Reset sandi” bila akun perlu dikunci).
 * Admin biasa hanya boleh menghapus pembeli/penjual; akun admin & super_admin hanya oleh Super Admin.
 * @return array [bool berhasil, string pesan]
 */
function hapus_akun(string $idUser, string $idPelaku): array
{
    $u = row('SELECT * FROM users WHERE id_user = ?', [$idUser]);
    if (!$u) {
        return [false, 'Akun tidak ditemukan.'];
    }
    if ($idUser === $idPelaku) {
        return [false, 'Akun yang sedang Anda pakai tidak bisa dihapus.'];
    }
    if (in_array($u['role'], PERAN_ADMIN, true)) {
        if (!is_super_admin()) {
            return [false, 'Hanya Super Admin yang dapat menghapus akun admin.'];
        }
        if ($u['role'] === 'super_admin' && (int) val('SELECT COUNT(*) FROM users WHERE role = ?', ['super_admin']) <= 1) {
            return [false, 'Harus tersisa minimal satu akun Super Admin.'];
        }
    }
    $punyaRiwayat = 'Akun “' . $u['username'] . '” punya riwayat pesanan sehingga tidak bisa dihapus (nota dan laporan lama harus tetap utuh). Gunakan “Reset sandi” bila akun perlu dikunci.';
    try {
        $foto = [];
        transaksi(function () use ($u, &$foto, $punyaRiwayat) {
            if ($u['role'] === 'pembeli') {
                $p = row('SELECT id_pembeli FROM pembeli WHERE id_user = ?', [$u['id_user']]);
                if ($p) {
                    if (val('SELECT 1 FROM nota_pembeli WHERE id_pembeli = ?', [$p['id_pembeli']])) {
                        throw new RuntimeException($punyaRiwayat);
                    }
                    q('DELETE FROM pembeli WHERE id_pembeli = ?', [$p['id_pembeli']]);
                }
            } elseif ($u['role'] === 'penjual') {
                $p = row('SELECT id_penjual FROM penjual WHERE id_user = ?', [$u['id_user']]);
                if ($p) {
                    if (val('SELECT 1 FROM rincian_pesanan rp JOIN produk pr ON pr.id_produk = rp.id_produk WHERE pr.id_penjual = ?', [$p['id_penjual']])) {
                        throw new RuntimeException($punyaRiwayat);
                    }
                    $foto = array_column(rows('SELECT foto_produk FROM produk WHERE id_penjual = ?', [$p['id_penjual']]), 'foto_produk');
                    q('DELETE FROM produk WHERE id_penjual = ?', [$p['id_penjual']]);
                    q('DELETE FROM penjual WHERE id_penjual = ?', [$p['id_penjual']]);
                }
            }
            q('DELETE FROM users WHERE id_user = ?', [$u['id_user']]);
        });
        foreach ($foto as $path) {
            foto_hapus($path);
        }
    } catch (RuntimeException $e) {
        return [false, $e->getMessage()];
    } catch (PDOException $e) {
        return [false, 'Akun “' . $u['username'] . '” masih terhubung dengan data lain sehingga belum bisa dihapus.'];
    }
    return [true, 'Akun “' . $u['username'] . '” dihapus.'];
}

/** Ubah peran admin ↔ super_admin (hak akses admin). Hanya Super Admin; minimal satu Super Admin harus tersisa. */
function ubah_peran_admin(string $idUser, string $peranBaru, string $idPelaku): array
{
    if (!is_super_admin()) {
        return [false, 'Hanya Super Admin yang dapat mengelola hak akses admin.'];
    }
    if (!in_array($peranBaru, PERAN_ADMIN, true)) {
        return [false, 'Peran tidak valid.'];
    }
    $u = row('SELECT * FROM users WHERE id_user = ?', [$idUser]);
    if (!$u || !in_array($u['role'], PERAN_ADMIN, true)) {
        return [false, 'Akun admin tidak ditemukan.'];
    }
    if ($u['role'] === $peranBaru) {
        return [false, 'Akun itu sudah berperan ' . label_peran($peranBaru) . '.'];
    }
    if ($u['role'] === 'super_admin' && (int) val('SELECT COUNT(*) FROM users WHERE role = ?', ['super_admin']) <= 1) {
        return [false, 'Harus tersisa minimal satu akun Super Admin.'];
    }
    q('UPDATE users SET role = ? WHERE id_user = ?', [$peranBaru, $idUser]);
    return [true, 'Hak akses “' . $u['username'] . '” diubah menjadi ' . label_peran($peranBaru) . '.'];
}
