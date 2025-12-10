<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require BASE_DIR . '/classes/myEntra.class.php';

$secrets = require dirname(__DIR__) . '/../secrets.php';
$owners  = require dirname(__DIR__) . '/entra/serviceOwners.php';

use App\Entra\MyEntra;

$entra = new myEntra(
    $secrets['ENTRA_TENANT_ID'],
    $secrets['ENTRA_APP_CLIENT'],
    $secrets['ENTRA_APP_SECRET'],
    'Europe/Vienna',                // timezone
    43200,                          // cache time in seconds
    __DIR__ . '/cache/'             // cache dir fallback
);
 

$entra->setFallbackDirectory($owners);

header('Content-Type: application/json; charset=utf-8');

echo $entra->getUsersOofJson([$_GET['upn']], true);
