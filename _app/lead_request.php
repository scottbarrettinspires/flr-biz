<?php
// POST /lead-request/ : "Request information" form on an agent's LeadStream page. Emails the broker; the email is the record (same as joinflr.com).
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
if (!empty($_POST['website'])) { echo '{"ok":true}'; exit; }          // honeypot: pretend success, send nothing
$v = function (string $k, int $max = 300): string { $s = isset($_POST[$k]) ? (string)$_POST[$k] : ''; return mb_substr(trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\0"], '', $s))), 0, $max); };
$name = $v('name', 120); $email = $v('email', 160);
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo '{"ok":false,"error":"invalid"}'; exit; }
$agent = null; foreach (seed() as $s) if ($s['code'] === strtolower($v('a', 20))) $agent = $s;
$sponsor = $agent ? ucwords(strtolower($agent['name'])) : '';
$message = mb_substr(trim(str_replace(["\r", "\0"], '', (string)($_POST['message'] ?? ''))), 0, 2000);
$lines = ['New request from a LeadStream page on ' . SITE_DOMAIN, '', "Name: $name", "Email: $email", 'Phone: ' . $v('phone', 40), 'I am: ' . $v('type', 80)];
if ($v('brokerage', 120) !== '') $lines[] = 'Current brokerage: ' . $v('brokerage', 120);
$lines[] = ''; $lines[] = 'SPONSOR TRACKING';
$lines[] = "This page's FLR agent: " . ($sponsor !== '' ? $sponsor . ' (' . $agent['code'] . ')' : 'unknown');
$lines[] = ''; $lines[] = 'Page: ' . $v('page_url', 300); $lines[] = 'Submitted: ' . gmdate('c');
if ($message !== '') { $lines[] = ''; $lines[] = 'Message:'; $lines[] = $message; }
$to = config()['broker_email'] ?? 'broker@myflroffice.com';
$subject = 'FLR request' . ($sponsor !== '' ? " via $sponsor" : '') . ": $name";
$hdr = 'From: ' . BROKERAGE . ' <noreply@' . SITE_DOMAIN . ">\r\nReply-To: " . str_replace(["\r", "\n"], '', $email) . "\r\nContent-Type: text/plain; charset=UTF-8";
$sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', implode("\n", $lines), $hdr);
if (!$sent) { http_response_code(500); echo '{"ok":false,"error":"mail"}'; exit; }
echo '{"ok":true}';
