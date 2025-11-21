<?php
// get.presence.php
// Erwartet, dass $pdo und json_response() bereits in presence.php existieren.

$stateFilter = isset($_GET['state'])
    ? strtolower(trim((string)$_GET['state']))
    : null;

try {
    if ($stateFilter) {
        $stmt = $pdo->prepare("
            SELECT machine, user, sessionId, pid, version, state, lastSeenUtc
            FROM presence
            WHERE state = :state
            ORDER BY lastSeenUtc DESC
        ");
        $stmt->execute([':state' => $stateFilter]);
    } else {
        $stmt = $pdo->query("
            SELECT machine, user, sessionId, pid, version, state, lastSeenUtc
            FROM presence
            ORDER BY lastSeenUtc DESC
        ");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // isOnline: running + lastSeen <= 90s
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    foreach ($rows as &$r) {
        $last = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $r['lastSeenUtc'])
             ?: new DateTimeImmutable($r['lastSeenUtc'], new DateTimeZone('UTC'));

        $ageSeconds = $now->getTimestamp() - $last->getTimestamp();
        $r['ageSeconds'] = $ageSeconds;
        $r['isOnline'] = ($r['state'] === 'running' && $ageSeconds <= 90);
    }
    unset($r);

    json_response([
        'ok' => true,
        'count' => count($rows),
        'presence' => $rows
    ], 200);

} catch (Throwable $e) {
    json_response(['error' => 'DB read failed', 'detail' => $e->getMessage()], 500);
}
