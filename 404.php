<?php
/** Halaman 404: kain robek yang gagal ditenun. Tidak memakai database. */
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(404);

$u = user();
$beranda = $u ? home_for($u['role']) : 'index.php';
$labelBeranda = $u ? 'Kembali ke dashboard' : 'Kembali ke beranda';

// Garis robekan: titik-titik zig-zag yang sama dipakai kedua potongan kain (potongan kanan digeser).
$robek = [[318, 40], [300, 72], [322, 100], [298, 132], [320, 162], [296, 192], [318, 222], [294, 252], [314, 280], [292, 300]];
$dx = 26;

mt_srand(404);
$rnd = function (int $a, int $b) { return mt_rand($a, $b); };

/** Benang lusi yang terurai di bawah kain; sebagian digambar melengkung & berayun. */
$benang = function (int $x0, int $x1, int $y0) use ($rnd): string {
    $o = '';
    for ($x = $x0, $i = 0; $x <= $x1; $x += 7, $i++) {
        $warna = $i % 3 === 0 ? '#e3b23c' : ($i % 3 === 1 ? '#eadcb9' : '#c9a86a');
        $len = $rnd(18, 74);
        if ($i % 4 === 1) { // benang panjang berayun
            $len += $rnd(30, 56);
            $s = $rnd(-9, 9);
            $o .= '<path class="benang-lepas" d="M' . $x . ' ' . ($y0 - 26) . ' V' . $y0 . ' c' . $s . ' ' . ($len * .35) . ' ' . (-$s) . ' ' . ($len * .65) . ' ' . ($s / 2) . ' ' . $len
                . ' c3 6 9 6 8 -1" fill="none" stroke="' . $warna . '" stroke-width="2" stroke-linecap="round"/>';
        } else {
            $o .= '<path d="M' . $x . ' ' . ($y0 - 26) . ' V' . ($y0 + $len) . ' q3 5 7 1" fill="none" stroke="' . $warna . '" stroke-width="1.6" stroke-linecap="round" opacity=".92"/>';
        }
    }
    return $o;
};

/** Baris pakan yang berhenti di tengah jalan (tenunan yang tak selesai). */
$pakan = function (int $x0, int $x1) use ($rnd): string {
    $o = '';
    foreach ([236, 249, 262, 275] as $k => $y) {
        $akhir = $x1 - $k * 46 - $rnd(0, 26);
        $o .= '<path d="M' . $x0 . ' ' . $y . ' H' . $akhir . ' q10 -2 14 5 q3 7 -5 8" fill="none" stroke="#e3b23c" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="' . ($k % 2 ? '14 3' : '40 2') . '"/>';
    }
    return $o;
};

$polaKiri = 'M40 40 L318 40';
foreach (array_slice($robek, 1) as $t) { $polaKiri .= ' L' . $t[0] . ' ' . $t[1]; }
$polaKiri .= ' L40 300 Z';
$polaKanan = 'M' . ($robek[0][0] + $dx) . ' 40';
$titikKanan = [];
foreach (array_slice($robek, 1) as $t) { $titikKanan[] = [$t[0] + $dx, $t[1]]; $polaKanan .= ' L' . ($t[0] + $dx) . ' ' . $t[1]; }
$polaKanan .= ' L' . ($robek[9][0] + $dx) . ' 300 L560 300 L560 40 Z';

