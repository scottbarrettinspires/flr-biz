<?php
// flr.biz agent mobile pages: data + rendering. No external dependencies.
const SITE_DOMAIN = 'flr.biz';
const BROKERAGE   = 'Florida Luxury Realty';
const ROOT_DIR    = __DIR__ . '/..';
const DATA_DIR    = ROOT_DIR . '/_data';
const UPLOAD_DIR  = ROOT_DIR . '/uploads';
const APP_URL_BASE = 'https://bk.homestack.com/floridaluxuryrealtyhomesearch/?aik=';   // current Lofty app download page; agents can replace it in setup
const EDITABLE    = ['phone', 'email', 'website', 'instagram', 'facebook', 'tiktok', 'linkedin', 'x', 'bio', 'join_url', 'pvid', 'brand_primary', 'brand_2', 'brand_3', 'youtube_id', 'vid_steps', 'vid_broker', 'vid_avoid', 'app_url', 'vid_listings', 'vid_openhouses'];

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
        if (isset($a['faq'])) $m[$x[1] . 'f' . $x[2]] = [$a['code'], 'faq'];
    }
    return $m;
}

function paths_for(string $code): array {
    $p = [];
    foreach (routes() as $name => [$c, $v]) if ($c === $code) $p[$v] = $name;
    if (isset($p['main'])) $p['social'] = $p['main'] . '/social';
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
        if (!array_key_exists('app_url', $a) && !empty($a['app_code'])) $a['app_url'] = APP_URL_BASE . rawurlencode($a['app_code']);
        return $a;
    }
    return null;
}

