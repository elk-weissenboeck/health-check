<?php
declare(strict_types=1);

namespace App\Entra;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use RuntimeException;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetQueryParameters;

class myEntra
{
    private GraphServiceClient $graph;
    private DateTimeZone $targetTz;

    /** @var array<string,array{name?:string,email?:string,durchwahl?:string}> */
    private array $fallbackDirectory = [];

    private int $cacheTtlSeconds = 0;            // 0 = disabled
    private string $cacheDir;                    // FS-Fallback dir
    private array $cacheStats = ['hits' => 0, 'misses' => 0];

    public function __construct(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        string $timeZone = 'Europe/Vienna',
        int $cacheTtlSeconds = 0,                // NEU: Cache-TTL in Sekunden
        ?string $cacheDir = null                 // NEU: Filesystem-Fallback-Pfad
    ) {
        $tokenContext   = new ClientCredentialContext($tenantId, $clientId, $clientSecret);
        $this->graph    = new GraphServiceClient($tokenContext, ['https://graph.microsoft.com/.default']);
        $this->targetTz = new DateTimeZone($timeZone);

        $this->cacheTtlSeconds = max(0, $cacheTtlSeconds);
        $this->cacheDir = $cacheDir ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myentra_cache');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0770, true);
        }
    }

    public function setFallbackDirectory(array $directory): void
    {
        $this->fallbackDirectory = $directory;
    }

    /** Hauptmethode: liefert JSON für mehrere Benutzer */
