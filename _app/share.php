<?php
// LeadStream Systems agent dashboard helpers: auth, tab bar, link list, share + QR cards.

function setup_agent(): ?array {
    $code = strtolower((string)($_REQUEST['a'] ?? '')); $key = (string)($_REQUEST['k'] ?? '');
    $agent = null; foreach (seed() as $s) if ($s['code'] === $code) $agent = $s;
    if ($agent && !empty($agent['setup_hash']) && hash_equals($agent['setup_hash'], hash('sha256', $key))) return $agent;
    sleep(1); http_response_code(403);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/style.css">'
       . '<main class="card"><h1>Setup link not valid</h1><p class="tag">Ask the brokerage office for your personal setup link.</p></main>';
    return null;
}

function dash_open(string $active, array $agent, string $key, string $title, string $sub = ''): string {
    $code = $agent['code']; $q = '?a=' . urlencode($code) . '&k=' . urlencode($key); $name = ucwords(strtolower($agent['name']));
    $main = '/' . (paths_for($code)['main'] ?? $code) . '/';
    $ic = ['home' => '<path d="M3 11l9-8 9 8v10h-6v-6H9v6H3z"/>', 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7z"/>',
           'house' => '<path d="M3 21V10l9-6 9 6v11h-7v-6h-4v6z"/>', 'qr' => '<path d="M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM14 14h3v3h-3zM18 18h3v3h-3zM18 14h3v2h-3zM14 18h2v3h-2z"/>'];
    $items = ['overview' => ['Overview', '/setup/', 'home'],
              'listings' => ['Listings & Open Houses', '/setup/listings/', 'house'], 'links' => ['My Mobile Pages', '/setup/links/', 'qr']];
    $nav = '';
    foreach ($items as $k => [$lab, $path, $i]) $nav .= '<a href="' . h($path . $q) . '"' . ($k === $active ? ' class="on"' : '') . '><svg viewBox="0 0 24 24" aria-hidden="true">' . $ic[$i] . '</svg><span>' . h($lab) . '</span></a>';
    $photo = photo_url($code);
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">'
      . '<title>' . h($title) . ' | LeadStream Systems</title><link rel="icon" href="/assets/leadstream-favicon.png">'
      . '<link rel="stylesheet" href="/assets/dashboard.css?v=' . filemtime(dirname(__DIR__) . '/assets/dashboard.css') . '"></head><body class="dash">'
      . '<header class="dtop"><a class="dlogo" href="/setup/' . h($q) . '"><img src="/assets/leadstream-logo.png" alt="LeadStream Systems"></a>'
      . '<div class="dtop-r"><a class="dview" href="' . h($main) . '" target="_blank" rel="noopener">View my mobile page &#8599;</a>'
      . '<span class="dwho">' . ($photo ? '<img src="' . h($photo) . '" alt="">' : '') . '<b>' . h($name) . '</b></span></div></header>'
      . '<div class="dbody"><aside class="dside"><nav>' . $nav . '</nav><p class="dpow">Florida Luxury Realty<br>Agent Dashboard</p></aside>'
      . '<main class="dmain"><h1>' . h($title) . '</h1>' . ($sub !== '' ? '<p class="dsub">' . $sub . '</p>' : '');
}
function dash_close(): string { return '<p class="dfoot">LeadStream Systems &middot; ' . h(BROKERAGE) . ' &middot; Keep your dashboard link private.</p></main></div>' . share_script() . '</body></html>'; }

// Every public page this agent has, as [label, absolute URL].
function agent_pages(array $agent): array {
    $code = $agent['code']; $p = paths_for($code); $abs = fn($path) => 'https://' . SITE_DOMAIN . '/' . $path . '/';
    $out = [['Mobile Business Card', $abs($p['main']), 'youtube_id']];
    if (isset($p['buyer']))  $out[] = ['Buyer page', $abs($p['buyer'])];
    if (isset($p['seller'])) $out[] = ['Seller page', $abs($p['seller'])];
    if (isset($p['faq']))    $out[] = ['Buyer FAQ page', $abs($p['faq'])];
    $out[] = ['Social media page', $abs($p['social'])];
    $labels = ['app' => 'Home search app page', 'buyer-consult' => 'Book a buyer consultation', 'private-showing' => 'Schedule a private showing', 'seller-consult' => 'Book a seller consultation',
               'seller-guide' => 'Download Free seller guide', 'buyer-guide' => 'Download Free buyer guide', 'value' => 'Home value page',
               'buyer-steps' => 'Right steps to buying a home', 'buyer-broker-agreement' => 'Why a buyer broker agreement',
               'avoid-buyer-agent-fee' => 'Avoiding the buyer agent fee'];
    foreach (page_defs() as $slug => $d) {
        if (($d['type'] ?? '') === 'video' && !isset($agent['faq'])) continue;
        $out[] = [$labels[$slug] ?? $d['title'], $abs($code . '/' . $slug), ($d['type'] ?? '') === 'video' ? ($d['vkey'] ?? '') : (($d['type'] ?? '') === 'app' ? 'app_url' : '')];
    }
    return $out;
}

