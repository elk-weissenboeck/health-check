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

    /** @var array<string,array{name?:string,email?:string,mobileExt?:string}> */
    private array $fallbackDirectory = [];

    private int $cacheTtlSeconds = 0;         // 0 = disabled
    private string $cacheDir;                 // FS-Fallback dir
    private ?int $minRemainingTtl = null;     // kleinste verbleibende TTL aller Hits
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

    public function getGraphClient(): GraphServiceClient
    {
        return $this->graph;
    }
    
    public function setFallbackDirectory(array $directory): void
    {
        $this->fallbackDirectory = $directory;
    }

    /** Hauptmethode: liefert JSON für mehrere Benutzer */
// signatur ändern: optional header ausgeben
    public function getUsersOofJson(array $users, bool $emitHeaders = false): string
    {
        // &nocache=1  -> Cache-BY-PASS aktivieren (siehe Punkt 2)
        $bypass = isset($_GET['nocache']) && $_GET['nocache'] === '1';

        $out = [
            'generatedAt' => (new \DateTimeImmutable('now', $this->targetTz))->format('c'),
            'timeZone'    => $this->targetTz->getName(),
            'cache'       => [
                'enabled'       => !$bypass && $this->cacheTtlSeconds > 0,
                'ttlSeconds'    => $this->cacheTtlSeconds,
                'hits'          => 0,
                'misses'        => 0,
                'minRemaining'  => null, // kleinste Rest-TTL unter allen Hits
                'bypass'        => $bypass,
            ],
            'users'       => [],
        ];

        $this->cacheStats = ['hits' => 0, 'misses' => 0];
        $this->minRemainingTtl = null;

        foreach ($users as $userId) {
            $out['users'][] = $this->getUserEntryCached($userId, $bypass);
        }

        $out['cache']['hits']        = $this->cacheStats['hits'];
        $out['cache']['misses']      = $this->cacheStats['misses'];
        $out['cache']['minRemaining']= $this->minRemainingTtl;

        if ($emitHeaders) {
            if ($bypass) {
                header('Cache-Control: no-store');
                header('X-MyEntra-Cache: BYPASS');
            } elseif ($this->cacheTtlSeconds > 0) {
                header('Cache-Control: private, max-age=' . $this->cacheTtlSeconds);
                $state = $this->cacheStats['hits'] > 0 ? 'HIT' : 'MISS';
                header('X-MyEntra-Cache: ' . $state);
                if ($this->minRemainingTtl !== null) {
                    header('X-MyEntra-Cache-Remaining: ' . $this->minRemainingTtl);
                }
            } else {
                header('Cache-Control: no-store');
                header('X-MyEntra-Cache: DISABLED');
            }
            header('X-MyEntra-Cache-Hits: ' . $this->cacheStats['hits']);
            header('X-MyEntra-Cache-Misses: ' . $this->cacheStats['misses']);
            header('X-MyEntra-Cache-TTL: ' . $this->cacheTtlSeconds);
            if ($this->minRemainingTtl !== null) {
                header('Age: ' . max(0, $this->cacheTtlSeconds - $this->minRemainingTtl));
            }
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }


    /* ===================== CACHE-LAYER ===================== */

    private function getUserEntryCached(string $userId, bool $bypassCache = false): array
    {
        if (!$bypassCache && $this->cacheTtlSeconds > 0) {
            $key = $this->cacheKey($userId);
            $cached = $this->cacheGet($key);
            if ($cached !== null) {
                $this->cacheStats['hits']++;
                $this->minRemainingTtl = min($this->minRemainingTtl ?? PHP_INT_MAX, (int)$cached['remaining']);
                $entry = $cached['data'];
                $entry['cache'] = ['status' => 'hit', 'remainingSeconds' => (int)$cached['remaining']];
                return $entry;
            }
        }

        $entry = $this->buildUserEntry($userId);
        if (!$bypassCache && $this->cacheTtlSeconds > 0) {
            $this->cacheSet($this->cacheKey($userId), $entry, $this->cacheTtlSeconds);
        }
        $this->cacheStats['misses']++;
        $entry['cache'] = ['status' => $bypassCache ? 'bypass' : 'miss', 'remainingSeconds' => 0];
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
        if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
            $ok = false;
            $payload = apcu_fetch($key, $ok);
            if (!$ok || !is_array($payload) || !isset($payload['expires'], $payload['data'])) return null;
            $remaining = max(0, (int)$payload['expires'] - time());
            if ($remaining === 0) return null;
            return ['data' => $payload['data'], 'remaining' => $remaining];
        }

        $file = $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        if (!is_file($file)) return null;
        $payload = json_decode((string)@file_get_contents($file), true);
        if (!is_array($payload) || !isset($payload['expires'], $payload['data'])) return null;

        $remaining = max(0, (int)$payload['expires'] - time());
        if ($remaining === 0) { @unlink($file); return null; }

        return ['data' => $payload['data'], 'remaining' => $remaining];
    }

    /** setzt Wert in APCu oder Dateisystem */
    private function cacheSet(string $key, array $value, int $ttl): void
    {
        if ($ttl <= 0) return;
        $payload = [
            'expires' => time() + $ttl,
            'data'    => $value,
        ];

        if (function_exists('apcu_store') && ini_get('apc.enabled')) {
            @apcu_store($key, $payload, $ttl);
            return;
        }
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /* ===================== DOMAIN-LOGIK ===================== */

    private function buildUserEntry(string $userId): array
    {
        $entry = [
            'user' => [
                'name'         => null,
                'email'        => null,
                'mobileExt'    => null,   // immer aus Fallback
                'mobilePhone'  => null,
                'businessPhone'=> null,
                'beschreibung' => null,
            ],
            'oof' => [
                'status'   => null,
                'period'   => ['start' => null, 'end' => null],
                'messages' => ['internal' => null, 'external' => null],
            ],
            'error'  => null,
            'source' => [
                'user' => 'entra'   // entra|fallback|none
            ],
        ];

        try {
            // (A) User (defensiv)
            $userSelect = new UserItemRequestBuilderGetQueryParameters(
                select: ['displayName','mail','userPrincipalName','mobilePhone', 'businessPhones']
            );
            $userConfig = new UserItemRequestBuilderGetRequestConfiguration();
            $userConfig->queryParameters = $userSelect;

            $user = null;
            try {
                $user = $this->graph->users()->byUserId($userId)->get($userConfig)->wait();
                //print_r($user);
            } catch (Throwable $e) {
                $entry['error'] = trim(($entry['error'] ?? '').' | users.get: '.$e->getMessage(), ' |');
            }

            $displayName   = $user?->getDisplayName() ?: null;
            $mail          = $user?->getMail();
            $upn           = $user?->getUserPrincipalName();
            $email         = (is_string($mail) && $mail !== '') ? $mail : ((is_string($upn) && $upn !== '') ? $upn : null);
            $mobilePhone   = $user?->getMobilePhone();
            $businessPhone = $user?->getBusinessPhones() ?: [];
            
            $job  = $user?->getJobTitle();
            $dept = $user?->getDepartment();
            $parts = array_filter([$job ?: null, $dept ?: null], fn($x)=>is_string($x) && trim($x) !== '');
            $beschreibung = $parts ? implode(' · ', $parts) : null;

            $entry['user']['name']         = $displayName;
            $entry['user']['email']        = $email;
            $entry['user']['beschreibung'] = $beschreibung;
            $entry['user']['mobilePhone']  = $mobilePhone;
            $entry['user']['businessPhone']= count($businessPhone) ? $businessPhone[0] : null;
            $entry['user']['photo']= $this->getUserPhotoUrl($userId);

            // (B) Fallback nur wenn Name UND Email leer
            $needsFallback = ($displayName === null || $displayName === '')
                          && ($email === null || $email === '');
            if ($needsFallback) {
                $matched = $this->applyIdentityFallback($entry, $userId, $upn ?? null);
                if (!$matched) $entry['source']['user'] = 'none';
            }

            // (C) mobileExt immer aus Fallback
            $this->applyMobileExtFallback($entry, $userId, $entry['user']['email'] ?? null, $upn ?? null);

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

    /**
     * Liefert eine öffentliche URL, die im <img>-Tag verwendet werden kann,
     * um das Benutzerfoto auszugeben.
     *
     * Intern ruft diese URL ein PHP-Script auf, das das Foto von Microsoft Graph
     * holt und direkt an den Browser streamt (ohne es zu speichern).
     *
     * @param string $userId  userPrincipalName (E-Mail) oder Objekt-ID
     * @return string         Öffentliche URL, z. B. /entra_photo.php?id=...
     */
    public function getUserPhotoPublicUrl(string $userId): string
    {
        // Passe den Pfad zu deinem Foto-Proxy-Script an
        $baseUrl = '/entra_photo.php';

        return $baseUrl . '?id=' . urlencode($userId);
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

    private function applyMobileExtFallback(array &$entry, string $userId, ?string $email, ?string $upn): void
    {
        $candidates = [];
        if (is_string($email) && $email !== '')           $candidates[] = strtolower($email);
        if ($this->looksLikeEmail($userId))               $candidates[] = strtolower($userId);
        if (is_string($upn) && $upn !== '')               $candidates[] = strtolower($upn);

        foreach (array_unique($candidates) as $k) {
            if (isset($this->fallbackDirectory[$k]['mobileExt'])) {
                $entry['user']['mobileExt'] = $this->fallbackDirectory[$k]['mobileExt'];
                return;
            }
        }
        $entry['user']['mobileExt'] = null;
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