// signatur ändern: optional header ausgeben
    public function getUsersOofJson(array $users, bool $emitHeaders = false): string
    {
        $out = [
            'generatedAt' => (new \DateTimeImmutable('now', $this->targetTz))->format('c'),
            'timeZone'    => $this->targetTz->getName(),
            // NEU: Cache-Summary (wird unten befüllt)
            'cache'       => [
                'enabled'    => $this->cacheTtlSeconds > 0,
                'ttlSeconds' => $this->cacheTtlSeconds,
                'hits'       => 0,
                'misses'     => 0,
            ],
            'users'       => [],
        ];

        // zurücksetzen
        $this->cacheStats = ['hits' => 0, 'misses' => 0];

        foreach ($users as $userId) {
            $out['users'][] = $this->getUserEntryCached($userId);
        }

        // Summary schreiben
        $out['cache']['hits']   = $this->cacheStats['hits'];
        $out['cache']['misses'] = $this->cacheStats['misses'];

        // Optional: HTTP-Header setzen (nur wenn sinnvoll)
        if ($emitHeaders) {
            if ($this->cacheTtlSeconds > 0) {
                header('Cache-Control: private, max-age=' . $this->cacheTtlSeconds);
            } else {
                header('Cache-Control: no-store');
            }
            $hitOrMiss = ($this->cacheStats['hits'] > 0) ? 'HIT' : 'MISS';
            header('X-MyEntra-Cache: ' . $hitOrMiss);
            header('X-MyEntra-Cache-Hits: ' . $this->cacheStats['hits']);
            header('X-MyEntra-Cache-Misses: ' . $this->cacheStats['misses']);
            header('X-MyEntra-Cache-TTL: ' . $this->cacheTtlSeconds);
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }


    /* ===================== CACHE-LAYER ===================== */

    private function getUserEntryCached(string $userId): array
    {
        $fromCache = false;
        $entry = null;

        if ($this->cacheTtlSeconds > 0) {
            $key = $this->cacheKey($userId);
            $cached = $this->cacheGet($key);
            if ($cached !== null) {
                $entry = $cached;
                $fromCache = true;
            }
        }

        if ($entry === null) {
            $entry = $this->buildUserEntry($userId);
            if ($this->cacheTtlSeconds > 0) {
                $this->cacheSet($this->cacheKey($userId), $entry, $this->cacheTtlSeconds);
            }
            $this->cacheStats['misses']++;
            // pro-user flag
            $entry['cache'] = ['status' => 'miss'];
            return $entry;
        }

        $this->cacheStats['hits']++;
        $entry['cache'] = ['status' => 'hit'];
        return $entry;
    }

    private function cacheKey(string $userId): string
    {
        // Zeitzone beeinflusst formatierte Zeiten → Bestandteil des Keys
        $tz = $this->targetTz->getName();
        return 'myentra:v1:'.$tz.':'.strtolower(trim($userId));
    }

    /** holt Wert aus APCu oder Dateisystem */
    private function cacheGet(string $key): ?array
    {
        // APCu?
        if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
            $ok = false;
            $val = apcu_fetch($key, $ok);
            if ($ok && is_array($val)) return $val;
            return null;
        }
        // FS-Fallback
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        if (!is_file($file)) return null;
        $json = @file_get_contents($file);
        if ($json === false) return null;

        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['expires'], $payload['data'])) return null;
        if (time() >= (int)$payload['expires']) {
            @unlink($file);
            return null;
        }
        return is_array($payload['data']) ? $payload['data'] : null;
    }

    /** setzt Wert in APCu oder Dateisystem */
    private function cacheSet(string $key, array $value, int $ttl): void
    {
        if ($ttl <= 0) return;

        if (function_exists('apcu_store') && ini_get('apc.enabled')) {
            @apcu_store($key, $value, $ttl);
            return;
        }
        // FS-Fallback
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        $payload = [
            'expires' => time() + $ttl,
            'data'    => $value,
        ];
        @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /* ===================== DOMAIN-LOGIK ===================== */

    private function buildUserEntry(string $userId): array
    {
        $entry = [
            'user' => [
                'name'         => null,
                'email'        => null,
                'durchwahl'    => null,   // immer aus Fallback
                'beschreibung' => null,
            ],
            'oof' => [
                'status'   => null,
                'period'   => ['start' => null, 'end' => null],
                'messages' => ['internal' => null, 'external' => null],
            ],
            'error'  => null,
            'source' => [
                'user'            => 'entra',   // entra|fallback|none
                'durchwahlSource' => 'none',    // fallback|none
            ],
        ];

        try {
            // (A) User (defensiv)
            $userSelect = new UserItemRequestBuilderGetQueryParameters(
                select: ['displayName','mail','userPrincipalName','jobTitle','department']
            );
            $userConfig = new UserItemRequestBuilderGetRequestConfiguration();
            $userConfig->queryParameters = $userSelect;

            $user = null;
            try {
                $user = $this->graph->users()->byUserId($userId)->get($userConfig)->wait();
            } catch (Throwable $e) {
                $entry['error'] = trim(($entry['error'] ?? '').' | users.get: '.$e->getMessage(), ' |');
            }

            $displayName = $user?->getDisplayName() ?: null;
            $mail        = $user?->getMail();
            $upn         = $user?->getUserPrincipalName();
            $email       = (is_string($mail) && $mail !== '') ? $mail : ((is_string($upn) && $upn !== '') ? $upn : null);

            $job  = $user?->getJobTitle();
            $dept = $user?->getDepartment();
            $parts = array_filter([$job ?: null, $dept ?: null], fn($x)=>is_string($x) && trim($x) !== '');
            $beschreibung = $parts ? implode(' · ', $parts) : null;

            $entry['user']['name']         = $displayName;
            $entry['user']['email']        = $email;
            $entry['user']['beschreibung'] = $beschreibung;

            // (B) Fallback nur wenn Name UND Email leer
            $needsFallback = ($displayName === null || $displayName === '')
                          && ($email === null || $email === '');
            if ($needsFallback) {
                $matched = $this->applyIdentityFallback($entry, $userId, $upn ?? null);
                if (!$matched) $entry['source']['user'] = 'none';
            }

            // (C) Durchwahl immer aus Fallback
            $this->applyDurchwahlFallback($entry, $userId, $entry['user']['email'] ?? null, $upn ?? null);

            // (D) OOF (defensiv)
            try {
                $mb   = $this->graph->users()->byUserId($userId)->mailboxSettings()->get()->wait();
                if ($mb !== null) {
                    $auto   = $mb->getAutomaticRepliesSetting();
                    $status = $this->enumToString($auto?->getStatus()) ?? 'unknown';
                    $entry['oof']['status'] = $status;

                    if ($status === 'scheduled') {
                        $entry['oof']['period']['start'] = $this->toLocalIso($auto->getScheduledStartDateTime());
                        $entry['oof']['period']['end']   = $this->toLocalIso($auto->getScheduledEndDateTime());
                    }

                    //$internal = $auto?->getInternalReplyMessage();
                    //$external = $auto?->getExternalReplyMessage();
                    //if (is_string($internal) && trim($internal) !== '') $entry['oof']['messages']['internal'] = $internal;
                    //if (is_string($external) && trim($external) !== '') $entry['oof']['messages']['external'] = $external;
                } else {
                    $entry['oof']['status'] = 'unknown';
                }
            } catch (Throwable $e) {
                $entry['error'] = trim(($entry['error'] ?? '').' | mailboxSettings.get: '.$e->getMessage(), ' |');
            }

        } catch (Throwable $e) {
            $entry['error'] = trim(($entry['error'] ?? '').' | fatal: '.$e->getMessage(), ' |');
        }

        return $entry;
    }

    private function applyIdentityFallback(array &$entry, string $userId, ?string $upn): bool
    {
        $candidates = [];
        if ($this->looksLikeEmail($userId)) $candidates[] = strtolower($userId);
        if (is_string($upn) && $upn !== '') $candidates[] = strtolower($upn);

        foreach (array_unique($candidates) as $k) {
            if (isset($this->fallbackDirectory[$k])) {
                $fb = $this->fallbackDirectory[$k];
                $entry['user']['name']  = $fb['name']  ?? $entry['user']['name'];
                $entry['user']['email'] = $fb['email'] ?? $entry['user']['email'];
                $entry['source']['user'] = 'fallback';
                return true;
            }
        }
        return false;
    }

    private function applyDurchwahlFallback(array &$entry, string $userId, ?string $email, ?string $upn): void
    {
        $candidates = [];
        if (is_string($email) && $email !== '')           $candidates[] = strtolower($email);
        if ($this->looksLikeEmail($userId))               $candidates[] = strtolower($userId);
        if (is_string($upn) && $upn !== '')               $candidates[] = strtolower($upn);

        foreach (array_unique($candidates) as $k) {
            if (isset($this->fallbackDirectory[$k]['durchwahl'])) {
                $entry['user']['durchwahl'] = $this->fallbackDirectory[$k]['durchwahl'];
                $entry['source']['durchwahlSource'] = 'fallback';
                return;
            }
        }
        $entry['user']['durchwahl'] = null;
        $entry['source']['durchwahlSource'] = 'none';
    }

    private function enumToString($enumObj): ?string
    {
        if ($enumObj === null) return null;
        if (method_exists($enumObj, 'value')) { try { return strtolower($enumObj->value()); } catch (Throwable) {} }
        if (property_exists($enumObj, 'value') && is_string($enumObj->value)) return strtolower($enumObj->value);
        if (property_exists($enumObj, 'name')  && is_string($enumObj->name))  return strtolower($enumObj->name);
        if (method_exists($enumObj, '__toString')) return strtolower((string)$enumObj);
        return null;
    }

    private function toLocalIso($dtz): ?string
    {
        if (!$dtz) return null;
        $srcTz = new DateTimeZone($dtz->getTimeZone() ?: 'UTC');
        $dt = new DateTimeImmutable($dtz->getDateTime(), $srcTz);
        return $dt->setTimezone($this->targetTz)->format('c');
    }

    private function looksLikeEmail(string $s): bool
    {
        return (bool) filter_var($s, FILTER_VALIDATE_EMAIL);
    }
}
