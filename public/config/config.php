<?php
// config.php - generische Verwaltung verschiedener JSON-Config-Dateien

// Debugging bei Bedarf aktivieren
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require __DIR__ . '/../../classes/myApiAuth.php';

$backupDir = __DIR__ . '/backup';

/**
 * Optional: Whitelist von erlaubten Config-Dateien.
 *
 * Wenn du später neue Configs hinzufügst, einfach hier ergänzen:
 *  - public.config.json
 *  - it.config.json
 *  - ...
 */
$allowedFiles = [
    'public.config.json' => [
        'roles' => ['admin', 'it', 'guest']
    ],
    'it.config.json' => [
        'roles' => ['admin', 'it']
    ],
    'admin.config.json' => [
        'roles' => ['admin']
    ]
];

$auth = new myApiAuth(
    __DIR__ . '/../../tokens.php',
    __DIR__ . '/../../log/UserTokenAccess.log'
);

// Alle Antworten sind JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * Holt den Dateinamen aus ?file=..., prüft ihn und gibt Pfad + Basisname zurück.
 *
 * @return array [string $fullPath, string $fileName]
 */
function resolveConfigPath(array $allowedFiles): array
{
    if (!isset($_GET['file']) || $_GET['file'] === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Parameter "file" fehlt']);
        exit;
    }

    // Nur Basename erlauben, damit keine ../-Spielereien möglich sind
    $file = basename((string)$_GET['file']);

    // Statt in_array() jetzt Key im assoziativen Array prüfen
    if (!isset($allowedFiles[$file])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Config-Datei']);
        exit;
    }

    $fullPath = __DIR__ . '/' . $file;

    // Hier kommst du an die Rollen ran
    $roles = $allowedFiles[$file]['roles'] ?? [];

    // z.B. Path, Dateiname und Rollen zurückgeben
    return [$fullPath, $file, $roles];
}


$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Anonymous oder echter Client – ungültige Tokens werden geblockt
    //$client = $auth->requireClient();

    [$fullPath, $file, $roles]  = resolveConfigPath($allowedFiles);

    $client = $auth->requireAnyRole($roles);
    $auth->logAction($client, 'read config ' . $file);

    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Config-Datei nicht gefunden']);
        exit;
    }

    $content = file_get_contents($fullPath);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Lesen der Datei']);
        exit;
    }

    // Inhalt direkt zurückgeben (bereits JSON)
    echo $content;
    exit;
}

if ($method === 'POST') {
    // Schreiben nur für Admins (ggf. Rollen anpassen)

    [$fullPath, $file, $roles] = resolveConfigPath($allowedFiles);
    
    $client = $auth->requireAnyRole($roles);

    // Rohes Request-Body lesen
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || $rawBody === '') {
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

    // === Backup der alten Version anlegen (falls Datei existiert) ===
    if (file_exists($fullPath)) {
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
                http_response_code(500);
                echo json_encode(['error' => 'Backup-Verzeichnis konnte nicht erstellt werden']);
                exit;
            }
        }

        // Dateinamen mit Timestamp: z.B. public.config.json.20250101_120000.json
        $timestamp  = date('Ymd_His');
        $backupFile = $backupDir . '/' . $file . '.' . $timestamp . '.json';

        if (!copy($fullPath, $backupFile)) {
            http_response_code(500);
            echo json_encode(['error' => 'Backup der bestehenden Config ist fehlgeschlagen']);
            exit;
        }

        // Nur die letzten 7 Backups dieser Datei behalten
        $pattern = $backupDir . '/' . $file . '.*.json';
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
    $result = file_put_contents($fullPath, $rawBody, LOCK_EX);
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Fehler beim Schreiben der Datei']);
        exit;
    }

    $auth->logAction($client, 'write config ' . $fullPath);

    echo json_encode(['ok' => true]);
    exit;
}

// Andere Methoden nicht erlaubt
http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
exit;
