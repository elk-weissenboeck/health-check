<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('BASE_DIR', __DIR__);  
define('PHOTO_CACHE_HOURS', 48);

// run "composer dump-autoload" to auto-load custom classed defined in composer.json
require_once BASE_DIR . '/public/vendor/autoload.php';