<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$secrets = require dirname(__DIR__) . '/sent-email/secrets.php';


/****************************************************
 * Konfiguration
 ****************************************************/
$tenantId     = $secrets['ENTRA_TENANT_ID'];
$clientId     = $secrets['ENTRA_APP_CLIENT_ID'];
$clientSecret = $secrets['ENTRA_APP_CLIENT_SECRET'];

/**
 * Liste der erlaubten Postfächer.
 * Key = E-Mail-Adresse (UserPrincipalName)
 * Value = Label im Dropdown (hier auch die E-Mail)
 */
$mailboxes = [
    'elkhaus@elk.at' => 'elkhaus@elk.at'
];

// Gewähltes Postfach per GET (mailbox), sonst erstes aus der Liste
$selectedMailbox = isset($_GET['mailbox']) ? $_GET['mailbox'] : array_key_first($mailboxes);
// Fallback: falls jemand eine Mailbox per URL schickt, die nicht erlaubt ist
if (!array_key_exists($selectedMailbox, $mailboxes)) {
    $selectedMailbox = array_key_first($mailboxes);
}

// Optionaler Betreff-Filter aus GET-Parameter ?subject=...
$subjectFilter = isset($_GET['subject']) ? trim($_GET['subject']) : '';

$emails      = [];
$errorMessage = '';
$hasSearched  = !empty($_GET); // true, sobald das Formular einmal abgeschickt wurde

/****************************************************
 * Funktion: Access Token holen (Client Credentials)
 ****************************************************/
function getAccessToken($tenantId, $clientId, $clientSecret) {
    $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";

    $postFields = [
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => 'https://graph.microsoft.com/.default',
        'grant_type'    => 'client_credentials'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Error getting token: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Error getting token: HTTP ' . $httpCode . ' Response: ' . $response);
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        throw new Exception('Access token not found in response: ' . $response);
    }

    return $data['access_token'];
}

/****************************************************
 * Funktion: Gesendete Mails über Graph abfragen
 ****************************************************/
function getSentEmails($accessToken, $mailboxUserPrincipalName, $subjectFilter = '') {
    $baseUrl = "https://graph.microsoft.com/v1.0/users/" . urlencode($mailboxUserPrincipalName) . "/mailFolders/sentitems/messages";

    // Basis-Parameter
    $params = [
        '$select' => 'subject,toRecipients,ccRecipients,hasAttachments,sentDateTime',
        '$top'    => 50
    ];

    if ($subjectFilter === '') {
        // Ohne Filter: ganz normal nach Datum sortieren
        $params['$orderby'] = 'sentDateTime DESC';
    } else {
        // MIT Filter: $search verwenden (ohne 'subject:')
        $clean = str_replace('"', '\"', $subjectFilter);
        // Suchtext in Anführungszeichen, damit z.B. "672468" als Phrase gesucht wird
        $params['$search'] = '"' . $clean . '"';
        // Kein $orderby bei $search, sonst wieder "zu komplex"
    }

    $url = $baseUrl . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    $headers = [
        "Authorization: Bearer {$accessToken}",
        "Accept: application/json"
    ];

    if ($subjectFilter !== '') {
        // Für $search erforderlich
        $headers[] = "ConsistencyLevel: eventual";
        // $count brauchen wir hier nicht zwingend
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Error calling Graph: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Error calling Graph: HTTP ' . $httpCode . ' Response: ' . $response);
    }

    $data = json_decode($response, true);
    return isset($data['value']) ? $data['value'] : [];
}


/****************************************************
 * Nur suchen, wenn das Formular abgeschickt wurde
 ****************************************************/
if ($hasSearched) {
    try {
        $accessToken = getAccessToken($tenantId, $clientId, $clientSecret);
        $emails = getSentEmails($accessToken, $selectedMailbox, $subjectFilter);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $emails = [];
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Gesendete Outlook-Mails durchsuchen</title>

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

</head>
<body class="bg-light">

<div class="container py-4">
    <h1 class="mb-4">Gesendete Outlook-Mails durchsuchen</h1>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="mailbox" class="form-label">Postfach</label>
            <select class="form-select" id="mailbox" name="mailbox">
                <?php foreach ($mailboxes as $email => $label): ?>
                    <option value="<?php echo htmlspecialchars($email); ?>"
                        <?php echo ($email === $selectedMailbox) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label for="subject" class="form-label">Betreff enthält</label>
            <input type="text"
                   class="form-control"
                   id="subject"
                   name="subject"
                   value="<?php echo htmlspecialchars($subjectFilter); ?>"
                   placeholder="z.B. Rechnung, Angebot ...">
        </div>

        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Suchen</button>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">
                Zurücksetzen
            </a>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            Ergebnisse für: <strong><?php echo htmlspecialchars($selectedMailbox); ?></strong>
            <?php if ($subjectFilter !== ''): ?>
                <span class="text-muted"> | Betreff enthält: "<?php echo htmlspecialchars($subjectFilter); ?>"</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                    <tr>
                        <th scope="col">Betreff</th>
                        <th scope="col">Empfänger</th>
                        <th scope="col">CC</th>
                        <th scope="col">Anhang</th>
                        <th scope="col">Gesendet am</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($emails)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-3">
                                Keine Ergebnisse gefunden.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($emails as $mail): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($mail['subject'] ?? ''); ?></td>
                                <td>
                                    <?php
                                    if (!empty($mail['toRecipients'])) {
                                        $recipients = [];
                                        foreach ($mail['toRecipients'] as $rec) {
                                            $name = $rec['emailAddress']['name'] ?? '';
                                            $addr = $rec['emailAddress']['address'] ?? '';
                                            if ($name && $addr) {
                                                $recipients[] = htmlspecialchars("$name <$addr>");
                                            } elseif ($addr) {
                                                $recipients[] = htmlspecialchars($addr);
                                            }
                                        }
                                        echo implode('<br>', $recipients);
                                    }
                                    ?>
                                </td>
                                <!-- CC -->
                                <td>
                                    <?php
                                    if (!empty($mail['ccRecipients'])) {
                                        $ccList = [];
                                        foreach ($mail['ccRecipients'] as $rec) {
                                            $name = $rec['emailAddress']['name'] ?? '';
                                            $addr = $rec['emailAddress']['address'] ?? '';
                                            if ($name && $addr) {
                                                $ccList[] = htmlspecialchars("$name <$addr>");
                                            } elseif ($addr) {
                                                $ccList[] = htmlspecialchars($addr);
                                            }
                                        }
                                        echo implode('<br>', $ccList);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php echo !empty($mail['hasAttachments']) ? 'Ja' : 'Nein'; ?>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($mail['sentDateTime'])) {
                                        try {
                                            $dt = new DateTime($mail['sentDateTime']);
                                            echo htmlspecialchars($dt->format('d.m.Y H:i:s'));
                                        } catch (Exception $e) {
                                            echo htmlspecialchars($mail['sentDateTime']);
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Bootstrap 5 JS (optional, z.B. für Dropdowns/Modals) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

</body>
</html>
