<?php
// Agent branding: two logos (full color + all white, transparent) and up to three brand colors. The mobile pages are themed from the colors.

const BRAND_KEYS = ['brand_primary', 'brand_2', 'brand_3'];

/** '#RRGGBB' / 'RRGGBB' / '#RGB' / '255, 128, 0' -> '#rrggbb', or '' when it is not a color. */
function brand_hex(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    if (preg_match('/^#?([0-9a-f]{3})$/i', $s, $m)) { $x = $m[1]; return strtolower('#' . $x[0] . $x[0] . $x[1] . $x[1] . $x[2] . $x[2]); }
    if (preg_match('/^#?([0-9a-f]{6})$/i', $s, $m)) return strtolower('#' . $m[1]);
    if (preg_match('/^(?:rgb\()?\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*[, ]\s*(\d{1,3})\s*\)?$/i', $s, $m) && max($m[1], $m[2], $m[3]) <= 255) return sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
    return '';
}
function brand_rgb(string $hex): array { return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))]; }
function brand_to_hex(array $c): string { return sprintf('#%02x%02x%02x', max(0, min(255, round($c[0]))), max(0, min(255, round($c[1]))), max(0, min(255, round($c[2])))); }
function brand_hsl(array $c): array {
    [$r, $g, $b] = [$c[0] / 255, $c[1] / 255, $c[2] / 255]; $mx = max($r, $g, $b); $mn = min($r, $g, $b); $l = ($mx + $mn) / 2; $d = $mx - $mn;
    if ($d == 0) return [0, 0, $l];
    $s = $l > .5 ? $d / (2 - $mx - $mn) : $d / ($mx + $mn);
    $h = $mx == $r ? ($g - $b) / $d + ($g < $b ? 6 : 0) : ($mx == $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
    return [$h / 6, $s, $l];
}
function brand_from_hsl(float $h, float $s, float $l): array {
    if ($s == 0) return [$l * 255, $l * 255, $l * 255];
    $q = $l < .5 ? $l * (1 + $s) : $l + $s - $l * $s; $p = 2 * $l - $q;
    $f = function ($t) use ($p, $q) { if ($t < 0) $t += 1; if ($t > 1) $t -= 1; return 255 * ($t < 1 / 6 ? $p + ($q - $p) * 6 * $t : ($t < .5 ? $q : ($t < 2 / 3 ? $p + ($q - $p) * (2 / 3 - $t) * 6 : $p))); };
    return [$f($h + 1 / 3), $f($h), $f($h - 1 / 3)];
}
function brand_lum(array $c): float {
    $f = fn($v) => ($v /= 255) <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
    return .2126 * $f($c[0]) + .7152 * $f($c[1]) + .0722 * $f($c[2]);
}
function brand_contrast(array $a, array $b): float { $x = brand_lum($a); $y = brand_lum($b); return (max($x, $y) + .05) / (min($x, $y) + .05); }
/** Lightens a color (keeping its hue) until it reads on the dark page background. */
function brand_readable(array $c, array $bg, float $min = 4.5): array {
    [$h, $s, $l] = brand_hsl($c);
    while (brand_contrast($c, $bg) < $min && $l < .95) { $l += .03; $c = brand_from_hsl($h, $s, $l); }
    return $c;
}

/** CSS variables for an agent's colors, or null when no primary color is set (the pages keep the default FLR look). */
function brand_theme(array $a): ?array {
    $p = brand_hex((string)($a['brand_primary'] ?? '')); if ($p === '') return null;
    $c2 = brand_hex((string)($a['brand_2'] ?? '')); $c3 = brand_hex((string)($a['brand_3'] ?? ''));
    [$h, $s, $l] = brand_hsl(brand_rgb($p));
    $sbg = min($s * .75, .6); $sbt = min($s, .65);
    $bg = brand_from_hsl($h, $sbg, .10); $bg2 = brand_from_hsl($h, $sbg, .19);
    $lb = ($l >= .15 && $l <= .42) ? $l : max(.15, min(.42, $l));                    // buttons use the primary itself when it is dark enough
    $bb = $lb === $l ? brand_rgb($p) : brand_from_hsl($h, $sbt, $lb);
    $btn_a = brand_from_hsl($h, $sbt, $lb + .07); $btn_hi = brand_from_hsl($h, $sbt, $lb + .12); $btn_lo = brand_from_hsl($h, $sbt, max(.08, $lb - .07));
    $acc = $c2 !== '' ? brand_rgb($c2) : brand_from_hsl($h, max(.45, min(.75, $s)), .68);
    $acc = brand_readable($acc, $bg);
    $acc2 = $c3 !== '' ? brand_readable(brand_rgb($c3), $bg) : $acc;
    $ink = brand_contrast($acc, [17, 17, 17]) >= brand_contrast($acc, [255, 255, 255]) ? '#111111' : '#ffffff';
    return ['--bg' => brand_to_hex($bg), '--bg2' => brand_to_hex($bg2), '--bg-rgb' => implode(',', array_map('round', $bg)),
            '--accent' => brand_to_hex($acc), '--accent-rgb' => implode(',', array_map('round', $acc)), '--accent2' => brand_to_hex($acc2), '--accent-ink' => $ink,
            '--btn-a' => brand_to_hex($btn_a), '--btn-b' => brand_to_hex($bb), '--btn-hi' => brand_to_hex($btn_hi), '--btn-lo' => brand_to_hex($btn_lo)];
}
/** <style> block that re-colors a public mobile page with the agent's brand colors ('' when they have none). */
function brand_style(array $a): string {
    $t = brand_theme($a); if (!$t) return '';
    $css = ''; foreach ($t as $k => $v) $css .= $k . ':' . $v . ';';
    return '<style>:root{' . $css . '}</style><meta name="theme-color" content="' . $t['--bg'] . '">';
}

function logo_file(string $code, string $kind): string { return UPLOAD_DIR . "/$code-logo-$kind.png"; }
function logo_url(string $code, string $kind): ?string { $f = logo_file($code, $kind); return is_file($f) ? "/uploads/$code-logo-$kind.png?v=" . filemtime($f) : null; }

/** Validates an uploaded logo, keeps its transparency, scales it down to fit 900x400 and stores it as a PNG. Returns [error, note]. */
function save_logo(string $code, string $kind, array $f): array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['', ''];
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 5 * 1024 * 1024) return ['Logo files must be under 5 MB.', ''];
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return ['Upload your logo as a PNG (or WebP) with a transparent background.', ''];
    $src = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($f['tmp_name']) : @imagecreatefromwebp($f['tmp_name']);
    if (!$src) return ['That logo file could not be read.', ''];
    $w = imagesx($src); $h = imagesy($src); $k = min(1, 900 / $w, 400 / $h); $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $dst = imagecreatetruecolor($nw, $nh); imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $transparent = false; for ($i = 0; $i < 400 && !$transparent; $i++) { $c = imagecolorat($dst, mt_rand(0, $nw - 1), mt_rand(0, $nh - 1)); if ((($c >> 24) & 127) > 0) $transparent = true; }
    foreach ([[0, 0], [$nw - 1, 0], [0, $nh - 1], [$nw - 1, $nh - 1]] as [$x, $y]) if ((imagecolorat($dst, $x, $y) >> 24) & 127) $transparent = true;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    imagepng($dst, logo_file($code, $kind));
    return ['', $transparent ? '' : 'The ' . ($kind === 'white' ? 'white' : 'color') . ' logo saved, but it does not look like it has a transparent background. A transparent PNG blends best into your pages.'];
}

