<?php
// get.logs.php
// Query: ?machine=NB334&date=2025-11-21&logType=auth

$machine = trim($_GET['machine'] ?? '');
$date    = trim($_GET['date'] ?? '');
$logType = trim($_GET['logType'] ?? '');

$where = [];
$params = [];

if ($machine !== '') { $where[] = "machine = :machine"; $params[":machine"] = $machine; }
if ($date !== '')    { $where[] = "logDate = :date";    $params[":date"] = $date; }
if ($logType !== '') { $where[] = "logType = :logType";$params[":logType"] = strtolower($logType); }

$sql = "
  SELECT id, machine, user, sessionId, logType, logDate, fileName, filePath, sizeBytes, checksum, uploadedUtc
  FROM logs
";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY logDate DESC, machine ASC, logType ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_response(["ok" => true, "count" => count($rows), "logs" => $rows]);
} catch (Throwable $e) {
    json_response(["error" => "DB read failed", "detail" => $e->getMessage()], 500);
}
