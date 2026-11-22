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
        'linkedin'   => clean_url($_POST['linkedin'] ?? '', 'https://linkedin.com/in/'),
        'x'          => clean_url($_POST['x'] ?? '', 'https://x.com/'),
        'bio'        => mb_substr(trim(str_replace("\r\n", "\n", (string)($_POST['bio'] ?? ''))), 0, 2000),
    ];
    if ($raw['phone'] === null) $errors[] = 'Phone can only contain numbers, spaces and ( ) + - .';
    if ($raw['email'] !== '' && !filter_var($raw['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'That email address does not look right.';
    foreach (['website', 'instagram', 'facebook', 'tiktok', 'linkedin', 'x'] as $k)
        if (trim($_POST[$k] ?? '') !== '' && $raw[$k] === '') $errors[] = ($k === 'x' ? 'X' : ($k === 'linkedin' ? 'LinkedIn' : ucfirst($k))) . ' link does not look right.';
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
$photo = photo_url($code);
$field = fn($id, $label, $v, $ph, $type = 'text', $hint = '') =>
    '<label for="' . $id . '">' . h($label) . '</label><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . h($v) . '" placeholder="' . h($ph) . '" autocomplete="off">'
    . ($hint ? '<small>' . h($hint) . '</small>' : '');
?><?php
$first = explode(' ', ucwords(strtolower($agent['name'])))[0]; $q = '?a=' . urlencode($code) . '&k=' . urlencode($key); $nslots = count(slots_load($code));
echo dash_open('overview', $agent, $key, 'Welcome, ' . $first, 'Your LeadStream Systems dashboard: mobile pages, listing and open house pages, and the links and QR codes that bring you leads.');
?>
<div class="tiles">
  <a class="tile" href="#info"><span class="n">1</span><strong>Complete your info</strong><small>Photo, phone, email, and social links.</small></a>
  <a class="tile" href="/setup/listings/<?= h($q) ?>"><span class="n">2</span><strong>Add listing &amp; open house pages</strong><small><?= $nslots ?> page<?= $nslots === 1 ? '' : 's' ?> ready for yard signs and flyers. Choose which home each one shows.</small></a>
  <a class="tile" href="/setup/links/<?= h($q) ?>"><span class="n">3</span><strong>Share your mobile pages &amp; QR codes</strong><small>Text, email or post any page, or download its QR code to print.</small></a>
</div>
<h2 id="info">My Personal Information</h2>
<?php if ($saved): ?><div class="note ok">Saved. Your page is updated. <a href="/<?= h($p['main']) ?>/" target="_blank" rel="noopener">View my page</a></div><?php endif; ?>
<?php foreach ($errors as $e): ?><div class="note err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form wideform">
<input type="hidden" name="a" value="<?= h($code) ?>"><input type="hidden" name="k" value="<?= h($key) ?>">
<label>Your photo</label>
<div class="photo-row"><div class="avatar sm" data-initials="<?= h(initials_text($agent['name'])) ?>"><?= $photo ? '<img src="' . h($photo) . '" alt="">' : '' ?></div>
<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div><small>JPG, PNG or WebP. A clear head-and-shoulders photo works best; it is cropped to a square.</small>
<label for="bio" style="margin-top:18px">About me <small style="display:inline;font-weight:400">(shown on your About Me page)</small></label>
<textarea id="bio" name="bio" rows="6" maxlength="2000" placeholder="Tell visitors who you are, how long you have worked in real estate, the areas you serve and how you help buyers and sellers. Leave a blank line between paragraphs."><?= h($val('bio')) ?></textarea>
<?php
$ifield = fn($id, $label, $ph, $type, $color) => '<div class="icell"><label for="' . $id . '">' . h($label) . '</label><div class="ifield"><span class="ico" style="color:' . $color . '"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . ICONS[$id === 'x' ? 'x' : ($id === 'phone' || $id === 'email' || $id === 'website' ? $id : $id)] . '</svg></span><input id="' . $id . '" name="' . $id . '" type="' . $type . '" value="' . h($val($id)) . '" placeholder="' . h($ph) . '" autocomplete="off"></div></div>';
echo '<div class="igrid">';
echo $ifield('phone', 'Phone', '(727) 555-1234', 'tel', '#2e9e5b');
echo $ifield('email', 'Email', 'you@example.com', 'email', '#e0302f');
echo $ifield('website', 'Website', 'https://', 'text', '#0e3a4d');
echo $ifield('instagram', 'Instagram', '@yourname or link', 'text', '#E4405F');
echo $ifield('facebook', 'Facebook', 'link to your page', 'text', '#1877F2');
echo $ifield('tiktok', 'TikTok', '@yourname or link', 'text', '#111111');
echo $ifield('linkedin', 'LinkedIn', 'link to your profile', 'text', '#0A66C2');
echo $ifield('x', 'X (Twitter)', '@yourname or link', 'text', '#111111');
echo '</div>';
?>
<button class="btn contact" type="submit"><span class="t">Save my page</span></button>
</form>
<?= dash_close() ?>
