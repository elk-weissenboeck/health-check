<?php
/**
 * proxy-neu.php (OOP)
 * -----------------------------------------------------------------------------
 * Sicherer Whitelist-Proxy für Health-Checks
 * - Ziel anhand ?key=... (Whitelist)
 * - Auth: basic (User/Pass oder Authorization-Header), bearer, headers, jenkins
 * - Custom-Header (z. B. NC-Token) unterstützt (assoziativ oder "Header: Wert")
 * - SSL via 'verifySSL' (true = prüfen), rückwärtskompatibel zu 'insecure'
 * - Query-Parameter kommen NUR aus 'query' (in targets.php) — Query in 'url' wird ignoriert
 * - Optionaler File-Cache für x Minuten (GET/HEAD), Bypass via ?nocache=1
 */

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// -----------------------------------------------------------------------------
// Klassen LADEN
// -----------------------------------------------------------------------------
require __DIR__ . '/../../classes/myHelpers.class.php';
require __DIR__ . '/../../classes/myCurl.class.php';
require __DIR__ . '/../../classes/myCache.class.php';


// -----------------------------------------------------------------------------
// 0) SECRETS & TARGETS LADEN
// -----------------------------------------------------------------------------
$secrets = require dirname(__DIR__) . '/../secrets.php';
$targets = require dirname(__DIR__) . '/../targets.php';


// -----------------------------------------------------------------------------
// Ablauf
// -----------------------------------------------------------------------------

// 1) Key & Target
$key = (string)($_GET['key'] ?? '');
$t   = myHelpers::requireTarget($targets, $key);

// 2) Methode & finale URL
$method   = myHelpers::method($t);
$finalUrl = myHelpers::buildFinalUrl($t);

// 3) Cache-READ
$noCache   = myHelpers::isNoCache();
$cacheOK   = myCache::isAllowed($t, $method, $noCache);
$cacheFile = $cacheOK ? myCache::path($method, $finalUrl, $key) : '';

if ($cacheOK && $cacheFile !== '') {
    if (myCache::DEBUG) header('X-Proxy-Cache-File: ' . $cacheFile);
    if ($hit = myCache::read($cacheFile)) {
        http_response_code((int)$hit['status']);
        header('Content-Type: ' . ($hit['content_type'] ?: 'application/octet-stream'));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Proxy-Cache: HIT');
    if (isset($hit['expires_at'])) {
      $remaining = max(0, (int)$hit['expires_at'] - time());
      header('X-Proxy-Cache-TTL: ' . $remaining);
    }
    echo (string)$hit['body'];
        return;
    }
}

// 4) cURL Request
[$httpCode, $contentType, $body] = myCurl::request($finalUrl, $method, $t, $secrets);

// 5) Cache-WRITE (nur Erfolgscodes 2xx/3xx)
if ($cacheOK && $cacheFile !== '' && $httpCode >= 200 && $httpCode < 400) {
    myCache::write($cacheFile, [
        'ttl'          => myCache::ttl($t),
        'status'       => (int)$httpCode,
        'content_type' => (string)$contentType,
        'body'         => (string)$body,
    ]);
    header('X-Proxy-Cache: MISS, stored');
    header('X-Proxy-Cache-TTL: ' . (string) myCache::ttl($t));
} else {
    header('X-Proxy-Cache: MISS');
}

// 6) Response an Client
http_response_code($httpCode);
if ($contentType) {
    header('Content-Type: ' . $contentType);
} else {
    header('Content-Type: application/octet-stream');
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $body;
