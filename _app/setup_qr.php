<?php
// /setup/qr/?a=&k=&u=<page url>&fmt=svg|png[&dl=1&n=<file name>]  (agent-authenticated QR generator, only for that agent's own pages)
$agent = setup_agent(); if (!$agent) return;
$code = $agent['code'];
$u = (string)($_GET['u'] ?? '');
$allowed = array_map(fn($n) => strtolower(explode('/', $n)[0]), array_values(paths_for($code))); $allowed[] = $code;
$ok = preg_match('~^https://' . preg_quote(SITE_DOMAIN, '~') . '/([a-z0-9][a-z0-9-]*)/~i', $u, $mm) && in_array(strtolower($mm[1]), $allowed, true) && strlen($u) <= 200 && !preg_match('/\s/', $u);
if (!$ok) { http_response_code(400); echo 'Invalid link'; return; }
$fmt = ($_GET['fmt'] ?? 'svg') === 'png' ? 'png' : 'svg';
$brand = !empty($_GET['brand']); $style = ($_GET['brand'] ?? '') === 'house' ? 'house' : 'photo';
$name = preg_replace('~[^a-z0-9-]+~i', '-', (string)($_GET['n'] ?? 'qr')) . ($brand ? '-photo-qr' : '-qr');
header('X-Robots-Tag: noindex'); header('Cache-Control: private, max-age=3600');
if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="' . $name . '.' . $fmt . '"');
if ($fmt === 'png') { $d = $brand ? qr_png_branded($u, $code, 1200, 4, $style) : qr_png($u, 1200); if (!$d) { http_response_code(400); return; } header('Content-Type: image/png'); echo $d; }
else { $d = $brand ? qr_svg_branded($u, $code, 4, $style) : qr_svg($u); if (!$d) { http_response_code(400); return; } header('Content-Type: image/svg+xml'); echo $d; }
