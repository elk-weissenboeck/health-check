<?php
declare(strict_types=1);


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
require '../../classes/myCurl.class.php';
require '../../classes/myHelpers.class.php';

$secrets = require dirname(__DIR__) . '/../secrets.php';
 
$base       = 'https://mantis.elkschrems.co.at';
$projectId  = $_GET['projectId'];
$filterId   = $_GET['filterId'];
$token      = $secrets['MANTIS_API_TOKEN']; 

$url = rtrim($base, '/') . '/api/rest/issues?project_id=' . $projectId.'&filter_id='. $filterId;

// Request-Parameter für myCurl::request
$target = [
    'method' => 'GET',
    'auth' => ['type' => 'basic', 'authorization' => $token],
    'headers' => [
        'Accept' => 'application/json'
    ]
];


// GET-Request absetzen
list($status, $contentType, $body) = myCurl::request($url, 'GET', $target, []);


// Falls über Web aufgerufen, als JSON ausgeben
http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
echo $body;
