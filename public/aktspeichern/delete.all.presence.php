<?php
// delete.all.presence.php
// Löscht ALLE Presence-Einträge.

try {
    $deleted = $pdo->exec("DELETE FROM presence");

    json_response([
        "ok" => true,
        "deletedRows" => $deleted
    ], 200);

} catch (Throwable $e) {
    json_response(["error" => "DB delete failed", "detail" => $e->getMessage()], 500);
}
