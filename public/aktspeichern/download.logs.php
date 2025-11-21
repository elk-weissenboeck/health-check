<?php
// download.logs.php
// ?id=123

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(["error" => "id required"], 400);
}

try {
    $stmt = $pdo->prepare("SELECT fileName, filePath, machine, logDate, logType FROM logs WHERE id=:id");
    $stmt->execute([":id" => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_response(["error" => "not found"], 404);

    $abs = __DIR__ . '/' . $row['filePath'];
    if (!is_file($abs)) json_response(["error" => "file missing on disk"], 404);

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $row['machine'].'_'.$row['logDate'].'_'.$row['logType'].'.log"');
    readfile($abs);
    exit;

} catch (Throwable $e) {
    json_response(["error" => "download failed", "detail" => $e->getMessage()], 500);
}
