<?php
require __DIR__ . '/_app/lib.php';
if (PHP_SAPI === 'cli-server') {           // local testing only
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && substr($f, -4) !== '.php') return false;
}
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = trim(rawurldecode($uri), '/');
$parts = $path === '' ? [] : explode('/', $path);

function not_found(): never {
    http_response_code(404);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/style.css">'
       . '<main class="card"><h1>Page not found</h1><p class="tag">Check the link, or visit Florida Luxury Realty.</p>'
       . '<a class="btn" href="https://floridaluxuryrealty.com"><span class="t">floridaluxuryrealty.com</span></a></main>';
    exit;
}

if (!$parts) { header('Location: https://floridaluxuryrealty.com', true, 302); exit; }
if ($parts[0] === 'setup' && count($parts) === 1) { require __DIR__ . '/_app/setup.php'; exit; }
if ($parts[0] === 'setup' && count($parts) === 2 && $parts[1] === 'listings') { require __DIR__ . '/_app/setup_listings.php'; exit; }
if ($parts[0] === 'setup' && count($parts) === 2 && $parts[1] === 'dashboard') { header('Location: /setup/?' . http_build_query(['a' => $_GET['a'] ?? '', 'k' => $_GET['k'] ?? '']), true, 301); exit; }
if ($parts[0] === 'setup' && count($parts) === 2 && $parts[1] === 'links') { require __DIR__ . '/_app/setup_links.php'; exit; }
if ($parts[0] === 'setup' && count($parts) === 2 && $parts[1] === 'qr') { require __DIR__ . '/_app/setup_qr.php'; exit; }
if ($parts[0] === 'share' && count($parts) === 1) { require __DIR__ . '/_app/sharepage.php'; exit; }
if ($parts[0] === 'share' && count($parts) === 2 && $parts[1] === 'qr') { require __DIR__ . '/_app/shareqr.php'; exit; }
if ($parts[0] === 'lead-request' && count($parts) === 1) { require __DIR__ . '/_app/lead_request.php'; exit; }
if ($parts[0] === 'lead' && count($parts) === 1) { require __DIR__ . '/_app/lead.php'; exit; }

$routes = routes();
$name = strtolower($parts[0]);
if (!isset($routes[$name])) not_found();
[$code, $view] = $routes[$name];
$a = agent($code);

if (count($parts) === 2 && $parts[1] === 'contact.vcf' && $view === 'main') {
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $code . '.vcf"');
    echo render_vcard($a); exit;
}
if ($view === 'main' && count($parts) >= 2 && slot_id_ok($parts[1])) {          // listing / open house pages (QR code URLs)
    $slot = slots_load($code)[$parts[1]] ?? null;
    if (!$slot) not_found();
    if (count($parts) === 3 && $parts[2] === 'calendar') {
        $ics = render_slot_ics($a, $parts[1], $slot);
        if (!$ics) not_found();
        header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename="open-house.ics"');
        echo $ics; exit;
    }
    if (count($parts) === 3 && $parts[2] === 'details') {
        if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/' . $parts[1] . '/details/', true, 301); exit; }
        $html = render_slot_details($a, $parts[1], $slot);
        header('Cache-Control: no-store');
        if (!$html) { header('Location: /' . $name . '/' . $parts[1] . '/', true, 302); exit; }   // no live MLS listing: back to the page itself
        header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
    }
    if (count($parts) !== 2) not_found();
    if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/' . $parts[1] . '/', true, 301); exit; }
    header('Cache-Control: no-store');
    if ($to = slot_redirect($a, $slot)) { header('Location: ' . $to, true, 302); exit; }
    header('Content-Type: text/html; charset=utf-8');
    echo render_slot($a, $parts[1], $slot); exit;
}
if (count($parts) === 2 && $view === 'main' && $parts[1] === 'about') {
    if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/about/', true, 301); exit; }
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache');
    echo render_about($a); exit;
}
if (count($parts) === 2 && $view === 'main' && $parts[1] === 'leadstream') {
    if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/leadstream/', true, 301); exit; }
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache');
    echo render_leadstream_pitch($a); exit;
}
if (count($parts) === 2 && $view === 'main' && $parts[1] === 'social') {
    if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/social/', true, 301); exit; }
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache');
    echo render_page($a, 'social'); exit;
}
if (count($parts) === 2 && $view === 'main' && isset(page_defs()[$parts[1]])) {
    if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/' . $parts[1] . '/', true, 301); exit; }
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
    echo render_landing($a, $parts[1], isset($_GET['done'])); exit;
}
if (count($parts) !== 1) not_found();
if (substr($uri, -1) !== '/') { header('Location: /' . $name . '/', true, 301); exit; }
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
echo render_page($a, $view);
