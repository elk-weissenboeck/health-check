<?php
declare(strict_types=1);

namespace App\Entra;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

// Request-Config-Klassen (Generated Namespace)
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetQueryParameters;

class myEntra {
    private GraphServiceClient $graph;
    private DateTimeZone $targetTz;
    /** @var array<string,array{name?:string,email?:string,durchwahl?:string}> */
    private array $fallbackDirectory = [];

    private int $cacheTtlSeconds = 100;            // 0 = disabled
    private string $cacheDir;                    // FS-Fallback dir
    
    /**
     * @param string $tenantId
     * @param string $clientId
     * @param string $clientSecret
     * @param string $timeZone        IANA TZ, z.B. 'Europe/Vienna'
     */
    public function __construct(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        string $timeZone = 'Europe/Vienna',
        int $cacheTtlSeconds = 0,
        ?string $cacheDir = null
    ) {
        $tokenContext = new ClientCredentialContext($tenantId, $clientId, $clientSecret);
        $this->graph = new GraphServiceClient($tokenContext, ['https://graph.microsoft.com/.default']);
        $this->targetTz = new DateTimeZone($timeZone);
        
        $this->cacheTtlSeconds = max(0, $cacheTtlSeconds);
        $this->cacheDir = $cacheDir ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'myentra_cache');
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0770, true);
        }
    }

    /**
     * Fallback-Adressbuch setzen (Keys bitte lowercase E-Mail).
     * Beispiel-Format:
     * [
     *   'alice@contoso.com' => ['name'=>'Alice Beispiel','email'=>'alice@contoso.com','durchwahl'=>'1234'],
     * ]
     * @param array<string,array{name?:string,email?:string,durchwahl?:string}> $directory
     */
    public function setFallbackDirectory(array $directory): void
    {
        $this->fallbackDirectory = $directory;
    }

    /**
     * Liefert JSON für die übergebenen Benutzer-IDs (UPN/E-Mail/GUID).
     * @param string[] $users
     */
    public function getUsersOofJson(array $users): string
    {
        $out = [
            'generatedAt' => (new DateTimeImmutable('now', $this->targetTz))->format('c'),
            'timeZone'    => $this->targetTz->getName(),
            'users'       => [],
        ];

        foreach ($users as $userId) {
            $out['users'][] = $this->getUserEntryCached($userId);
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Einzelnen Benutzer inklusive OOF ermitteln.
     * @return array<string,mixed>
     */
    private function buildUserEntry(string $userId): array
    {
        $entry = [
            'user' => [
                'name'         => null,   // Entra oder Fallback (nur wenn Name+Email leer)
                'email'        => null,   // Entra oder Fallback (nur wenn Name+Email leer)
                'durchwahl'    => null,   // IMMER aus Fallback
                'beschreibung' => null,   // aus jobTitle/department
            ],
            'oof' => [
                'status'   => null,
                'period'   => ['start' => null, 'end' => null],
                'messages' => ['internal' => null, 'external' => null],
            ],
            'error'  => null,
            'source' => [
                'user'            => 'entra',   // entra|fallback|none (für Name/Email)
                'durchwahlSource' => 'none',    // fallback|none
            ],
        ];

        try {
            // ----- (A) User aus Entra
            $userSelect = new UserItemRequestBuilderGetQueryParameters(
                select: ['displayName','mail','userPrincipalName','jobTitle','department']
            );
            $userConfig = new UserItemRequestBuilderGetRequestConfiguration();
            $userConfig->queryParameters = $userSelect;

            $user = null;
            try {
                $user = $this->graph->users()->byUserId($userId)->get($userConfig)->wait();
            } catch (\Throwable $e) {
                // Netzwerk/403/401/etc. -> notieren, aber nicht abbrechen
                $entry['error'] = trim(($entry['error'] ?? '').' | users.get: '.$e->getMessage(), ' |');
            }
            
            $displayName = $user?->getDisplayName() ?: null;
            $mail        = $user?->getMail();
            $upn         = $user?->getUserPrincipalName();
            $email       = (is_string($mail) && $mail !== '') ? $mail : ((is_string($upn) && $upn !== '') ? $upn : null);


            // Zuweisen (erstmal Entra)
            $entry['user']['name']    = $displayName;
            $entry['user']['email']   = $email;

            // ----- (B) Fallback NUR wenn DisplayName UND Email leer sind
            $needsFallback = ($displayName === null || $displayName === '');
            
            if ($needsFallback) {
                $matched = $this->applyIdentityFallback($entry, $userId, $upn ?? null);
                if (!$matched) {
                    $entry['source']['user'] = 'none';
                }
            }

            // ----- (C) Durchwahl IMMER aus Fallback (per Email-Schlüssel)
            $this->applyDurchwahlFallback($entry, $userId, $entry['user']['email'] ?? null, $upn ?? null);

            // ----- (D) OOF / mailboxSettings
            $mb   = $this->graph->users()->byUserId($userId)->mailboxSettings()->get()->wait();
            $auto = $mb->getAutomaticRepliesSetting();

            $status = $this->enumToString($auto?->getStatus()) ?? 'unknown';
            $entry['oof']['status'] = $status;

            if ($status === 'scheduled') {
                $entry['oof']['period']['start'] = $this->toLocalIso($auto->getScheduledStartDateTime());
                $entry['oof']['period']['end']   = $this->toLocalIso($auto->getScheduledEndDateTime());
            }
            
            /*
            $internal = $auto?->getInternalReplyMessage();
            $external = $auto?->getExternalReplyMessage();
            if (is_string($internal) && trim($internal) !== '') $entry['oof']['messages']['internal'] = $internal;
            if (is_string($external) && trim($external) !== '') $entry['oof']['messages']['external'] = $external;
            */
        } catch (Throwable $e) {
            $entry['error'] = $e->getMessage();
            // Selbst bei Fehler: Durchwahl aus Fallback versuchen
            $this->applyDurchwahlFallback($entry, $userId, $entry['user']['email'] ?? null, null);
        }

        return $entry;
    }

    /**
     * Fallback für Name/Email anwenden, wenn beide leer sind.
     * @return bool true, wenn ein Fallback-Treffer genutzt wurde
     */
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

    /**
     * Durchwahl immer aus Fallback ziehen.
     */
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

    /** Enum sicher in String wandeln (versch. SDK-Builds) */
    private function enumToString($enumObj): ?string
    {
        if ($enumObj === null) return null;
        if (method_exists($enumObj, 'value')) { try { return strtolower($enumObj->value()); } catch (Throwable) {} }
        if (property_exists($enumObj, 'value') && is_string($enumObj->value)) return strtolower($enumObj->value);
        if (property_exists($enumObj, 'name')  && is_string($enumObj->name))  return strtolower($enumObj->name);
        if (method_exists($enumObj, '__toString')) return strtolower((string)$enumObj);
        return null;
    }

    /** DateTimeTimeZone -> ISO8601 in Ziel-TZ */
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
    
    
    private function getUserEntryCached(string $userId): array
    {
        if ($this->cacheTtlSeconds > 0) {
            $key = $this->cacheKey($userId);
            $cached = $this->cacheGet($key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $entry = $this->buildUserEntry($userId);

        if ($this->cacheTtlSeconds > 0) {
            $this->cacheSet($this->cacheKey($userId), $entry, $this->cacheTtlSeconds);
        }
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
}
 