<?php
// status-config.php


// Pfad zu deiner JSON-Datei
$configPath = __DIR__ . '/status.config.json';
// Backup-Verzeichnis
$backupDir  = __DIR__ . '/backup';
// secrets laden
$secrets = require dirname(__DIR__) . '/../secrets.php';

// === Einfacher Auth-Token ===
// Diesen Token musst du auch im Frontend eintragen.
// Am besten einen zufälligen langen String verwenden.
$AUTH_TOKEN = $secrets['GENERAL_AUTH_TOKEN'];


// Standard-Header
header('Content-Type: application/json; charset=utf-8');

// --- Auth-Funktion für POST ---
function requirePostAuth(string $expectedToken): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        // Für GET usw. keine Auth
        return;
    }

    // Token aus Header lesen (X-Auth-Token)
    $headerToken = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;

    // Optional: Fallback über Query-Parameter ?token=...
    if ($headerToken === null && isset($_GET['token'])) {
        $headerToken = $_GET['token'];
    }

    if ($headerToken !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

// Nur für POST prüfen
requirePostAuth($AUTH_TOKEN);

// Ab hier nur noch authentifizierte Requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Datei einlesen
    if (!file_exists($configPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'status.config.json nicht gefunden']);
        exit;
    }

    $content = file_get_contents($configPath);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Lesen der Datei']);
        exit;
    }

    // Rohes JSON zurückgeben
    header('Content-Type: application/json');
    echo $content;
    exit;
}

if ($method === 'POST') {
    // Rohes Request-Body lesen
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Kein Body empfangen']);
        exit;
    }

    // JSON validieren
    json_decode($rawBody);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error'   => 'Ungültiges JSON',
            'message' => json_last_error_msg()
        ]);
        exit;
    }

    // === Backup der ALTEN Version anlegen, falls vorhanden ===
    if (file_exists($configPath)) {
        // Backup-Verzeichnis sicherstellen
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
                http_response_code(500);
                echo json_encode(['error' => 'Backup-Verzeichnis konnte nicht erstellt werden']);
                exit;
            }
        }

        // Dateinamen mit Timestamp
        $timestamp  = date('Ymd_His');
        $backupFile = $backupDir . '/status.config.' . $timestamp . '.json';

        // Alte Datei in Backup kopieren
        if (!copy($configPath, $backupFile)) {
            http_response_code(500);
            echo json_encode(['error' => 'Backup der bestehenden Config ist fehlgeschlagen']);
            exit;
        }

        // Nur die letzten 7 Backups behalten
        $pattern = $backupDir . '/status.config.*.json';
        $files   = glob($pattern);

        if ($files !== false && count($files) > 7) {
            // Nach Änderungsdatum sortieren (älteste zuerst)
            usort($files, static function ($a, $b) {
                return filemtime($a) <=> filemtime($b);
            });

            // Alles außer die 7 neuesten löschen
            $toDelete = array_slice($files, 0, count($files) - 7);
            foreach ($toDelete as $file) {
                @unlink($file); // Fehler hier sind nicht kritisch
            }
        }
    }

    // === Neue Version schreiben ===
    $result = file_put_contents($configPath, $rawBody, LOCK_EX);
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Schreiben der Datei']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

// Andere Methoden nicht erlaubt
http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
exit;
