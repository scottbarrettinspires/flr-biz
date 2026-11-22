<?php
// MLS Grid (Stellar MLS, RESO Web API v2) listings for the agent dashboard.
// An agent's Active / Coming Soon / Pending listings appear in a pull-down; assigning one fills a listing page and its "View Listing Details" page.
// Credentials live in _data/config.json (never in git):  {"mls_token":"...", "mls_agent_ids":{"ab1":"<agent MLS ID>"}, "mls_system":"mfrmls", "mls_base_url":"https://api.mlsgrid.com/v2"}

const MLS_STATUSES = ['Active', 'Coming Soon', 'Active Under Contract', 'Pending'];
const MLS_LIST_TTL = 900;      // agent's listing list is cached 15 minutes
const MLS_REFRESH_TTL = 3600;  // a linked listing's status/price is re-checked at most hourly when its pages are viewed

function mls_config(): array {
    $f = DATA_DIR . '/config.json'; $c = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    return ['token' => trim((string)($c['mls_token'] ?? '')), 'base' => rtrim((string)($c['mls_base_url'] ?? 'https://api.mlsgrid.com/v2'), '/'),
            'system' => preg_replace('/[^A-Za-z0-9 _-]/', '', (string)($c['mls_system'] ?? 'mfrmls')) ?: 'mfrmls', 'agents' => (array)($c['mls_agent_ids'] ?? [])];
}
function mls_agent_id(array $a): string {
    $c = mls_config(); $id = trim((string)($c['agents'][$a['code']] ?? $a['mls_agent_id'] ?? ''));
    return preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) ? $id : '';
}
function mls_dir(): string { $d = DATA_DIR . '/mls'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }

/** GET one RESO Web API page. Returns [ok, rows|note]. */
function mls_get(string $path, array $query, int $timeout = 20): array {
    $c = mls_config(); if ($c['token'] === '') return [false, 'MLS Grid is not connected yet.'];
    $ch = curl_init($c['base'] . '/' . ltrim($path, '/') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_ENCODING => 'gzip', CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['token'], 'Accept: application/json']]);
    $body = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    if ($body === false) return [false, 'Could not reach MLS Grid (' . $err . ').'];
    $j = json_decode((string)$body, true);
    if ($http === 401 || $http === 403) return [false, 'MLS Grid did not accept the access token.'];
    if ($http === 429) return [false, 'MLS Grid asked us to slow down. Try again in a minute.'];
    if ($http !== 200 || !is_array($j)) return [false, 'MLS Grid returned HTTP ' . $http . '.'];
    return [true, (array)($j['value'] ?? [])];
}

function mls_parse(array $r): array {
    $unit = trim((string)($r['UnitNumber'] ?? ''));
    $street = trim(preg_replace('/\s+/', ' ', ($r['StreetNumber'] ?? '') . ' ' . ($r['StreetDirPrefix'] ?? '') . ' ' . ($r['StreetName'] ?? '') . ' ' . ($r['StreetSuffix'] ?? '') . ' ' . ($r['StreetDirSuffix'] ?? '')))
            . ($unit !== '' ? ' #' . ltrim($unit, '#') : '');
    $city = trim((string)($r['City'] ?? '')); $st = trim((string)($r['StateOrProvince'] ?? '')); $zip = substr(trim((string)($r['PostalCode'] ?? '')), 0, 5);
    return ['key' => (string)($r['ListingKey'] ?? ''), 'number' => preg_replace('/^MFR/i', '', (string)($r['ListingId'] ?? '')), 'status' => (string)($r['StandardStatus'] ?? ''),
        'street' => $street, 'city' => $city, 'state' => $st, 'zip' => $zip,
        'address' => $street . ($city !== '' ? ', ' . $city : '') . ($st !== '' ? ', ' . $st : '') . ($zip !== '' ? ' ' . $zip : ''),
        'price' => (float)($r['ListPrice'] ?? 0), 'beds' => (int)($r['BedroomsTotal'] ?? 0), 'baths' => (float)($r['BathroomsTotalDecimal'] ?? $r['BathroomsTotalInteger'] ?? 0),
        'sqft' => (int)($r['LivingArea'] ?? 0), 'year' => (int)($r['YearBuilt'] ?? 0), 'acres' => (float)($r['LotSizeAcres'] ?? 0),
        'type' => trim((string)($r['PropertySubType'] ?? $r['PropertyType'] ?? '')), 'remarks' => trim((string)($r['PublicRemarks'] ?? '')),
        'office' => trim((string)($r['ListOfficeName'] ?? '')), 'agent' => trim((string)($r['ListAgentFullName'] ?? '')),
        'modified' => (string)($r['ModificationTimestamp'] ?? ''), 'canview' => !array_key_exists('MlgCanView', $r) || $r['MlgCanView'] !== false];
}
function mls_money(float $n): string { return $n > 0 ? '$' . number_format($n) : ''; }
function mls_visible(array $l): bool { return !empty($l['canview']) && in_array($l['status'], MLS_STATUSES, true); }

