<?php

final class myCurl
{
    /**
     * Führt eine HTTP-Anfrage aus und liefert [status, contentType, body].
     * - wendet SSL-Optionen, Header und Auth an
     */
     
    private static $DEBUG = false; 

    public const DEFAULT_TIMEOUT = 10; // Sekunden

    public static function request(string $finalUrl, string $method, array $t, array $secrets): array
    {
        $ch = curl_init();

        // URL & Methode
        curl_setopt($ch, CURLOPT_URL, $finalUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Follow redirects & Response inkl. Header holen
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::DEFAULT_TIMEOUT);

        // SSL
        $verifySSL = myHelpers::verifySSL($t);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);

        // Header
        $headers = myHelpers::normalizeHeaders($t['headers'] ?? []);

        // Auth
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

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // HEAD-Requests ohne Body
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        // ---------- Request-Body (für POST/PUT/PATCH/DELETE) ----------
        $payload = null;
        $hasBodyMethod = in_array($method, ['POST','PUT','PATCH','DELETE'], true);

        // Body aus Targets oder optional Passthrough vom Client übernehmen
        if ($hasBodyMethod) {
            // 1) Explizit aus targets.php
            if (isset($t['body']) && is_array($t['body'])) {
                if (array_key_exists('json', $t['body'])) {
                    // JSON-Body
                    $payload = json_encode($t['body']['json'], JSON_UNESCAPED_SLASHES);
                    // Content-Type setzen, falls nicht bereits gesetzt
                    $hasCT = false;
                    foreach ($headers as $h) {
                        if (stripos($h, 'content-type:') === 0) { $hasCT = true; break; }
                    }
                    if (!$hasCT) $headers[] = 'Content-Type: application/json; charset=utf-8';
                } elseif (array_key_exists('form', $t['body'])) {
                    // application/x-www-form-urlencoded
                    $payload = http_build_query($t['body']['form'], '', '&', PHP_QUERY_RFC3986);
                    $hasCT = false;
                    foreach ($headers as $h) {
                        if (stripos($h, 'content-type:') === 0) { $hasCT = true; break; }
                    }
                    if (!$hasCT) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                } elseif (array_key_exists('raw', $t['body'])) {
                    // Raw-String (du kannst Content-Type über headers im Target setzen)
                    $payload = (string)$t['body']['raw'];
                } elseif (array_key_exists('multipart', $t['body'])) {
                    // multipart/form-data (Array-Struktur; ggf. mit CURLFile-Objekten)
                    // Kein Content-Type manuell setzen, cURL generiert Boundary automatisch.
                    $payload = $t['body']['multipart'];
                }
            }
            // 2) Oder Body-Passthrough vom Client (wenn im Target erlaubt)
            elseif (!empty($t['passthroughBody'])) {
                $payload = file_get_contents('php://input');
                // Content-Type vom Client wird automatisch weitergereicht, falls du ihn
                // als Header in $headers schon übernommen hast – sonst hier optional parsen.
            }

            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        }
        
        // --- DEBUG: cURL verbose in Logdatei ---
        if (self::$DEBUG) {
            $fp = fopen(__DIR__ . '/../log/curl-debug.log', 'ab');
            if ($fp) {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                curl_setopt($ch, CURLOPT_STDERR, $fp);
                // optional: Anfrage-ID ausgeben
                fwrite($fp, "\\n==== ".date('c')." ".($method)." ".$finalUrl." ===="."\\n");
            }
        }

        // Execute
        $resp = curl_exec($ch);
        if ($resp === false) {
            $errNo = curl_errno($ch);
            $err   = curl_error($ch);
            $info  = curl_getinfo($ch); // enthält URL, IP, SSL-Protokoll, Redirects etc.

            curl_close($ch);

            if (self::$DEBUG) {
                header('X-Debug-curl-errno: ' . $errNo);
                header('X-Debug-curl-error: ' . $err);
                header('X-Debug-url: ' . ($info['url'] ?? $finalUrl));
                header('X-Debug-primary-ip: ' . ($info['primary_ip'] ?? ''));
                header('X-Debug-ssl-proto: ' . ($info['ssl_verify_result'] ?? ''));
            }
            
            http_response_code(502);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Upstream error (cURL): ['.$errNo.'] '.$err);
        }

        // Split
        $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $body        = substr($resp, $headerSize);
        $respHeaders = substr($resp, 0, $headerSize);

        curl_close($ch);
        return [$httpCode, $contentType, $body];
    }
}