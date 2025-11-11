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
$projectId  = $_GET['projectId'] ?? null;
$filterTag   = $_GET['filterTag'] ?? null;
$token      = $secrets['MANTIS_API_TOKEN']; 
$target     = [
        'method' => 'GET',
        'auth' => ['type' => 'basic', 'authorization' => $token],
        'headers' => [
            'Accept' => 'application/json'
        ]
    ];


if(!$projectId)
    die('set projectId');

$url = rtrim($base, '/') . '/api/rest/filters';

// GET-Request absetzen
list($status, $contentType, $body) = 
    myCurl::request(
        rtrim($base, '/') . '/api/rest/filters', 
        'GET', 
        $target, 
        []
    );
    
    
$filterId = myHelpers::findFilterIdByTagNameFromMantis($body, $filterTag);


// GET-Request absetzen
list($status, $contentType, $body) = 
    myCurl::request(
        rtrim($base, '/') . '/api/rest/issues?project_id=' . $projectId.'&filter_id='. $filterId, 
        'GET', 
        $target, 
        []
    );

// Falls über Web aufgerufen, als JSON ausgeben
http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
echo $body;
