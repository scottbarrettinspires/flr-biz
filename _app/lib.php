<?php
// flr.biz agent mobile pages: data + rendering. No external dependencies.
const SITE_DOMAIN = 'flr.biz';
const BROKERAGE   = 'Florida Luxury Realty';
const ROOT_DIR    = __DIR__ . '/..';
const DATA_DIR    = ROOT_DIR . '/_data';
const UPLOAD_DIR  = ROOT_DIR . '/uploads';
const EDITABLE    = ['phone', 'email', 'website', 'instagram', 'facebook', 'tiktok', 'youtube_id'];

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function seed(): array {
    static $d; 
    return $d ??= json_decode(file_get_contents(__DIR__ . '/agents.json'), true)['agents'];
}

/** Map of every public path name -> [agent code, view]. e.g. abb1 => [ab1, buyer] */
function routes(): array {
    $m = [];
    foreach (seed() as $a) {
        preg_match('/^([a-z]+?)(\d+)$/', $a['code'], $x);
        $m[$a['code']] = [$a['code'], 'main'];
        $m[$x[1] . 'b' . $x[2]] = [$a['code'], 'buyer'];
        $m[$x[1] . 's' . $x[2]] = [$a['code'], 'seller'];
    }
    return $m;
}

function paths_for(string $code): array {
    $p = [];
    foreach (routes() as $name => [$c, $v]) if ($c === $code) $p[$v] = $name;
    return $p;
}

function agent(string $code): ?array {
    foreach (seed() as $a) {
        if ($a['code'] !== $code) continue;
        $f = DATA_DIR . "/$code.json";
        if (is_file($f)) {
            $o = json_decode((string)file_get_contents($f), true) ?: [];
            foreach (EDITABLE as $k) if (array_key_exists($k, $o)) $a[$k === 'youtube_id' ? 'youtube_id' : $k] = $o[$k];
        }
        return $a;
    }
    return null;
}

function photo_url(string $code): ?string {
    $f = UPLOAD_DIR . "/$code.jpg";
    return is_file($f) ? "/uploads/$code.jpg?v=" . filemtime($f) : null;
}

function initials_text(string $name): string {
    preg_match_all('/[A-Za-z]+/', $name, $m);
    return strtoupper(substr($m[0][0] ?? '?', 0, 1) . (count($m[0]) > 1 ? substr(end($m[0]), 0, 1) : ''));
}

