<?php
// post.logs.php
// multipart/form-data Upload: file + meta.
// Erwartet $pdo, json_response() aus presence.php.

try {
    if (!isset($_FILES['file'])) {
        json_response(["error" => "No file uploaded (field name: file)"], 400);
    }

    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        json_response(["error" => "Upload error", "code" => $f['error']], 400);
    }

    $machine   = trim($_POST['machine'] ?? '');
    $user      = trim($_POST['user'] ?? '');
    $sessionId = trim($_POST['sessionId'] ?? '');
    $logType   = trim($_POST['logType'] ?? '');
    $logDate   = trim($_POST['logDate'] ?? ''); // YYYY-MM-DD
    $version   = trim($_POST['version'] ?? '');

    if ($machine === '' || $logType === '' || $logDate === '') {
        json_response(["error" => "machine, logType, logDate are required"], 400);
    }

    // basic logType normalization
    $logType = strtolower($logType);

    // Zielpfad
    $safeMachine = preg_replace('/[^A-Za-z0-9._-]/', '_', $machine);
    $safeType    = preg_replace('/[^A-Za-z0-9._-]/', '_', $logType);
    $safeDate    = preg_replace('/[^0-9-]/', '', $logDate);

    $baseDir = __DIR__ . "/logstore/$safeMachine/$safeDate";
    if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true)) {
        json_response(["error" => "Failed to create logstore dir"], 500);
    }

    $origName = basename($f['name']);
    $ext = pathinfo($origName, PATHINFO_EXTENSION);
    $fileName = "$safeType.log";  // wir vereinheitlichen auf <type>.log
    $destPath = "$baseDir/$fileName";

    // move upload
    if (!move_uploaded_file($f['tmp_name'], $destPath)) {
        json_response(["error" => "Failed to move uploaded file"], 500);
    }

    $sizeBytes = filesize($destPath) ?: 0;
    $checksum  = hash_file('sha256', $destPath);

    $relPath = "logstore/$safeMachine/$safeDate/$fileName";
    $nowUtc  = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                 ->format(DateTimeInterface::ATOM);

    // Upsert-Logik: pro machine+date+type überschreiben wir den Indexeintrag
    $stmt = $pdo->prepare("
        INSERT INTO logs (machine, user, sessionId, logType, logDate, fileName, filePath, sizeBytes, checksum, uploadedUtc)
        VALUES (:machine, :user, :sessionId, :logType, :logDate, :fileName, :filePath, :sizeBytes, :checksum, :uploadedUtc)
        ON CONFLICT(machine, logDate, logType) DO UPDATE SET
            user=excluded.user,
            sessionId=excluded.sessionId,
            fileName=excluded.fileName,
            filePath=excluded.filePath,
            sizeBytes=excluded.sizeBytes,
            checksum=excluded.checksum,
            uploadedUtc=excluded.uploadedUtc
    ");
    // dafür braucht es UNIQUE constraint — falls noch nicht vorhanden:
    // siehe Hinweis unten.

    $stmt->execute([
        ":machine" => $machine,
        ":user" => $user,
        ":sessionId" => $sessionId,
        ":logType" => $logType,
        ":logDate" => $logDate,
        ":fileName" => $fileName,
        ":filePath" => $relPath,
        ":sizeBytes" => $sizeBytes,
        ":checksum" => $checksum,
        ":uploadedUtc" => $nowUtc
    ]);

    json_response([
        "ok" => true,
        "stored" => [
            "machine" => $machine,
            "logType" => $logType,
            "logDate" => $logDate,
            "filePath" => $relPath,
            "sizeBytes" => $sizeBytes,
            "checksum" => $checksum
        ]
    ]);

} catch (Throwable $e) {
    json_response(["error" => "Log upload failed", "detail" => $e->getMessage()], 500);
}