/** Handles the My Brand form post. Returns [errors, notes]. */
function brand_save(string $code): array {
    $err = []; $notes = [];
    $vals = [];
    foreach (['brand_primary' => 'Primary color', 'brand_2' => 'Second color', 'brand_3' => 'Third color'] as $k => $lab) {
        $raw = trim((string)($_POST[$k] ?? '')); $hex = brand_hex($raw);
        if ($raw !== '' && $hex === '') $err[] = "$lab: enter a hex code like #0A6EBD, or pick it with the color picker.";
        $vals[$k] = $hex;
    }
    if ($vals['brand_primary'] === '' && ($vals['brand_2'] !== '' || $vals['brand_3'] !== '')) $err[] = 'Add a primary color first.';
    foreach (['color' => 'logo_color', 'white' => 'logo_white'] as $kind => $field) {
        if (!empty($_POST['remove_' . $field])) { @unlink(logo_file($code, $kind)); continue; }
        [$e, $n] = save_logo($code, $kind, $_FILES[$field] ?? []); if ($e) $err[] = $e; if ($n) $notes[] = $n;
    }
    if (!$err) save_overrides($code, $vals);
    return [$err, $notes];
}

/** The "My Brand" card on the Overview page. */
function brand_form_html(array $a, string $key, array $err = [], array $notes = [], bool $saved = false): string {
    $code = $a['code']; $o = '';
    if ($saved) $o .= '<div class="note ok">Saved. Your mobile pages now use your brand colors.</div>';
    foreach ($err as $e) $o .= '<div class="note err">' . h($e) . '</div>';
    foreach ($notes as $n) $o .= '<div class="note">' . h($n) . '</div>';
    $o .= '<form method="post" enctype="multipart/form-data" class="form wideform brandform"><input type="hidden" name="a" value="' . h($code) . '"><input type="hidden" name="k" value="' . h($key) . '"><input type="hidden" name="form" value="brand">';
    $o .= '<div class="logos">';
    foreach (['color' => ['logo_color', 'Logo in full color', 'Transparent PNG. Shown on white and light backgrounds.', 'logoprev light'],
              'white' => ['logo_white', 'Logo in all white', 'Transparent PNG, every part of the logo white. Shown on your dark mobile pages.', 'logoprev dark']] as $kind => [$f, $lab, $hint, $cls]) {
        $u = logo_url($code, $kind);
        $o .= '<div class="logobox"><label for="' . $f . '">' . h($lab) . '</label><div class="' . $cls . '">' . ($u ? '<img src="' . h($u) . '" alt="">' : '<span>No logo yet</span>') . '</div>'
            . '<input id="' . $f . '" name="' . $f . '" type="file" accept="image/png,image/webp"><small>' . h($hint) . '</small>'
            . ($u ? '<label class="rm"><input type="checkbox" name="remove_' . $f . '" value="1"> Remove this logo</label>' : '') . '</div>';
    }
    $o .= '</div><h3 class="bh">Brand colors</h3><small class="bhs">Pick a color or type its hex code (like #0A6EBD). Your primary color sets the look of your pages; the other two are accents. Colors are adjusted slightly where needed so text stays easy to read.</small><div class="colors">';
    foreach (['brand_primary' => 'Primary color', 'brand_2' => 'Second color (optional)', 'brand_3' => 'Third color (optional)'] as $k => $lab) {
        $v = brand_hex((string)($a[$k] ?? ''));
        $o .= '<div class="colorrow"><label for="t_' . $k . '">' . h($lab) . '</label><div class="cr"><span class="sw" id="s_' . $k . '" style="background:' . ($v !== '' ? h($v) : 'transparent') . '"></span>'
            . '<input type="color" id="p_' . $k . '" value="' . ($v !== '' ? h($v) : '#0e3a4d') . '" aria-label="Pick ' . h($lab) . '">'
            . '<input type="text" id="t_' . $k . '" name="' . $k . '" value="' . h($v) . '" placeholder="#0A6EBD" maxlength="20" autocomplete="off"></div></div>';
    }
    $o .= '</div><button class="btn contact" type="submit"><span class="t">Save my brand</span></button></form>';
    $o .= '<script>(function(){function hex(s){s=s.trim();var m=s.match(/^#?([0-9a-f]{3})$/i);if(m){var x=m[1];return "#"+x[0]+x[0]+x[1]+x[1]+x[2]+x[2]}m=s.match(/^#?([0-9a-f]{6})$/i);return m?"#"+m[1]:""}'
        . '["brand_primary","brand_2","brand_3"].forEach(function(k){var t=document.getElementById("t_"+k),p=document.getElementById("p_"+k),s=document.getElementById("s_"+k);'
        . 't.addEventListener("input",function(){var h=hex(t.value);if(h){p.value=h.toLowerCase();s.style.background=h}else if(!t.value.trim()){s.style.background="transparent"}});'
        . 'p.addEventListener("input",function(){t.value=p.value.toUpperCase();s.style.background=p.value})})})();</script>';
    return $o;
}
