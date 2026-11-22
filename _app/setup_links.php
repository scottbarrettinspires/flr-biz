<?php
// /setup/links/ : every page link with share buttons and QR codes
$agent = setup_agent(); if (!$agent) return;
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$code = $agent['code']; $key = (string)$_REQUEST['k'];
$name = ucwords(strtolower($agent['name']));
?><?= dash_open('links', $agent, $key, 'Links & QR Codes', 'Share any of your main pages by text, email or social post, or download its QR code for flyers and business cards. Every link is permanent.') ?>
<a class="slotrow dashlink" href="/setup/listings/?a=<?= h(urlencode($code)) ?>&amp;k=<?= h(urlencode($key)) ?>"><span class="tx"><span class="t">Listing &amp; open house links and QR codes &rarr;</span><small>Yard sign, flyer and open house pages are managed on the Listings &amp; Open Houses tab.</small></span></a>
<h2>My pages</h2>
<?php foreach (agent_pages($agent) as [$lab, $url]) echo share_card($lab, $url, $code, $key); ?>
<?= dash_close() ?>
