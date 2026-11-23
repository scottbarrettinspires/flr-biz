<?php
// Minimal QR Code generator (byte mode, error correction level M or H, versions 1-10: URLs up to 213 characters at M, 119 at H).
// No dependencies. qr_matrix() returns a 2D array of bools; qr_svg() and qr_png() render it with a 4-module quiet zone.

function qr_matrix(string $text, ?int $forceMask = null, string $ecc = 'M'): ?array {
    // per version at level M: [data codewords per block group list [[count, dataCw]...], ecCw per block]
    static $VM = [1 => [[[1, 16]], 10], 2 => [[[1, 28]], 16], 3 => [[[1, 44]], 26], 4 => [[[2, 32]], 18], 5 => [[[2, 43]], 24],
                 6 => [[[4, 27]], 16], 7 => [[[4, 31]], 18], 8 => [[[2, 38], [2, 39]], 22], 9 => [[[3, 36], [2, 37]], 22], 10 => [[[4, 43], [1, 44]], 26]];
    static $VH = [1 => [[[1, 9]], 17], 2 => [[[1, 16]], 28], 3 => [[[2, 13]], 22], 4 => [[[4, 9]], 16], 5 => [[[2, 11], [2, 12]], 22],
                  6 => [[[4, 15]], 28], 7 => [[[4, 13], [1, 14]], 26], 8 => [[[4, 14], [2, 15]], 26], 9 => [[[4, 12], [4, 13]], 24], 10 => [[[6, 15], [2, 16]], 28]];
    static $ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    $V = $ecc === 'H' ? $VH : $VM;
    $bytes = array_values(unpack('C*', $text) ?: []);
    $n = count($bytes);
    $ver = 0;
    foreach ($V as $v => [$groups, $ec]) {
        $cap = 0; foreach ($groups as [$c, $d]) $cap += $c * $d;
        $lb = $v >= 10 ? 16 : 8;
        if (4 + $lb + 8 * $n <= 8 * $cap) { $ver = $v; break; }
    }
    if (!$ver) return null;
    [$groups, $ecLen] = $V[$ver];
    $dataCw = 0; foreach ($groups as [$c, $d]) $dataCw += $c * $d;

    // bit stream: mode 0100, 8-bit length, data, terminator, pad
    $bits = '0100' . str_pad(decbin($n), $ver >= 10 ? 16 : 8, '0', STR_PAD_LEFT);
    foreach ($bytes as $b) $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    $bits .= str_repeat('0', min(4, $dataCw * 8 - strlen($bits)));
    $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
    $cw = array_map('bindec', str_split($bits, 8));
    for ($pad = 0; count($cw) < $dataCw; $pad ^= 1) $cw[] = $pad ? 0x11 : 0xEC;

    // Reed-Solomon
    $exp = []; $log = []; $x = 1;
    for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    $gen = [1];
    for ($i = 0; $i < $ecLen; $i++) {
        $next = array_fill(0, count($gen) + 1, 0);
        foreach ($gen as $j => $g) {
            $next[$j] ^= $g;
            $next[$j + 1] ^= $g ? $exp[$log[$g] + $i] : 0;
        }
        $gen = $next;
    }
    $blocks = []; $ecs = []; $pos = 0;
    foreach ($groups as [$c, $d]) for ($i = 0; $i < $c; $i++) {
        $blk = array_slice($cw, $pos, $d); $pos += $d;
        $rem = array_merge($blk, array_fill(0, $ecLen, 0));
        for ($j = 0; $j < $d; $j++) {
            $f = $rem[$j];
            if ($f) for ($k = 1; $k <= $ecLen; $k++) $rem[$j + $k] ^= $gen[$k] ? $exp[$log[$f] + $log[$gen[$k]]] : 0;
        }
        $blocks[] = $blk; $ecs[] = array_slice($rem, $d);
    }
    $final = []; $maxd = 0; foreach ($blocks as $b) $maxd = max($maxd, count($b));
    for ($i = 0; $i < $maxd; $i++) foreach ($blocks as $b) if (isset($b[$i])) $final[] = $b[$i];
    for ($i = 0; $i < $ecLen; $i++) foreach ($ecs as $e) $final[] = $e[$i];
    $stream = '';
    foreach ($final as $b) $stream .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    if ($ver >= 2 && $ver <= 6) $stream .= str_repeat('0', 7);

    // function patterns
    $N = 17 + 4 * $ver;
    $m = array_fill(0, $N, array_fill(0, $N, 0));      // module values
    $fn = array_fill(0, $N, array_fill(0, $N, false)); // reserved
    $set = function ($r, $c, $v) use (&$m, &$fn, $N) { if ($r >= 0 && $c >= 0 && $r < $N && $c < $N) { $m[$r][$c] = $v ? 1 : 0; $fn[$r][$c] = true; } };
    foreach ([[0, 0], [0, $N - 7], [$N - 7, 0]] as [$r0, $c0]) {
        for ($dr = -1; $dr <= 7; $dr++) for ($dc = -1; $dc <= 7; $dc++) {
            $in = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
            $v = $in && ($dr == 0 || $dr == 6 || $dc == 0 || $dc == 6 || ($dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4));
            $set($r0 + $dr, $c0 + $dc, $v);
        }
    }
    for ($i = 8; $i < $N - 8; $i++) { $set(6, $i, $i % 2 == 0); $set($i, 6, $i % 2 == 0); }
    $ap = $ALIGN[$ver];
    foreach ($ap as $r) foreach ($ap as $c) {
        if (($r == 6 && $c == 6) || ($r == 6 && $c == end($ap)) || ($r == end($ap) && $c == 6)) continue;
        for ($dr = -2; $dr <= 2; $dr++) for ($dc = -2; $dc <= 2; $dc++) $set($r + $dr, $c + $dc, max(abs($dr), abs($dc)) != 1);
    }
    $set($N - 8, 8, 1); // dark module
    for ($i = 0; $i < 9; $i++) { if ($i == 6) continue; $set(8, $i, 0); $set($i, 8, 0); }  // reserve format areas (timing row/col stay)
    for ($i = 0; $i < 8; $i++) { $set(8, $N - 1 - $i, 0); $set($N - 1 - $i, 8, 0); }
    $set($N - 8, 8, 1);
    if ($ver >= 7) {
        $rem = $ver; for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $vb = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) { $bit = ($vb >> $i) & 1; $a = intdiv($i, 3); $b = $i % 3 + $N - 11; $set($a, $b, $bit); $set($b, $a, $bit); }
    }

    // place data bits (zigzag)
    $data = $m; $idx = 0; $len = strlen($stream); $up = true;
    for ($col = $N - 1; $col > 0; $col -= 2) {
        if ($col == 6) $col--;
        for ($i = 0; $i < $N; $i++) {
            $r = $up ? $N - 1 - $i : $i;
            for ($k = 0; $k < 2; $k++) {
                $c = $col - $k;
                if ($fn[$r][$c]) continue;
                $data[$r][$c] = ($idx < $len && $stream[$idx] === '1') ? 1 : 0; $idx++;
            }
        }
        $up = !$up;
    }

    $masks = [fn($r, $c) => ($r + $c) % 2 == 0, fn($r, $c) => $r % 2 == 0, fn($r, $c) => $c % 3 == 0, fn($r, $c) => ($r + $c) % 3 == 0,
              fn($r, $c) => (intdiv($r, 2) + intdiv($c, 3)) % 2 == 0, fn($r, $c) => ($r * $c) % 2 + ($r * $c) % 3 == 0,
              fn($r, $c) => (($r * $c) % 2 + ($r * $c) % 3) % 2 == 0, fn($r, $c) => (($r + $c) % 2 + ($r * $c) % 3) % 2 == 0];
    $best = null; $bestScore = PHP_INT_MAX;
    for ($mk = 0; $mk < 8; $mk++) {
        if ($forceMask !== null && $mk !== $forceMask) continue;
        $t = $data;
        for ($r = 0; $r < $N; $r++) for ($c = 0; $c < $N; $c++) if (!$fn[$r][$c] && $masks[$mk]($r, $c)) $t[$r][$c] ^= 1;
        // format info (level M = 00, H = 10)
        $fd = (($ecc === 'H' ? 2 : 0) << 3) | $mk; $rem = $fd; for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $fmt = ((($fd << 10) | $rem) ^ 0x5412);
        for ($i = 0; $i < 15; $i++) {
            $bit = ($fmt >> $i) & 1;
            if ($i < 6) $t[$i][8] = $bit; elseif ($i == 6) $t[7][8] = $bit; elseif ($i == 7) $t[8][8] = $bit; elseif ($i == 8) $t[8][7] = $bit; else $t[8][14 - $i] = $bit;
            if ($i < 8) $t[8][$N - 1 - $i] = $bit; else $t[$N - 15 + $i][8] = $bit;
        }
        $s = qr_penalty($t, $N);
        if ($s < $bestScore) { $bestScore = $s; $best = $t; }
    }
    return array_map(fn($row) => array_map(fn($v) => (bool)$v, $row), $best);
}

