<?php

final class myHelpers
{

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
    
    /**
     * Extrahiert alle Tag-Namen aus filters[*].criteria.tags[*].name.
     *
     * @param string|array|object $input JSON-String oder bereits dekodierte Struktur (assoc array oder stdClass)
     * @return array Liste der Tag-Namen (ohne Duplikate), z.B. ["pinnedToHC", "hybridforms"]
     */
    public static function extractTagNamesFromMantis($input): array {
        // Falls ein JSON-String übergeben wurde: dekodieren
        if (is_string($input)) { 
            $decoded = json_decode($input, false); // als Objekt
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }
        } else {
            // beliebige Struktur in Objektform bringen, um einheitlich zu iterieren
            $decoded = json_decode(json_encode($input), false);
        }

        $names = [];

        if (!isset($decoded->filters) || !is_array($decoded->filters)) {
            return [];
        }

        foreach ($decoded->filters as $filter) {
            if (!isset($filter->criteria->tags) || !is_array($filter->criteria->tags)) {
                continue;
            }
            foreach ($filter->criteria->tags as $tag) {
                if (isset($tag->name) && $tag->name !== '') {
                    $names[] = (string)$tag->name;
                }
            }
        }

        // Duplikate entfernen und Indizes neu setzen
        return array_values(array_unique($names));
    }
    
    /**
     * Gibt ein Mapping Filter-ID => Liste von Tag-Namen zurück.
     * @param string|array|object $input
     * @return array z.B. [537 => ["pinnedToHC"], 535 => ["hybridforms"]]
     */
    public static function extractTagNamesByFilterFromMantis($input): array {
        if (is_string($input)) {
            $decoded = json_decode($input, false);
            if (json_last_error() !== JSON_ERROR_NONE) return [];
        } else {
            $decoded = json_decode(json_encode($input), false);
        }

        $out = [];
        if (!isset($decoded->filters) || !is_array($decoded->filters)) return [];

        foreach ($decoded->filters as $filter) {
            $fid = isset($filter->id) ? $filter->id : null;
            if ($fid === null) continue;

            $out[$fid] = [];
            if (isset($filter->criteria->tags) && is_array($filter->criteria->tags)) {
                foreach ($filter->criteria->tags as $tag) {
                    if (isset($tag->name) && $tag->name !== '') {
                        $out[$fid][] = (string)$tag->name;
                    }
                }
            }
        }
        return $out;
    }
    
    /**
     * Sucht in filters[*].criteria.tags[*].name nach $tagName
     * und gibt die ID des ersten passenden Filters zurück.
     *
     * @param string|array|object $input  JSON-String oder bereits dekodierte Daten
     * @param string              $tagName Gesuchter Tag-Name (z.B. "hybridforms")
     * @return int|null
     */
    public static function findFilterIdByTagNameFromMantis($input, string $tagName): ?int {
        // In einheitliche Objektstruktur bringen
        if (is_string($input)) {
            $data = json_decode($input, false);
            if (json_last_error() !== JSON_ERROR_NONE) return null;
        } else {
            $data = json_decode(json_encode($input), false);
        }

        if (!isset($data->filters) || !is_array($data->filters)) return null;

        $needle = mb_strtolower($tagName);

        foreach ($data->filters as $filter) {
            if (!isset($filter->criteria->tags) || !is_array($filter->criteria->tags)) {
                continue;
            }
            foreach ($filter->criteria->tags as $tag) {
                if (isset($tag->name) && mb_strtolower((string)$tag->name) === $needle) {
                    return isset($filter->id) ? (int)$filter->id : null;
                }
            }
        }
        return null;
    }
}