/** The agent's Active/Coming Soon/Pending listings. Returns [listings, note]; note is '' when fine, else why the list is empty/stale. */
function mls_agent_listings(array $a, bool $force = false): array {
    $c = mls_config(); $agentId = mls_agent_id($a);
    if ($c['token'] === '') return [[], 'The MLS connection has not been turned on yet.'];
    if ($agentId === '') return [[], 'Your MLS agent ID is not on file yet. Ask the office to add it.'];
    $cf = mls_dir() . '/' . $a['code'] . '.json'; $cache = is_file($cf) ? (json_decode((string)file_get_contents($cf), true) ?: null) : null;
    if (!$force && $cache && time() - (int)($cache['fetched'] ?? 0) < MLS_LIST_TTL) return [$cache['listings'], ''];
    $all = []; $note = '';
    foreach (MLS_STATUSES as $stt) {
        [$ok, $rows] = mls_get('Property', ['$filter' => "OriginatingSystemName eq '{$c['system']}' and MlgCanView eq true and ListAgentMlsId eq '$agentId' and StandardStatus eq '$stt'", '$top' => 50]);
        if (!$ok) { $note = (string)$rows; break; }
        foreach ($rows as $r) { $p = mls_parse($r); if ($p['key'] !== '' && mls_visible($p)) $all[$p['key']] = $p; }
    }
    if ($note !== '') return $cache ? [$cache['listings'], $note . ' Showing the last saved list.'] : [[], $note];
    $order = array_flip(MLS_STATUSES); $list = array_values($all);
    usort($list, fn($x, $y) => [$order[$x['status']] ?? 9, $x['address']] <=> [$order[$y['status']] ?? 9, $y['address']]);
    file_put_contents($cf, json_encode(['fetched' => time(), 'listings' => $list]), LOCK_EX);
    return [$list, ''];
}

function mls_detail_path(string $code, string $slot): string { return mls_dir() . "/$code-$slot.json"; }
function mls_details(string $code, string $slot): ?array {
    $f = mls_detail_path($code, $slot); return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: null) : null;
}
function mls_photo_dir(): string { $d = UPLOAD_DIR . '/mls'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function mls_clear_photos(string $code, string $slot): void { foreach (glob(mls_photo_dir() . "/$code-$slot-*.jpg") ?: [] as $f) @unlink($f); }
function mls_photo_urls(string $code, string $slot): array {
    $o = []; foreach (glob(mls_photo_dir() . "/$code-$slot-*.jpg") ?: [] as $f) $o[] = '/uploads/mls/' . basename($f) . '?v=' . filemtime($f);
    natsort($o); return array_values($o);
}

/** Download one MLS photo (MLS Grid requires serving images from our own server) as a 1200px-wide JPEG. */
function mls_download_photo(string $url, string $dest): bool {
    if (!preg_match('~^https?://~i', $url)) return false;
    $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_MAXFILESIZE => 15 * 1024 * 1024]);
    $b = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($b === false || $http !== 200) return false;
    $src = @imagecreatefromstring($b); if (!$src) return false;
    $w = imagesx($src); $h = imagesy($src); $nw = min(1200, $w); $nh = (int)round($h * $nw / $w);
    $dst = imagecreatetruecolor($nw, $nh); imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return imagejpeg($dst, $dest, 82);
}