function share_card(string $label, string $url, string $code, string $key, string $id = '', string $edit = '', string $extra = ''): string {
    $qs = '?a=' . urlencode($code) . '&k=' . urlencode($key) . '&u=' . urlencode($url);
    $file = preg_replace('~[^a-z0-9]+~', '-', strtolower($code . '-' . trim(preg_replace('~^https://[^/]+/~', '', $url), '/')));
    $png = '/setup/qr/' . $qs . '&fmt=png&dl=1&n=' . urlencode($file); $svg = '/setup/qr/' . $qs . '&fmt=svg&dl=1&n=' . urlencode($file);
    $body = rawurlencode($label . ': ' . $url);
    return '<section class="sharecard"' . ($id ? ' id="' . h($id) . '"' : '') . '><div class="sc-top"><div class="sc-info"><strong>' . h($label) . '</strong></div>'
      . '<div class="sc-qrbox"><img class="sc-qr" src="/setup/qr/' . h($qs) . '&fmt=svg" alt="QR code for ' . h($label) . '" width="110" height="110" loading="lazy">'
      . '<a class="sc-url" href="' . h($url) . '" target="_blank" rel="noopener">' . h(preg_replace('~^https://|/$~', '', $url)) . '</a></div></div>'
      . '<div class="sc-btns">' . ($edit !== '' ? '<a class="sb pri" href="' . h($edit) . '">Edit property</a>' : '')
      . '<button type="button" class="sb" data-copy="' . h($url) . '">Copy link</button>'
      . '<a class="sb" href="sms:?&body=' . $body . '">Text it</a>'
      . '<a class="sb" href="mailto:?subject=' . rawurlencode($label) . '&body=' . $body . '">Email it</a>'
      . '<a class="sb" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url) . '">Facebook</a>'
      . '<button type="button" class="sb sbshare" data-share-url="' . h($url) . '" data-share-title="' . h($label) . '" hidden>Share&hellip;</button>'
      . '<a class="sb gold" href="' . h($png) . '">QR PNG</a><a class="sb gold" href="' . h($svg) . '">QR SVG (print)</a></div>' . $extra . '</section>';
}

function share_script(): string {
    return '<script>(function(){document.addEventListener("click",function(e){var b=e.target.closest("[data-copy]");if(b){var u=b.getAttribute("data-copy"),t=b.textContent;'
      . 'var done=function(){b.textContent="Copied!";setTimeout(function(){b.textContent=t},1600)};'
      . 'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(done,function(){fb(u);done()})}else{fb(u);done()}}'
      . 'var s=e.target.closest("[data-share-url]");if(s&&navigator.share){navigator.share({title:s.getAttribute("data-share-title"),url:s.getAttribute("data-share-url")}).catch(function(){})}});'
      . 'function fb(u){var x=document.createElement("textarea");x.value=u;document.body.appendChild(x);x.select();try{document.execCommand("copy")}catch(_){}document.body.removeChild(x)}'
      . 'if(navigator.share){document.querySelectorAll(".sbshare").forEach(function(n){n.hidden=false})}})();</script>';
}

// Inline "YouTube video link" form shown on a page's card in My Mobile Pages.
function video_form(string $code, string $key, string $vkey, ?string $current, string $hint): string {
    $v = $current !== null && $current !== '' ? 'https://youtu.be/' . $current : '';
    return '<form method="post" class="vidform" action="/setup/links/"><input type="hidden" name="a" value="' . h($code) . '"><input type="hidden" name="k" value="' . h($key) . '">'
      . '<input type="hidden" name="act" value="video"><input type="hidden" name="vkey" value="' . h($vkey) . '">'
      . '<label for="v_' . h($vkey) . '">YouTube video link</label><div class="vrow"><input id="v_' . h($vkey) . '" name="url" type="text" value="' . h($v) . '" placeholder="https://youtu.be/..." autocomplete="off"><button class="sb pri" type="submit">Save video</button></div>'
      . '<small>' . h($hint) . '</small></form>';
}

// Inline "download link" form on the Home search app page card.
function applink_form(string $code, string $key, string $current): string {
    return '<form method="post" class="vidform" action="/setup/links/"><input type="hidden" name="a" value="' . h($code) . '"><input type="hidden" name="k" value="' . h($key) . '">'
      . '<input type="hidden" name="act" value="video"><input type="hidden" name="vkey" value="app_url">'
      . '<label for="v_app_url">Home search app download link</label><div class="vrow"><input id="v_app_url" name="url" type="text" value="' . h($current) . '" placeholder="https://..." autocomplete="off"><button class="sb pri" type="submit">Save link</button></div>'
      . '<small>The download button on your app page goes here.</small></form>';
}
