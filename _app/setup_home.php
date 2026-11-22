<?php
// /setup/dashboard/ : overview
$agent = setup_agent(); if (!$agent) return;
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$code = $agent['code']; $key = (string)$_REQUEST['k']; $q = '?a=' . urlencode($code) . '&k=' . urlencode($key);
$first = explode(' ', ucwords(strtolower($agent['name'])))[0];
$slots = slots_load($code); $p = paths_for($code);
echo dash_open('overview', $agent, $key, 'Welcome, ' . $first, 'Your LeadStream Systems dashboard: mobile pages, listing and open house pages, and the links and QR codes that bring you leads.');
?>
<div class="tiles">
  <a class="tile" href="/setup/<?= h($q) ?>"><span class="n">1</span><strong>Complete your info</strong><small>Photo, phone, email, social links, intro video and your home search app link.</small></a>
  <a class="tile" href="/setup/listings/<?= h($q) ?>"><span class="n">2</span><strong>Add listing &amp; open house pages</strong><small><?= count($slots) ? count($slots) . ' page' . (count($slots) === 1 ? '' : 's') . ' set up.' : 'Create a permanent link for each yard sign or open house.' ?></small></a>
  <a class="tile" href="/setup/links/<?= h($q) ?>"><span class="n">3</span><strong>Share links &amp; QR codes</strong><small>Text, email or post any page, or download its QR code to print.</small></a>
</div>
<h2>Your main page</h2>
<?= share_card('My main page (all links)', 'https://' . SITE_DOMAIN . '/' . $p['main'] . '/', $code, $key) ?>
<?= dash_close() ?>
