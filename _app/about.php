<?php
/** Header buttons under the agent's photo: "Call Me" (when a phone is on file) and "About Me" (the agent's About Me page). */
function contact_row(array $a, bool $small = false): string {
    $p = paths_for($a['code']); $o = '';
    if (!empty($a['phone']))
        $o .= '<a class="soc pill" href="tel:' . h(preg_replace('/[^0-9+]/', '', $a['phone'])) . '">' . icon('phone') . '<span>Call Me</span></a>';
    $o .= '<a class="soc pill" href="/' . h($p['main']) . '/about/">' . icon('contact') . '<span>About Me</span></a>';
    return '<div class="socials' . ($small ? ' sm' : '') . '">' . $o . '</div>';
}

/** /<code>/about/ : the agent's About Me page (photo, bio, ways to reach them). */
function render_about(array $a): string {
    $code = $a['code']; $p = paths_for($code); $name = ucwords(strtolower($a['name'])); $first = explode(' ', $name)[0];
    $photo = photo_url($code);
    $bio = trim((string)($a['bio'] ?? ''));
    $paras = '';
    foreach (preg_split('/\R{2,}/', $bio) ?: [] as $para) { $para = trim($para); if ($para !== '') $paras .= '<p class="lead">' . nl2br(h($para)) . '</p>'; }
    if ($paras === '') $paras = '<p class="lead">More about ' . h($first) . ' is coming soon. In the meantime, call or email to say hello.</p>';
    $facts = '';
    if (!empty($a['license'])) $facts .= '<div>Broker License ID: ' . h($a['license']) . '</div>';
    if (!empty($a['office']))  $facts .= '<div>' . h($a['office']) . '</div>';
    $links = '';
    if (!empty($a['phone'])) $links .= menu_button('phone', 'Call Me', 'tel:' . preg_replace('/[^0-9+]/', '', $a['phone']));
    if (!empty($a['phone'])) $links .= menu_button('chat', 'Text Me', 'sms:' . preg_replace('/[^0-9+]/', '', $a['phone']));
    if (!empty($a['email'])) $links .= menu_button('email', 'Email Me', 'mailto:' . $a['email']);
    if (social_links($a))    $links .= menu_button('share', 'Connect With Me On Social Media', '/' . $p['social'] . '/');
    $links .= share_button($code . '/about');
    $tag = $a['title'] . ' · ' . BROKERAGE;
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>About ' . h($name) . ' | ' . BROKERAGE . '</title><meta name="description" content="' . h($tag) . '">'
      . '<meta property="og:title" content="About ' . h($name) . '">' . ($photo ? '<meta property="og:image" content="https://' . SITE_DOMAIN . h($photo) . '">' : '')
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . '/' . $p['main'] . '/about/"><link rel="stylesheet" href="/assets/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/style.css') . '"></head><body>'
      . '<main class="card wide aboutpage"><a class="back vback" href="/' . h($p['main']) . '/">&larr; My page</a>'
      . ($photo ? '<img class="aboutphoto" src="' . h($photo) . '" alt="' . h($name) . '">' : '')
      . '<h1 class="pt">About ' . h($first) . '</h1><p class="tag">' . h($name) . ' &middot; ' . h($tag) . '</p>'
      . $paras . ($facts !== '' ? '<div class="aboutfacts">' . $facts . '</div>' : '')
      . '<nav class="links">' . $links . '</nav>'
      . page_footer($a) . '</main></body></html>';
}

/** Page footer: brokerage line plus the agent's "Are you a real estate agent? Learn more here" recruiting link (set in the dashboard). */
function page_footer(array $a): string {
    $j = trim((string)($a['join_url'] ?? ''));
    return '<footer>' . BROKERAGE . ', Inc.<br><span>Equal Housing Opportunity</span>'
        . ($j !== '' ? '<p class="joinlink">Are you a real estate agent? <a href="' . h($j) . '" target="_blank" rel="noopener">Learn more here</a></p>' : '') . '</footer>';
}
