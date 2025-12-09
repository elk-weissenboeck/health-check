<?php

$tokens =  require dirname(__DIR__) . '/../tokens.php';

// Rollen -> erlaubte Configs
$roleConfigMap = [
    'admin'     => ['public.config.json', 'it.config.json', 'admin.config.json'],
    'it'        => ['public.config.json', 'it.config.json'],
    'read_only' => ['public.config.json'],
    'guest'     => []
];

// --- Token aus Cookie ziehen
$userToken = $_COOKIE['UserToken'] ?? null;

$clientRoles = ['guest'];
$clientName  = 'anonymous';

if ($userToken !== null && isset($tokens[$userToken])) {
    $tokenData = $tokens[$userToken];

    if (!empty($tokenData['active'])) {
        $clientName  = $tokenData['name'];
        $clientRoles = $tokenData['roles'] ?: ['guest'];
    }
}

// Erlaubte Configs aus allen Rollen ableiten
$allowedConfigs = [];

foreach ($clientRoles as $role) {
    if (!isset($roleConfigMap[$role])) {
        continue;
    }
    $allowedConfigs = array_merge($allowedConfigs, $roleConfigMap[$role]);
}

$allowedConfigs = array_values(array_unique($allowedConfigs));

$capabilities = [
    'name'    => $clientName,
    'roles'   => $clientRoles,
    'configs' => $allowedConfigs,
];

header('Content-Type: application/json; charset=utf-8');
echo json_encode($capabilities);

