<?php
// Per-agent setup page: /setup/?a=<code>&k=<private key>. The key is checked against a hash in agents.json.
$code = strtolower((string)($_REQUEST['a'] ?? ''));
$key  = (string)($_REQUEST['k'] ?? '');
$agent = null;
foreach (seed() as $s) if ($s['code'] === $code) $agent = $s;
$ok = $agent && !empty($agent['setup_hash']) && hash_equals($agent['setup_hash'], hash('sha256', $key));
if (!$ok) {
    sleep(1); http_response_code(403);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/style.css">'
       . '<main class="card"><h1>Setup link not valid</h1><p class="tag">Ask the brokerage office for your personal setup link.</p></main>';
    return;
}
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$p = paths_for($code);
$errors = []; $saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = [
        'phone'      => preg_match('/^[0-9()+ .\-]{0,24}$/', trim($_POST['phone'] ?? '')) ? trim($_POST['phone'] ?? '') : null,
        'email'      => trim($_POST['email'] ?? ''),
        'website'    => clean_url($_POST['website'] ?? ''),
        'instagram'  => clean_url($_POST['instagram'] ?? '', 'https://instagram.com/'),
        'facebook'   => clean_url($_POST['facebook'] ?? '', 'https://facebook.com/'),
        'tiktok'     => clean_url($_POST['tiktok'] ?? '', 'https://tiktok.com/@'),
        'app_url'    => clean_url($_POST['app_url'] ?? ''),
        'youtube_id' => youtube_id($_POST['youtube'] ?? ''),
        'vid_steps'  => youtube_id($_POST['vid_steps'] ?? ''),
        'vid_broker' => youtube_id($_POST['vid_broker'] ?? ''),
        'vid_avoid'  => youtube_id($_POST['vid_avoid'] ?? ''),
    ];
    if ($raw['phone'] === null) $errors[] = 'Phone can only contain numbers, spaces and ( ) + - .';
    if ($raw['email'] !== '' && !filter_var($raw['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'That email address does not look right.';
    foreach (['website', 'instagram', 'facebook', 'tiktok', 'app_url'] as $k)
        if (trim($_POST[$k] ?? '') !== '' && $raw[$k] === '') $errors[] = ($k === 'app_url' ? 'Home search app' : ucfirst($k)) . ' link does not look right.';
    foreach (['youtube' => 'youtube_id', 'vid_steps' => 'vid_steps', 'vid_broker' => 'vid_broker', 'vid_avoid' => 'vid_avoid'] as $in => $out)
        if (trim($_POST[$in] ?? '') !== '' && $raw[$out] === '') $errors[] = 'Paste the full YouTube video link (youtube.com/watch?v=... or youtu.be/...).';
    $errors = array_values(array_unique($errors));
    if (!$errors) $errors[] = save_photo($code, $_FILES['photo'] ?? []) ?: null;
    $errors = array_values(array_filter($errors));
    if (!$errors) {
        save_overrides($code, $raw);
        header('Location: /setup/?a=' . urlencode($code) . '&k=' . urlencode($key) . '&saved=1', true, 303); exit;
    }
}

$cur = agent($code);
$val = fn($k) => $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$k] ?? '') : ($cur[$k] ?? '');
$ytv = fn($f, $k) => $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$f] ?? '') : (!empty($cur[$k]) ? 'https://youtu.be/' . $cur[$k] : '');
$yt  = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['youtube'] ?? '') : (!empty($cur['youtube_id']) ? 'https://youtu.be/' . $cur['youtube_id'] : '');
$photo = photo_url($code);
$field = fn($id, $label, $v, $ph, $type = 'text', $hint = '') =>
    '<label for="' . $id . '">' . h($label) . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . h($v) . '" placeholder="' . h($ph) . '" autocomplete="off">'
    . ($hint ? '<small>' . h($hint) . '</small>' : '');
?><?php
$first = explode(' ', ucwords(strtolower($agent['name'])))[0]; $q = '?a=' . urlencode($code) . '&k=' . urlencode($key); $nslots = count(slots_load($code));
echo dash_open('overview', $agent, $key, 'Welcome, ' . $first, 'Your LeadStream Systems dashboard: mobile pages, listing and open house pages, and the links and QR codes that bring you leads.');
?>
<div class="tiles">
  <a class="tile" href="#info"><span class="n">1</span><strong>Complete your info</strong><small>Photo, phone, email, social links, intro video and your home search app link.</small></a>
  <a class="tile" href="/setup/listings/<?= h($q) ?>"><span class="n">2</span><strong>Add listing &amp; open house pages</strong><small><?= $nslots ?> page<?= $nslots === 1 ? '' : 's' ?> ready for yard signs and flyers. Choose which home each one shows.</small></a>
  <a class="tile" href="/setup/links/<?= h($q) ?>"><span class="n">3</span><strong>Share your mobile pages &amp; QR codes</strong><small>Text, email or post any page, or download its QR code to print.</small></a>
</div>
<h2 id="info">My Page &amp; Info</h2>
<p class="dsub">Update the details shown on your mobile page: <b>flr.biz/<?= h($p['main']) ?></b></p>
<?php if ($saved): ?><div class="note ok">Saved. Your page is updated. <a href="/<?= h($p['main']) ?>/" target="_blank" rel="noopener">View my page</a></div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="note err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>">
<label>Your photo</label>
<div class="photo-row"><div class="avatar sm" data-initials="<?= h(initials_text($agent['name'])) ?>"><?= $photo ? '<img src="' . h($photo) . '" alt="">' : '' ?></div>
<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div><small>JPG, PNG or WebP. A clear head-and-shoulders photo works best; it is cropped to a square.</small>
<?= $field('youtube', 'YouTube video link', $yt, 'https://youtu.be/...', 'text', 'Shows on your main page. Leave blank for no video.') ?>
<?php if (isset($agent['faq'])): ?>
<label style="margin-top:18px;font-size:1.02rem;color:var(--accent)">Buyer FAQ videos</label><small>Your video for each question on your Buyer FAQ page. Leave blank until recorded.</small>
<?= $field('vid_steps', 'What are the right steps to buying a home?', $ytv('vid_steps', 'vid_steps'), 'https://youtu.be/...') ?>
<?= $field('vid_broker', 'Why is a buyer broker agreement required?', $ytv('vid_broker', 'vid_broker'), 'https://youtu.be/...') ?>
<?= $field('vid_avoid', 'Can I avoid paying a buyer agent?', $ytv('vid_avoid', 'vid_avoid'), 'https://youtu.be/...') ?>
<?php endif; ?>
<?= $field('phone', 'Phone', $val('phone'), '(727) 555-1234', 'tel') ?>
<?= $field('email', 'Email', $val('email'), 'you@example.com', 'email') ?>
<?= $field('website', 'Website', $val('website'), 'https://') ?>
<?= $field('instagram', 'Instagram', $val('instagram'), '@yourname or link') ?>
<?= $field('facebook', 'Facebook', $val('facebook'), 'link to your page') ?>
<?= $field('tiktok', 'TikTok', $val('tiktok'), '@yourname or link') ?>
<?= $field('app_url', 'Home search app download link (the download button on your app page goes here)', $val('app_url'), 'https://...') ?>
<button class="btn contact" type="submit"><span class="t">Save my page</span></button>
</form>
<?= dash_close() ?>
