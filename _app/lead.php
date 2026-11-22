<?php
// POST /lead : validate, rate-limit, store and deliver a lead from a landing page form.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /', true, 303); exit; }
$a = agent(strtolower((string)($_POST['agent'] ?? '')));
$form = (string)($_POST['form'] ?? '');
$defs = page_defs();
if (!$a || !isset($defs[$form])) { http_response_code(400); echo 'Bad request'; exit; }
$ret = (string)($_POST['return'] ?? '');
$back = '/' . $a['code'] . '/' . ((isset($defs[$ret]) && $defs[$ret]['type'] === 'video' && $defs[$form]['type'] === 'consult') ? $ret : $form) . '/';
$fail = function (string $msg) use ($back) {
    http_response_code(422);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/style.css"><main class="card"><div class="note err">'
       . h($msg) . '</div><a class="btn" href="' . h($back) . '"><span class="t">Go back</span></a></main>'; exit;
};
// bots: honeypot + too-fast submit
if (!empty($_POST['company'])) { header('Location: ' . $back . '?done=1', true, 303); exit; }
$ts = (int)($_POST['ts'] ?? 0);
if ($ts < time() - 86400 || $ts > time() - 3) $fail('Please wait a moment and submit the form again.');
// rate limit: 8 per hour per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$rl = DATA_DIR . '/rl'; if (!is_dir($rl)) { @mkdir($rl, 0755, true); }
$rf = $rl . '/' . hash('sha256', $ip) . '.json';
$hits = array_filter(is_file($rf) ? (json_decode((string)file_get_contents($rf), true) ?: []) : [], fn($t) => $t > time() - 3600);
if (count($hits) >= 8) $fail('Too many submissions. Please try again later.');
$hits[] = time(); @file_put_contents($rf, json_encode(array_values($hits)), LOCK_EX);

$name  = trim(preg_replace('/\s+/', ' ', (string)($_POST['name'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
if ($name === '' || mb_strlen($name) > 100) $fail('Please enter your name.');
if (strlen(preg_replace('/\D/', '', $phone)) < 10 || !preg_match('/^[0-9()+ .\-]{10,24}$/', $phone)) $fail('Please enter a valid phone number.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fail('Please enter a valid email address.');
if (empty($_POST['consent'])) $fail('Please check the consent box to continue.');
$lead = ['form' => $form, 'name' => $name, 'phone' => $phone, 'email' => $email,
         'consented_at' => gmdate('c'), 'ip' => $ip, 'ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200)];
if ($defs[$form]['type'] === 'value' || !empty($defs[$form]['address_field'])) {
    $addr = trim((string)($_POST['address'] ?? ''));
    if ($addr === '' || mb_strlen($addr) > 200) $fail('Please enter the property address.');
    $lead['address'] = $addr;
}
if (!empty($defs[$form]['time_field'])) {
    [$dmin, $dmax] = consult_window();
    $pd = (string)($_POST['pref_date'] ?? ''); $pt = (string)($_POST['pref_time'] ?? '');
    $okd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $pd) && checkdate((int)substr($pd, 5, 2), (int)substr($pd, 8, 2), (int)substr($pd, 0, 4)) && $pd >= $dmin && $pd <= $dmax;
    if (!$okd) $fail('Please choose a date from tomorrow up to 60 days out.');
    if (!in_array($pt, consult_times(), true)) $fail('Please choose a time.');
    $lead['pref_date'] = $pd; $lead['pref_time'] = $pt;
}
if (!empty($_POST['message'])) $lead['message'] = mb_substr(trim((string)$_POST['message']), 0, 800);
if (!save_lead($a, $lead)) $fail('Sorry, something went wrong. Please call instead.');
header('Location: ' . $back . '?done=1', true, 303);
