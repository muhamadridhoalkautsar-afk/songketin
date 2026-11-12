<?php
/**
 * MiniPDF — pembuat PDF kecil berbahasa PHP murni (tanpa pustaka luar, tanpa Composer).
 * Memakai font standar PDF (Helvetica) sehingga berkas kecil dan terbuka di semua pembaca PDF.
 * Koordinat dalam poin (1 pt = 1/72 inci); titik (0,0) ada di KIRI ATAS halaman.
 */
class MiniPDF
{
    public float $w = 595.28;   // A4
    public float $h = 841.89;
    /** @var callable|null fn(MiniPDF $pdf, int $halaman, int $total): void — dipanggil untuk tiap halaman saat output() */
    public $footer = null;
    private array $pages = [];
    private int $cur = -1;
    private string $style = '';
    private float $size = 10;
    private array $met;
    private array $info = [];

    public function __construct(string $title = '', string $author = 'Songketin')
    {
        $this->met = require __DIR__ . '/pdf_metrics.php';
        $this->info = ['Title' => $title, 'Author' => $author, 'Producer' => 'Songketin MiniPDF'];
    }

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(sprintf('%.2F', $v), '0'), '.') ?: '0';
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return self::n(hexdec(substr($hex, 0, 2)) / 255) . ' ' . self::n(hexdec(substr($hex, 2, 2)) / 255) . ' ' . self::n(hexdec(substr($hex, 4, 2)) / 255);
    }

    private function out(string $s): void
    {
        $this->pages[$this->cur] .= $s . "\n";
    }

    public function font(string $style = '', float $size = 10): void
    {
        $this->style = $style;
        $this->size = $size;
    }

    private function enc(string $s): string
    {
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($r === false) {
            $r = mb_convert_encoding($s, 'CP1252', 'UTF-8');
        }
        return $r;
    }

    public function width(string $s, ?string $style = null, ?float $size = null): float
    {
        $tabel = ($style ?? $this->style) === 'B' ? $this->met['bold'] : $this->met['reg'];
        $total = 0;
        foreach (str_split($this->enc($s)) as $c) {
            $o = ord($c);
            $total += $o >= 32 ? $tabel[$o - 32] : 0;
        }
        return $total * ($size ?? $this->size) / 1000;
    }

    /** Pecah teks menjadi baris-baris yang muat dalam $maxW. */
    public function wrap(string $s, float $maxW, ?string $style = null, ?float $size = null): array
    {
        $baris = [];
        foreach (preg_split('/\R/', $s) as $para) {
            $cur = '';
            foreach (preg_split('/\s+/', trim($para)) ?: [] as $kata) {
                while ($this->width($kata, $style, $size) > $maxW && mb_strlen($kata) > 1) { // kata terlalu panjang: potong
                    $n = mb_strlen($kata);
                    while ($n > 1 && $this->width(mb_substr($kata, 0, $n), $style, $size) > $maxW) {
                        $n--;
                    }
                    if ($cur !== '') {
                        $baris[] = $cur;
                        $cur = '';
                    }
                    $baris[] = mb_substr($kata, 0, $n);
                    $kata = mb_substr($kata, $n);
                }
                $coba = $cur === '' ? $kata : $cur . ' ' . $kata;
                if ($cur !== '' && $this->width($coba, $style, $size) > $maxW) {
                    $baris[] = $cur;
                    $cur = $kata;
                } else {
                    $cur = $coba;
                }
            }
            $baris[] = $cur;
        }
        return $baris;
    }

    /** Tulis teks. $y = garis dasar (dari atas). $align: L, R, C relatif terhadap $x. */
    public function text(float $x, float $y, string $s, string $color = '#2a1a14', string $align = 'L'): void
    {
        $lebar = $this->width($s);
        if ($align === 'R') {
            $x -= $lebar;
        } elseif ($align === 'C') {
            $x -= $lebar / 2;
        }
        $f = $this->style === 'B' ? 'F2' : ($this->style === 'I' ? 'F3' : 'F1');
        $esc = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $this->enc($s));
        $this->out('q ' . self::rgb($color) . ' rg BT /' . $f . ' ' . self::n($this->size) . ' Tf ' . self::n($x) . ' ' . self::n($this->h - $y) . ' Td (' . $esc . ') Tj ET Q');
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#e2d4b6', float $lw = 0.6, string $dash = ''): void
    {
        $this->out('q ' . self::rgb($color) . ' RG ' . self::n($lw) . ' w ' . ($dash !== '' ? '[' . $dash . '] 0 d ' : '')
            . self::n($x1) . ' ' . self::n($this->h - $y1) . ' m ' . self::n($x2) . ' ' . self::n($this->h - $y2) . ' l S Q');
    }

    /** $mode: F isi, S garis, FD isi+garis. */
    public function rect(float $x, float $y, float $w, float $h, string $fill = '#ffffff', ?string $stroke = null, float $lw = 0.6, float $r = 0): void
    {
        $y2 = $this->h - $y - $h;
        $op = ($fill !== '' ? 'f' : '') . ($stroke !== null ? 'S' : '');
        $op = $fill !== '' && $stroke !== null ? 'B' : ($fill !== '' ? 'f' : 'S');
        $set = ($fill !== '' ? self::rgb($fill) . ' rg ' : '') . ($stroke !== null ? self::rgb($stroke) . ' RG ' . self::n($lw) . ' w ' : '');
        if ($r > 0) { // sudut membulat (kurva Bezier)
            $k = $r * 0.5523;
            $p = self::n($x + $r) . ' ' . self::n($y2) . ' m ' . self::n($x + $w - $r) . ' ' . self::n($y2) . ' l '
                . self::n($x + $w - $r + $k) . ' ' . self::n($y2) . ' ' . self::n($x + $w) . ' ' . self::n($y2 + $r - $k) . ' ' . self::n($x + $w) . ' ' . self::n($y2 + $r) . ' c '
                . self::n($x + $w) . ' ' . self::n($y2 + $h - $r) . ' l '
                . self::n($x + $w) . ' ' . self::n($y2 + $h - $r + $k) . ' ' . self::n($x + $w - $r + $k) . ' ' . self::n($y2 + $h) . ' ' . self::n($x + $w - $r) . ' ' . self::n($y2 + $h) . ' c '
                . self::n($x + $r) . ' ' . self::n($y2 + $h) . ' l '
                . self::n($x + $r - $k) . ' ' . self::n($y2 + $h) . ' ' . self::n($x) . ' ' . self::n($y2 + $h - $r + $k) . ' ' . self::n($x) . ' ' . self::n($y2 + $h - $r) . ' c '
                . self::n($x) . ' ' . self::n($y2 + $r) . ' l '
                . self::n($x) . ' ' . self::n($y2 + $r - $k) . ' ' . self::n($x + $r - $k) . ' ' . self::n($y2) . ' ' . self::n($x + $r) . ' ' . self::n($y2) . ' c h';
            $this->out('q ' . $set . $p . ' ' . $op . ' Q');
        } else {
            $this->out('q ' . $set . self::n($x) . ' ' . self::n($y2) . ' ' . self::n($w) . ' ' . self::n($h) . ' re ' . $op . ' Q');
        }
    }

    public function triangle(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3, string $fill): void
    {
        $this->out('q ' . self::rgb($fill) . ' rg ' . self::n($x1) . ' ' . self::n($this->h - $y1) . ' m ' . self::n($x2) . ' ' . self::n($this->h - $y2) . ' l '
            . self::n($x3) . ' ' . self::n($this->h - $y3) . ' l h f Q');
    }

    public function output(): string
    {
        $total = count($this->pages);
        if ($this->footer) {
            $simpan = $this->cur;
            for ($i = 0; $i < $total; $i++) {
                $this->cur = $i;
                ($this->footer)($this, $i + 1, $total);
            }
            $this->cur = $simpan;
        }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $total; $i++) {
            $kids[] = (6 + 2 * $i) . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        foreach ([3 => 'Helvetica', 4 => 'Helvetica-Bold', 5 => 'Helvetica-Oblique'] as $no => $nama) {
            $objs[$no] = '<< /Type /Font /Subtype /Type1 /BaseFont /' . $nama . ' /Encoding /WinAnsiEncoding >>';
        }
        for ($i = 0; $i < $total; $i++) {
            $po = 6 + 2 * $i;
            $co = $po + 1;
            $objs[$po] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::n($this->w) . ' ' . self::n($this->h) . '] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents ' . $co . ' 0 R >>';
            $data = $this->pages[$i];
            $filter = '';
            if (function_exists('gzcompress')) {
                $data = gzcompress($data, 6);
                $filter = ' /Filter /FlateDecode';
            }
            $objs[$co] = '<< /Length ' . strlen($data) . $filter . " >>\nstream\n" . $data . "\nendstream";
        }
        $infoNo = 6 + 2 * $total;
        $d = '';
        foreach ($this->info as $k => $v) {
            // teks Info ditulis sebagai UTF-16BE ber-BOM supaya tanda baca seperti “—” terbaca benar di semua pembaca PDF
            $d .= '/' . $k . ' <FEFF' . strtoupper(bin2hex(mb_convert_encoding($v, 'UTF-16BE', 'UTF-8'))) . '> ';
        }
        $zona = date('O'); // mis. +0700 → +07'00'
        $objs[$infoNo] = '<< ' . $d . "/CreationDate (D:" . date('YmdHis') . substr($zona, 0, 3) . "'" . substr($zona, 3, 2) . "') >>";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offset = [];
        foreach ($objs as $no => $isi) {
            $offset[$no] = strlen($pdf);
            $pdf .= $no . " 0 obj\n" . $isi . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . ($infoNo + 1) . "\n0000000000 65535 f \n";
        for ($no = 1; $no <= $infoNo; $no++) {
            $pdf .= sprintf("%010d 00000 n \n", $offset[$no]);
        }
        $pdf .= "trailer\n<< /Size " . ($infoNo + 1) . ' /Root 1 0 R /Info ' . $infoNo . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    /** Kirim ke browser. $unduh = true memaksa unduh, bila tidak dibuka di tab (bisa langsung dicetak). */
    public function kirim(string $namaBerkas, bool $unduh = false): void
    {
        $data = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $namaBerkas) . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $data;
        exit;
    }
}
