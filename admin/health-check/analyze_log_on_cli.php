<?php
/**
 * Einfaches Auswertungs-Skript für das ApiAuth-Logfile.
 *
 * Erwartetes Format pro Zeile (Tab-getrennt):
 * 0: timestamp (ISO 8601)
 * 1: ip
 * 2: token
 * 3: name
 * 4: roles (comma-separated)
 * 5: action
 * 6: status
 *
 * Aufruf-Beispiele:
 *
 *   php analyze_log.php /pfad/zu/access.log
 *   php analyze_log.php /pfad/zu/access.log --action=edit_article
 *   php analyze_log.php /pfad/zu/access.log --token=abc123
 *   php analyze_log.php /pfad/zu/access.log --status=ok
 *   php analyze_log.php /pfad/zu/access.log --action=edit_article --status=ok
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Dieses Skript ist für die Kommandozeile gedacht.\n");
    exit(1);
}

// --- einfache Argument-Parsing-Logik ---

if ($argc < 2) {
    fwrite(STDERR, "Verwendung: php {$argv[0]} /pfad/zu/access.log [--action=XY] [--token=ABC] [--status=ok]\n");
    exit(1);
}

$logFile = $argv[1];

if (!is_readable($logFile)) {
    fwrite(STDERR, "Logfile nicht lesbar oder nicht vorhanden: {$logFile}\n");
    exit(1);
}

$filters = [
    'action' => null,
    'token'  => null,
    'status' => null,
];

// weitere Argumente (ab argv[2]) durchsuchen
for ($i = 2; $i < $argc; $i++) {
    $arg = $argv[$i];

    if (strpos($arg, '--action=') === 0) {
        $filters['action'] = substr($arg, strlen('--action='));
    } elseif (strpos($arg, '--token=') === 0) {
        $filters['token'] = substr($arg, strlen('--token='));
    } elseif (strpos($arg, '--status=') === 0) {
        $filters['status'] = substr($arg, strlen('--status='));
    }
}

// --- Auswertung ---

$totalLines      = 0;
$matchedLines    = 0;

$byAction        = [];
$byToken         = [];
$byName          = [];
$byStatus        = [];
$byRole          = []; // einzelne Rollen
$firstTimestamp  = null;
$lastTimestamp   = null;

// Logfile zeilenweise lesen
$handle = fopen($logFile, 'r');

if (!$handle) {
    fwrite(STDERR, "Konnte Logfile nicht öffnen: {$logFile}\n");
    exit(1);
}

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $totalLines++;

    $parts = explode("\t", $line);

    if (count($parts) < 7) {
        // Zeile entspricht nicht dem erwarteten Format
        continue;
    }

    list($timestamp, $ip, $token, $name, $rolesStr, $action, $status) = $parts;

    // Filter anwenden (falls gesetzt)
    if ($filters['action'] !== null && $filters['action'] !== $action) {
        continue;
    }

    if ($filters['token'] !== null && $filters['token'] !== $token) {
        continue;
    }

    if ($filters['status'] !== null && $filters['status'] !== $status) {
        continue;
    }

    $matchedLines++;

    // First/Last Timestamp merken (als String)
    if ($firstTimestamp === null || $timestamp < $firstTimestamp) {
        $firstTimestamp = $timestamp;
    }
    if ($lastTimestamp === null || $timestamp > $lastTimestamp) {
        $lastTimestamp = $timestamp;
    }

    // Zähler aktualisieren
    if (!isset($byAction[$action])) {
        $byAction[$action] = 0;
    }
    $byAction[$action]++;

    if (!isset($byToken[$token])) {
        $byToken[$token] = 0;
    }
    $byToken[$token]++;

    if (!isset($byName[$name])) {
        $byName[$name] = 0;
    }
    $byName[$name]++;

    if (!isset($byStatus[$status])) {
        $byStatus[$status] = 0;
    }
    $byStatus[$status]++;

    // Rollen splitten
    $roles = array_filter(array_map('trim', explode(',', $rolesStr)));
    foreach ($roles as $role) {
        if ($role === '') {
            continue;
        }
        if (!isset($byRole[$role])) {
            $byRole[$role] = 0;
        }
        $byRole[$role]++;
    }
}

fclose($handle);

// --- Ausgabe ---

echo "Logfile: {$logFile}\n";
echo "Gesamtzeilen: {$totalLines}\n";
echo "Gefilterte Treffer: {$matchedLines}\n";

if ($filters['action'] !== null) {
    echo "Filter: action = {$filters['action']}\n";
}
if ($filters['token'] !== null) {
    echo "Filter: token  = {$filters['token']}\n";
}
if ($filters['status'] !== null) {
    echo "Filter: status = {$filters['status']}\n";
}
echo "\n";

if ($matchedLines === 0) {
    echo "Keine passenden Einträge gefunden.\n";
    exit(0);
}

echo "Zeitraum:\n";
echo "  Erster Eintrag: {$firstTimestamp}\n";
echo "  Letzter Eintrag: {$lastTimestamp}\n\n";

// Hilfsfunktion zum sortierten Ausgeben
$printTop = function (string $title, array $data, int $limit = 10) {
    echo $title . " (Top {$limit}):\n";

    if (empty($data)) {
        echo "  (keine Daten)\n\n";
        return;
    }

    arsort($data); // absteigend nach Häufigkeit

    $i = 0;
    foreach ($data as $key => $count) {
        $i++;
        echo "  {$key}: {$count}\n";
        if ($i >= $limit) {
            break;
        }
    }

    echo "\n";
};

$printTop("Nach Aktion", $byAction);
$printTop("Nach Status", $byStatus);
$printTop("Nach Token",  $byToken);
$printTop("Nach Name",   $byName);
$printTop("Nach Rolle",  $byRole);
