<?php
// Listing / open house page setup: /setup/listings/?a=<code>&k=<private key>[&s=p1]
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
$self = '/setup/listings/?a=' . urlencode($code) . '&k=' . urlencode($key);
$slots = slots_load($code);
$errors = []; $msg = '';
const MAX_SLOTS = 40;

$sid = (string)($_REQUEST['s'] ?? '');
if ($sid !== '' && !slot_id_ok($sid)) $sid = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'new') {
        $pre = (($_POST['kind'] ?? '') === 'o') ? 'o' : 'p';
        $have = count(array_filter(array_keys($slots), fn($i) => $i[0] === $pre));
        if ($have >= MAX_SLOTS) { $errors[] = 'You have reached the limit of ' . MAX_SLOTS . ' pages of this kind. Reuse an existing one.'; }
        else {
            $n = 1; while (isset($slots[$pre . $n])) $n++;
            $slots[$pre . $n] = ['title' => ''];
            slots_save($code, $slots);
            header('Location: ' . $self . '&s=' . $pre . $n . '&new=1', true, 303); exit;
        }
    } elseif ($act === 'videos') {
        $v = ['vid_listings' => youtube_id((string)($_POST['vid_listings'] ?? '')), 'vid_openhouses' => youtube_id((string)($_POST['vid_openhouses'] ?? ''))];
        foreach (['vid_listings', 'vid_openhouses'] as $k) if (trim((string)($_POST[$k] ?? '')) !== '' && $v[$k] === '') $errors[] = 'Paste the full YouTube video link (youtube.com/watch?v=... or youtu.be/...).';
        if (!$errors) { save_overrides($code, $v); header('Location: ' . $self . '&videos=1', true, 303); exit; }
    } elseif ($act === 'reset' && $sid !== '' && isset($slots[$sid])) {
        // Clear the property details but keep the permanent link live (printed signs and flyers depend on it).
        $slots[$sid] = ['title' => ''];
        slots_save($code, $slots);
        @unlink(UPLOAD_DIR . "/$code-$sid.jpg");
        header('Location: ' . $self . '&s=' . $sid . '&reset=1', true, 303); exit;
    } elseif ($act === 'save' && $sid !== '' && isset($slots[$sid])) {
        $s = [
            'title'    => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 60),
            'address'  => mb_substr(trim((string)($_POST['address'] ?? '')), 0, 120),
            'price'    => mb_substr(trim((string)($_POST['price'] ?? '')), 0, 40),
            'welcome'  => mb_substr(trim((string)($_POST['welcome'] ?? '')), 0, 240),
            'oh_date'  => slot_is_oh($sid) ? trim((string)($_POST['oh_date'] ?? '')) : '',
            'oh_start' => slot_is_oh($sid) ? trim((string)($_POST['oh_start'] ?? '')) : '',
            'oh_end'   => slot_is_oh($sid) ? trim((string)($_POST['oh_end'] ?? '')) : '',
            'property_url' => clean_url((string)($_POST['property_url'] ?? '')),
            'target'   => (string)($_POST['target'] ?? ''),
            'custom_url' => clean_url((string)($_POST['custom_url'] ?? '')),
        ];
        if ($s['oh_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['oh_date'])) $errors[] = 'Open house date does not look right.';
        foreach (['oh_start', 'oh_end'] as $k) if ($s[$k] !== '' && !preg_match('/^\d{2}:\d{2}$/', $s[$k])) $errors[] = 'Open house time does not look right.';
        if (($s['oh_date'] !== '') xor ($s['oh_start'] !== '')) $errors[] = 'For an open house, enter both the date and the start time (or leave both blank).';
        if (trim((string)($_POST['property_url'] ?? '')) !== '' && $s['property_url'] === '') $errors[] = 'Property page link does not look right.';
        if ($s['target'] !== '' && $s['target'] !== 'custom' && !isset(SLOT_TARGETS[$s['target']])) $s['target'] = '';
        if ($s['target'] === 'custom' && $s['custom_url'] === '') $errors[] = 'Enter the link to send visitors to, or choose a different option.';
        if ($s['target'] !== 'custom') $s['custom_url'] = '';
        if (!$errors) $errors[] = save_slot_photo($code, $sid, $_FILES['photo'] ?? []);
        $errors = array_values(array_filter($errors));
        if (!$errors) {
            $slots[$sid] = $s; slots_save($code, $slots);
            header('Location: ' . $self . '&s=' . $sid . '&saved=1', true, 303); exit;
        }
        $slots[$sid] = $s + ($slots[$sid] ?? []);
    }
}