function qr_penalty(array $t, int $N): int {
    $p = 0;
    $lines = [];
    for ($i = 0; $i < $N; $i++) { $lines[] = $t[$i]; $lines[] = array_column($t, $i); }
    foreach ($lines as $ln) {
        $run = 1;
        for ($i = 1; $i < $N; $i++) {
            if ($ln[$i] === $ln[$i - 1]) { $run++; } else { if ($run >= 5) $p += $run - 2; $run = 1; }
        }
        if ($run >= 5) $p += $run - 2;
        $s = implode('', $ln);
        $p += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
    }
    for ($r = 0; $r < $N - 1; $r++) for ($c = 0; $c < $N - 1; $c++)
        if ($t[$r][$c] === $t[$r][$c + 1] && $t[$r][$c] === $t[$r + 1][$c] && $t[$r][$c] === $t[$r + 1][$c + 1]) $p += 3;
    $dark = 0; foreach ($t as $row) $dark += array_sum($row);
    $p += 10 * intdiv(abs($dark * 20 - $N * $N * 10), $N * $N);
    return $p;
}

function qr_svg(string $text, int $quiet = 4): ?string {
    $m = qr_matrix($text); if (!$m) return null;
    $n = count($m); $s = $n + 2 * $quiet; $d = '';
    foreach ($m as $r => $row) { $c = 0; while ($c < $n) {
        if (!$row[$c]) { $c++; continue; }
        $st = $c; while ($c < $n && $row[$c]) $c++;
        $d .= 'M' . ($st + $quiet) . ' ' . ($r + $quiet) . 'h' . ($c - $st) . 'v1h-' . ($c - $st) . 'z';
    } }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $s . ' ' . $s . '" shape-rendering="crispEdges"><rect width="' . $s . '" height="' . $s . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
}

