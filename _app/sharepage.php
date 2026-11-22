<?php
// /share/?u=<page url> : public "Share This" page with the QR code and quick share buttons for one page.
require_once __DIR__ . '/sharepage_lib.php';
$u = share_target_url((string)($_GET['u'] ?? ''));
if (!$u) not_found();
preg_match('~^https://[^/]+/([^/]+)~', $u, $m);
[$code, $view] = routes()[strtolower($m[1])];
$a = agent($code);
$name = ucwords(strtolower($a['name'])); $photo = photo_url($code);
$qs = '?u=' . rawurlencode($u);
$msg = 'From ' . $name . ' (' . BROKERAGE . '): ' . $u;
$short = preg_replace('~^https://|/$~', '', $u);
$back = parse_url($u, PHP_URL_PATH);
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex, nofollow');
echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">'
  . '<title>Share This | ' . h($name) . ' | ' . BROKERAGE . '</title>'
  . '<link rel="stylesheet" href="/assets/style.css?v=' . filemtime(dirname(__DIR__) . '/assets/style.css') . '"></head><body><main class="card sharepage">'
  . '<a class="back vback" href="' . h($back) . '">&larr; Back</a>'
  . '<div class="shp-who">' . ($photo ? '<img src="' . h($photo) . '" alt="">' : '') . '<div><strong>' . h($name) . '</strong><span>' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . '</span></div></div>'
  . '<h1 class="pt">Share This</h1><p class="tag">Have someone scan the QR code with their phone camera, or send them the link.</p>'
  . '<div class="shp-qr"><img src="/share/qr/' . h($qs) . '&amp;fmt=svg" alt="QR code for ' . h($short) . '" width="220" height="220"><a class="shp-url" href="' . h($u) . '">' . h($short) . '</a></div>'
  . '<nav class="links">'
  . menu_button('email', 'Email It To Someone', 'mailto:?subject=' . rawurlencode('A link from ' . $name) . '&body=' . rawurlencode($msg))
  . menu_button('chat', 'Text It To Someone', 'sms:?&body=' . rawurlencode($msg))
  . menu_button('facebook', 'Share To Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($u), true)
  . '<a class="btn" href="https://www.instagram.com/" id="shp-ig">' . btn_icon(['icon' => 'instagram']) . '<span class="tx"><span class="t">Share To Instagram</span></span></a>'
  . '<p class="shp-note" id="shp-note" hidden>Link copied. Paste it into your Instagram story, post, bio or message.</p>'
  . '<a class="btn" href="#" id="shp-copy">' . btn_icon(['icon' => 'link']) . '<span class="tx"><span class="t">Copy Link</span></span></a>'
  . '<a class="btn" href="#" id="shp-native" hidden>' . btn_icon(['icon' => 'share']) . '<span class="tx"><span class="t">More Ways To Share</span></span></a>'
  . '<a class="btn" href="/share/qr/' . h($qs) . '&amp;fmt=png&amp;dl=1">' . btn_icon(['icon' => 'download']) . '<span class="tx"><span class="t">Download QR Code</span></span></a>'
  . '</nav><footer>' . BROKERAGE . '<br><span>Equal Housing Opportunity</span></footer></main>'
  . '<script>(function(){var U=' . json_encode($u) . ',T=' . json_encode('From ' . $name) . ';'
  . 'function copy(){try{if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(U)}catch(e){}'
  . 'return new Promise(function(r){var t=document.createElement("textarea");t.value=U;document.body.appendChild(t);t.select();try{document.execCommand("copy")}catch(e){}document.body.removeChild(t);r()})}'
  . 'function flash(el,txt){var s=el.querySelector(".t"),o=s.textContent;s.textContent=txt;setTimeout(function(){s.textContent=o},2000)}'
  . 'document.getElementById("shp-copy").addEventListener("click",function(e){e.preventDefault();var el=this;copy().then(function(){flash(el,"Link copied!")})});'
  . 'document.getElementById("shp-ig").addEventListener("click",function(e){e.preventDefault();var n=document.getElementById("shp-note");copy().then(function(){n.hidden=false;setTimeout(function(){window.open("https://www.instagram.com/","_blank","noopener")},700)})});'
  . 'if(navigator.share){var b=document.getElementById("shp-native");b.hidden=false;b.addEventListener("click",function(e){e.preventDefault();navigator.share({title:T,url:U}).catch(function(){})})}'
  . '})();</script></body></html>';