$base = 'https://' . SITE_DOMAIN . '/' . $code . '/';
$f2 = fn($id, $label, $v) => '<label for="' . $id . '">' . h($label) . '</label><input id="' . $id . '" name="' . $id . '" type="text" value="' . h($v) . '" placeholder="https://youtu.be/..." autocomplete="off">';
?><?= dash_open('listings', $agent, $key, $sid !== '' && isset($slots[$sid]) ? slot_kind($sid) . ' ' . $sid : 'Listings & Open Houses', $sid !== '' && isset($slots[$sid]) ? 'Choose which property this link shows. The link and QR code never change.' : 'Your listing pages and open house pages are ready, each with a permanent link and QR code for yard signs and flyers. Click Edit property to choose which home each one shows.') ?>
<?php if (isset($_GET['saved'])): ?><div class="note ok">Saved. Your page is updated.</div><?php endif; ?>
<?php if (isset($_GET['videos'])): ?><div class="note ok">Videos saved. They now show on all of your listing and open house pages.</div><?php endif; ?>
<?php if (isset($_GET['reset'])): ?><div class="note ok">Property cleared. The link and QR code still work and show a general welcome page until you add the next property.</div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="note err"><?= h($e) ?></div><?php endforeach; ?>
<?php if ($sid !== '' && isset($slots[$sid])): $s = $slots[$sid]; $photo = slot_photo_url($code, $sid);
  $val = fn($k) => $s[$k] ?? ''; $url = $base . $sid . '/';
  $f = fn($id, $label, $v, $ph, $type = 'text', $hint = '') => '<label for="' . $id . '">' . h($label) . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . h($v) . '" placeholder="' . h($ph) . '" autocomplete="off">' . ($hint ? '<small>' . h($hint) . '</small>' : ''); ?>
<?= share_card(slot_kind($sid) . ' ' . $sid . ': ' . slot_status($agent, $s), $url, $code, $key) ?>
<p style="margin:6px 0 12px"><small>This link and QR code never change, even when you edit the page or point it somewhere else.</small></p>
<form method="post" enctype="multipart/form-data" class="form">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="s" value="<?= h($sid) ?>"><input type="hidden" name="act" value="save">
<label>What should this link do?</label>
<select name="target" id="target">
  <option value="">Show the property details below</option>
  <?php foreach (SLOT_TARGETS as $k => $lab): ?><option value="<?= h($k) ?>"<?= ($s['target'] ?? '') === $k ? ' selected' : '' ?>>Send visitors to: <?= h($lab) ?></option><?php endforeach; ?>
  <option value="custom"<?= ($s['target'] ?? '') === 'custom' ? ' selected' : '' ?>>Send visitors to a link I paste</option>
