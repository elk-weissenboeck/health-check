<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

$auth = new myApiAuth(
    BASE_DIR . '/tokens.php',
    BASE_DIR . '/logs/UserTokenAccess.log'
);

// Wichtig: Cookie als Token-Quelle aktivieren
$auth->useCookieToken();   // liest ggf. $_COOKIE['UserToken'] ein

$client = $auth->requireAnyRole(['admin']);


// Pfad zum Logfile anpassen:
$logFile = BASE_DIR . '/logs/UserTokenAccess.log';

// einfache Konfiguration
$maxRowsDefault = 500;

// --- Filter aus GET einlesen ---
$filterAction = $_GET['action'] ?? '';
$filterToken  = $_GET['token']  ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterRole   = $_GET['role']   ?? '';
$maxRows      = isset($_GET['limit']) && ctype_digit($_GET['limit'])
    ? (int)$_GET['limit']
    : $maxRowsDefault;

// --- Logfile einlesen ---
$rows = [];
$error = null;

if (!is_readable($logFile)) {
    $error = "Logfile nicht lesbar oder nicht vorhanden: {$logFile}";
} else {
    $handle = fopen($logFile, 'r');
    if (!$handle) {
        $error = "Konnte Logfile nicht öffnen: {$logFile}";
    } else {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);
            if (count($parts) < 7) {
                // Zeile entspricht nicht dem erwarteten Format
                continue;
            }

            list($timestamp, $ip, $token, $name, $rolesStr, $action, $status) = $parts;

            // Filter anwenden
            if ($filterAction !== '' && stripos($action, $filterAction) === false) {
                continue;
            }
            if ($filterToken !== '' && stripos($token, $filterToken) === false) {
                continue;
            }
            if ($filterStatus !== '' && stripos($status, $filterStatus) === false) {
                continue;
            }
            if ($filterRole !== '') {
                $roles = array_map('trim', explode(',', $rolesStr));
                $matchRole = false;
                foreach ($roles as $r) {
                    if ($r !== '' && stripos($r, $filterRole) !== false) {
                        $matchRole = true;
                        break;
                    }
                }
                if (!$matchRole) {
                    continue;
                }
            }

            $rows[] = [
                'timestamp' => $timestamp,
                'ip'        => $ip,
                'token'     => $token,
                'name'      => $name,
                'roles'     => $rolesStr,
                'action'    => $action,
                'status'    => $status,
            ];

            if (count($rows) >= $maxRows) {
                break;
            }
        }

        fclose($handle);
    }
}

// Für einfache Sortierung nach Zeit absteigend (neueste oben)
usort($rows, function ($a, $b) {
    return strcmp($b['timestamp'], $a['timestamp']);
});

// HTML esc helper
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>API Log Viewer</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        h1 {
            margin-bottom: 0.5rem;
        }
        .filters {
            background: #ffffff;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .filters label {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 5px;
        }
        .filters input {
            padding: 4px 6px;
            font-size: 0.9rem;
        }
        .filters button {
            padding: 5px 10px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: #ffffff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        thead {
            background: #333;
            color: #fff;
        }
        th, td {
            padding: 6px 8px;
            font-size: 0.85rem;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        th {
            text-align: left;
            white-space: nowrap;
        }
        tbody tr:nth-child(every) {
            background: #fafafa;
        }
        tbody tr:nth-child(odd) {
            background: #fafafa;
        }
        tbody tr:nth-child(even) {
            background: #ffffff;
        }
        .status-ok {
            color: #1a7f37;
            font-weight: 600;
        }
        .status-error, .status-denied {
            color: #b91c1c;
            font-weight: 600;
        }
        .pill {
            display: inline-block;
            padding: 2px 6px;
            margin: 1px 2px;
            border-radius: 999px;
            background: #e5e7eb;
            font-size: 0.75rem;
        }
        .meta {
            margin-bottom: 10px;
            font-size: 0.85rem;
            color: #555;
        }
        .error {
            padding: 10px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<h1>API Log Viewer</h1>

<div class="meta">
    Logfile: <code><?= h($logFile) ?></code><br>
    Gefundene Einträge: <?= count($rows) ?> (Limit: <?= (int)$maxRows ?>)
</div>

<div class="filters">
    <form method="get">
        <label>
            Aktion:
            <input type="text" name="action" value="<?= h($filterAction) ?>" placeholder="z.B. edit_article">
        </label>
        <label>
            Token:
            <input type="text" name="token" value="<?= h($filterToken) ?>" placeholder="z.B. abc123">
        </label>
        <label>
            Status:
            <input type="text" name="status" value="<?= h($filterStatus) ?>" placeholder="z.B. ok">
        </label>
        <label>
            Rolle:
            <input type="text" name="role" value="<?= h($filterRole) ?>" placeholder="z.B. admin">
        </label>
        <label>
            Limit:
            <input type="number" name="limit" value="<?= (int)$maxRows ?>" min="1" max="10000" style="width: 70px;">
        </label>
        <button type="submit">Filtern</button>
        <button type="submit" name="reset" value="1" onclick="window.location='<?= h(basename($_SERVER['PHP_SELF'])) ?>';return false;">Zurücksetzen</button>
    </form>
</div>

<?php if ($error): ?>
    <div class="error">
        <?= h($error) ?>
    </div>
<?php elseif (empty($rows)): ?>
    <p>Keine Einträge (entsprechend der Filter).</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Zeit</th>
                <th>IP</th>
                <th>Token</th>
                <th>Name</th>
                <th>Rollen</th>
                <th>Aktion</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= h($row['timestamp']) ?></td>
                <td><?= h($row['ip']) ?></td>
                <td><code><?= h(substr($row['token'],0,8)) ?></code></td>
                <td><?= h($row['name']) ?></td>
                <td>
                    <?php
                    $roles = array_filter(array_map('trim', explode(',', $row['roles'])));
                    if (empty($roles)) {
                        echo '<span class="pill">–</span>';
                    } else {
                        foreach ($roles as $role) {
                            echo '<span class="pill">' . h($role) . '</span>';
                        }
                    }
                    ?>
                </td>
                <td><?= h($row['action']) ?></td>
                <td>
                    <?php
                    $statusClass = '';
                    if (strcasecmp($row['status'], 'ok') === 0) {
                        $statusClass = 'status-ok';
                    } elseif (in_array(strtolower($row['status']), ['denied','error','fail','failed'], true)) {
                        $statusClass = 'status-denied';
                    }
                    ?>
                    <span class="<?= h($statusClass) ?>"><?= h($row['status']) ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

</body>
</html>
