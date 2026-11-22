<?php
// /share/qr/?u=<flr.biz page url>&fmt=svg|png[&dl=1]  (public: only encodes pages on this site that belong to an agent)
require_once __DIR__ . '/sharepage_lib.php';
$u = share_target_url((string)($_GET['u'] ?? ''));
if (!$u) { http_response_code(400); echo 'Invalid link'; return; }
$fmt = ($_GET['fmt'] ?? 'svg') === 'png' ? 'png' : 'svg';
header('X-Robots-Tag: noindex'); header('Cache-Control: public, max-age=3600');
if (!empty($_GET['dl'])) header('Content-Disposition: attachment; filename="' . preg_replace('~[^a-z0-9]+~i', '-', trim(preg_replace('~^https://[^/]+/~', '', $u), '/')) . '-qr.' . $fmt . '"');
if ($fmt === 'png') { $d = qr_png($u, 1200); if (!$d) { http_response_code(400); return; } header('Content-Type: image/png'); echo $d; }
else { $d = qr_svg($u); if (!$d) { http_response_code(400); return; } header('Content-Type: image/svg+xml'); echo $d; }
