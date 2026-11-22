<?php
// /setup/links/ : every page link with share buttons and QR codes
$agent = setup_agent(); if (!$agent) return;
header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
$code = $agent['code']; $key = (string)$_REQUEST['k'];
$name = ucwords(strtolower($agent['name']));
$vkeys = ['app_url' => '', 'youtube_id' => 'Shows on your main page. Leave blank for no video.', 'vid_steps' => 'Your answer video for this question. Leave blank until recorded.', 'vid_broker' => 'Your answer video for this question. Leave blank until recorded.', 'vid_avoid' => 'Your answer video for this question. Leave blank until recorded.'];
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'video' && isset($vkeys[$_POST['vkey'] ?? ''])) {
    $vk = $_POST['vkey']; $raw = trim((string)($_POST['url'] ?? '')); $id = $vk === 'app_url' ? clean_url($raw) : youtube_id($raw);
    if ($raw !== '' && $id === '') $msg = $vk === 'app_url' ? 'errapp' : 'err';
    else { save_overrides($code, [$vk => $id]); header('Location: /setup/links/?a=' . urlencode($code) . '&k=' . urlencode($key) . '&vsaved=1#v_' . urlencode($vk), true, 303); exit; }
}
$cur = agent($code);
?><?= dash_open('links', $agent, $key, 'My Mobile Pages', 'Share any of your main pages by text, email or social post, or download its QR code for flyers and business cards. Every link is permanent.') ?>
<a class="slotrow dashlink" href="/setup/listings/?a=<?= h(urlencode($code)) ?>&amp;k=<?= h(urlencode($key)) ?>"><span class="tx"><span class="t">Listing &amp; open house links and QR codes &rarr;</span><small>Yard sign, flyer and open house pages are managed on the Listings &amp; Open Houses tab.</small></span></a>
<?php if (isset($_GET['vsaved'])): ?><div class="note ok">Saved. Your page is updated.</div><?php endif; ?>
<?php if ($msg === 'errapp'): ?><div class="note err">That app download link does not look right.</div><?php endif; ?>
<?php if ($msg === 'err'): ?><div class="note err">Paste the full YouTube video link (youtube.com/watch?v=... or youtu.be/...).</div><?php endif; ?>
<h2>My pages</h2>
<?php foreach (agent_pages($agent) as [$lab, $url, $vk]) echo share_card($lab, $url, $code, $key, '', '', ($vk !== '' && isset($vkeys[$vk])) ? ($vk === 'app_url' ? applink_form($code, $key, (string)($cur['app_url'] ?? '')) : video_form($code, $key, $vk, $cur[$vk] ?? '', $vkeys[$vk])) : ''); ?>
<?= dash_close() ?>
