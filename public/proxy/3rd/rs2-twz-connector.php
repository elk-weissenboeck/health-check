<?php
// ============================================
// Konfiguration
// ============================================
  
// Pfad zum Log-Verzeichnis anpassen:
$logDir = '/mnt/twzlogs';

// optional: nur bestimmte Dateien berücksichtigen
$pattern = $logDir . DIRECTORY_SEPARATOR . 'TWZConnector_*.log';

// ============================================
// Log-Dateien suchen
// ============================================
$files = glob($pattern);

header('Content-Type: application/json; charset=utf-8');

if ($files === false || count($files) === 0) {
    echo json_encode([
        'status'  => 'PROBLEM',
        'message' => 'Keine Log-Dateien gefunden.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// Neueste Datei ermitteln
// ============================================
$latestFile   = null;
$latestMTime  = 0;

foreach ($files as $file) {
    $mtime = @filemtime($file);
    if ($mtime !== false && $mtime > $latestMTime) {
        $latestMTime = $mtime;
        $latestFile  = $file;
    }
}

if ($latestFile === null) {
    echo json_encode([
        'status'  => 'PROBLEM',
        'message' => 'Konnte das Änderungsdatum der Log-Dateien nicht ermitteln.',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// Alter der letzten Datei prüfen (15 Minuten)
// ============================================
$now          = time();
$ageSeconds   = $now - $latestMTime;
$maxAge       = 15 * 60; // 15 Minuten in Sekunden
$isTooOld     = $ageSeconds > $maxAge;

$response = [
    'status'        => $isTooOld ? 'PROBLEM' : 'OK',
    'latest_file'   => $latestFile,
    'last_modified' => date('Y-m-d H:i:s', $latestMTime),
    'age_seconds'   => $ageSeconds,
    'age_minutes'   => round($ageSeconds / 60, 1),
    'age_milliseconds'  => $ageSeconds * 1000
];

if ($isTooOld) {
    $response['message'] = 'Die aktuellste Log-Datei ist älter als 15 Minuten. Es liegt vermutlich ein Problem vor.';
} else {
    $response['message'] = 'Die aktuellste Log-Datei ist nicht älter als 15 Minuten.';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
