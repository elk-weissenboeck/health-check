<?php
/**
 * proxy.php
 * Sicherer Whitelist-Proxy für Health-Checks mit Auth & optional -k
 * - Wählt Ziel anhand ?key=... (keine freien URLs!)
 * - Unterstützt: Basic (User/Pass ODER Authorization-Header), Bearer, eigene Header
 * - Optional: SSL-Verify abschalten (entspricht curl -k)
 * - Passthrough: gibt Upstream-Status & Body 1:1 zurück
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// 0) SECRETS & TARGETS LADEN
// -----------------------------------------------------------------------------
$secrets = require dirname(__DIR__) . '/config/secrets.php';
$targets = require dirname(__DIR__) . '/config/targets.php';

// Standard-Timeout (Sekunden) – Targets sollen keinen 'timeout' mehr liefern
const DEFAULT_TIMEOUT = 8;

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
// 2) CURL AUFBAUEN
// -----------------------------------------------------------------------------
$ch = curl_init();

// URL & Methode
curl_setopt($ch, CURLOPT_URL, (string)($t['url'] ?? ''));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, (string)($t['method'] ?? 'GET'));

// Follow redirects & Response inkl. Header holen
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

// Timeout festlegen (nicht mehr aus Targets beziehbar)
curl_setopt($ch, CURLOPT_TIMEOUT, (int) DEFAULT_TIMEOUT);

// -----------------------------------------------------------------------------
// 2a) SSL-Verify: bevorzugt 'verifySSL' (true = prüfen), Fallback 'insecure'
// -----------------------------------------------------------------------------
$verifySSL = null;
if (array_key_exists('verifySSL', $t)) {
  $verifySSL = (bool)$t['verifySSL'];          // neue Semantik
} elseif (array_key_exists('insecure', $t)) {
  $verifySSL = !$t['insecure'];                // alt: insecure=true => verify=false
}
if ($verifySSL === null) {
  $verifySSL = true;                           // Default: SSL prüfen
}
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySSL ? 2 : 0);

// -----------------------------------------------------------------------------
// 2b) Header vorbereiten (Custom-Header wie 'NC-Token' erlaubt)
// - akzeptiert sowohl indexierte Arrays ["Header: Wert"] als auch assoziative
//   Arrays ['Header' => 'Wert'] und normalisiert sie zu ["Header: Wert"]
// -----------------------------------------------------------------------------
$headers = $t['headers'] ?? [];
$normalizedHeaders = [];
if (is_array($headers)) {
  foreach ($headers as $k => $v) {
    if (is_int($k)) {
      // Bereits "Header: Wert"
      $normalizedHeaders[] = (string)$v;
    } else {
      $normalizedHeaders[] = $k . ': ' . $v;
    }
  }
}
$headers = $normalizedHeaders;

// -----------------------------------------------------------------------------
// 2c) Auth behandeln
// Unterstützt:
//   - 'basic'   : user/pass ODER 'authorization' => 'Basic base64...'
//   - 'bearer'  : token
//   - 'headers' : zusätzliche Header
//   - 'jenkins' : mappt auf Basic (user/pass) – abhängig von 'jenkins-tng' in URL
// -----------------------------------------------------------------------------
if (!empty($t['auth']) && is_array($t['auth'])) {
  // Normalfall: $t['auth']['type'] enthält den Typ
  $authType = $t['auth']['type'] ?? null;

  if ($authType) {
    switch ($authType) {

      case 'basic':
        // Variante A: fertiger Authorization-Header (z. B. wenn bereits Base64-kodiert)
        if (!empty($t['auth']['authorization'])) {
          $headers[] = 'Authorization: ' . $t['auth']['authorization'];
        } else {
          // Variante B: klassisch mit user/pass
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
        // zusätzliche Header; akzeptiert indexiert oder assoziativ
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
        
    }
  }
}

if ($headers) {
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// Bei HEAD keinen Body übertragen
if (strtoupper((string)($t['method'] ?? '')) === 'HEAD') {
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
// 4) RESPONSE DURCHREICHEN (Passthrough)
// -----------------------------------------------------------------------------
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