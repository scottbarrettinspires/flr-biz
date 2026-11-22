<?php
/** /<code>/leadstream/ : the LeadStream Systems pitch for agents (same section as on the joinflr.com agent page), shown from the "Are you a real estate agent?" footer link. */
function render_leadstream_pitch(array $a): string {
    $code = $a['code']; $p = paths_for($code); $name = ucwords(strtolower($a['name'])); $first = explode(' ', $name)[0];
    $photo = photo_url($code);
    $join = trim((string)($a['join_url'] ?? ''));                       // the agent's joinflr.com page: its #contact form is the "request information" target
    $req = $join !== '' ? rtrim($join, '/') . '/#contact' : '';
    $cta = $req !== '' ? '<a class="btn btn-ls" href="' . h($req) . '" target="_blank" rel="noopener">Ask for a LeadStream walkthrough</a>' : '';
    $section = str_replace('{{CTA}}', $cta, (string)file_get_contents(__DIR__ . '/ls_section.html'));
    $call = !empty($a['phone']) ? '<a class="btn btn-teal btn-sm" href="tel:' . h(preg_replace('/[^0-9+]/', '', $a['phone'])) . '">Call ' . h($first) . '</a>' : '';
    $bye = '<div class="wrap"><div class="lsbye">' . ($photo ? '<img src="' . h($photo) . '" alt="">' : '') . '<div class="who"><small>Shared with you by</small><strong>' . h($name) . '</strong><span>' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . ', Inc.</span></div>' . $call
         . ' <a class="btn btn-ghost btn-sm" href="/' . h($p['main']) . '/">See my mobile page</a></div></div>';
    $menu = '<a href="/' . h($p['main']) . '/">My mobile page</a><a href="/' . h($p['main']) . '/about/">About ' . h($first) . '</a>'
          . ($req !== '' ? '<a class="btn btn-ls btn-sm" href="' . h($req) . '" target="_blank" rel="noopener">Request information</a>' : '');
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>LeadStream Systems | ' . h($name) . ' | ' . BROKERAGE . ', Inc.</title><meta name="description" content="Lead generation and conversion, exclusively at Florida Luxury Realty. Turn everything you do into a lead funnel.">'
      . '<meta name="robots" content="noindex,follow"><meta property="og:title" content="LeadStream Systems at Florida Luxury Realty"><meta property="og:image" content="https://' . SITE_DOMAIN . '/assets/leadstream-logo.png">'
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . '/' . $p['main'] . '/leadstream/"><link rel="icon" type="image/png" href="/assets/leadstream-favicon.png"><meta name="theme-color" content="#3D4246">'
      . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
      . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600;1,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">'
      . '<link rel="stylesheet" href="/assets/ls-pitch.css?v=' . filemtime(dirname(__DIR__) . '/assets/ls-pitch.css') . '"></head><body class="lspitch">'
      . '<header class="nav"><div class="wrap nav-inner"><a class="brand" href="/' . h($p['main']) . '/" aria-label="Back to ' . h($name) . '"><img src="/assets/flr-logo-white.png" width="173" height="46" alt="Florida Luxury Realty"></a>'
      . '<button class="menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="menu">Menu</button><nav id="menu" class="menu">' . $menu . '</nav></div></header>'
      . '<main>' . $section . $bye . '</main>'
      . '<footer class="lsfoot"><div class="wrap">' . BROKERAGE . ', Inc.<br>Equal Housing Opportunity</div></footer>'
      . '<script>(function(){var b=document.querySelector(".menu-btn"),m=document.getElementById("menu");b.addEventListener("click",function(){var o=m.classList.toggle("open");b.setAttribute("aria-expanded",o)})})();</script>'
      . '</body></html>';
}