function photo_url(string $code): ?string {
    $f = UPLOAD_DIR . "/$code.jpg";
    if (is_file($f)) return "/uploads/$code.jpg?v=" . filemtime($f);
    $g = dirname(__DIR__) . "/assets/agents/$code.jpg";      // seeded headshot from the shared drive
    return is_file($g) ? "/assets/agents/$code.jpg?v=" . filemtime($g) : null;
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
 'linkedin'=>'<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
 'x'=>'<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
 'tiktok'=>'<path d="M16.6 2h-3.2v13.4a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .9.1V9.3a6.1 6.1 0 1 0 5.2 6V8.7a7.3 7.3 0 0 0 4.3 1.4V6.9a4.3 4.3 0 0 1-4.3-4.9z"/>',
 'website'=>'<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.9 9h-3a15.7 15.7 0 0 0-1.2-5.2A8 8 0 0 1 18.9 11zM12 4c.8 1 1.7 3.2 2 7H10c.3-3.8 1.2-6 2-7zM9.3 5.8A15.7 15.7 0 0 0 8.1 11h-3a8 8 0 0 1 4.2-5.2zM5.1 13h3a15.7 15.7 0 0 0 1.2 5.2A8 8 0 0 1 5.1 13zM12 20c-.8-1-1.7-3.2-2-7h4c-.3 3.8-1.2 6-2 7zm2.7-1.8A15.7 15.7 0 0 0 15.9 13h3a8 8 0 0 1-4.2 5.2z"/>',
 'contact'=>'<path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4 0-8 2-8 5v2h16v-2c0-3-4-5-8-5z"/>',
];
function icon(string $n): string { return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">' . ICONS[$n] . '</svg>'; }

const BTN_ICONS = [
 'home'=>'<path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>',
 'sell'=>'<path d="M21.41 11.58l-9-9A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/>',
 'calendar'=>'<path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/>',
 'dollar'=>'<path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/>',
 'help'=>'<path d="M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z"/>',
 'list'=>'<path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>',
 'doc'=>'<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
 'download'=>'<path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>',
 'pin'=>'<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
 'photo'=>'<path d="M22 16V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2zm-11-4l2.03 2.71L16 11l4 5H8l3-4zM2 6v14c0 1.1.9 2 2 2h14v-2H4V6H2z"/>',
 'heart'=>'<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
 'chat'=>'<path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 9h12v2H6V9zm8 5H6v-2h8v2zm4-6H6V6h12v2z"/>',
 'search'=>'<path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>',
 'share'=>'<path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>',
 'link'=>'<path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7a5 5 0 0 0 0 10h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4a5 5 0 0 0 0-10z"/>',
];
function btn_icon(array $l): string {
    $k = $l['icon'] ?? null;
    if (!$k) {
        $v = $l['view'] ?? ''; $pg = $l['page'] ?? ''; $u = strtolower($l['url'] ?? '');
        $map = ['buyer'=>'home','seller'=>'sell','faq'=>'help','buyer-consult'=>'calendar','seller-consult'=>'calendar','value'=>'dollar',
                'seller-guide'=>'download','buyer-guide'=>'download','buyer-steps'=>'list','buyer-broker-agreement'=>'doc','avoid-buyer-agent-fee'=>'dollar','app'=>'search'];
        $k = $map[$v] ?? $map[$pg] ?? (str_contains($u, 'sellers') ? 'sell' : (str_contains($u, 'buyers') ? 'home' : 'link'));
    }
    return '<span class="ic"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">' . (BTN_ICONS[$k] ?? ICONS[$k] ?? BTN_ICONS['link']) . '</svg></span>';
}

function link_href(array $a, array $l): string {
    $p = paths_for($a['code']);
    if (isset($l['view'])) return '/' . $p[$l['view']] . '/';
    if (isset($l['page'])) return '/' . $a['code'] . '/' . $l['page'] . '/';
    if (isset($l['lofty'])) return rtrim($a['lofty_base'], '/') . $l['lofty'];
    return $l['url'];
}

function social_links(array $a): array {
    $defs = [['instagram', 'Follow Me On Instagram'], ['facebook', 'Like My Page On Facebook'], ['tiktok', 'Follow Me On TikTok'], ['linkedin', 'Connect With Me On LinkedIn'], ['x', 'Follow Me On X'], ['website', 'Visit My Website']];
    $out = [];
    foreach ($defs as [$k, $t]) if (!empty($a[$k])) $out[] = ['title' => $t, 'url' => $a[$k], 'icon' => $k];
    return $out;
}

function fit_script(): string {
    return '<script>(function(){function fit(){var ts=[].slice.call(document.querySelectorAll("nav.links .btn:not(.contact) .t")),m=18;ts.forEach(function(t){var b=t.parentElement;if(!b||!b.clientWidth)return;var s=18;t.style.fontSize=s+"px";while(s>12&&t.scrollWidth>b.clientWidth){s-=0.25;t.style.fontSize=s+"px";}if(s<m)m=s;});ts.forEach(function(t){t.style.fontSize=m+"px";});}fit();addEventListener("resize",fit);if(document.fonts&&document.fonts.ready)document.fonts.ready.then(fit);})();</script>';
}
function menu_button(string $icon, string $title, string $href, bool $ext = false, string $attrs = ''): string {
    return '<a class="btn" href="' . h($href) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . ($attrs ? ' ' . $attrs : '') . '>' . btn_icon(['icon' => $icon])
        . '<span class="tx"><span class="t">' . h($title) . '</span></span></a>' . "\n";
}

/** "Share This" button: opens the public share page (QR code + email / text / Facebook / Instagram) for this page. */
function share_href(string $path): string { return '/share/?u=' . rawurlencode('https://' . SITE_DOMAIN . '/' . trim($path, '/') . '/'); }
/** Label for the share button/page: "Share This With Someone" on the app page and listing / open house pages, else "Share Me With Others". */
function share_label(string $path): string {
    $seg = explode('/', trim($path, '/'))[1] ?? '';
    return ($seg === 'app' || ($seg !== '' && slot_id_ok($seg))) ? 'Share This With Someone' : 'Share Me With Others';
}
function share_button(string $path): string {
    return '<a class="btn contact" href="' . h(share_href($path)) . '"><span class="t"><svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">' . BTN_ICONS['share'] . '</svg> ' . share_label($path) . '</span></a>' . "\n";
}

/** Optional YouTube video an agent adds to any page (keyed by view or landing slug). */
function page_video(array $a, string $key): string {
    $id = $a['pvid'][$key] ?? '';
    return $id ? '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . h($id) . '" title="Video" loading="lazy" allowfullscreen></iframe></div>' : '';
}
function render_page(array $a, string $view): string {
    if ($view === 'social') $a['social'] = ['name' => 'CONNECT WITH ME', 'sub' => 'Follow along on social media', 'headline' => '', 'section' => '', 'links' => social_links($a)];
    $code = $a['code']; $main = $view === 'main'; $v = $a[$view]; $p = paths_for($code);
    $title = $main ? $a['name'] . ' - ' . $a['title'] : $v['name'];
    $tag = $main ? $a['tagline'] : $v['sub'];
    $photo = photo_url($code);

    $socHtml = contact_row($a);

    if ($main && social_links($a)) $v['links'][] = ['title' => 'Connect With Me On Social Media', 'view' => 'social', 'icon' => 'share'];
    if ($view === 'social' && !$v['links']) $links0 = '<div class="note">Social links are coming soon.</div>';
    $links = !empty($v['section']) ? '<h2 class="sec">' . h($v['section']) . '</h2>' : '';
    foreach ($v['links'] as $l) {
        $isApp = ($l['page'] ?? '') === 'app';    // home-search app page: shows once the agent adds its link
        if ($isApp ? empty($a['app_url']) : !empty($l['pending'])) continue;
        $ext = !isset($l['view']) && !isset($l['page']);
        $links .= '<a class="btn" href="' . h(link_href($a, $l)) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . btn_icon($l) . '<span class="tx"><span class="t">'
            . h($l['title']) . '</span></span></a>' . "\n";
    }
    $video = $main ? (!empty($a['youtube_id'])
        ? '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . h($a['youtube_id']) . '" title="Video" loading="lazy" allowfullscreen></iframe></div>' : '') : page_video($a, $view);
    $back = $main ? '' : '<a class="back" href="/' . $p['main'] . '/">&larr; ' . h(ucwords(strtolower($a['name']))) . '</a>';
    $contact = $main ? '<a class="btn contact" href="/' . $p['main'] . '/contact.vcf" download><span class="t">' . icon('contact') . ' Add me to your contacts</span></a>' : '';
    $img = $photo ? '<img src="' . h($photo) . '" alt="' . h(ucwords(strtolower($a['name']))) . '">' : '';
    $og = $photo ? '<meta property="og:image" content="https://' . SITE_DOMAIN . h($photo) . '">' : '';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . h($title) . ' | ' . BROKERAGE . '</title><meta name="description" content="' . h($tag) . '">'
      . '<meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($tag) . '">' . $og
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . '/' . $p[$view] . '/"><link rel="stylesheet" href="/assets/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/style.css') . '">' . brand_style($a) . '</head><body><main class="card">'
      . '<header class="hero' . ($photo ? '' : ' nophoto') . '"' . ($photo ? ' style="--hero:url(\'' . h($photo) . '\')"' : '') . '>'
      . '<div class="hero-in">' . $back . '<div class="avatar" data-initials="' . h(initials_text($a['name'])) . '">' . $img . '</div>'
      . '<h1>' . h($title) . '</h1><p class="tag">' . h($tag) . '</p>' . '</div></header>'
      . '<div class="socials">' . $socHtml . '</div>' . $video . '<nav class="links">' . ($links0 ?? '') . $links . $contact . share_button($p[$view]) . '</nav>'
      . page_footer($a) . '</main>' . fit_script() . '</body></html>';
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
    $f = DATA_DIR . "/$code.json";
    $old = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    file_put_contents($f, json_encode(array_merge($old, $vals), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);   // merge: each setup screen saves only its own fields
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


// ---------- config + landing pages + leads ----------
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/slots.php';
require_once __DIR__ . '/qr.php';
require_once __DIR__ . '/share.php';
require_once __DIR__ . '/mls.php';
require_once __DIR__ . '/about.php';
require_once __DIR__ . '/lspitch.php';
require_once __DIR__ . '/brand.php';

function config(): array {
    static $c;
    if ($c === null) {
        $c = ['broker_email' => 'broker@myflroffice.com', 'webhook_url' => '',
              'terms_url' => 'https://floridaluxuryrealty.com/site-privacy-terms', 'privacy_url' => 'https://floridaluxuryrealty.com/site-privacy-terms',
              'consent_url' => 'https://floridaluxuryrealty.com/site-privacy-terms'];
        $f = DATA_DIR . '/config.json';
        if (is_file($f)) $c = (json_decode((string)file_get_contents($f), true) ?: []) + $c;
    }
    return $c;
}

function consent_html(array $a): string {
    $c = config(); $b = h(BROKERAGE);
    return 'By checking this box, I give my electronic signature and agree to the <a href="' . h($c['consent_url']) . '" target="_blank" rel="noopener">Electronic Disclosure Consent Agreement</a>, '
      . 'and to receive recurring marketing messages from or on behalf of ' . $b . ' and ' . h(ucwords(strtolower($a['name']))) . ', including automated calls, texts and prerecorded voice messages, at the number I provided. '
      . 'Message frequency varies and message and data rates may apply. Reply STOP to stop texts or HELP for help. I also agree to the '
      . '<a href="' . h($c['terms_url']) . '" target="_blank" rel="noopener">Terms of Service</a> and <a href="' . h($c['privacy_url']) . '" target="_blank" rel="noopener">Privacy Policy</a>. '
      . 'Consent is not a condition of any purchase. You can also call for direct assistance.';
}

function agent_card(array $a, bool $compact = false): string {
    if ($compact) {
        $p = paths_for($a['code']); $photo = photo_url($a['code']);
        return '<header class="minihead"><a class="back" href="/' . $p['main'] . '/">&larr; My page</a><div class="mh">'
          . ($photo ? '<img src="' . h($photo) . '" alt="">' : '') . '<div><strong>' . h(ucwords(strtolower($a['name']))) . '</strong><span>' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . '</span></div></div></header>';
    }
    // Landing-page header: headshot as background, agent details over the bottom of the photo
    $p = paths_for($a['code']); $photo = photo_url($a['code']);
    $rows = '';
    if (!empty($a['license'])) $rows .= '<div>Broker License ID: ' . h($a['license']) . '</div>';
    if (!empty($a['office']))  $rows .= '<div>' . h($a['office']) . '</div>';
    $ic = contact_row($a, true);
    if ($ic) $rows .= $ic;
    return '<header class="hero' . ($photo ? '' : ' nophoto') . '"' . ($photo ? ' style="--hero:url(\'' . h($photo) . '\')"' : '') . '>'
      . '<div class="hero-in"><a class="back" href="/' . $p['main'] . '/">&larr; My page</a>'
      . '<h1>' . h(ucwords(strtolower($a['name']))) . '</h1><p class="tag">' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . '</p>'
      . '<div class="ainfo">' . $rows . '</div></div></header>';
}

function consult_fields(): string {
    [$min, $max] = consult_window();
    $o = '<div class="sched"><h2 class="sch">Choose a date</h2>'
       . '<div id="cal" class="cal" data-min="' . $min . '" data-max="' . $max . '"><div class="calh"><button type="button" id="cal_prev" aria-label="Previous month">&#8249;</button><strong id="cal_title"></strong><button type="button" id="cal_next" aria-label="Next month">&#8250;</button></div>'
       . '<div class="calw"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div><div id="cal_grid" class="calg"></div></div>'
       . '<input type="hidden" id="pref_date" name="pref_date" value="">'
       . '<div id="times_wrap" hidden><h2 class="sch" id="times_label">Choose a time</h2><div class="times">';
    foreach (consult_times() as $t) $o .= '<label class="tm"><input type="radio" name="pref_time" value="' . h($t) . '"><span>' . h($t) . '</span></label>';
    $o .= '</div></div><p id="cal_err" class="note err" hidden>Please choose a date and a time first.</p>'
       . '<h2 class="sch">Your contact information</h2></div>'
       . '<script>(function(){var root=document.getElementById("cal");if(!root)return;var min=root.dataset.min,max=root.dataset.max;'
       . 'function P(s){var a=s.split("-");return new Date(+a[0],+a[1]-1,+a[2]);}'
       . 'function S(d){return d.getFullYear()+"-"+("0"+(d.getMonth()+1)).slice(-2)+"-"+("0"+d.getDate()).slice(-2);}'
       . 'var dmin=P(min),dmax=P(max),cur=new Date(dmin.getFullYear(),dmin.getMonth(),1);'
       . 'var grid=document.getElementById("cal_grid"),title=document.getElementById("cal_title"),prev=document.getElementById("cal_prev"),next=document.getElementById("cal_next"),hid=document.getElementById("pref_date"),tw=document.getElementById("times_wrap"),tl=document.getElementById("times_label");'
       . 'function render(){var y=cur.getFullYear(),m=cur.getMonth();title.textContent=cur.toLocaleDateString("en-US",{month:"long",year:"numeric"});grid.innerHTML="";'
       . 'var f=new Date(y,m,1).getDay(),n=new Date(y,m+1,0).getDate(),i;for(i=0;i<f;i++)grid.appendChild(document.createElement("span"));'
       . 'for(i=1;i<=n;i++){(function(d){var s=S(new Date(y,m,d)),b=document.createElement("button");b.type="button";b.textContent=d;if(s<min||s>max)b.disabled=true;if(s===hid.value)b.className="sel";'
       . 'b.onclick=function(){hid.value=s;tw.hidden=false;tl.textContent="Choose a time on "+P(s).toLocaleDateString("en-US",{weekday:"long",month:"long",day:"numeric"})+" (Eastern Time)";render();};grid.appendChild(b);})(i);}'
       . 'prev.disabled=cur<=new Date(dmin.getFullYear(),dmin.getMonth(),1);next.disabled=cur>=new Date(dmax.getFullYear(),dmax.getMonth(),1);}'
       . 'prev.onclick=function(){cur=new Date(cur.getFullYear(),cur.getMonth()-1,1);render();};next.onclick=function(){cur=new Date(cur.getFullYear(),cur.getMonth()+1,1);render();};render();'
       . 'var form=root.closest("form"),err=document.getElementById("cal_err");form.addEventListener("submit",function(e){if(!hid.value||!form.querySelector("input[name=pref_time]:checked")){e.preventDefault();err.hidden=false;root.scrollIntoView({behavior:"smooth",block:"center"});}else{err.hidden=true;}});'
       . '})();</script>';
    return $o;
}

/** The home for a private showing: looked up from the listing page the visitor came from (?l=p1), so it is already filled in. */
function showing_home(array $a): ?string {
    $id = (string)($_GET['l'] ?? '');
    if (!slot_id_ok($id)) return null;
    $addr = trim((string)(slots_load($a['code'])[$id]['address'] ?? ''));
    return $addr !== '' ? $addr : null;
}

function lead_form_html(array $a, array $d, string $return = ''): string {
    $code = $a['code']; $ts = time(); $extra = '';
    if ($d['type'] === 'value') $extra .= '<label for="address">Property address *</label><input id="address" name="address" type="text" required maxlength="200" placeholder="Street, city, state, ZIP" autocomplete="street-address">';
    if (!empty($d['address_field'])) {
        if ($home = showing_home($a)) $extra .= '<input type="hidden" name="address" value="' . h($home) . '"><div class="homebox"><small>Private showing at</small><strong>' . h($home) . '</strong></div>';
        else $extra .= '<label for="address">Home you would like to see *</label><input id="address" name="address" type="text" required maxlength="200" placeholder="Street, city, state" autocomplete="off">';
    }
    return '<form method="post" action="/lead" class="form">'
      . '<input type="hidden" name="agent" value="' . h($code) . '"><input type="hidden" name="form" value="' . h($d['form']) . '"><input type="hidden" name="ts" value="' . $ts . '">'
      . ($return !== '' ? '<input type="hidden" name="return" value="' . h($return) . '">' : '')
      . '<div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="company" tabindex="-1" autocomplete="off"></label></div>'
      . $extra
      . (!empty($d['time_field']) ? consult_fields() : '')
      . '<label for="name">Name *</label><input id="name" name="name" type="text" required maxlength="100" autocomplete="name">'
      . '<label for="phone">Phone *</label><input id="phone" name="phone" type="tel" required maxlength="24" autocomplete="tel">'
      . '<label for="email">Email *</label><input id="email" name="email" type="email" required maxlength="120" autocomplete="email">'
      . (!empty($d['time_field']) ? '<label for="msg">Anything you would like me to know (optional)</label><textarea id="msg" name="message" rows="3" maxlength="800"></textarea>' : '')
      . '<label class="consent"><input type="checkbox" name="consent" value="1" required> <span>' . consent_html($a) . '</span></label>'
      . '<button class="btn contact" type="submit"><span class="t">' . h($d['button']) . '</span></button></form>';
}

function render_landing(array $a, string $slug, bool $done): string {
    $defs = page_defs(); $d = $defs[$slug]; $code = $a['code']; $name = ucwords(strtolower($a['name']));
    if ($slug === 'private-showing' && ($home = showing_home($a))) $d['sub'] = $home;
    $title = $d['title'];
    $body = '';
    foreach ($d['body'] as $para) $body .= '<p class="lead">' . h($para) . '</p>';
    $fileUrl = null;
    if (($d['file'] ?? null) && is_file(ROOT_DIR . '/guides/' . $d['file'])) $fileUrl = '/guides/' . $d['file'];

    if ($d['type'] === 'app') {
        require_once __DIR__ . '/appview.php';
        $inner = render_app_inner($a, $name);
        $done = null;
    }
    if ($d['type'] === 'video') {
        $vid = $a[$d['vkey']] ?? '';
        $inner = $vid ? '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . h($vid) . '" title="' . h($title) . '" loading="lazy" allowfullscreen></iframe></div>'
                      : '<div class="note">My video for this question is coming soon. In the meantime, ask me directly.</div>';
        $cd = page_defs()['buyer-consult'];
        $inner .= '<h2 class="vsched">Book A Complimentary Consultation</h2>';
        $inner .= $done
            ? '<div class="note ok">Submitted successfully. Thank you, ' . h(explode(' ', $name)[0]) . ' will confirm your appointment time shortly.</div>'
            : lead_form_html($a, $cd, $slug);
        $paths = paths_for($code);
        if (isset($paths['faq'])) $inner .= '<a class="back" href="/' . $paths['faq'] . '/">&larr; More answers to your questions</a>';
        $done = null;
    }
    if ($done === null) {
        // video page: $inner already built
    } elseif ($done) {
        $inner = '<div class="note ok">Submitted successfully. Thank you' . ($d['type'] === 'guide' ? '.' : ', ' . h(explode(' ', $name)[0]) . (($d['type'] === 'consult') ? ' will confirm your appointment time shortly.' : ' will be in touch shortly.')) . '</div>';
        if ($d['type'] === 'guide') $inner .= $fileUrl
            ? '<p class="lead">Your guide should download automatically. If it does not, <a href="' . h($fileUrl) . '" download>click here</a>.</p><meta http-equiv="refresh" content="1;url=' . h($fileUrl) . '">'
            : '<p class="lead">Your guide is on its way. ' . h($name) . ' will send it to you shortly.</p>';
        if ($d['type'] === 'value') $inner .= '<p class="lead">Your home market value report is being prepared and will be sent to you shortly.</p>';
    } else {
        $inner = lead_form_html($a, $d);
    }
    if ($d['type'] !== 'video') $inner = page_video($a, $slug) . $inner;
    $h2 = isset($d['h2']) ? '<h2>' . h($d['h2']) . '</h2>' : '';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . h($title) . ' | ' . h($name) . ' | ' . BROKERAGE . '</title><meta name="description" content="' . h($d['sub']) . '">'
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . '/' . $code . '/' . $slug . '/"><link rel="stylesheet" href="/assets/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/style.css') . '">' . brand_style($a) . '</head><body>'
      . '<main class="card wide">' . ($d['type'] === 'video' ? '<a class="back vback" href="/' . paths_for($code)['main'] . '/">&larr; My page</a>' : agent_card($a, in_array($d['type'], ['app', 'consult', 'value'], true))) . ($d['type'] === 'app' ? '<h1 class="pt ahd">The Home Search App for Every Home and Detail.</h1>' : '<h1 class="pt' . ($slug === 'private-showing' ? ' gold' : '') . '">' . h($title) . '</h1>') . '<p class="tag">' . h($d['sub']) . '</p>' . $h2 . $body . $inner
      . '<nav class="links sharenav">' . share_button($code . '/' . $slug) . '</nav>'
      . page_footer($a) . '</main></body></html>';
}

/** Store a lead, email the agent + broker, optionally post to a webhook (e.g. Zapier -> Google Sheet). Returns true when stored. */
function save_lead(array $a, array $lead): bool {
    if (!is_dir(DATA_DIR)) { mkdir(DATA_DIR, 0755, true); file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\n"); }
    $lead += ['received' => gmdate('c'), 'agent' => $a['code'], 'consent_version' => CONSENT_VERSION];
    $ok = file_put_contents(DATA_DIR . '/leads.jsonl', json_encode($lead, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX) !== false;

    $c = config();
    $to = array_values(array_unique(array_filter([$a['email'] ?? '', $c['broker_email']], fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    if ($to && function_exists('mail')) {
        $clean = fn($s) => str_replace(["\r", "\n"], ' ', (string)$s);
        $subject = 'New lead: ' . $clean($lead['form']) . ' - ' . $clean($lead['name']);
        $body = "New lead for " . $a['name'] . " (" . $a['code'] . ")\nForm: {$lead['form']}\nName: {$lead['name']}\nPhone: {$lead['phone']}\nEmail: {$lead['email']}\n"
              . (!empty($lead['address']) ? "Address: {$lead['address']}\n" : '') . (!empty($lead['pref_date']) ? "Requested appointment: {$lead['pref_date']} at {$lead['pref_time']} Eastern\n" : '') . (!empty($lead['message']) ? "Message: {$lead['message']}\n" : '')
              . "Received: {$lead['received']}\nConsent given: yes (version " . CONSENT_VERSION . ")\n";
        $hdr = "From: " . BROKERAGE . " <noreply@" . SITE_DOMAIN . ">\r\nReply-To: " . $clean($lead['email']) . "\r\nContent-Type: text/plain; charset=utf-8";
        @mail(implode(',', $to), $subject, $body, $hdr);
    }
    if (!empty($c['webhook_url']) && preg_match('~^https://~', $c['webhook_url'])) {
        @file_get_contents($c['webhook_url'], false, stream_context_create(['http' => ['method' => 'POST', 'timeout' => 4,
            'header' => "Content-Type: application/json\r\n", 'content' => json_encode($lead, JSON_UNESCAPED_UNICODE)]]));
    }
    return $ok;
}
