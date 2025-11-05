<?php
declare(strict_types=1);


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
require '../../classes/myEntra.class.php';

$secrets = require dirname(__DIR__) . '/../secrets.php';


use App\Entra\MyEntra;

$entra = new myEntra(
    $secrets['ENTRA_TENANT_ID'],
    $secrets['ENTRA_APP_CLIENT'],
    $secrets['ENTRA_APP_SECRET'],
    'Europe/Vienna',
    300,
    __DIR__ . '/cache/'
);
 

$entra->setFallbackDirectory([
    'georg.weissenboeck@elk.at' => ['name' => 'Georg Weissenböck', 'email' => 'georg.weissenboeck@elk.at', 'durchwahl' => '527'],
    'gabriele.schmid@elk.at' => ['name' => 'Gabi Schmid', 'email' => 'gabriele.schmid@elk.at', 'durchwahl' => '528'],
    'lukas.faltin@elk.at' => ['name' => 'Lukas Faltin', 'email' => 'lukas.faltin@elk.at', 'durchwahl' => '529'],
    'support@levatis.com' => ['name' => 'Hr. Weiss', 'email' => 'support@levatis.com', 'durchwahl' => '+ 43 (0) 5 700 900 700'],
]);

header('Content-Type: application/json; charset=utf-8');

echo $entra->getUsersOofJson([$_GET['upn']], true);
