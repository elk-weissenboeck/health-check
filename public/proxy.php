<?php
/**
 * proxy.php
 * Sicherer Whitelist-Proxy für Health-Checks mit Auth & optionalem SSL-Bypass.
 * - Ziel anhand ?key=... (Whitelist), keine freien URLs
 * - Auth: basic (User/Pass oder Authorization-Header), bearer, headers, jenkins
 * - Custom-Header (z. B. NC-Token) unterstützt
 * - SSL via 'verifySSL' (true = prüfen), rückwärtskompatibel zu 'insecure'
 * - Query-Parameter kommen aus 'query' (in targets.php); Query in 'url' wird IGNORIERT
 * - Optionaler File-Cache für x Minuten
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// 0) SECRETS & TARGETS LADEN
// -----------------------------------------------------------------------------
$secrets = require dirname(__DIR__) . '/config/secrets.php';
$targets = require dirname(__DIR__) . '/config/targets.php';

// -----------------------------------------------------------------------------
// BASIS-KONFIG
// -----------------------------------------------------------------------------
const DEFAULT_TIMEOUT   = 8;                        // Sekunden
const CACHE_DEFAULT_TTL = 300;                      // Sekunden (5 Minuten)
const CACHE_DIR         = __DIR__ . '/cache';       // Cache-Verzeichnis
const CACHE_DEBUG       = false;                    // bei true: Debug-Header

// -----------------------------------------------------------------------------
// CACHE-HELPER
// -----------------------------------------------------------------------------
function _proxy_cache_path(string $method, string $url, string $targetKey): string {
  $dir  = CACHE_DIR ?: sys_get_temp_dir();
  $hash = hash('sha256', $method . "\n" . $url . "\n" . $targetKey);
  return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'proxycache_' . $hash . '.json';
}

function _proxy_cache_read(string $path): ?array {
  if (!is_file($path)) return null;
  $raw = @file_get_contents($path);
  if ($raw === false) return null;
  $data = json_decode($raw, true);
  if (!is_array($data)) return null;
  if (time() > (int)($data['expires_at'] ?? 0)) return null;
  return $data;
}

function _proxy_cache_write(string $path, array $payload): void {
  $dir = dirname($path);
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
  $payload['expires_at'] = time() + (int)($payload['ttl'] ?? CACHE_DEFAULT_TTL);
  @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

// -----------------------------------------------------------------------------
// 1) KEY LESEN & VALIDIEREN
// -----------------------------------------------------------------------------
$key = $_GET['key'] ?? '';
if (!isset($targets[$key])) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Unknown key');
}
$t = $targets[$key];

// -----------------------------------------------------------------------------
// 1a) URL FINAL AUFBAUEN (Query in URL IGNORIEREN, nur 'query' verwenden)
// -----------------------------------------------------------------------------
$baseUrl = (string)($t['url'] ?? '');
$parts   = parse_url($baseUrl);

$scheme   = $parts['scheme'] ?? null;
$host     = $parts['host'] ?? null;
$port     = isset($parts['port']) ? ':' . $parts['port'] : '';
$user     = $parts['user'] ?? null;
$pass     = $parts['pass'] ?? null;
$userInfo = $user !== null ? $user . ($pass !== null ? ':' . $pass : '') . '@' : '';
$path     = $parts['path'] ?? '';
$fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

// 'query' aus targets.php (Array oder String) -> Querystring
$queryArray = [];
if (isset($t['query']) && $t['query'] !== null) {
  if (is_array($t['query'])) {
    $queryArray = $t['query'];
  } elseif (is_string($t['query'])) {
    parse_str(ltrim($t['query'], "?& \t\r\n"), $queryArray);
  }
}
$queryString = http_build_query($queryArray, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);

$finalUrl =
  ($scheme ? $scheme . '://' : '') .
  $userInfo . $host . $port . $path .
  ($queryString !== '' ? '?' . $queryString : '') .
  $fragment;

// -----------------------------------------------------------------------------
// 1b) METHOD & CACHE-READ (nur GET/HEAD, optional Target-Overrides)
// -----------------------------------------------------------------------------
$method     = strtoupper((string)($t['method'] ?? 'GET'));
$ttl        = (int)($t['cache']['ttl'] ?? CACHE_DEFAULT_TTL);
$allowCache = ($t['cache']['enabled'] ?? true) && $ttl > 0 && in_array($method, ['GET','HEAD'], true);
$noCache    = isset($_GET['nocache']) && $_GET['nocache'] !== '0' && $_GET['nocache'] !== '';

$cacheFile = '';
if ($allowCache && !$noCache) {
  $cacheFile = _proxy_cache_path($method, $finalUrl, (string)$key);
  if (CACHE_DEBUG) header('X-Proxy-Cache-File: ' . $cacheFile);
  if ($hit = _proxy_cache_read($cacheFile)) {
    http_response_code((int)$hit['status']);
    header('Content-Type: ' . ($hit['content_type'] ?: 'application/octet-stream'));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Proxy-Cache: HIT');
    echo (string)$hit['body'];
    return;
  }
}

// -----------------------------------------------------------------------------
// 2) CURL AUFBAUEN
// -----------------------------------------------------------------------------
$ch = curl_init();

// URL & Methode
curl_setopt($ch, CURLOPT_URL, $finalUrl);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

// Follow redirects & Response inkl. Header holen
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

// Timeout festlegen
curl_setopt($ch, CURLOPT_TIMEOUT, (int) DEFAULT_TIMEOUT);

// -----------------------------------------------------------------------------
// 2a) SSL-Verify: bevorzugt 'verifySSL' (true = prüfen), Fallback 'insecure'
// -----------------------------------------------------------------------------
$verifySSL = null;
if (array_key_exists('verifySSL', $t)) {
  $verifySSL = (bool)$t['verifySSL'];     // neue Semantik
} elseif (array_key_exists('insecure', $t)) {
  $verifySSL = !$t['insecure'];           // alt: insecure=true => verify=false
}
if ($verifySSL === null) $verifySSL = true;

curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySSL ? 2 : 0);

// -----------------------------------------------------------------------------
// 2b) Header vorbereiten (Custom-Header; indexiert oder assoziativ)
// -----------------------------------------------------------------------------
$headers = $t['headers'] ?? [];
$normalizedHeaders = [];
if (is_array($headers)) {
  foreach ($headers as $k => $v) {
    if (is_int($k)) {
      $normalizedHeaders[] = (string)$v;        // bereits "Header: Wert"
    } else {
      $normalizedHeaders[] = $k . ': ' . $v;    // assoziativ
    }
  }
}
$headers = $normalizedHeaders;

// -----------------------------------------------------------------------------
// 2c) Auth behandeln: basic / bearer / headers / jenkins
// -----------------------------------------------------------------------------
if (!empty($t['auth']) && is_array($t['auth'])) {
  $authType = $t['auth']['type'] ?? null;
  if ($authType) {
    switch ($authType) {
      case 'basic':
        if (!empty($t['auth']['authorization'])) {
          $headers[] = 'Authorization: ' . $t['auth']['authorization'];
        } else {
          $user = (string)($t['auth']['user'] ?? '');
          $pass = (string)($t['auth']['pass'] ?? '');
          curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        }
        break;

      case 'bearer':
        $token = (string)($t['auth']['token'] ?? '');
        $headers[] = 'Authorization: Bearer ' . $token;
        break;

      case 'headers':
        if (!empty($t['auth']['headers']) && is_array($t['auth']['headers'])) {
          foreach ($t['auth']['headers'] as $k => $v) {
            if (is_int($k)) {
              $headers[] = (string)$v;
            } else {
              $headers[] = $k . ': ' . $v;
            }
          }
        }
        break;

      case 'jenkins':
        // Versand exakt wie Basic mit user/password:
        // Wenn URL 'jenkins-tng' enthält -> TNG-Creds, sonst klassische Jenkins-Creds
        $isTng = (strpos((string)($t['url'] ?? ''), 'jenkins-tng') !== false);
        $user  = 'georgw';
        $pass  = $isTng ? ($secrets['JENKINS_TNG_TOKEN'] ?? '') : ($secrets['JENKINS_TOKEN'] ?? '');
        curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
        break;

      case 'nextcloud':
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['NC-Token: ' . $t['auth']['token']]);
        break; 
    }
  }
}

if ($headers) {
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// HEAD-Requests ohne Body
if ($method === 'HEAD') {
  curl_setopt($ch, CURLOPT_NOBODY, true);
}

// -----------------------------------------------------------------------------
// 3) AUSFÜHREN
// -----------------------------------------------------------------------------
$resp = curl_exec($ch);
if ($resp === false) {
  $err = curl_error($ch);
  curl_close($ch);

  http_response_code(502);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Upstream error: ' . $err);
}

// Antwort aufsplitten
$headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
$respHeaders = substr($resp, 0, $headerSize);
$body        = substr($resp, $headerSize);

curl_close($ch);

// -----------------------------------------------------------------------------
// 3a) CACHE WRITE (nur erfolgreiche Antworten)
// -----------------------------------------------------------------------------
if ($allowCache && !$noCache && $cacheFile !== '' && $httpCode >= 200 && $httpCode < 400) {
  _proxy_cache_write($cacheFile, [
    'ttl'          => $ttl,
    'status'       => (int)$httpCode,
    'content_type' => (string)$contentType,
    'body'         => (string)$body,
  ]);
  header('X-Proxy-Cache: MISS, stored');
} else {
  header('X-Proxy-Cache: MISS');
}

// -----------------------------------------------------------------------------
// 4) RESPONSE DURCHREICHEN (Passthrough)
// -----------------------------------------------------------------------------
http_response_code($httpCode);

// Content-Type weitergeben (falls vorhanden), sonst sinnvoller Default
if ($contentType) {
  header('Content-Type: ' . $contentType);
} else {
  header('Content-Type: application/octet-stream');
}

// Caching beim Client unterdrücken (Health-Checks)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// (Optional) bestimmte Upstream-Header whitelisten (bewusst weggelassen)
echo $body;
