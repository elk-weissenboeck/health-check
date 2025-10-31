<?php
/**
 * proxy.php
 * Sicherer Whitelist-Proxy für Health-Checks mit Auth & optional -k
 * - Wählt Ziel anhand ?key=... (keine freien URLs!)
 * - Unterstützt: Basic, Bearer, eigene Header
 * - Optional: SSL-Verify abschalten (entspricht curl -k)
 * - Passthrough: gibt Upstream-Status & Body 1:1 zurück
 */

declare(strict_types=1);

$secrets = require dirname(__DIR__) . '/config/secrets.php';
$targets = require dirname(__DIR__) . '/config/targets.php';


// ---------- 2) KEY LESEN ----------
$key = $_GET['key'] ?? '';
if (!isset($targets[$key])) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Unknown key');
}
$t = $targets[$key];

// ---------- 3) CURL AUFBAUEN ----------
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $t['url']);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $t['method'] ?? 'GET');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, (int)($t['timeout'] ?? 8));
// SSL-Verify wie bei curl -k aus/an
// SSL verification: supports new 'verifySSL' (true = verify) and falls back to old 'insecure' for compatibility
$verifySSL = null;
if (array_key_exists('verifySSL', $t)) {
  $verifySSL = (bool)$t['verifySSL'];
} elseif (array_key_exists('insecure', $t)) {
  $verifySSL = !$t['insecure'];
}
if (is_null($verifySSL)) $verifySSL = true; // default: verify SSL
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySSL ? 2 : 0);
// Auth
$headers = $t['headers'] ?? [];
if (!empty($t['auth'])) {
  switch ($t['auth']) {
    case 'basic':
      curl_setopt($ch, CURLOPT_USERPWD, ($t['auth']['user'] ?? '') . ':' . ($t['auth']['pass'] ?? ''));
      break;
    case 'bearer':
      $headers[] = 'Authorization: Bearer ' . ($t['auth']['token'] ?? '');
      break;
    case 'headers':
      if (!empty($t['auth']['headers']) && is_array($t['auth']['headers'])) {
        $headers = array_merge($headers, $t['auth']['headers']);
      }
      break;
    case 'jenkins':
      // Choose credentials based on URL: contains 'jenkins-tng' => secret1, else secret2
      $isTng = (strpos($t['url'] ?? '', 'jenkins-tng') !== false);
      $pass = $isTng ? ($secrets['JENKINS_TNG_TOKEN'] ?? '') : ($secrets['JENKINS_TOKEN'] ?? '');
      curl_setopt($ch, CURLOPT_USERPWD, 'georgw:' . $pass);
      break;
  }
}
if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Bei HEAD keinen Body übertragen
if (strtoupper($t['method'] ?? '') === 'HEAD') {
  curl_setopt($ch, CURLOPT_NOBODY, true);
}

// ---------- 4) AUSFÜHREN ----------
$resp = curl_exec($ch);
if ($resp === false) {
  $err = curl_error($ch);
  curl_close($ch);
  http_response_code(502);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Upstream error: ' . $err);
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType= curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '';
$respHeaders= substr($resp, 0, $headerSize);
$body       = substr($resp, $headerSize);
curl_close($ch);

// ---------- 5) RESPONSE DURCHREICHEN (Passthrough) ----------
http_response_code($httpCode);

// Content-Type weitergeben (falls vorhanden), sonst sinnvoller Default
if ($contentType) {
  header('Content-Type: ' . $contentType);
} else {
  header('Content-Type: application/octet-stream');
}

// Caching unterdrücken (Health-Checks)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// (Optional) einzelne Header aus Upstream whitelisten (z. B. ETag / Cache-Control)
// Hier weglassen, um keine sensiblen Informationen zu leaken.

echo $body;