function qr_png(string $text, int $targetPx = 1200, int $quiet = 4): ?string {
    $m = qr_matrix($text); if (!$m) return null;
    $n = count($m); $s = $n + 2 * $quiet; $scale = max(4, intdiv($targetPx, $s));
    $im = imagecreatetruecolor($s * $scale, $s * $scale);
    $w = imagecolorallocate($im, 255, 255, 255); $b = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $w);
    foreach ($m as $r => $row) foreach ($row as $c => $v) if ($v) imagefilledrectangle($im, ($c + $quiet) * $scale, ($r + $quiet) * $scale, ($c + $quiet + 1) * $scale - 1, ($r + $quiet + 1) * $scale - 1, $b);
    ob_start(); imagepng($im); return ob_get_clean();
}


/** Square, centered-on-top crop of the agent's photo (or the LeadStream icon when there is none) as a JPEG/PNG data URI for the middle of a branded QR code. */
function qr_logo_file(string $code): string {
    foreach ([UPLOAD_DIR . "/$code.jpg", dirname(__DIR__) . "/assets/agents/$code.jpg"] as $f) if (is_file($f)) return $f;
    return dirname(__DIR__) . '/assets/leadstream-favicon.png';
}
function qr_logo_image(string $code, int $px) {
    $raw = @file_get_contents(qr_logo_file($code)); $src = $raw ? @imagecreatefromstring($raw) : false;
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
    $sx = intdiv($w - $side, 2); $sy = $h > $w ? intdiv(($h - $side), 5) : 0;     // headshots: keep the top of a tall photo
    $dst = imagecreatetruecolor($px, $px); imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $px, $px, $side, $side);
    return $dst;
}

/** Branded QR (error correction H) with the agent's photo in a white circle in the middle. */
const QR_HOUSE = [12,3, 22,11.5, 19,11.5, 19,21, 13.5,21, 13.5,15, 10.5,15, 10.5,21, 5,21, 5,11.5, 2,11.5];   // house icon (24x24 grid) for listing and open house codes

