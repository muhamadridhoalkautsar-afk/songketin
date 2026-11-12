<?php
/**
 * Gambar kain otomatis (SVG) untuk produk tanpa foto: warna dasar dari kolom `warna`,
 * motif dari `pola_kain`, benang hias dari `jenis_benang`. Tidak perlu berkas gambar apa pun.
 */

function hex_rgb(string $h): array
{
    $h = ltrim($h, '#');
    return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
}

function mix_hex(string $a, string $b, float $t): string
{
    [$r1, $g1, $b1] = hex_rgb($a);
    [$r2, $g2, $b2] = hex_rgb($b);
    return sprintf('#%02x%02x%02x', $r1 + ($r2 - $r1) * $t, $g1 + ($g2 - $g1) * $t, $b1 + ($b2 - $b1) * $t);
}

function warna_dasar(?string $nama, string $seed = ''): string
{
    $n = mb_strtolower((string) $nama);
    $peta = [
        'merah hati' => '#7a1626', 'merah bata' => '#a2432a', 'marun' => '#6b1a28', 'maroon' => '#6b1a28', 'merah muda' => '#d7708a',
        'merah' => '#a8232f', 'zamrud' => '#0f6b4f', 'hijau daun' => '#4c8a3c', 'hijau' => '#2f6b4f', 'navy' => '#1b2a55',
        'biru tua' => '#1f3a7a', 'indigo' => '#2b3a7a', 'biru' => '#2b5d9b', 'ungu tua' => '#4b2a63', 'ungu' => '#6a3b8a',
        'hitam' => '#1c1614', 'oranye' => '#d9722b', 'jingga' => '#d9722b', 'cokelat' => '#6b4429', 'coklat' => '#6b4429',
        'kuning' => '#d9a81e', 'emas' => '#c8962e', 'putih' => '#efe8d6', 'krem' => '#e7d9b8', 'pink' => '#d7708a', 'abu' => '#7c7a78',
    ];
    foreach ($peta as $kunci => $hex) {
        if ($n !== '' && strpos($n, $kunci) !== false) {
            return $hex;
        }
    }
    $h = crc32($n . $seed) % 360;
    [$r, $g, $b] = [0, 0, 0];
    $c = 0.42; $x = $c * (1 - abs(fmod($h / 60, 2) - 1)); $m = 0.2;
    $seg = [[$c, $x, 0], [$x, $c, 0], [0, $c, $x], [0, $x, $c], [$x, 0, $c], [$c, 0, $x]][(int) ($h / 60) % 6];
    return sprintf('#%02x%02x%02x', ($seg[0] + $m) * 255, ($seg[1] + $m) * 255, ($seg[2] + $m) * 255);
}

function warna_benang(?string $benang): string
{
    $n = mb_strtolower((string) $benang);
    if (strpos($n, 'emas') !== false) return '#e3b23c';
    if (strpos($n, 'perak') !== false) return '#dfe3ea';
    if (strpos($n, 'sutra') !== false) return '#f4e8cf';
    if (strpos($n, 'wol') !== false) return '#d9c7a3';
    return '#f0dfb4';
}

/** Ubin motif 40×40 menurut nama pola kain. $T = warna benang, $B = warna dasar. */
function swatch_ubin(string $pola, string $T, string $B): string
{
    $p = mb_strtolower($pola);
    if (strpos($p, 'lepus') !== false) {
        return '<path d="M20 2L38 20 20 38 2 20Z" fill="none" stroke="' . $T . '" stroke-width="2"/><path d="M20 12L28 20 20 28 12 20Z" fill="' . $T . '"/><circle cx="0" cy="0" r="2.4" fill="' . $T . '"/><circle cx="40" cy="0" r="2.4" fill="' . $T . '"/><circle cx="0" cy="40" r="2.4" fill="' . $T . '"/><circle cx="40" cy="40" r="2.4" fill="' . $T . '"/>';
    }
    if (strpos($p, 'nago') !== false) {
        return '<path d="M0 12Q10 -2 20 12T40 12" fill="none" stroke="' . $T . '" stroke-width="2.6"/><path d="M0 30Q10 16 20 30T40 30" fill="none" stroke="' . $T . '" stroke-width="2.6"/><circle cx="10" cy="22" r="2" fill="' . $T . '"/><circle cx="30" cy="4" r="2" fill="' . $T . '"/>';
    }
    if (strpos($p, 'bungo') !== false || strpos($p, 'bunga') !== false) {
        return '<g fill="' . $T . '"><circle cx="20" cy="11" r="5.2"/><circle cx="29" cy="20" r="5.2"/><circle cx="20" cy="29" r="5.2"/><circle cx="11" cy="20" r="5.2"/></g><circle cx="20" cy="20" r="3.4" fill="' . $B . '"/><path d="M0 0l3 3-3 3-3-3zM40 0l3 3-3 3-3-3zM0 40l3 3-3 3-3-3zM40 40l3 3-3 3-3-3z" fill="' . $T . '"/>';
    }
    if (strpos($p, 'tawur') !== false) {
        return '<g fill="' . $T . '"><circle cx="8" cy="9" r="2"/><circle cx="29" cy="17" r="2"/><circle cx="16" cy="32" r="2"/><circle cx="36" cy="36" r="1.6"/></g><path d="M22 4v6M19 7h6M6 24v6M3 27h6" stroke="' . $T . '" stroke-width="1.6"/>';
    }
    if (strpos($p, 'pucuk') !== false || strpos($p, 'rebung') !== false || strpos($p, 'tumpal') !== false) {
        return '<path d="M0 40L20 6 40 40Z" fill="' . $T . '"/><path d="M11 40L20 22 29 40Z" fill="' . $B . '"/><circle cx="20" cy="31" r="2.2" fill="' . $T . '"/>';
    }
    if (strpos($p, 'ikat') !== false || strpos($p, 'geometri') !== false) {
        return '<path d="M0 20L10 8 20 20 30 32 40 20" fill="none" stroke="' . $T . '" stroke-width="3.4" stroke-linejoin="miter"/><rect x="17" y="2" width="6" height="6" fill="' . $T . '"/><rect x="17" y="32" width="6" height="6" fill="' . $T . '" opacity=".85"/>';
    }
    if (strpos($p, 'lurik') !== false || strpos($p, 'garis') !== false) {
        return '<rect x="4" width="4" height="40" fill="' . $T . '"/><rect x="14" width="1.6" height="40" fill="' . $T . '"/><rect x="22" width="1.6" height="40" fill="' . $T . '"/><rect x="31" width="5" height="40" fill="' . $T . '" opacity=".8"/>';
    }
    if (strpos($p, 'subahnale') !== false) {
        return '<path d="M20 3L24 16 37 20 24 24 20 37 16 24 3 20 16 16Z" fill="' . $T . '"/><circle cx="20" cy="20" r="2.6" fill="' . $B . '"/>';
    }
    if (strpos($p, 'limar') !== false) {
        return '<path d="M0 8H40M0 20H40M0 32H40" stroke="' . $T . '" stroke-width="2" stroke-dasharray="6 3" opacity=".75"/><path d="M0 14H40M0 26H40" stroke="' . $T . '" stroke-width="1" opacity=".5"/>';
    }
    return '<path d="M20 4L36 20 20 36 4 20Z" fill="none" stroke="' . $T . '" stroke-width="1.8"/><circle cx="20" cy="20" r="3" fill="' . $T . '"/>';
}

