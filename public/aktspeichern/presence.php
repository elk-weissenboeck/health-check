<?php
// aktspeichern/presence.php

declare(strict_types=1);

// ---------- Basic response helpers ----------
function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['error' => 'Invalid JSON body'], 400);
    }
    return $data;
}

// ---------- SQLite init ----------
$dbFile = __DIR__ . '/presence.sqlite';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS presence (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            machine TEXT NOT NULL,
            user TEXT NOT NULL,
            sessionId TEXT NOT NULL UNIQUE,
            pid INTEGER,
            version TEXT,
            state TEXT NOT NULL,
            lastSeenUtc TEXT NOT NULL
        );
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_presence_lastSeen
        ON presence(lastSeenUtc);
    ");
} catch (Throwable $e) {
    json_response(['error' => 'DB init failed', 'detail' => $e->getMessage()], 500);
}

// ---------- Simple routing ----------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// WICHTIG: Unterordner-Pfad hier setzen
$basePath = '/aktspeichern';

$path = $basePath !== '' && str_starts_with($uri, $basePath)
    ? substr($uri, strlen($basePath))
    : $uri;

if ($path === '/api/presence' && $method === 'POST') {
    require __DIR__ . '/post.presence.php';
}

if ($path === '/api/presence' && $method === 'GET') {
    require __DIR__ . '/get.presence.php';
} 

if ($path === '/api/presence' && $method === 'DELETE') {
    require __DIR__ . '/delete.all.presence.php';
}

if ($path === '/api/presence/stale' && $method === 'DELETE') {
    require __DIR__ . '/delete.stale.presence.php';
}

json_response(['error' => 'Not found'], 404);
