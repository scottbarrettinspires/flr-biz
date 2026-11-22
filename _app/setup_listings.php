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
        if (count($slots) >= MAX_SLOTS) { $errors[] = 'You have reached the limit of ' . MAX_SLOTS . ' pages. Reuse an existing one.'; }
        else {
            $n = 1; while (isset($slots['p' . $n])) $n++;
            $slots['p' . $n] = ['title' => 'OPEN HOUSE'];
            slots_save($code, $slots);
            header('Location: ' . $self . '&s=p' . $n . '&new=1', true, 303); exit;
        }
    } elseif ($act === 'delete' && $sid !== '' && isset($slots[$sid])) {
        unset($slots[$sid]); slots_save($code, $slots);
        @unlink(UPLOAD_DIR . "/$code-$sid.jpg");
        header('Location: ' . $self . '&deleted=1', true, 303); exit;
    } elseif ($act === 'save' && $sid !== '' && isset($slots[$sid])) {
        $s = [
            'title'    => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 60),
            'address'  => mb_substr(trim((string)($_POST['address'] ?? '')), 0, 120),
            'price'    => mb_substr(trim((string)($_POST['price'] ?? '')), 0, 40),
            'welcome'  => mb_substr(trim((string)($_POST['welcome'] ?? '')), 0, 240),
            'oh_date'  => trim((string)($_POST['oh_date'] ?? '')),
            'oh_start' => trim((string)($_POST['oh_start'] ?? '')),
            'oh_end'   => trim((string)($_POST['oh_end'] ?? '')),
            'property_url' => clean_url((string)($_POST['property_url'] ?? '')),
            'video'    => youtube_id((string)($_POST['video'] ?? '')),
            'target'   => (string)($_POST['target'] ?? ''),
            'custom_url' => clean_url((string)($_POST['custom_url'] ?? '')),
        ];
        if ($s['oh_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s['oh_date'])) $errors[] = 'Open house date does not look right.';
        foreach (['oh_start', 'oh_end'] as $k) if ($s[$k] !== '' && !preg_match('/^\d{2}:\d{2}$/', $s[$k])) $errors[] = 'Open house time does not look right.';
        if (($s['oh_date'] !== '') xor ($s['oh_start'] !== '')) $errors[] = 'For an open house, enter both the date and the start time (or leave both blank).';
        if (trim((string)($_POST['property_url'] ?? '')) !== '' && $s['property_url'] === '') $errors[] = 'Property page link does not look right.';
        if (trim((string)($_POST['video'] ?? '')) !== '' && $s['video'] === '') $errors[] = 'Paste the full YouTube video link.';
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
?><?= dash_open('listings', $agent, $key, 'Listings & Open Houses', 'Each page has a permanent link and QR code that never change. Edit what it shows, or point it at any existing page, whenever you like.') ?>
<?php if (isset($_GET['saved'])): ?><div class="note ok">Saved. Your page is updated.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="note ok">Page cleared.</div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="note err"><?= h($e) ?></div><?php endforeach; ?>
<?php if ($sid !== '' && isset($slots[$sid])): $s = $slots[$sid]; $photo = slot_photo_url($code, $sid);
  $val = fn($k) => $s[$k] ?? ''; $url = $base . $sid . '/';
  $f = fn($id, $label, $v, $ph, $type = 'text', $hint = '') => '<label for="' . $id . '">' . h($label) . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . h($v) . '" placeholder="' . h($ph) . '" autocomplete="off">' . ($hint ? '<small>' . h($hint) . '</small>' : ''); ?>
<?= share_card(trim(($s['title'] ?? 'Listing') . ': ' . ($s['address'] ?? $sid), ': '), $url, $code, $key) ?>
<p style="margin:6px 0 12px"><small>This link and QR code never change, even when you edit the page or point it somewhere else.</small></p>
<form method="post" enctype="multipart/form-data" class="form">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="s" value="<?= h($sid) ?>"><input type="hidden" name="act" value="save">
<label style="color:var(--accent)">What should this link do?</label>
<select name="target" id="target">
  <option value="">Show this listing / open house page</option>
  <?php foreach (SLOT_TARGETS as $k => $lab): ?><option value="<?= h($k) ?>"<?= ($s['target'] ?? '') === $k ? ' selected' : '' ?>>Send visitors to: <?= h($lab) ?></option><?php endforeach; ?>
  <option value="custom"<?= ($s['target'] ?? '') === 'custom' ? ' selected' : '' ?>>Send visitors to a link I paste</option>
</select>
<small>Your QR code stays the same. Change this any time to switch what visitors see.</small>
<div id="custom_wrap"><?= $f('custom_url', 'Link to send visitors to', $val('custom_url'), 'https://...') ?></div>
<div id="slot_fields">
<?= $f('title', 'Headline', $val('title') ?: 'OPEN HOUSE', 'GRAND OPEN HOUSE EVENT', 'text', 'Examples: OPEN HOUSE, JUST LISTED, COMING SOON.') ?>
<?= $f('address', 'Property address', $val('address'), '8716 Green Street, Port Richey') ?>
<?= $f('price', 'Price (optional)', $val('price'), '$425,000') ?>
<label for="welcome">Welcome message (optional)</label><textarea id="welcome" name="welcome" rows="2" maxlength="240" placeholder="Welcome to my open house!"><?= h($val('welcome')) ?></textarea>
<label style="margin-top:18px;color:var(--accent)">Open house date and time</label>
<small>Leave blank for a listing page without an open house. The calendar button disappears automatically after the event.</small>
<div class="row3"><div><label for="oh_date">Date</label><input id="oh_date" name="oh_date" type="date" value="<?= h($val('oh_date')) ?>"></div>
<div><label for="oh_start">Starts</label><input id="oh_start" name="oh_start" type="time" value="<?= h($val('oh_start')) ?>"></div>
<div><label for="oh_end">Ends</label><input id="oh_end" name="oh_end" type="time" value="<?= h($val('oh_end')) ?>"></div></div>
<?= $f('property_url', 'Property page link (adds a "View This Home" button)', $val('property_url'), 'https://... (MLS, your website, Zillow, flyer)') ?>
<?= $f('video', 'Home tour video (YouTube link, optional)', !empty($s['video']) ? 'https://youtu.be/' . $s['video'] : '', 'https://youtu.be/...') ?>
<label>Property photo</label>
<?php if ($photo): ?><img class="slotprev" src="<?= h($photo) ?>" alt=""><?php endif; ?>
<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP. Landscape works best; it is cropped to 3:2. Leave empty to keep the current photo.</small>
</div>
<button class="btn contact" type="submit"><span class="t">Save this page</span></button>
</form>
<form method="post" onsubmit="return confirm('Clear this page? The link stays reserved but the page will show Not Found until you set it up again.');" style="margin-top:14px">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="s" value="<?= h($sid) ?>"><input type="hidden" name="act" value="delete">
<button class="linkbtn" type="submit">Delete this page</button></form>
<p style="margin-top:14px"><a href="<?= h($self) ?>">&larr; All my listing pages</a></p>
<script>(function(){var t=document.getElementById('target'),c=document.getElementById('custom_wrap'),f=document.getElementById('slot_fields');
function u(){c.style.display=t.value==='custom'?'':'none';f.style.display=(t.value===''?'':'none');}t.addEventListener('change',u);u();})();</script>
<?php else: ?>
<?php foreach ($slots as $id => $s): $rd = slot_redirect(agent($code), $s); ?>
<a class="btn slotrow" href="<?= h($self . '&s=' . $id) ?>"><span class="tx"><span class="t"><?= h($id) ?> &middot; <?= h($rd ? 'Sends to ' . (SLOT_TARGETS[$s['target']] ?? 'a link') : (trim(($s['title'] ?? '') . ' ' . ($s['address'] ?? '')) ?: 'Not set up')) ?></span><small>flr.biz/<?= h($code) ?>/<?= h($id) ?>/</small></span></a>
<?php endforeach; ?>
<form method="post"><input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>"><input type="hidden" name="act" value="new">
<button class="btn contact" type="submit"><span class="t">+ Add a listing or open house page</span></button></form>
<?php endif; ?>
<?= dash_close() ?>
