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

$jenkinsBase  = rtrim($secrets['JENKINS_BASE_URL'] ?? '', '/');
$jenkinsUser  = $secrets['JENKINS_USER'] ?? '';
$jenkinsToken = $secrets['JENKINS_TOKEN'] ?? '';
$jenkinsTree  = 'result,duration,timestamp,building';
$jenkinsAuth  = ['type' => 'basic', 'user' => $jenkinsUser, 'pass' => $jenkinsToken];


// ---------- 1) ZIELE DEFINIEREN (WHITELIST) ----------
$TARGETS = [
  // Beispiel: Basic-Auth + SSL-Verify AUS + GET
  'hf-enterprise-full' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Unternehmensdaten%20PROD%20Daily%20FULL/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,   // -> wie curl -k (nur wenn nötig!)
    'timeout'  => 8,
  ],
  'hf-enterprise-quick' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Unternehmensdaten%20PROD%20Daily%20S5+S6/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-hauseubergabe-ninox' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Hausuebergabe%20Ninox%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-hauseubergabe-documents' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Hausuebergabe%20PROD%20Daily%20Documents/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-hauseubergabe-pictures' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Hausuebergabe%20PROD%20Daily%20Pictures/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-maengelkostenanzeige' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20MaengelKostenAnzeige%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-planbesprechung' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Planbesprechung%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-protokolle' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Protokolle%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-qualitaetsmanagement' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Qualitaetsmanagement%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-regieschein' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Regieschein%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],
  'hf-wochenbericht' => [
    'url'      => "{$jenkinsBase}/job/HF-API%20Wochenbericht%20PROD%20Daily/lastBuild/api/json?tree={$jenkinsTree}",
    'method'   => 'GET',
    'auth'     => $jenkinsAuth,
    'headers'  => ['Accept: application/json'],
    'insecure' => true,
    'timeout'  => 8,
  ],


];

// ---------- 2) KEY LESEN ----------
$key = $_GET['key'] ?? '';
if (!isset($TARGETS[$key])) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  exit('Unknown key');
}
$t = $TARGETS[$key];

// ---------- 3) CURL AUFBAUEN ----------
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $t['url']);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $t['method'] ?? 'GET');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, (int)($t['timeout'] ?? 8));
// SSL-Verify wie bei curl -k aus/an
$insecure = !empty($t['insecure']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$insecure);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $insecure ? 0 : 2);

// Auth
$headers = $t['headers'] ?? [];
if (!empty($t['auth']['type'])) {
  switch ($t['auth']['type']) {
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
