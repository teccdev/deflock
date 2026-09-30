<?php
/**
 * Server-side proxy for the DeFlock ALPR counts feed.
 *
 * Fetches https://cdn.deflock.me/alpr-counts.json from the server instead of the
 * browser, which avoids the cross-origin restrictions that the public
 * allorigins.win proxy was working around. A short-lived local cache keeps the
 * feed available even when the upstream is slow or briefly unreachable.
 */

const UPSTREAM_URL = 'https://cdn.deflock.me/alpr-counts.json';
const CACHE_TTL = 300; // seconds a cached response is considered fresh
const STALE_TTL = 86400; // seconds a cached response may be served as a fallback
const CACHE_FILE = __DIR__ . '/.alpr-counts.cache.json';
const REQUEST_TIMEOUT = 10; // seconds

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('X-Content-Type-Options: nosniff');

/**
 * Fetch the feed with cURL, falling back to the streams wrapper.
 * Returns the raw response body, or null on failure.
 */
function fetch_upstream($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_USERAGENT => 'deflockindiana.org/1.0 (+https://deflockindiana.org)',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            return $body;
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => REQUEST_TIMEOUT,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: deflockindiana.org/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);

    return $body === false ? null : $body;
}

/**
 * Return the decoded payload only when it is valid JSON with a numeric "us"
 * value, otherwise null.
 */
function valid_payload($body)
{
    $data = json_decode($body, true);

    return is_array($data) && isset($data['us']) && is_numeric($data['us']) ? $data : null;
}

/** Emit a cached body, optionally flagging it as stale. */
function serve_cache($stale = false)
{
    $body = file_get_contents(CACHE_FILE);
    if ($stale) {
        header('X-Cache: stale');
    }
    echo $body;
    exit;
}

// Serve a fresh cached copy when available.
if (is_readable(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL) {
    if (valid_payload(file_get_contents(CACHE_FILE)) !== null) {
        header('X-Cache: hit');
        serve_cache();
    }
}

// Fetch fresh data from upstream.
$body = fetch_upstream(UPSTREAM_URL);
$data = $body === null ? null : valid_payload($body);

if ($data !== null) {
    @file_put_contents(CACHE_FILE, $body, LOCK_EX);
    header('X-Cache: miss');
    echo $body;
    exit;
}

// Upstream failed: fall back to a stale cache if it is recent enough.
if (is_readable(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < STALE_TTL) {
    if (valid_payload(file_get_contents(CACHE_FILE)) !== null) {
        serve_cache(true);
    }
}

http_response_code(502);
echo json_encode(['error' => 'Unable to retrieve ALPR counts']);