function qr_svg_branded(string $text, string $code, int $quiet = 4, string $style = 'photo'): ?string {
    $m = qr_matrix($text, null, 'H'); if (!$m) return null;
    $n = count($m); $s = $n + 2 * $quiet; $d = '';
    foreach ($m as $r => $row) { $c = 0; while ($c < $n) {
        if (!$row[$c]) { $c++; continue; }
        $st = $c; while ($c < $n && $row[$c]) $c++;
        $d .= 'M' . ($st + $quiet) . ' ' . ($r + $quiet) . 'h' . ($c - $st) . 'v1h-' . ($c - $st) . 'z';
    } }
    $cx = $s / 2; $ro = round($n * 0.145, 2); $ri = round($n * 0.12, 2);
    if ($style === 'house') {
        $k = round(2 * $ri * 0.92 / 24, 4); $ox = round($cx - 12 * $k, 3); $oy = round($cx - 12.2 * $k, 3);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $s . ' ' . $s . '"><rect width="' . $s . '" height="' . $s . '" fill="#fff"/>'
            . '<path d="' . $d . '" fill="#000" shape-rendering="crispEdges"/><circle cx="' . $cx . '" cy="' . $cx . '" r="' . $ro . '" fill="#fff"/>'
            . '<polygon transform="translate(' . $ox . ' ' . $oy . ') scale(' . $k . ')" fill="#0e3a4d" points="' . implode(' ', array_map(null, array_map(fn($i) => QR_HOUSE[$i] . ',' . QR_HOUSE[$i + 1], range(0, count(QR_HOUSE) - 2, 2)))) . '"/></svg>';
    }
    $img = qr_logo_image($code, 240); if (!$img) return null;
    ob_start(); imagejpeg($img, null, 88); $uri = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' . $s . ' ' . $s . '"><rect width="' . $s . '" height="' . $s . '" fill="#fff"/>'
        . '<path d="' . $d . '" fill="#000" shape-rendering="crispEdges"/><circle cx="' . $cx . '" cy="' . $cx . '" r="' . $ro . '" fill="#fff"/>'
        . '<clipPath id="lc"><circle cx="' . $cx . '" cy="' . $cx . '" r="' . $ri . '"/></clipPath>'
        . '<image href="' . $uri . '" xlink:href="' . $uri . '" x="' . ($cx - $ri) . '" y="' . ($cx - $ri) . '" width="' . (2 * $ri) . '" height="' . (2 * $ri) . '" clip-path="url(#lc)" preserveAspectRatio="xMidYMid slice"/></svg>';
}
function qr_png_branded(string $text, string $code, int $targetPx = 1200, int $quiet = 4, string $style = 'photo'): ?string {
    $m = qr_matrix($text, null, 'H'); if (!$m) return null;
    $n = count($m); $s = $n + 2 * $quiet; $scale = max(4, intdiv($targetPx, $s)); $W = $s * $scale;
    $im = imagecreatetruecolor($W, $W);
    $w = imagecolorallocate($im, 255, 255, 255); $b = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $w);
    foreach ($m as $r => $row) foreach ($row as $c => $v) if ($v) imagefilledrectangle($im, ($c + $quiet) * $scale, ($r + $quiet) * $scale, ($c + $quiet + 1) * $scale - 1, ($r + $quiet + 1) * $scale - 1, $b);
    $cx = intdiv($W, 2); $ro = (int)round($n * 0.145 * $scale); $ri = (int)round($n * 0.12 * $scale);
    imagefilledellipse($im, $cx, $cx, $ro * 2, $ro * 2, $w);
    if ($style === 'house') {
        $k = 2 * $ri * 0.92 / 24; $ox = $cx - 12 * $k; $oy = $cx - 12.2 * $k; $pts = [];
        foreach (QR_HOUSE as $i => $v) $pts[] = (int)round(($i % 2 ? $oy : $ox) + $v * $k);
        imagefilledpolygon($im, $pts, imagecolorallocate($im, 14, 58, 77));
        ob_start(); imagepng($im); return ob_get_clean();
    }
    $logo = qr_logo_image($code, $ri * 2); if (!$logo) return null;
    for ($y = 0; $y < $ri * 2; $y++) for ($x = 0; $x < $ri * 2; $x++) {                // circular crop
        $dx = $x - $ri + .5; $dy = $y - $ri + .5;
        if ($dx * $dx + $dy * $dy <= $ri * $ri) imagesetpixel($im, $cx - $ri + $x, $cx - $ri + $y, imagecolorat($logo, $x, $y));
    }
    ob_start(); imagepng($im); return ob_get_clean();
}
