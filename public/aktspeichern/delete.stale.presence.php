<?php
// delete.stale.presence.php
// Löscht stale/alte Einträge.
// Default: alles was NICHT online ist und älter als 3600 Sekunden (1h).
// Optional: ?ageSeconds=NNN

$ageSeconds = isset($_GET['ageSeconds']) ? (int)$_GET['ageSeconds'] : 3600;
if ($ageSeconds < 0) $ageSeconds = 0;

$nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$cutoffUtc = $nowUtc->sub(new DateInterval('PT' . $ageSeconds . 'S'))
                    ->format(DateTimeInterface::ATOM);

try {
    // "nicht online" heißt:
    //  - state != running
    //  - ODER state=running aber lastSeenUtc älter als Stale-Grenze (90s)
    // hier vereinfachen wir: alles mit lastSeenUtc < cutoff löschen,
    // sofern es nicht gerade online ist (online = running und lastSeen <= 90s).
    //
    // Da SQLite keine NOW() hat wie andere DBs, rechnen wir cutoff in PHP.
    //
    // Bedingung:
    //  - lastSeenUtc < cutoffUtc
    //  - und NICHT (state='running' AND lastSeenUtc >= nowUtc-90s)
    $onlineCutoffUtc = $nowUtc->sub(new DateInterval('PT90S'))
                              ->format(DateTimeInterface::ATOM);

    $stmt = $pdo->prepare("
        DELETE FROM presence
        WHERE lastSeenUtc < :cutoff
          AND NOT (state='running' AND lastSeenUtc >= :onlineCutoff)
    ");

    $stmt->execute([
        ":cutoff" => $cutoffUtc,
        ":onlineCutoff" => $onlineCutoffUtc
    ]);

    json_response([
        "ok" => true,
        "deletedRows" => $stmt->rowCount(),
        "cutoffUtc" => $cutoffUtc,
        "ageSeconds" => $ageSeconds
    ], 200);

} catch (Throwable $e) {
    json_response(["error" => "DB stale delete failed", "detail" => $e->getMessage()], 500);
}
