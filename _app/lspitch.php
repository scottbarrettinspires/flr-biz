<?php
/** /<code>/leadstream/ : the LeadStream Systems pitch for agents (same section as on the joinflr.com agent page), shown from the "Are you a real estate agent?" footer link. */
function render_leadstream_pitch(array $a): string {
    $code = $a['code']; $p = paths_for($code); $name = ucwords(strtolower($a['name'])); $first = explode(' ', $name)[0];
    $photo = photo_url($code);
    $cta = '<a class="btn btn-ls" href="#contact">Ask for a LeadStream walkthrough</a>';
    $section = str_replace('{{CTA}}', $cta, (string)file_get_contents(__DIR__ . '/ls_section.html'));
    $call = !empty($a['phone']) ? '<a class="btn btn-teal btn-sm" href="tel:' . h(preg_replace('/[^0-9+]/', '', $a['phone'])) . '">Call ' . h($first) . '</a>' : '';
    $contact = '<section class="section contact" id="contact"><div class="wrap split"><div class="scott-card"><img class="scott-photo" src="/assets/scott-headshot.png" width="563" height="700" alt="Scott Barrett, managing broker of Florida Luxury Realty" loading="lazy"><div class="scott-text">'
      . '<p class="eyebrow">Next step</p><h2>Request information</h2>'
      . '<p>The fastest way to find out if FLR is right for you is to talk with me directly. No pressure, just a straight conversation about your business and your goals.</p>'
      . '<p class="scott-motto">Your success is my mission.</p><p class="scott-name"><strong>Scott Barrett</strong><br>Managing Broker, Florida Luxury Realty</p>'
      . '<div class="scott-actions"><a class="btn btn-teal btn-call" href="tel:+17272433664">Call Scott directly<br><span style="white-space:nowrap;font-size:1.25rem">727-243-3664</span></a></div>'
      . '<p class="scott-note">Call my direct line. I answer personally.</p><p class="muted">Can\'t talk right now? Send the form and I will call you back. Always here to help.</p></div></div>'
      . '<form id="lead-form" class="form" novalidate><input type="hidden" name="a" value="' . h($code) . '">'
      . '<label>Full name<input name="name" type="text" autocomplete="name" required></label><label>Email<input name="email" type="email" autocomplete="email" required></label>'
      . '<label>Phone<input name="phone" type="tel" autocomplete="tel"></label>'
      . '<label>I am<select name="type"><option>An experienced agent</option><option>A new licensee</option><option>Getting licensed soon</option><option>Referring an agent</option></select></label>'
      . '<label>Current brokerage (optional)<input name="brokerage" type="text"></label><label>Anything you want us to know<textarea name="message" rows="3"></textarea></label>'
      . '<p class="muted ref-note">Your request goes to Scott Barrett, Managing Broker. We record that ' . h($name) . ' introduced you to FLR.</p>'
      . '<div class="hp" aria-hidden="true"><label>Leave blank<input name="website" tabindex="-1" autocomplete="off"></label></div>'
      . '<button class="btn btn-teal" type="submit">Send my request</button><p id="form-status" class="status" role="status"></p></form></div></section>';
    $bye = '<div class="wrap"><div class="lsbye">' . ($photo ? '<img src="' . h($photo) . '" alt="">' : '') . '<div class="who"><small>Shared with you by</small><strong>' . h($name) . '</strong><span>' . h($a['title']) . ' &middot; ' . h(BROKERAGE) . ', Inc.</span></div>' . $call
         . ' <a class="btn btn-ghost btn-sm" href="/' . h($p['main']) . '/">See my mobile page</a></div></div>';
    $menu = '<a href="/' . h($p['main']) . '/">My mobile page</a><a href="/' . h($p['main']) . '/about/">About ' . h($first) . '</a>'
          . '<a class="btn btn-ls btn-sm" href="#contact">Request information</a>';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
      . '<title>LeadStream Systems | ' . h($name) . ' | ' . BROKERAGE . ', Inc.</title><meta name="description" content="Lead generation and conversion, exclusively at Florida Luxury Realty. Turn everything you do into a lead funnel.">'
      . '<meta name="robots" content="noindex,follow"><meta property="og:title" content="LeadStream Systems at Florida Luxury Realty"><meta property="og:image" content="https://' . SITE_DOMAIN . '/assets/leadstream-logo.png">'
      . '<link rel="canonical" href="https://' . SITE_DOMAIN . '/' . $p['main'] . '/leadstream/"><link rel="icon" type="image/png" href="/assets/leadstream-favicon.png"><meta name="theme-color" content="#3D4246">'
      . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
      . '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600;1,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">'
      . '<link rel="stylesheet" href="/assets/ls-pitch.css?v=' . filemtime(dirname(__DIR__) . '/assets/ls-pitch.css') . '"></head><body class="lspitch">'
      . '<header class="nav"><div class="wrap nav-inner"><a class="brand" href="/' . h($p['main']) . '/" aria-label="Back to ' . h($name) . '"><img src="/assets/flr-logo-white.png" width="173" height="46" alt="Florida Luxury Realty"></a>'
      . '<button class="menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="menu">Menu</button><nav id="menu" class="menu">' . $menu . '</nav></div></header>'
      . '<main>' . $section . $contact . $bye . '</main>'
      . '<footer class="lsfoot"><div class="wrap">' . BROKERAGE . ', Inc.<br>Equal Housing Opportunity</div></footer>'
      . '<script>(function(){var f=document.getElementById("lead-form"),st=document.getElementById("form-status");'
      . 'function say(m,c){st.textContent=m;st.className="status "+(c||"")}'
      . 'f.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){d[k]=String(v).trim()});if(d.website)return;'
      . 'if(!d.name||!/^\\S+@\\S+\\.\\S+$/.test(d.email||"")){say("Please add your name and a valid email.","err");return}'
      . 'd.page_url=location.href;var btn=f.querySelector("button[type=submit]");btn.disabled=true;say("Sending...");'
      . 'fetch("/lead-request/",{method:"POST",body:new URLSearchParams(d)}).then(function(r){return r.json().catch(function(){return{}})}).then(function(j){if(!j.ok)throw 0;'
      . 'var b=document.createElement("div");b.className="form-done";b.setAttribute("role","status");b.innerHTML="<span class=\\"form-done-ic\\" aria-hidden=\\"true\\">&#10003;</span><h3>Your request has been submitted</h3><p>Scott will reach out to you soon and looks forward to speaking with you.</p>";f.replaceWith(b);b.scrollIntoView({behavior:"smooth",block:"center"})})'
      . '.catch(function(){btn.disabled=false;say("We could not send that just now. Please call Scott directly at 727-243-3664.","err")})});})();'
      . '(function(){var b=document.querySelector(".menu-btn"),m=document.getElementById("menu");b.addEventListener("click",function(){var o=m.classList.toggle("open");b.setAttribute("aria-expanded",o)})})();</script>'
      . '</body></html>';
}
