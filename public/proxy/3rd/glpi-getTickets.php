<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../classes/myCurl.class.php';
require __DIR__ . '/../../classes/myHelpers.class.php';
require __DIR__ . '/../../classes/myGlpi.class.php';

$BASE   = 'https://helpdesk.elkkampa.com/apirest.php';
$APP    = $secrets['GLPI_API_APP'];
$USER   = $secrets['GLPI_API_USER'];

//header('Content-Type: application/json; charset=utf-8');

$glpi = new MyGlpi($BASE, $APP, $USER);

$result = $glpi->searchTicketsByTag('hcd-test', '0-49');

foreach ($result['data'] as $row) {
    $ticket = $glpi->getTicket($row['2']);           // enthält u. a. name, content, date, date_mod
    $reqs   = $glpi->getTicketRequesters($row['2']); // Liste der Anforderer

    printf(
        "#%d | %s\n- Erstellt: %s\n- Anforderer: %s\n- Beschreibung:\n%s\n\n",
        $ticket['id'],
        $ticket['name']    ?? '(ohne Titel)',
        $ticket['date']    ?? '-',
        $reqs ? implode(', ', $reqs) : '-',
        $ticket['content'] ?? '-'
    );
}
// Debug: Welche Suchfelder sieht GLPI überhaupt für Tickets?
$opts = $glpi->listSearchOptions('Ticket');
//echo json_encode($opts, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

$tagFieldId = $glpi->findTagFieldId('Ticket');
if (!$tagFieldId) {
  die("Kein Tag-Feld gefunden.\n");
}

$result = $glpi->searchTicketsByTag('hcd-test', '0-49');

echo "Gefundene Tickets: " . ($result['totalcount'] ?? count($result['data'] ?? [])) . PHP_EOL;
    print_r($result);

foreach ($result['data'] ?? [] as $row) {
  $id    = $row['id']   ?? '?';
  $title = $row['name'] ?? ($row['2'] ?? '(ohne Betreff)');
  echo "#$id  $title\n";
}