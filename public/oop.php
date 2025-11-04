<?php
declare(strict_types=1);

/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/

require 'vendor/autoload.php';

$secrets = require dirname(__DIR__) . '/config/secrets.php';

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetQueryParameters;

header('Content-Type: application/json; charset=utf-8');

/**
 * Konfiguration
 */
$scopes = ['https://graph.microsoft.com/.default'];



if (!isset($_GET['upn'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Unknown upn');
}

// Drei Beispiel-Benutzer (UPN oder GUID)
$users = [
    $_GET['upn']
];

$fallbackDirectory = [
    'georg.weissenboeck@elk.at' => ['name' => 'Georg Weissenböck', 'email' => 'georg.weissenboeck@elk.at', 'durchwahl' => '527'],
    'gabriele.schmid@elk.at' => ['name' => 'Gabi Schmid', 'email' => 'gabriele.schmid@elk.at', 'durchwahl' => '528'],
    'lukas.faltin@elk.at' => ['name' => 'Lukas Faltin', 'email' => 'lukas.faltin@elk.at', 'durchwahl' => '529'],
];

// Zielzeitzone für Ausgabe
$targetTz = new DateTimeZone('Europe/Vienna');

// Graph-Client (App-Only)
$graph = new GraphServiceClient(
    new ClientCredentialContext($tenantId, $clientId, $clientSecret),
    $scopes
);

// --- Helper: Enum sicher in String
$enumToString = function ($enumObj): ?string {
    if ($enumObj === null) return null;
    if (method_exists($enumObj, 'value')) { try { return strtolower($enumObj->value()); } catch (Throwable) {} }
    if (property_exists($enumObj, 'value') && is_string($enumObj->value)) return strtolower($enumObj->value);
    if (property_exists($enumObj, 'name')  && is_string($enumObj->name))  return strtolower($enumObj->name);
    if (method_exists($enumObj, '__toString')) return strtolower((string)$enumObj);
    return null;
};

// Helper: DateTimeTimeZone -> ISO8601 in $targetTz
$toLocalIso = function ($dtz) use ($targetTz): ?string {
    if (!$dtz) return null;
    $srcTz = new DateTimeZone($dtz->getTimeZone() ?: 'UTC');
    $dt = new DateTimeImmutable($dtz->getDateTime(), $srcTz);
    return $dt->setTimezone($targetTz)->format('c'); // ISO8601
};

// --- Helper: E-Mail bevorzugt 'mail', sonst 'userPrincipalName'
$chooseEmail = function ($userObj): ?string {
    $mail = $userObj->getMail();
    if (is_string($mail) && $mail !== '') return $mail;
    $upn = $userObj->getUserPrincipalName();
    return is_string($upn) ? $upn : null;
};


$out = [
    'generatedAt' => (new DateTimeImmutable('now', $targetTz))->format('c'),
    'timeZone'    => $targetTz->getName(),
    'users'       => [],
];

foreach ($users as $userId) {
    $entry = [
        'user' => [
            'name'         => null,   // Entra oder Fallback (nur wenn Name+Email leer)
            'email'        => null,   // Entra oder Fallback (nur wenn Name+Email leer)
            'durchwahl'    => null    // IMMER aus Fallback
        ],
        'oof' => [
            'status'   => null,
            'period'   => ['start' => null, 'end' => null],
            'messages' => ['internal' => null, 'external' => null],
        ],
        'error'  => null,
        'source' => [
            'user'           => 'entra',   // entra|fallback|none (für Name/Email)
            'durchwahlSource'=> 'none',    // fallback|none
        ],
    ];

    try {
        // (A) User aus Entra
        $userSelect = new UserItemRequestBuilderGetQueryParameters(
            select: ['displayName','mail','userPrincipalName']
        );
        $userConfig = new UserItemRequestBuilderGetRequestConfiguration();
        $userConfig->queryParameters = $userSelect;

        $user = $graph->users()->byUserId($userId)->get($userConfig)->wait();

        $displayName = $user->getDisplayName() ?: null;
        $mail        = $user->getMail();
        $upn         = $user->getUserPrincipalName();
        $email       = (is_string($mail) && $mail !== '') ? $mail : ((is_string($upn) && $upn !== '') ? $upn : null);

        // Beschreibung aus Job/Dept
        $job  = $user->getJobTitle();
        $dept = $user->getDepartment();
        $parts = array_filter([$job ?: null, $dept ?: null], fn($x)=>is_string($x) && trim($x) !== '');

        // Zuweisen (erstmal Entra)
        $entry['user']['name']         = $displayName;
        $entry['user']['email']        = $email;

        // (B) Fallback NUR wenn DisplayName UND Email leer sind
        $needsFallback = ($displayName === null || $displayName === '')
                      && ($email === null || $email === '');

        if ($needsFallback) {
            // Key-Kandidaten für Fallback: userId (falls email), upn
            $candidates = [];
            if (filter_var($userId, FILTER_VALIDATE_EMAIL)) $candidates[] = strtolower($userId);
            if (is_string($upn) && $upn !== '')             $candidates[] = strtolower($upn);

            $matched = false;
            foreach (array_unique($candidates) as $k) {
                if (isset($fallbackDirectory[$k])) {
                    $fb = $fallbackDirectory[$k];
                    $entry['user']['name']  = $fb['name']  ?? $entry['user']['name'];
                    $entry['user']['email'] = $fb['email'] ?? $entry['user']['email'];
                    $entry['source']['user'] = 'fallback';
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $entry['source']['user'] = 'none';
            }
        }

        // (C) Durchwahl IMMER aus Fallback (per Email-Schlüssel)
        // Schlüsselpräferenz: (1) finale user.email (Entra oder Fallback), (2) userId wenn E-Mail, (3) upn
        $dwKeyCandidates = [];
        if (is_string($entry['user']['email']) && $entry['user']['email'] !== '') $dwKeyCandidates[] = strtolower($entry['user']['email']);
        if (filter_var($userId, FILTER_VALIDATE_EMAIL))                           $dwKeyCandidates[] = strtolower($userId);
        if (is_string($upn) && $upn !== '')                                       $dwKeyCandidates[] = strtolower($upn);

        $dwMatched = false;
        foreach (array_unique($dwKeyCandidates) as $k) {
            if (isset($fallbackDirectory[$k]) && isset($fallbackDirectory[$k]['durchwahl'])) {
                $entry['user']['durchwahl'] = $fallbackDirectory[$k]['durchwahl'];
                $entry['source']['durchwahlSource'] = 'fallback';
                $dwMatched = true;
                break;
            }
        }
        if (!$dwMatched) {
            $entry['user']['durchwahl'] = null;
            $entry['source']['durchwahlSource'] = 'none';
        }

        // (D) OOF / mailboxSettings
        $mb   = $graph->users()->byUserId($userId)->mailboxSettings()->get()->wait();
        $auto = $mb->getAutomaticRepliesSetting();

        $status = $enumToString($auto?->getStatus()) ?? 'unknown';
        $entry['oof']['status'] = $status;

        if ($status === 'scheduled') {
            $entry['oof']['period']['start'] = $toLocalIso($auto->getScheduledStartDateTime());
            $entry['oof']['period']['end']   = $toLocalIso($auto->getScheduledEndDateTime());
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
        $dwKeyCandidates = [];
        if (filter_var($userId, FILTER_VALIDATE_EMAIL)) $dwKeyCandidates[] = strtolower($userId);
        // kein upn verfügbar bei Fehler

        foreach (array_unique($dwKeyCandidates) as $k) {
            if (isset($fallbackDirectory[$k]) && isset($fallbackDirectory[$k]['durchwahl'])) {
                $entry['user']['durchwahl'] = $fallbackDirectory[$k]['durchwahl'];
                $entry['source']['durchwahlSource'] = 'fallback';
                break;
            }
        }
    }

    $out['users'][] = $entry;
}

