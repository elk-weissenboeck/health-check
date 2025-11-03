<?php

final class myHelpers
{
    public const DEFAULT_TIMEOUT = 8; // Sekunden

    /**
     * Liefert Target anhand key oder beendet mit 404.
     */
    public static function requireTarget(array $targets, string $key): array
    {
        if (!isset($targets[$key])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Unknown key');
        }
        return $targets[$key];
    }

    /**
     * Extrahiert Methode (standardisiert auf Uppercase).
     */
    public static function method(array $t): string
    {
        $m = strtoupper((string)($t['method'] ?? 'GET'));
        return $m !== '' ? $m : 'GET';
    }

    /**
     * Baut finale URL aus Basis-URL (ohne Query) + optionalem 'query' (Array oder String).
     * Query-Anteil in 'url' wird KONSEQUENT verworfen.
     */
    public static function buildFinalUrl(array $t): string
    {
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

        // 'query' (Array oder String) -> Array
        $queryArray = [];
        if (isset($t['query']) && $t['query'] !== null) {
            if (is_array($t['query'])) {
                $queryArray = $t['query'];
            } elseif (is_string($t['query'])) {
                parse_str(ltrim($t['query'], "?& \t\r\n"), $queryArray);
            }
        }
        $queryString = http_build_query($queryArray, '', '&', PHP_QUERY_RFC3986);

        $finalUrl =
            ($scheme ? $scheme . '://' : '') .
            $userInfo . $host . $port . $path .
            ($queryString !== '' ? '?' . $queryString : '') .
            $fragment;

        return $finalUrl;
    }

    /**
     * Normalisiert Header-Definitionen zu ["Header: Wert"]-Strings.
     * $headers kann indexiert (Strings) oder assoziativ (key => value) sein.
     */
    public static function normalizeHeaders($headers): array
    {
        $out = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (is_int($k)) {
                    $out[] = (string)$v;
                } else {
                    $out[] = $k . ': ' . $v;
                }
            }
        }
        return $out;
    }

    /**
     * Liest verifySSL (true=prüfen) mit Fallback auf altes 'insecure'.
     */
    public static function verifySSL(array $t): bool
    {
        if (array_key_exists('verifySSL', $t)) {
            return (bool)$t['verifySSL'];
        }
        if (array_key_exists('insecure', $t)) {
            return !$t['insecure'];
        }
        return false;
    }

    /**
     * Liest "nocache" Query-Parameter (truthy) aus $_GET.
     */
    public static function isNoCache(): bool
    {
        if (!isset($_GET['nocache'])) return false;
        $v = $_GET['nocache'];
        return $v !== '0' && $v !== '' && $v !== 0 && $v !== null;
    }
}