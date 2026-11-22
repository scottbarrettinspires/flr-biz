<?php
// /setup/links/ : every page link with share buttons and QR codes
$agent = setup_agent(); if (!$agent) return;
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$code = $agent['code']; $key = (string)$_REQUEST['k'];
$name = ucwords(strtolower($agent['name']));
?><?= dash_open('links', $agent, $key, 'Links & QR Codes', 'Share any page by text, email or social post, or download its QR code for yard signs, flyers and business cards. Every link is permanent.') ?>
<?php $slots = slots_load($code); $lself = '/setup/listings/?a=' . urlencode($code) . '&k=' . urlencode($key); ?>
<h2>Listing &amp; open house pages</h2>
<?php foreach ($slots as $id => $s) echo share_card(slot_kind($id) . ' ' . $id . ': ' . slot_status($agent, $s), 'https://' . SITE_DOMAIN . '/' . $code . '/' . $id . '/', $code, $key, $id, $lself . '&s=' . $id); ?>
<h2>My pages</h2>
<?php foreach (agent_pages($agent) as [$lab, $url]) echo share_card($lab, $url, $code, $key); ?>
<?= dash_close() ?>
