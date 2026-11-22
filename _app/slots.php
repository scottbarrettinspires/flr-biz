<?php
// Listing / open house pages: permanent URLs flr.biz/<agent code>/p1/, /p2/ ... for QR codes on signs and printed materials.
// The URL never changes; the agent edits what it shows, or points it at any existing page, from the setup screen.

const SLOT_TARGETS = ['main' => 'My main page', 'buyer' => 'My buyer page', 'seller' => 'My seller page', 'faq' => 'My buyer FAQ page', 'social' => 'My social media page',
                      'app' => 'My home search app page', 'consult' => 'Book a consultation page', 'value' => 'Home value page'];

const SLOT_SEED = ['p1', 'p2', 'p3', 'p4', 'p5', 'o1'];   // created automatically for every agent

function slot_id_ok(string $id): bool { return (bool)preg_match('/^[po][0-9]{1,3}$/', $id); }
function slot_is_oh(string $id): bool { return ($id[0] ?? '') === 'o'; }
function slot_kind(string $id): string { return slot_is_oh($id) ? 'Open house page' : 'Listing page'; }
function slots_path(string $code): string { return DATA_DIR . "/$code.slots.json"; }
// Every agent always has p1-p5 (listings) and o1 (open house). Pages are never removed, so printed QR codes never go dead.
function slots_load(string $code): array {
    $f = slots_path($code); $slots = []; $corrupt = false;
    if (is_file($f)) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) $slots = $d; else { $corrupt = true; error_log("flr.biz: unreadable $f"); }
    }
    $add = false;
    foreach (SLOT_SEED as $id) if (!isset($slots[$id])) { $slots[$id] = ['title' => '']; $add = true; }
    if ($add && !$corrupt) slots_save($code, $slots);    // never overwrite a file we could not read
    uksort($slots, fn($x, $y) => [$x[0] === 'o', (int)substr($x, 1)] <=> [$y[0] === 'o', (int)substr($y, 1)]);
    return $slots;
}
function slots_save(string $code, array $slots): void {
    if (!is_dir(DATA_DIR)) { mkdir(DATA_DIR, 0755, true); file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\n"); }
    $f = slots_path($code);
    if (is_file($f)) @copy($f, $f . '.bak');             // keep the previous version
    $tmp = $f . '.tmp' . getmypid();
    file_put_contents($tmp, json_encode($slots, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    rename($tmp, $f);
}
// Headline shown on the page. A page with no property is a WELCOME page; old default headlines are corrected by page kind.
function slot_title(string $id, array $s): string {
    $t = trim((string)($s['title'] ?? '')); $addr = trim((string)($s['address'] ?? '')); $oh = slot_is_oh($id);
    if (!$oh && strcasecmp($t, 'OPEN HOUSE') === 0) $t = '';                                  // listing pages are never "open house"
    if ($addr === '' && ($t === '' || in_array(strtoupper($t), ['OPEN HOUSE', 'JUST LISTED'], true))) return 'WELCOME';
    if ($t !== '' && in_array(strtoupper($t), ['JUST LISTED', 'UNDER CONTRACT', 'COMING SOON'], true) && !empty($s['mls_status'])) $t = '';   // MLS-linked pages follow the listing's real status
    if ($t !== '') return $t;
    if ($oh) return 'OPEN HOUSE';
    $ms = (string)($s['mls_status'] ?? '');
    return in_array($ms, ['Pending', 'Active Under Contract'], true) ? 'UNDER CONTRACT' : ($ms === 'Coming Soon' ? 'COMING SOON' : 'JUST LISTED');
}
function slot_status(array $a, array $s): string {
    if ($rd = slot_redirect($a, $s)) return 'Sends visitors to ' . (SLOT_TARGETS[$s['target']] ?? 'a link you pasted');
    $addr = trim((string)($s['address'] ?? ''));
    $mls = !empty($s['mls_key']) ? ' (MLS ' . ($s['mls_status'] ?? '') . ')' : '';
    return $addr !== '' ? $addr . $mls : 'Not set up yet (shows a welcome page)';
}
function slot_photo_url(string $code, string $id): ?string {
    $f = UPLOAD_DIR . "/$code-$id.jpg";
    return is_file($f) ? "/uploads/$code-$id.jpg?v=" . filemtime($f) : null;
}
function save_slot_photo(string $code, string $id, array $f): string {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024) return 'Photo must be under 10 MB.';
    return slot_store_photo($code, $id, $f['tmp_name']);
}
/** Validate an image file, crop to 3:2 landscape and store it as the page's cover image. Returns error text or ''. */
function slot_store_photo(string $code, string $id, string $path): string {
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return 'Photo must be a JPG, PNG or WebP image.';
    if ($info[0] * $info[1] > 40000000) return 'Photo is too large; please use a smaller image.';
    $src = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($path), IMAGETYPE_PNG => @imagecreatefrompng($path), default => @imagecreatefromwebp($path) };
    if (!$src) return 'Could not read that photo.';
    $w = imagesx($src); $h = imagesy($src);
    $tw = $w; $th = (int)round($w * 2 / 3);
    if ($th > $h) { $th = $h; $tw = (int)round($h * 3 / 2); }
    $dst = imagecreatetruecolor(1200, 800);
    imagecopyresampled($dst, $src, 0, 0, intdiv($w - $tw, 2), intdiv($h - $th, 2), 1200, 800, $tw, $th);
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    imagejpeg($dst, UPLOAD_DIR . "/$code-$id.jpg", 85);
    return '';
}

// Where a "send visitors to another page" slot points. Returns a path or https URL, or null for a normal page.
function slot_redirect(array $a, array $s): ?string {
    $t = (string)($s['target'] ?? '');
    if ($t === '') return null;
    $p = paths_for($a['code']);
    return match ($t) {
        'main' => '/' . $p['main'] . '/', 'buyer' => '/' . $p['buyer'] . '/', 'seller' => '/' . $p['seller'] . '/',
        'faq' => isset($p['faq']) ? '/' . $p['faq'] . '/' : '/' . $p['main'] . '/', 'social' => '/' . $p['social'] . '/',
        'app' => '/' . $a['code'] . '/app/', 'consult' => '/' . $a['code'] . '/buyer-consult/', 'value' => '/' . $a['code'] . '/value/',
        'custom' => (preg_match('~^https://~', (string)($s['custom_url'] ?? '')) ? $s['custom_url'] : null),
        default => null,
    };
}

// Open house date/time in Eastern Time. Returns null when not set or already over.
function slot_when(array $s): ?array {
    $d = $s['oh_date'] ?? ''; $st = $s['oh_start'] ?? '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !preg_match('/^\d{2}:\d{2}$/', $st)) return null;
    $tz = new DateTimeZone('America/New_York');
    $start = DateTime::createFromFormat('Y-m-d H:i', "$d $st", $tz);
    if (!$start) return null;
    $en = $s['oh_end'] ?? '';
    $end = (preg_match('/^\d{2}:\d{2}$/', $en) ? DateTime::createFromFormat('Y-m-d H:i', "$d $en", $tz) : null) ?: (clone $start)->modify('+2 hours');
    if ($end <= $start) $end = (clone $start)->modify('+2 hours');
    if ($end < new DateTime('now', $tz)) return null;
    $fmt = fn(DateTime $t) => ltrim($t->format('g:i'), '0') . ' ' . $t->format('A');
    $rangeShort = ltrim($start->format('g'), '0') . (($start->format('i') !== '00') ? $start->format(':i') : '') . '-' . ltrim($end->format('g'), '0') . (($end->format('i') !== '00') ? $end->format(':i') : '') . ' ' . $end->format('A');
    return ['start' => $start, 'end' => $end, 'short' => $start->format('D, M j') . ' · ' . $rangeShort, 'long' => $start->format('l, F j') . ' · ' . $fmt($start) . ' to ' . $fmt($end)];
}

function render_slot_ics(array $a, string $id, array $s): ?string {
    $w = slot_when($s);
    if (!$w) return null;
    $utc = new DateTimeZone('UTC');
    $esc = fn($t) => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\;", "\\,", ' ', '\\n'], (string)$t);
    $name = ucwords(strtolower($a['name']));
    $url = 'https://' . SITE_DOMAIN . '/' . $a['code'] . '/' . $id . '/';
    $l = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . BROKERAGE . '//flr.biz//EN', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT',
          'UID:' . $a['code'] . '-' . $id . '-' . $w['start']->format('Ymd') . '@' . SITE_DOMAIN, 'DTSTAMP:' . gmdate('Ymd\THis\Z'),
          'DTSTART:' . (clone $w['start'])->setTimezone($utc)->format('Ymd\THis\Z'), 'DTEND:' . (clone $w['end'])->setTimezone($utc)->format('Ymd\THis\Z'),
          'SUMMARY:' . $esc('Open House' . (!empty($s['address']) ? ': ' . $s['address'] : '')),
          'LOCATION:' . $esc($s['address'] ?? ''), 'DESCRIPTION:' . $esc("Hosted by $name, " . BROKERAGE . ". Details: $url"), 'URL:' . $url,
          'END:VEVENT', 'END:VCALENDAR'];
    return implode("\r\n", $l) . "\r\n";
}

