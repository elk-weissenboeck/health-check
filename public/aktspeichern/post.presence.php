<?php
// post.presence.php
// Erwartet, dass $pdo, json_response(), read_json_body() bereits in presence.php existieren.

$body = read_json_body();

$type      = strtolower(trim($body['Type'] ?? $body['type'] ?? ''));
$sessionId = trim($body['SessionId'] ?? $body['sessionId'] ?? '');
$machine   = trim($body['Machine'] ?? $body['machine'] ?? '');
$user      = trim($body['User'] ?? $body['user'] ?? '');
$pid       = isset($body['Pid']) ? (int)$body['Pid'] : (isset($body['pid']) ? (int)$body['pid'] : null);
$version   = trim($body['Version'] ?? $body['version'] ?? '');

$allowedTypes = ['start', 'heartbeat', 'stop'];
if (!in_array($type, $allowedTypes, true)) {
    json_response(['error' => 'Type must be one of start|heartbeat|stop'], 400);
}
if ($sessionId === '' || $machine === '' || $user === '') {
    json_response(['error' => 'SessionId, Machine, User are required'], 400);
}

$nowUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->format(DateTimeInterface::ATOM);

$state = ($type === 'stop') ? 'stopped' : 'running';

try {
    // Upsert by sessionId
    $stmt = $pdo->prepare("
        INSERT INTO presence (machine, user, sessionId, pid, version, state, lastSeenUtc)
        VALUES (:machine, :user, :sessionId, :pid, :version, :state, :lastSeenUtc)
        ON CONFLICT(sessionId) DO UPDATE SET
            machine     = excluded.machine,
            user        = excluded.user,
            pid         = excluded.pid,
            version     = excluded.version,
            state       = excluded.state,
            lastSeenUtc = excluded.lastSeenUtc
    ");

    $stmt->execute([
        ':machine'     => $machine,
        ':user'        => $user,
        ':sessionId'   => $sessionId,
        ':pid'         => $pid,
        ':version'     => $version,
        ':state'       => $state,
        ':lastSeenUtc' => $nowUtc
    ]);

    json_response([
        'ok' => true,
        'stored' => [
            'type' => $type,
            'sessionId' => $sessionId,
            'machine' => $machine,
            'user' => $user,
            'pid' => $pid,
            'version' => $version,
            'state' => $state,
            'lastSeenUtc' => $nowUtc
        ]
    ], 200);

} catch (Throwable $e) {
    json_response(['error' => 'DB write failed', 'detail' => $e->getMessage()], 500);
}