/** Link a listing to a page: copies its facts, downloads up to 8 photos, and uses the first as the cover image. Returns [slot fields|null, error]. */
function mls_assign(array $a, string $slotId, string $key): array {
    [$list] = mls_agent_listings($a);
    if (!isset(array_column($list, null, 'key')[$key])) return [null, 'That listing is not in your list of active or pending MLS listings. Click Refresh list and try again.'];
    $c = mls_config();
    [$ok, $rows] = mls_get('Property', ['$filter' => "OriginatingSystemName eq '{$c['system']}' and ListingKey eq '" . str_replace("'", '', $key) . "'", '$expand' => 'Media', '$top' => 1]);
    if (!$ok) return [null, (string)$rows];
    if (!$rows) return [null, 'MLS Grid did not return that listing.'];
    $l = mls_parse($rows[0]); if (!mls_visible($l)) return [null, 'That listing is no longer active, so it cannot be shown.'];
    $media = (array)($rows[0]['Media'] ?? []);
    usort($media, fn($x, $y) => (int)($x['Order'] ?? 999) <=> (int)($y['Order'] ?? 999));
    mls_clear_photos($a['code'], $slotId); $n = 0;
    foreach ($media as $m) {
        if ($n >= 8) break;
        if (!empty($m['MediaCategory']) && stripos((string)$m['MediaCategory'], 'photo') === false) continue;
        if (mls_download_photo((string)($m['MediaURL'] ?? ''), mls_photo_dir() . "/{$a['code']}-$slotId-" . ($n + 1) . '.jpg')) $n++;
    }
    if ($n > 0) slot_store_photo($a['code'], $slotId, mls_photo_dir() . "/{$a['code']}-$slotId-1.jpg");   // first photo becomes the page's cover image
    $l['photos'] = $n; $l['fetched'] = time();
    file_put_contents(mls_detail_path($a['code'], $slotId), json_encode($l), LOCK_EX);
    return [['address' => $l['address'], 'price' => mls_money($l['price']), 'mls_key' => $key, 'mls_number' => $l['number'], 'mls_status' => $l['status']], ''];
}
function mls_unlink(string $code, string $slot): void {
    @unlink(mls_detail_path($code, $slot)); mls_clear_photos($code, $slot);
}

/** Re-check a linked listing's status and price (at most hourly). Marks it unavailable when it is no longer active/viewable. */
function mls_refresh(array $a, string $slotId, array $slot): ?array {
    $d = mls_details($a['code'], $slotId); if (!$d || empty($slot['mls_key'])) return $d;
    if (time() - (int)($d['fetched'] ?? 0) < MLS_REFRESH_TTL) return $d;
    $c = mls_config(); if ($c['token'] === '') return $d;
    [$ok, $rows] = mls_get('Property', ['$filter' => "OriginatingSystemName eq '{$c['system']}' and ListingKey eq '" . str_replace("'", '', $slot['mls_key']) . "'", '$top' => 1], 6);
    if (!$ok) { $d['fetched'] = time() - MLS_REFRESH_TTL + 300; file_put_contents(mls_detail_path($a['code'], $slotId), json_encode($d), LOCK_EX); return $d; }   // API trouble: keep what we have, retry in 5 min
    if (!$rows) { $d['status'] = 'Unavailable'; $d['canview'] = false; }
    else { $photos = $d['photos'] ?? 0; $d = mls_parse($rows[0]) + ['photos' => $photos]; }
    $d['fetched'] = time(); file_put_contents(mls_detail_path($a['code'], $slotId), json_encode($d), LOCK_EX);
    return $d;
}