function render_slot(array $a, string $id, array $s): string {
    $mls = null; $gone = false;
    if (!empty($s['mls_key'])) {                      // linked to an MLS listing: follow its real status and price
        $mls = mls_refresh($a, $id, $s);
        if ($mls && mls_visible($mls)) { $s['mls_status'] = $mls['status']; if ($mls['price'] > 0) $s['price'] = mls_money($mls['price']); }
        else { $s = ['title' => '']; $mls = null; $gone = true; }   // sold, withdrawn or hidden by the MLS: the page falls back to a welcome page
    }
    $code = $a['code']; $p = paths_for($code); $name = ucwords(strtolower($a['name'])); $first = explode(' ', $name)[0];
    $addr = trim((string)($s['address'] ?? '')); $price = trim((string)($s['price'] ?? ''));
    $oh = slot_is_oh($id);
    $title = slot_title($id, $s);
    $photo = $gone ? null : slot_photo_url($code, $id);
    $heroPhoto = $photo ?: photo_url($code);   // no property photo yet: use the agent's headshot so the page is never blank
    $when = $oh ? slot_when($s) : null;
    $base = '/' . $code . '/' . $id . '/';

    $soc = contact_row($a);

    $links = '';
    if ($when) $links .= menu_button('calendar', 'Open House ' . $when['short'], $base . 'calendar/');
    if ($mls) $links .= menu_button('home', 'View Listing Details', $base . 'details/');
    elseif (!empty($s['property_url'])) $links .= menu_button('home', 'View This Home', $s['property_url'], true);
    if ($addr !== '') $links .= menu_button('pin', 'Get Directions', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr), true);
    if (!empty($a['app_url'])) $links .= menu_button('search', 'Find The Perfect Home Now', '/' . $code . '/app/');
    $links .= $oh ? menu_button('calendar', 'Book A Complimentary Consultation', '/' . $code . '/buyer-consult/')
                  : menu_button('calendar', 'Book A Private Showing', '/' . $code . '/private-showing/' . ($addr !== '' ? '?l=' . rawurlencode($id) : ''));
    $links .= menu_button('dollar', "Get Your Home's Value Instantly", '/' . $code . '/value/');
    $links .= share_button($code . '/' . $id);

    $vid = $a[$oh ? 'vid_openhouses' : 'vid_listings'] ?? '';   // one video for all listing pages, one for all open house pages (set on the dashboard)
    $video = $vid !== '' ? '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/' . h($vid) . '" title="Video" loading="lazy" allowfullscreen></iframe></div>' : '';
    $welcome = trim((string)($s['welcome'] ?? ''));
    $ap = photo_url($code);
    $by = '<a class="presby" href="/' . $p['main'] . '/">' . ($ap ? '<img src="' . h($ap) . '" alt="">' : '')
        . '<span><small>Presented by</small><strong>' . h($name) . '</strong><em>' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . '</em></span></a>';
    $hero = $heroPhoto ? ' style="--hero:url(\'' . h($heroPhoto) . '\')"' : '';
    $ogt = $title . ($addr !== '' ? ' - ' . $addr : '');
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . h($ogt) . ' | ' . h($name) . ' | ' . BROKERAGE . '</title><meta property="og:title" content="' . h($ogt) . '">'
      . ($photo ? '<meta property="og:image" content="https://' . SITE_DOMAIN . h($photo) . '">' : '')
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . $base . '"><link rel="stylesheet" href="/assets/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/style.css') . '"></head><body><main class="card">'
      . '<header class="hero slothero' . ($heroPhoto ? '' : ' nophoto') . '"' . $hero . '><div class="hero-in"><h1>' . h($title) . '</h1>'
      . ($addr !== '' ? '<p class="tag">' . h($addr) . '</p>' : '') . ($price !== '' ? '<p class="headline">' . h($price) . '</p>' : '') . '</div></header>'
      . ($welcome !== '' ? '<p class="welcome">' . h($welcome) . '</p>' : '')
      . $soc . $video . '<nav class="links">' . $links . '</nav>' . $by
      . '<footer>' . BROKERAGE . ', Inc.<br><span>Equal Housing Opportunity</span></footer></main>' . fit_script() . '</body></html>';
}


