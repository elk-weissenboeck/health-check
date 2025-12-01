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
$bounceMails  = [];
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



function getUndeliverableInboxMessages(string $accessToken, string $userPrincipalName, int $max = 50): array
{
    $baseUrl = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($userPrincipalName) . '/mailFolders/inbox/messages';

    $params = [
        '$select'  => 'subject,receivedDateTime,body,bodyPreview',
        '$top'     => max($max * 3, 50),
        '$orderby' => 'receivedDateTime desc'
    ];

    $url = $baseUrl . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $response = curl_exec($ch);

    if ($response === false) {
        throw new Exception('Fehler beim Abruf der unzustellbaren Nachrichten: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception('Fehler beim Abruf der unzustellbaren Nachrichten (HTTP ' . $httpCode . '): ' . $response);
    }

    $data     = json_decode($response, true);
    $messages = $data['value'] ?? [];

    // wie gehabt: nach Betreff filtern
    $needles = [
        'unzustellbar',
        'undeliverable',
        'delivery has failed',
        'mail delivery subsystem',
        'mail delivery failed',
    ];

    $result = [];
    foreach ($messages as $msg) {
        $subject = $msg['subject'] ?? '';
        $lower   = mb_strtolower((string)$subject);

        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($lower, mb_strtolower($needle)) !== false) {
                $result[] = $msg;
                break;
            }
        }

        if (count($result) >= $max) {
            break;
        }
    }

    return $result;
}


function extractBounceRecipients(array $mail, string $userMailbox): array
{
    $result = [];

    // 1) Präzise: zweite Spalte bei "Recipient Address:"
    if (!empty($mail['body']['content'])) {
        $result = extractRecipientAddressFromBodyHtml($mail['body']['content']);
    }

    // 2) Wenn immer noch nichts gefunden, generischer Fallback
    if (empty($result)) {
        $sources = [];

        if (!empty($mail['bodyPreview'])) {
            $sources[] = $mail['bodyPreview'];
        }
        if (!empty($mail['body']['content'])) {
            $sources[] = strip_tags($mail['body']['content']);
        }

        if (!empty($sources)) {
            $text = implode("\n", $sources);

            if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
                $emails = array_unique($matches[0]);
                $user   = mb_strtolower($userMailbox);

                foreach ($emails as $addr) {
                    $addrLower = mb_strtolower($addr);

                    if ($addrLower === $user) {
                        continue; // eigene Adresse raus
                    }

                    if (str_contains($addrLower, 'prod.outlook.com') ||
                        str_contains($addrLower, 'protection.outlook.com') ||
                        str_starts_with($addrLower, 'postmaster@') ||
                        str_starts_with($addrLower, 'mailer-daemon@')) {
                        continue; // Systemadressen raus
                    }

                    $result[] = $addr;
                }
            }
        }
    }

    return array_unique($result);
}


function extractRecipientAddressFromBodyHtml(string $html): array
{
    $result = [];

    if (trim($html) === '') {
        return $result;
    }

    // HTML in DOM laden
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // Encoding-Header davor setzen, damit Umlaute sauber sind
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // td finden, dessen Text "Recipient Address:" enthält (case-insensitive)
    $nodes = $xpath->query(
        "//td[contains(translate(normalize-space(.),
               'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
               'abcdefghijklmnopqrstuvwxyz'),
               'recipient address:')]/following-sibling::td[1]"
    );

    if ($nodes !== false) {
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '' && filter_var($text, FILTER_VALIDATE_EMAIL)) {
                $result[] = $text;
            }
        }
    }

    return array_unique($result);
}



/****************************************************
 * Nur suchen, wenn das Formular abgeschickt wurde
 ****************************************************/

try {
    $accessToken = getAccessToken($tenantId, $clientId, $clientSecret);

    $bounceMails = getUndeliverableInboxMessages($accessToken, $selectedMailbox, 50);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $emails      = [];
    $bounceMails = [];
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Unzustellbare Emails</title>

    <!-- Bootstrap 5 CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <style>
        .badge-mail {
            font-size: 0.8rem;
        }

        .badge-mail-to {
            /* Normale Empfänger */
        }

        .badge-mail-cc {
            background-color: transparent;
            border: 1px solid #6c757d; /* Bootstrap secondary */
            color: #6c757d;
        }
        
        .badge-mail-bounce {
            background-color: transparent;
            border: 1px dashed #dc3545; /* rot gestrichelt */
            color: #dc3545;
            font-family: monospace;
            font-size: 0.8rem;
        }

        .recipient-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #6c757d;
        }
        
        .attachment-line {
            margin-bottom: 0.15rem;
        }

        .nowrap-date {
            white-space: nowrap;
            display: inline-block;
        }
    </style>

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
        
    <h2 class="mt-4">Unzustellbare E-Mails im Posteingang</h2>

    <div class="table-responsive" style="min-width: 600px;">
        <table class="table table-sm table-striped table-bordered align-middle">
            <thead class="table-light">
            <tr>
                <th scope="col">Betreff</th>
                <th scope="col">Empfänger (aus Meldung)</th>
                <th scope="col">Eingegangen am</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($bounceMails)): ?>
                <tr>
                    <td colspan="3" class="text-center py-3">
                        Keine unzustellbaren Nachrichten gefunden.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bounceMails as $mail): ?>
                    <tr>
                        <!-- Betreff -->
                        <td><?php echo htmlspecialchars($mail['subject'] ?? ''); ?></td>

                        <!-- Empfängerliste aus bodyPreview -->
                        <td>
                            <?php
                            $recipients = extractBounceRecipients($mail, $selectedMailbox);

                            if (!empty($recipients)) {
                                foreach ($recipients as $addr) {
                                    echo '<span class="badge badge-mail-bounce me-1">'.htmlspecialchars($addr).'</span>';
                                }
                            } else {
                                echo '<span class="text-muted">–</span>';
                            }
                            ?>
                        </td>


                        <!-- Datum, nicht umbrechen -->
                        <td>
                            <?php
                            if (!empty($mail['receivedDateTime'])) {
                                echo '<span class="nowrap-date">';
                                try {
                                    $dt = new DateTime($mail['receivedDateTime']);
                                    echo $dt->format('d.m.Y H:i');
                                } catch (Exception $e) {
                                    echo htmlspecialchars($mail['receivedDateTime']);
                                }
                                echo '</span>';
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

<!-- Bootstrap 5 JS (optional, z.B. für Dropdowns/Modals) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

</body>
</html>
