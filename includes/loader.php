<?php
/** Animasi loading: seorang penenun mengoper teropong di alat tenun. SVG murni + SMIL/CSS, tanpa gambar eksternal. */

function weaver_svg(string $kelas = 'weaver'): string
{
    $t = '2.4s';
    $spl = 'calcMode="spline" keySplines=".45 0 .55 1;.45 0 .55 1;.45 0 .55 1;.45 0 .55 1" keyTimes="0;.25;.5;.75;1"';
    ob_start(); ?>
<svg class="<?= e($kelas) ?>" viewBox="0 0 320 250" role="img" aria-label="Penenun sedang menenun kain songket" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <pattern id="wv-motif" width="18" height="14" patternUnits="userSpaceOnUse">
      <rect width="18" height="14" fill="#7a1f2e"/>
      <path d="M9 1 L15 7 L9 13 L3 7Z" fill="none" stroke="#e3b23c" stroke-width="1.6"/>
      <circle cx="9" cy="7" r="1.6" fill="#e3b23c"/>
    </pattern>
    <clipPath id="wv-klip"><rect x="62" y="184" width="196" height="6">
      <animate attributeName="y" values="184;184;150;150" keyTimes="0;.04;.96;1" dur="9.6s" repeatCount="indefinite"/>
      <animate attributeName="height" values="6;6;40;40" keyTimes="0;.04;.96;1" dur="9.6s" repeatCount="indefinite"/>
    </rect></clipPath>
  </defs>
  <ellipse cx="160" cy="232" rx="128" ry="9" fill="#2a1a14" opacity=".12"/>

  <!-- rangka alat tenun -->
  <rect x="38" y="34" width="12" height="176" rx="3" fill="#5a3a22"/>
  <rect x="270" y="34" width="12" height="176" rx="3" fill="#5a3a22"/>
  <rect x="34" y="28" width="252" height="13" rx="5" fill="#6d4628"/>

  <!-- benang lusi -->
  <g stroke-linecap="round" opacity=".7">
    <?php for ($x = 64, $i = 0; $x <= 256; $x += 8, $i++): ?>
    <line x1="<?= $x ?>" y1="41" x2="<?= $x ?>" y2="188" stroke="<?= $i % 4 === 0 ? '#e3b23c' : '#e8dcc2' ?>" stroke-width="<?= $i % 4 === 0 ? 1.6 : 1.1 ?>"/>
    <?php endfor; ?>
  </g>

  <!-- kain yang tumbuh -->
  <g clip-path="url(#wv-klip)">
    <rect x="62" y="150" width="196" height="40" fill="url(#wv-motif)"/>
  </g>

  <!-- penenun (duduk di depan alat tenun) -->
  <g class="wv-orang">
    <path d="M118 214 Q122 176 138 168 H182 Q198 176 202 214Z" fill="#22305f"/>
    <path d="M118 204 H202" stroke="#e3b23c" stroke-width="3"/>
    <path d="M137 112 Q160 104 183 112 L192 176 H128Z" fill="#7a1f2e"/>
    <path d="M149 108 L160 128 L171 108" fill="none" stroke="#e3b23c" stroke-width="2.4" stroke-linejoin="round"/>
    <rect x="152" y="96" width="16" height="14" rx="6" fill="#e0a878"/>
    <circle cx="160" cy="84" r="16.5" fill="#e8b88a"/>
    <path d="M143.5 84 A16.5 16.5 0 0 1 176.5 84 Q168 74 160 74 Q152 74 143.5 84Z" fill="#2a1a14"/>
    <circle cx="160" cy="66" r="8" fill="#2a1a14"/>
    <path d="M150 63 L170 69" stroke="#e3b23c" stroke-width="2.2" stroke-linecap="round"/>
    <circle cx="154" cy="86" r="1.5" fill="#2a1a14"/><circle cx="166" cy="86" r="1.5" fill="#2a1a14"/>
    <path d="M156 93 Q160 96 164 93" fill="none" stroke="#9c5a3c" stroke-width="1.4" stroke-linecap="round"/>
  </g>

  <!-- balok kain -->
  <rect x="34" y="188" width="252" height="15" rx="6" fill="#6d4628"/>

  <!-- sisir (beater) -->
  <rect x="60" y="128" width="200" height="7" rx="3.5" fill="#8a5a30">
    <animate attributeName="y" values="128;128;136;128;128" keyTimes="0;.42;.5;.58;1" dur="<?= $t ?>" repeatCount="indefinite"/>
  </rect>

  <!-- benang pakan + teropong -->
  <line x1="62" y1="141" x2="62" y2="141" stroke="#e3b23c" stroke-width="2.4" stroke-linecap="round">
    <animate attributeName="x2" values="70;145;220;145;70" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/>
  </line>
  <g>
    <animateTransform attributeName="transform" type="translate" values="0 0;75 0;150 0;75 0;0 0" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/>
    <path d="M52 141 Q62 133 80 133 H92 Q102 135 104 141 Q102 147 92 149 H80 Q62 149 52 141Z" fill="#e3b23c" stroke="#8a5a14" stroke-width="1.2"/>
    <line x1="64" y1="141" x2="94" y2="141" stroke="#8a5a14" stroke-width="1.4" stroke-linecap="round"/>
  </g>

  <!-- lengan (di depan benang) -->
  <g stroke-linecap="round" fill="none">
    <line x1="141" y1="120" x2="74" y2="146" stroke="#7a1f2e" stroke-width="11">
      <animate attributeName="x2" values="76;150;120;150;76" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/>
      <animate attributeName="y2" values="146;150;142;150;146" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/>
    </line>
    <line x1="179" y1="120" x2="190" y2="148" stroke="#7a1f2e" stroke-width="11">
      <animate attributeName="x2" values="190;172;226;172;190" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/>
      <animate attributeName="y2" values="148;150;146;150;148" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/>
    </line>
  </g>
  <circle r="6.5" fill="#e8b88a" cx="76" cy="146"><animate attributeName="cx" values="76;150;120;150;76" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/><animate attributeName="cy" values="146;150;142;150;146" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/></circle>
  <circle r="6.5" fill="#e8b88a" cx="190" cy="148"><animate attributeName="cx" values="190;172;226;172;190" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/><animate attributeName="cy" values="148;150;146;150;148" <?= $spl ?> dur="<?= $t ?>" repeatCount="indefinite"/></circle>
</svg>
<?php
    return ob_get_clean();
}

/** Lapisan loading layar penuh — tampil saat halaman dimuat / berpindah, lalu memudar. */
function loader_html(): string
{
    return '<div id="loader" class="loader" role="status" aria-live="polite">'
        . '<div class="loader-in">' . weaver_svg('weaver') . '<p class="loader-teks">Sedang ditenun<span class="titik"><i>.</i><i>.</i><i>.</i></span></p>'
        . '<div class="loader-benang"><span></span></div></div></div>';
}
