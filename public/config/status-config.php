<?php
// status-config.php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require __DIR__ . '/../../classes/myApiAuth.php';

$mainConfig  = __DIR__ . '/status.config.json';
$adminConfig = __DIR__ . '/admin.status.config.json';
$backupDir   = __DIR__ . '/backup';

$auth = new myApiAuth(__DIR__ . '/../../tokens.php');


// Ab hier nur noch authentifizierte Requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Anonymous ODER echter Client – ungültige Tokens werden geblockt
    $client = $auth->requireClient();

    // Datei einlesen
    if (!file_exists($mainConfig)) {
        http_response_code(404);
        echo json_encode(['error' => 'status.config.json nicht gefunden']);
        exit;
    }

    $content = file_get_contents($mainConfig);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Lesen der Datei']);
        exit;
    }
    
    header('Content-Type: application/json');

    if($auth->clientHasRole($client, 'admin')){
        $data1 = json_decode($content, true);
        $data2 = json_decode(file_get_contents($adminConfig), true);
        
        // Safety: wenn groups nicht existiert, leeres Array
        $groups1 = $data1['groups'] ?? [];
        $groups2 = $data2['groups'] ?? [];
        
        $data1['groups'] = array_merge($groups1, $groups2);

        echo json_encode($data1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        echo $content;
        exit;
    }
}

if ($method === 'POST') {
    $client = $auth->requireAnyRole('admin','editor');
    
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
    if (file_exists($mainConfig)) {
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
        if (!copy($mainConfig, $backupFile)) {
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
    $result = file_put_contents($mainConfig, $rawBody, LOCK_EX);
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