function swatch_svg(array $p): string
{
    $id = (string) ($p['id_produk'] ?? 'x');
    $B = warna_dasar($p['warna'] ?? '', $id);
    $T = warna_benang($p['nama_benang'] ?? '');
    $pola = (string) ($p['nama_pola_kain'] ?? '');
    $gelap = mix_hex($B, '#000000', .38);
    $s = crc32($id);
    $skala = [1, 1.15, 1.3][$s % 3];
    $dx = $s % 40;
    $dy = ($s >> 5) % 40;
    $limar = stripos($pola, 'limar') !== false;
    $o = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" preserveAspectRatio="xMidYMid slice">';
    $o .= '<defs>'
        . '<pattern id="a" width="4" height="4" patternUnits="userSpaceOnUse"><rect width="4" height="4" fill="' . $B . '"/><path d="M0 .5H4" stroke="#000" stroke-opacity=".11"/><path d="M.5 0V4" stroke="#fff" stroke-opacity=".08"/></pattern>'
        . '<pattern id="m" width="40" height="40" patternUnits="userSpaceOnUse" patternTransform="translate(' . $dx . ' ' . $dy . ') scale(' . $skala . ')">' . swatch_ubin($pola, $T, $B) . '</pattern>'
        . '<pattern id="t" width="24" height="16" patternUnits="userSpaceOnUse"><path d="M0 16L12 2 24 16Z" fill="' . $T . '"/><path d="M8 16L12 9 16 16Z" fill="' . $gelap . '"/></pattern>'
        . '<linearGradient id="c" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".2"/><stop offset=".45" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".3"/></linearGradient>'
        . ($limar ? '<linearGradient id="l" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="' . $B . '"/><stop offset=".5" stop-color="' . mix_hex($B, '#ffd9a0', .55) . '"/><stop offset="1" stop-color="' . mix_hex($B, '#c94a2a', .5) . '"/></linearGradient>' : '')
        . '</defs>';
    $o .= '<rect width="400" height="400" fill="url(#a)"/>';
    if ($limar) {
        $o .= '<rect width="400" height="400" fill="url(#l)" opacity=".85"/>';
    }
    $o .= '<rect y="62" width="400" height="276" fill="url(#m)" opacity=".96"/>';
    // kepala kain (pita atas & bawah) dengan tumpal
    $o .= '<rect width="400" height="62" fill="' . $gelap . '"/><rect y="338" width="400" height="62" fill="' . $gelap . '"/>';
    $o .= '<rect y="40" width="400" height="16" fill="url(#t)"/><g transform="translate(0 400) scale(1 -1)"><rect y="40" width="400" height="16" fill="url(#t)"/></g>';
    $o .= '<path d="M0 31H400M0 62H400M0 338H400M0 369H400" stroke="' . $T . '" stroke-width="2" opacity=".85"/>';
    $o .= '<rect width="400" height="400" fill="url(#c)"/>';
    $o .= '<path d="M-20 130L420 50V96L-20 190Z" fill="#fff" opacity=".07"/><path d="M-20 250L420 170V212L-20 310Z" fill="#000" opacity=".07"/>';
    return $o . '</svg>';
}