/** Listing details page for an MLS-linked page: /<code>/<id>/details/. Returns null when the page has no live MLS listing. */
function render_slot_details(array $a, string $id, array $s): ?string {
    if (empty($s['mls_key'])) return null;
    $d = mls_refresh($a, $id, $s);
    if (!$d || !mls_visible($d)) return null;
    $code = $a['code']; $p = paths_for($code); $name = ucwords(strtolower($a['name'])); $base = '/' . $code . '/' . $id . '/';
    $photos = mls_photo_urls($code, $id);
    $ap = photo_url($code);
    $statusLab = ['Active' => 'Active', 'Coming Soon' => 'Coming Soon', 'Pending' => 'Pending', 'Active Under Contract' => 'Under Contract'][$d['status']] ?? $d['status'];
    $facts = [];
    if ($d['beds'] > 0) $facts[] = [(string)$d['beds'], 'Beds'];
    if ($d['baths'] > 0) $facts[] = [rtrim(rtrim(number_format($d['baths'], 1), '0'), '.'), 'Baths'];
    if ($d['sqft'] > 0) $facts[] = [number_format($d['sqft']), 'Sq Ft'];
    if ($d['year'] > 0) $facts[] = [(string)$d['year'], 'Year Built'];
    if ($d['acres'] > 0) $facts[] = [rtrim(rtrim(number_format($d['acres'], 2), '0'), '.'), 'Acres'];
    $fh = ''; foreach ($facts as [$v, $l]) $fh .= '<div><b>' . h($v) . '</b><span>' . h($l) . '</span></div>';
    $hero = $photos ? ' style="--hero:url(\'' . h($photos[0]) . '\')"' : '';
    $gal = ''; foreach (array_slice($photos, 1) as $i => $u) $gal .= '<img src="' . h($u) . '" alt="Photo ' . ($i + 2) . ' of ' . h($d['street']) . '" loading="lazy">';
    $links = menu_button('calendar', 'Book A Private Showing', '/' . $code . '/private-showing/?l=' . rawurlencode($id))
           . menu_button('pin', 'Get Directions', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($d['address']), true)
           . (!empty($a['app_url']) ? menu_button('search', 'Find The Perfect Home Now', '/' . $code . '/app/') : '');
    $upd = $d['modified'] !== '' ? date('F j, Y', strtotime($d['modified']) ?: time()) : date('F j, Y');
    $by = '<a class="presby" href="/' . $p['main'] . '/">' . ($ap ? '<img src="' . h($ap) . '" alt="">' : '')
        . '<span><small>Presented by</small><strong>' . h($name) . '</strong><em>' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . '</em></span></a>';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>' . h($d['address']) . ' | ' . h($name) . ' | ' . BROKERAGE . '</title><meta name="robots" content="noindex">'
      . ($photos ? '<meta property="og:image" content="https://' . SITE_DOMAIN . h(strtok($photos[0], '?')) . '">' : '')
      . '<link rel="stylesheet" href="/assets/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/style.css') . '"></head><body><main class="card wide">'
      . '<a class="back vback" href="' . h($base) . '">&larr; Back</a>'
      . '<header class="hero slothero' . ($photos ? '' : ' nophoto') . '"' . $hero . '><div class="hero-in"><p class="statusbadge">' . h($statusLab) . '</p><h1>' . h($d['street']) . '</h1>'
      . '<p class="tag">' . h(trim($d['city'] . ', ' . $d['state'] . ' ' . $d['zip'], ' ,')) . '</p>' . ($d['price'] > 0 ? '<p class="headline">' . h(mls_money($d['price'])) . '</p>' : '') . '</div></header>'
      . ($fh !== '' ? '<div class="facts">' . $fh . '</div>' : '')
      . ($d['type'] !== '' ? '<p class="ptype">' . h($d['type']) . ' &middot; MLS #' . h($d['number']) . '</p>' : '')
      . ($gal !== '' ? '<div class="gal">' . $gal . '</div>' : '')
      . ($d['remarks'] !== '' ? '<p class="remarks">' . nl2br(h($d['remarks'])) . '</p>' : '')
      . '<nav class="links">' . $links . share_button($code . '/' . $id . '/details') . '</nav>' . $by
      . '<p class="mlsdisc">' . ($d['office'] !== '' ? 'Listing courtesy of ' . h($d['office']) . '. ' : '') . 'Information is deemed reliable but is not guaranteed and should be independently verified. Listing data last updated ' . h($upd) . '.</p>'
      . '<footer>' . BROKERAGE . ', Inc.<br><span>Equal Housing Opportunity</span></footer></main>' . fit_script() . '</body></html>';
}
