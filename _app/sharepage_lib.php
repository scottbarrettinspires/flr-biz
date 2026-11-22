<?php
/** Returns the normalized public page URL when $u is a page on this site that belongs to an agent, else null. */
function share_target_url(string $u): ?string {
    $u = trim($u);
    if (strlen($u) > 200 || preg_match('/\s/', $u)) return null;
    if (!preg_match('~^https://' . preg_quote(SITE_DOMAIN, '~') . '/([a-z0-9][a-z0-9-]*)((?:/[a-z0-9-]+){0,2})/?$~i', $u, $m)) return null;
    if (!isset(routes()[strtolower($m[1])])) return null;
    return 'https://' . SITE_DOMAIN . '/' . strtolower($m[1]) . strtolower($m[2]) . '/';
}