page_start('Halaman tidak ditemukan', ['layout' => 'public', 'flash' => false, 'body' => 'halaman-404']);
?>
<div class="nf">
  <div class="nf-in">
    <svg class="nf-art" viewBox="0 0 600 450" role="img" aria-label="Selembar kain tenun yang robek, benangnya terurai karena gagal ditenun" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <pattern id="nf-a" width="4" height="4" patternUnits="userSpaceOnUse"><rect width="4" height="4" fill="#7a1f2e"/><path d="M0 .5H4" stroke="#000" stroke-opacity=".12"/><path d="M.5 0V4" stroke="#fff" stroke-opacity=".08"/></pattern>
        <pattern id="nf-m" width="28" height="24" patternUnits="userSpaceOnUse"><path d="M14 2L24 12 14 22 4 12Z" fill="none" stroke="#e3b23c" stroke-width="1.6"/><circle cx="14" cy="12" r="2.1" fill="#e3b23c"/><path d="M0 0l2 2-2 2-2-2zM28 0l2 2-2 2-2-2zM0 24l2 2-2 2-2-2zM28 24l2 2-2 2-2-2z" fill="#e3b23c" opacity=".8"/></pattern>
        <pattern id="nf-t" width="20" height="14" patternUnits="userSpaceOnUse"><path d="M0 14L10 2 20 14Z" fill="#e3b23c"/><path d="M7 14L10 8 13 14Z" fill="#4a1019"/></pattern>
        <linearGradient id="nf-g" gradientUnits="userSpaceOnUse" x1="0" y1="195" x2="0" y2="300"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
        <mask id="nf-pudar" maskUnits="userSpaceOnUse" x="0" y="0" width="600" height="450"><rect width="600" height="450" fill="url(#nf-g)"/><rect width="600" height="195" fill="#fff"/></mask>
        <linearGradient id="nf-c" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".25"/></linearGradient>
      </defs>
      <ellipse cx="300" cy="436" rx="262" ry="11" fill="#2a1a14" opacity=".1"/>

      <!-- potongan kiri -->
      <g>
        <g mask="url(#nf-pudar)">
          <path d="<?= $polaKiri ?>" fill="url(#nf-a)"/>
          <path d="<?= $polaKiri ?>" fill="url(#nf-m)"/>
          <rect x="40" y="40" width="280" height="18" fill="#4a1019"/><rect x="40" y="58" width="280" height="14" fill="url(#nf-t)"/>
          <path d="M40 49H320M40 74H320" stroke="#e3b23c" stroke-width="1.6"/>
          <path d="<?= $polaKiri ?>" fill="url(#nf-c)"/>
        </g>
        <?= $pakan(44, 290) ?>
        <?= $benang(46, 292, 300) ?>
      </g>

      <!-- potongan kanan: terlepas dan sedikit miring -->
      <g transform="rotate(4.5 560 40) translate(6 10)">
        <g mask="url(#nf-pudar)">
          <path d="<?= $polaKanan ?>" fill="url(#nf-a)"/>
          <path d="<?= $polaKanan ?>" fill="url(#nf-m)"/>
          <rect x="340" y="40" width="220" height="18" fill="#4a1019"/><rect x="340" y="58" width="220" height="14" fill="url(#nf-t)"/>
          <path d="M340 49H560M340 74H560" stroke="#e3b23c" stroke-width="1.6"/>
          <path d="<?= $polaKanan ?>" fill="url(#nf-c)"/>
        </g>
        <?= $pakan(330, 556) ?>
        <?= $benang(334, 556, 300) ?>
      </g>

      <!-- benang yang masih menyambung di sela robekan, sebagian sudah putus -->
      <g fill="none" stroke-linecap="round">
        <?php foreach ($robek as $i => $t): if ($i === 0 || $i === 9) { continue; }
            $putus = $i % 3 === 0; $x1 = $t[0]; $x2 = $titikKanan[$i - 1][0] + 4; $y = $t[1] + 1; ?>
          <?php if ($putus): ?>
            <path d="M<?= $x1 ?> <?= $y ?> q4 5 7 3" stroke="#e3b23c" stroke-width="2"/><path d="M<?= $x2 ?> <?= $y + 4 ?> q-4 6 -7 3" stroke="#e3b23c" stroke-width="2"/>
          <?php else: ?>
            <path d="M<?= $x1 ?> <?= $y ?> Q<?= ($x1 + $x2) / 2 ?> <?= $y + 7 + ($i % 2) * 5 ?> <?= $x2 ?> <?= $y + 4 ?>" stroke="<?= $i % 2 ? '#e3b23c' : '#eadcb9' ?>" stroke-width="2"/>
          <?php endif; ?>
        <?php endforeach; ?>
      </g>

      <!-- teropong yang terjatuh dan benang kusut -->
      <g class="teropong-jatuh">
        <g transform="translate(318 418)">
          <path d="M-42 0Q-32 -9 -14 -9H14Q30 -7 34 0Q30 7 14 9H-14Q-32 9 -42 0Z" fill="#e3b23c" stroke="#8a5a14" stroke-width="1.4"/>
          <path d="M-24 0H18" stroke="#8a5a14" stroke-width="1.6" stroke-linecap="round"/>
        </g>
      </g>
      <path d="M284 414 C250 390 292 372 266 352 C250 340 222 360 238 380 C252 396 282 388 272 370" fill="none" stroke="#e3b23c" stroke-width="2.2" stroke-linecap="round"/>
      <path d="M360 418 C400 424 430 408 468 418 C496 426 498 440 474 440" fill="none" stroke="#eadcb9" stroke-width="2" stroke-linecap="round"/>
    </svg>
    <div class="kode">Kesalahan 404</div>
    <h1>Kain ini gagal ditenun</h1>
    <p>Halaman yang Anda cari tidak ada &mdash; benangnya sudah terlepas, atau alamatnya keliru. Mari kembali ke alat tenun dan mulai dari awal.</p>
    <div class="nf-aksi">
      <a class="btn btn-primary btn-lg" href="<?= e(url($beranda)) ?>"><?= e($labelBeranda) ?></a>
      <a class="btn btn-ghost btn-lg" href="javascript:history.back()" data-tanpa-loader>Halaman sebelumnya</a>
    </div>
  </div>
</div>
<?php page_end();
