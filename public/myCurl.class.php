<?php

final class myCurl
{
    /**
     * Führt eine HTTP-Anfrage aus und liefert [status, contentType, body].
     * - wendet SSL-Optionen, Header und Auth an
     */
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
        curl_setopt($ch, CURLOPT_TIMEOUT, myHelpers::DEFAULT_TIMEOUT);

        // SSL
        $verifySSL = myHelpers::verifySSL($t);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySSL ? 2 : 0);

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

        // Execute
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            http_response_code(502);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Upstream error: ' . $err);
        }

        // Split
        $headerSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $body        = substr($resp, $headerSize);

        curl_close($ch);
        return [$status, $contentType, $body];
    }
}