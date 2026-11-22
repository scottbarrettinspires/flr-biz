<?php
// /setup/links/ : every page link with share buttons and QR codes
$agent = setup_agent(); if (!$agent) return;
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$code = $agent['code']; $key = (string)$_REQUEST['k'];
$name = ucwords(strtolower($agent['name']));
?><?= dash_open('links', $agent, $key, 'Links & QR Codes', 'Share any page by text, email or social post, or download its QR code for yard signs, flyers and business cards. Every link is permanent.') ?>
<?php $slots = slots_load($code);
if ($slots): ?><h2>Listing &amp; open house pages</h2>
<?php foreach ($slots as $id => $s) { $rd = slot_redirect($agent, $s);
    $lab = $rd ? 'Link ' . $id . ' (sends visitors to ' . (SLOT_TARGETS[$s['target']] ?? 'a link') . ')' : trim(($s['title'] ?? 'Listing') . ': ' . ($s['address'] ?? $id), ': ');
    echo share_card($lab, 'https://' . SITE_DOMAIN . '/' . $code . '/' . $id . '/', $code, $key, $id); } ?>
<?php endif; ?>
<h2>My pages</h2>
<?php foreach (agent_pages($agent) as [$lab, $url]) echo share_card($lab, $url, $code, $key); ?>
<?= dash_close() ?>
