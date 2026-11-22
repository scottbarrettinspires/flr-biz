<?php
// Illustrated app landing page: phone mockups drawn in HTML/SVG (no app-store screenshots or third-party images).
function app_house(int $v): string {
    static $n = 0; $n++;
    $c = [[ '#9fd0ff', '#e6f3ff', '#f2e6d3', '#b5523b' ], [ '#ffd9a8', '#fff1dc', '#dfe9f2', '#3f5f7a' ], [ '#b7e0d4', '#eaf7f2', '#fff4e0', '#8a5a3c' ]][$v % 3];
    return '<svg viewBox="0 0 200 120" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><linearGradient id="hs' . $n . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' . $c[0] . '"/><stop offset="1" stop-color="' . $c[1] . '"/></linearGradient></defs>'
        . '<rect width="200" height="120" fill="url(#hs' . $n . ')"/><rect y="94" width="200" height="26" fill="#6fae6a"/>'
        . '<rect x="48" y="52" width="104" height="46" fill="' . $c[2] . '"/><polygon points="38,54 100,16 162,54" fill="' . $c[3] . '"/>'
        . '<rect x="92" y="70" width="16" height="28" fill="#6b4a33"/><rect x="60" y="64" width="22" height="16" fill="#cfe8ff"/><rect x="118" y="64" width="22" height="16" fill="#cfe8ff"/>'
        . '<circle cx="168" cy="40" r="9" fill="#ffe08a" opacity=".9"/></svg>';
}
function app_icon(string $k): string { return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">' . BTN_ICONS[$k] . '</svg>'; }
function app_phone(string $screen, ?string $photo = null, string $cls = ''): string {
    $in = '';
    if ($screen === 'near') {
        $pins = [[40, 70, '$489K'], [118, 112, '$355K'], [70, 170, '$612K'], [140, 210, '$429K']];
        $svg = '<svg class="map" viewBox="0 0 204 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="204" height="300" fill="#e9efe4"/>'
            . '<path d="M-10 250 C60 215 110 270 220 225 L220 310 L-10 310Z" fill="#bcd9ee"/>'
            . '<g stroke="#fff" stroke-width="7" fill="none" stroke-linecap="round"><path d="M-5 100 L210 140"/><path d="M60 -5 L95 300"/><path d="M-5 190 L210 170"/><path d="M150 -5 L120 300"/></g>'
            . '<g stroke="#fff" stroke-width="3" fill="none"><path d="M-5 40 L210 60"/><path d="M20 -5 L10 300"/></g>';
        foreach ($pins as [$x, $y, $t]) $svg .= '<g transform="translate(' . $x . ' ' . $y . ')"><rect x="-19" y="-10" width="38" height="18" rx="9" fill="#c9a34e"/><text x="0" y="3" text-anchor="middle" font-size="9" font-weight="700" fill="#1a1405">' . $t . '</text><path d="M-4 8 L0 14 L4 8Z" fill="#c9a34e"/></g>';
        $svg .= '<circle cx="102" cy="150" r="7" fill="#2f80ed" stroke="#fff" stroke-width="3"/><circle cx="102" cy="150" r="18" fill="#2f80ed" opacity=".15"/></svg>';
        $in = $svg . '<div class="sbar"><span>&#9906;</span> Homes near you</div>'
            . '<div class="scard"><div class="th">' . app_house(0) . '</div><div class="tx"><b>$489,000</b><span>3 bd &middot; 2 ba &middot; 1,950 sqft</span><i>Just listed</i></div><span class="hrt">&#9825;</span></div>';
    } elseif ($screen === 'list') {
        $it = [['$489,000', '3 bd · 2 ba', '0.4 mi', 0], ['$355,000', '3 bd · 2 ba', '0.9 mi', 1], ['$612,000', '4 bd · 3 ba', '1.3 mi', 2]];
        $in = '<div class="sbar plain"><b>Homes near you</b></div><div class="sub2">3 closest homes for sale</div>';
        foreach ($it as [$pr, $bb, $mi, $hv]) $in .= '<div class="scard flat"><div class="th">' . app_house($hv) . '</div><div class="tx"><b>' . $pr . '</b><span>' . $bb . '</span><i>' . $mi . ' away</i></div><span class="hrt">&#9825;</span></div>';
    } elseif ($screen === 'details') {
        $in = '<div class="pic">' . app_house(1) . '<span class="cnt">1 / 24</span></div>'
            . '<div class="pad"><b class="pr">$489,000</b><span class="ad">3 bd &middot; 2 ba &middot; 1,950 sqft</span>'
            . '<div class="chips"><em>Interior photos</em><em>Taxes</em><em>Schools</em></div>'
            . '<div class="row"><span>Property taxes</span><i></i></div><div class="row"><span>Nearby schools</span><i></i></div><div class="row"><span>Year built &amp; lot</span><i></i></div></div>';
    } else {
        $av = $photo ? '<img src="' . h($photo) . '" alt="">' : '<span>A</span>';
        $in = '<div class="sbar plain"><b>My Favorites</b></div>'
            . '<div class="scard flat"><div class="th">' . app_house(2) . '</div><div class="tx"><b>$612,000</b><span>4 bd &middot; 3 ba</span></div><span class="hrt on">&#9829;</span></div>'
            . '<div class="scard flat"><div class="th">' . app_house(1) . '</div><div class="tx"><b>$355,000</b><span>3 bd &middot; 2 ba</span></div><span class="hrt on">&#9829;</span></div>'
            . '<div class="chat"><div class="b them"><span class="av">' . $av . '</span><p>Want to see this one this weekend?</p></div><div class="b me"><p>Yes, please!</p></div><div class="b them"><span class="av">' . $av . '</span><p>Done. I will set it up for you.</p></div></div>';
    }
    return '<div class="phone ' . $cls . '"><div class="notch"></div><div class="screen s-' . $screen . '">' . $in . '</div></div>';
}
function render_app_inner(array $a, string $name): string {
    $first = explode(' ', $name)[0]; $photo = photo_url($a['code']);
    $cta = !empty($a['app_url'])
        ? '<a class="btn contact" href="' . h($a['app_url']) . '" target="_blank" rel="noopener"><span class="t">CLICK TO DOWNLOAD NOW</span></a>'
        : '<div class="note">The app link is coming soon. In the meantime, ask ' . h($first) . ' directly.</div>';
    $feat = function (string $ico, string $title, string $text, string $screen, bool $flip) use ($photo) {
        return '<section class="afeat' . ($flip ? ' flip' : '') . '"><div class="afx"><span class="ic">' . app_icon($ico) . '</span><h3>' . h($title) . '</h3><p>' . h($text) . '</p></div><div class="afp">' . app_phone($screen, $photo) . '</div></section>';
    };
    $o = '<section class="ahero"><h2 class="ah">Every home. Every detail.</h2>'
       . '<div class="afp"><img class="heroimg" src="/assets/app-phone.png?v=1" alt="Home search app on a smartphone" width="512" height="582"></div></section>';
    $o .= '<h2 class="asec">The Easiest Way To Find Homes</h2>';
    $o .= '<ul class="abul">'
        . '<li><strong>View Listings Near You</strong> See the homes for sale closest to where you are right now, with the details that matter to you.</li>'
        . '<li><strong>Photos and Details</strong> Swipe through interior photos and see the details on any home, including taxes and schools.</li>'
        . '<li><strong>Save Favorites</strong> Save the homes you love and come back to them any time.</li>'
        . '<li><strong>Get Help From Me</strong> Chat with me directly with questions or to set up a showing.</li>'
        . '</ul>';
    if (!empty($a['app_url'])) $o .= '<div class="astick"><a class="btn contact" href="' . h($a['app_url']) . '" target="_blank" rel="noopener"><span class="t">Download FREE Home Search App</span></a></div>';
    return $o;
}