</select>
<small>Your QR code stays the same. Change this any time to switch what visitors see.</small>
<div id="custom_wrap"><?= $f('custom_url', 'Link to send visitors to', $val('custom_url'), 'https://...') ?></div>
<div id="slot_fields">
<?php $oh = slot_is_oh($sid); ?>
<?= $f('title', 'Headline', $val('title') ?: ($oh ? 'OPEN HOUSE' : 'JUST LISTED'), $oh ? 'GRAND OPEN HOUSE EVENT' : 'JUST LISTED', 'text', $oh ? 'Examples: OPEN HOUSE, GRAND OPEN HOUSE EVENT.' : 'Examples: JUST LISTED, COMING SOON, FOR SALE, UNDER CONTRACT, SOLD.') ?>
<?= $f('address', 'Property address', $val('address'), '8716 Green Street, Port Richey') ?>
<?= $f('price', 'Price (optional)', $val('price'), '$425,000') ?>
<label for="welcome">Welcome message (optional)</label><textarea id="welcome" name="welcome" rows="2" maxlength="240" placeholder="Welcome to my open house!"><?= h($val('welcome')) ?></textarea>
<?php if ($oh): ?>
<label style="margin-top:18px">Open house date and time</label>
<small>Visitors get an Add to Calendar button. It disappears automatically after the event.</small>
<div class="row3"><div><label for="oh_date">Date</label><input id="oh_date" name="oh_date" type="date" value="<?= h($val('oh_date')) ?>"></div>
<div><label for="oh_start">Starts</label><input id="oh_start" name="oh_start" type="time" value="<?= h($val('oh_start')) ?>"></div>
<div><label for="oh_end">Ends</label><input id="oh_end" name="oh_end" type="time" value="<?= h($val('oh_end')) ?>"></div></div>
<?php endif; ?>
<?= $f('property_url', 'Property page link (adds a "View This Home" button)', $val('property_url'), 'https://... (MLS, your website, Zillow, flyer)') ?>
<label>Cover image</label>
<?php if ($photo): ?><img class="slotprev" src="<?= h($photo) ?>" alt=""><?php endif; ?>
<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>The photo behind the headline at the top of this page, usually the front of the home. JPG, PNG or WebP; landscape works best (cropped to 3:2). Leave empty to keep the current image. With no cover image, your headshot is used.</small>
</div>
<button class="btn contact" type="submit"><span class="t">Save this page</span></button>
</form>
<form method="post" onsubmit="return confirm('Clear the property details on this page? The link and QR code keep working and show a general welcome page until you add the next property.');" style="margin-top:14px">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="s" value="<?= h($sid) ?>"><input type="hidden" name="act" value="reset">
<button class="linkbtn" type="submit">Done with this property: clear it for the next one</button></form>
<p><small>Listing links can't be deleted, so printed signs and flyers with this QR code never lead to a dead page.</small></p>
<p style="margin-top:14px"><a href="<?= h($self) ?>">&larr; All my listing and open house pages</a></p>
<script>(function(){var t=document.getElementById('target'),c=document.getElementById('custom_wrap'),f=document.getElementById('slot_fields');
function u(){c.style.display=t.value==='custom'?'':'none';f.style.display=(t.value===''?'':'none');}t.addEventListener('change',u);u();})();</script>
<?php else: ?>
<form method="post" class="form" style="margin-bottom:6px">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="act" value="videos">
<label style="font-size:1.05rem">Videos for all your pages</label>
<small style="margin-top:0">Paste a YouTube link once and it plays on every page of that kind. Leave blank for no video.</small>
<?php $cur = agent($code); $yt = fn($v) => !empty($v) ? 'https://youtu.be/' . $v : ''; ?>
<?= $f2('vid_listings', 'Video for all listing pages', $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['vid_listings'] ?? '') : $yt($cur['vid_listings'] ?? '')) ?>
<?= $f2('vid_openhouses', 'Video for all open house pages', $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['vid_openhouses'] ?? '') : $yt($cur['vid_openhouses'] ?? '')) ?>
<button class="btn contact" type="submit"><span class="t">Save videos</span></button></form>
<?php foreach (['p' => ['Listing pages', 'For yard signs and listing flyers. One link per property.', 'Add another listing page'], 'o' => ['Open house pages', 'For open house and coming-soon signs. Includes the date and an Add to Calendar button.', 'Add another open house page']] as $pre => [$hd, $ds, $addlab]): ?>
<h2><?= h($hd) ?></h2><p class="dsub" style="margin-bottom:6px"><?= h($ds) ?></p>
<?php foreach ($slots as $id => $s) if ($id[0] === $pre) echo share_card(slot_kind($id) . ' ' . $id . ': ' . slot_status($agent, $s), $base . $id . '/', $code, $key, $id, $self . '&s=' . $id); ?>
<form method="post"><input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="act" value="new"><input type="hidden" name="kind" value="<?= $pre ?>">
<button class="btn contact" type="submit" style="margin-top:6px"><span class="t">+ <?= h($addlab) ?></span></button></form>
<?php endforeach; ?>
<?php endif; ?>
<?= dash_close() ?>
