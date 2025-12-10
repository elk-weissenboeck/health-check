<?php
declare(strict_types=1);

define('PHOTO_CACHE_HOURS', 48);

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require dirname(__DIR__) . '../../../vendor/autoload.php';
require dirname(__DIR__) . '../../../../classes/myEntra.class.php';

$secrets = require dirname(__DIR__) . '/../secrets.php';
$owners  = require dirname(__DIR__) . '/entra/serviceOwners.php';

use App\Entra\MyEntra;

// User-ID (UPN oder Objekt-ID) aus der URL
$userId = $_GET['id'] ?? null;

if (!$userId) {
    http_response_code(400);
    echo 'Missing id parameter';
    exit;
}

try {
    $entra = new myEntra(
        $secrets['ENTRA_TENANT_ID'],
        $secrets['ENTRA_APP_CLIENT'],
        $secrets['ENTRA_APP_SECRET']
    );

    $graphClient = $entra->getGraphClient();

    // Foto von Graph holen
    $photoBinary = $graphClient
        ->users()
        ->byUserId($userId)
        ->photo()
        ->content()
        ->get()
        ->wait(); // Binärdaten (JPEG)

    if (!$photoBinary) {
        throw new \RuntimeException('No photo');
    }

    $cacheSeconds = PHOTO_CACHE_HOURS * 3600;
    header('Cache-Control: private, max-age=' . $cacheSeconds);
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');
    header('Content-Type: image/jpeg');
    
    echo $photoBinary;
    exit;
} catch (\Throwable $e) {
    // Wenn kein Bild vorhanden oder Fehler -> Default-Avatar oder 404
    http_response_code(404);

    // Entweder 404 ohne Inhalt:
    // echo 'No photo';
    // print_r($e->getMessage());
    // oder ein Fallback-Bild streamen:
    // header('Content-Type: image/png');
    // readfile(__DIR__ . '/assets/default_avatar.png');
}