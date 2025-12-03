<?php
// status-config.php

require __DIR__ . '/../../classes/myApiAuth.php';

$configPath = __DIR__ . '/status.config.json';
$backupDir  = __DIR__ . '/backup';
$auth = new myApiAuth(__DIR__ . '/../../tokens.php');

// Anonymous ODER echter Client – ungültige Tokens werden geblockt
$client = $auth->requireClient();

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