const ICONS = [
 'phone'=>'<path d="M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11 11 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.6 3.6a1 1 0 0 1-.25 1z"/>',
 'email'=>'<path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7.2L4.6 7H19.4zM4 9.3V17h16V9.3l-8 5.6z"/>',
 'instagram'=>'<path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm5 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5zM17.3 6a1.1 1.1 0 1 1-1.1 1.1A1.1 1.1 0 0 1 17.3 6z"/>',
 'facebook'=>'<path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.5 1.6-1.5h1.7V4.4A22 22 0 0 0 14.4 4C12 4 10.3 5.5 10.3 8.2v2.6H7.5V14h2.8v8z"/>',
 'tiktok'=>'<path d="M16.6 2h-3.2v13.4a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .9.1V9.3a6.1 6.1 0 1 0 5.2 6V8.7a7.3 7.3 0 0 0 4.3 1.4V6.9a4.3 4.3 0 0 1-4.3-4.9z"/>',
 'website'=>'<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.9 9h-3a15.7 15.7 0 0 0-1.2-5.2A8 8 0 0 1 18.9 11zM12 4c.8 1 1.7 3.2 2 7H10c.3-3.8 1.2-6 2-7zM9.3 5.8A15.7 15.7 0 0 0 8.1 11h-3a8 8 0 0 1 4.2-5.2zM5.1 13h3a15.7 15.7 0 0 0 1.2 5.2A8 8 0 0 1 5.1 13zM12 20c-.8-1-1.7-3.2-2-7h4c-.3 3.8-1.2 6-2 7zm2.7-1.8A15.7 15.7 0 0 0 15.9 13h3a8 8 0 0 1-4.2 5.2z"/>',
 'contact'=>'<path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4 0-8 2-8 5v2h16v-2c0-3-4-5-8-5z"/>',
];
function icon(string $n): string { return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">' . ICONS[$n] . '</svg>'; }

function link_href(array $a, array $l): string {
    $p = paths_for($a['code']);
    if (isset($l['view'])) return '/' . $p[$l['view']] . '/';
    if (isset($l['lofty'])) return rtrim($a['lofty_base'], '/') . $l['lofty'];
    return $l['url'];
}

function render_page(array $a, string $view): string {
    $code = $a['code']; $main = $view === 'main'; $v = $a[$view]; $p = paths_for($code);
    $title = $main ? $a['name'] . ' - ' . $a['title'] : $v['name'];
    $headline = $main ? $a['headline'] : $v['headline'];
    $tag = $main ? $a['tagline'] : $v['sub'];
    $photo = photo_url($code);

    $soc = [];
    if (!empty($a['phone'])) $soc[] = ['phone', 'tel:' . preg_replace('/[^0-9+]/', '', $a['phone']), 'Call', false];
    if (!empty($a['email'])) $soc[] = ['email', 'mailto:' . $a['email'], 'Email', false];
    foreach (['instagram'=>'Instagram','facebook'=>'Facebook','tiktok'=>'TikTok','website'=>'Website'] as $k => $lab)
        if (!empty($a[$k])) $soc[] = [$k, $a[$k], $lab, true];
    $socHtml = '';
    foreach ($soc as [$k, $u, $lab, $ext])
        $socHtml .= '<a class="soc" href="' . h($u) . '" aria-label="' . $lab . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . icon($k) . '</a>';

    $links = '';
    foreach ($v['links'] as $l) {
        $ext = !isset($l['view']);
        $links .= '<a class="btn" href="' . h(link_href($a, $l)) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '><span class="t">'
            . h($l['title']) . '</span>' . (!empty($l['sub']) ? '<span class="sub">' . h($l['sub']) . '</span>' : '') . '</a>' . "\n";
    }
    $video = (!empty($a['youtube_id']) && $main)
        ? '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . h($a['youtube_id']) . '" title="Video" loading="lazy" allowfullscreen></iframe></div>' : '';
    $back = $main ? '' : '<a class="back" href="/' . $p['main'] . '/">&larr; ' . h(ucwords(strtolower($a['name']))) . '</a>';
    $contact = $main ? '<a class="btn contact" href="/' . $p['main'] . '/contact.vcf" download><span class="t">' . icon('contact') . ' Add me to your contacts</span></a>' : '';
    $img = $photo ? '<img src="' . h($photo) . '" alt="' . h(ucwords(strtolower($a['name']))) . '">' : '';
    $og = $photo ? '<meta property="og:image" content="https://' . SITE_DOMAIN . h($photo) . '">' : '';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . h($title) . ' | ' . BROKERAGE . '</title><meta name="description" content="' . h($tag) . '">'
      . '<meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($tag) . '">' . $og
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . '/' . $p[$view] . '/"><link rel="stylesheet" href="/assets/style.css"></head><body><main class="card">'
      . $back . '<div class="avatar" data-initials="' . h(initials_text($a['name'])) . '">' . $img . '</div>'
      . '<h1>' . h($title) . '</h1><p class="tag">' . h($tag) . '</p>' . ($main ? '<p class="headline">' . h($headline) . '</p>' : '')
      . '<div class="socials">' . $socHtml . '</div>' . $video . '<nav class="links">' . $links . $contact . '</nav>'
      . '<footer>' . BROKERAGE . '<br><span>Equal Housing Opportunity</span></footer></main></body></html>';
}

function render_vcard(array $a): string {
    $parts = preg_split('/\s+/', trim(ucwords(strtolower($a['name']))));
    $first = array_shift($parts);
    $l = ['BEGIN:VCARD', 'VERSION:3.0', 'N:' . implode(' ', $parts) . ';' . $first . ';;;', 'FN:' . ucwords(strtolower($a['name'])),
          'ORG:' . BROKERAGE, 'TITLE:' . $a['title']];
    if (!empty($a['phone'])) $l[] = 'TEL;TYPE=CELL:' . $a['phone'];
    if (!empty($a['email'])) $l[] = 'EMAIL:' . $a['email'];
    if (!empty($a['website'])) $l[] = 'URL:' . $a['website'];
    $l[] = 'URL:https://' . SITE_DOMAIN . '/' . $a['code'] . '/';
    $l[] = 'END:VCARD';
    return implode("\r\n", $l) . "\r\n";
}

// ---------- input cleaning (setup form) ----------
function clean_url(string $s, ?string $handleBase = null): string {
    $s = trim($s);
    if ($s === '') return '';
    if ($handleBase && preg_match('/^@?[A-Za-z0-9._]{1,40}$/', $s)) return $handleBase . ltrim($s, '@');
    if (!preg_match('~^https?://~i', $s)) $s = 'https://' . $s;
    $u = parse_url($s);
    if (!$u || empty($u['host']) || !preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $u['host']) || strlen($s) > 300) return '';
    return 'https://' . $u['host'] . ($u['path'] ?? '') . (isset($u['query']) ? '?' . $u['query'] : '');
}

function youtube_id(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) return $s;
    if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})~', $s, $m)) return $m[1];
    return '';
}

function save_overrides(string $code, array $vals): void {
    if (!is_dir(DATA_DIR)) { mkdir(DATA_DIR, 0755, true); file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\n"); }
    file_put_contents(DATA_DIR . "/$code.json", json_encode($vals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Validate an uploaded photo, crop to a square and re-encode as a clean JPEG. Returns error text or ''. */
function save_photo(string $code, array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 6 * 1024 * 1024) return 'Photo must be under 6 MB.';
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return 'Photo must be a JPG, PNG or WebP image.';
    if ($info[0] * $info[1] > 40000000) return 'Photo is too large; please use a smaller image.';
    $src = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']), IMAGETYPE_PNG => @imagecreatefrompng($f['tmp_name']), default => @imagecreatefromwebp($f['tmp_name']) };
    if (!$src) return 'Could not read that photo.';
    $w = imagesx($src); $h = imagesy($src); $s = min($w, $h);
    $dst = imagecreatetruecolor(600, 600);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $s, 2), intdiv($h - $s, 3), 600, 600, $s, $s);
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    imagejpeg($dst, UPLOAD_DIR . "/$code.jpg", 88);
    return '';
}
