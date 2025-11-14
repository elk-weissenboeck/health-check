<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__)); // -> Projektwurzel neben vendor/, public/, config/
require_once APP_ROOT . '/public/vendor/autoload.php';

$BOOTSTRAP_CSS = '../vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
$BOOTSTRAP_JS  = '../vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js';
$BI_CSS        = '../vendor/twbs/bootstrap-icons/font/bootstrap-icons.css';
?><!doctype html>
<html lang="de" data-bs-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Health-Check Dashboard</title>

  <!-- Bootstrap & Icons -->
  <link rel="stylesheet" href="<?= htmlspecialchars($BOOTSTRAP_CSS) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($BI_CSS) ?>">

  <!-- Eigenes CSS -->
  <link rel="stylesheet" href="./src/status.css?t=<?=time()?>">
  <link rel="stylesheet" href="./src/ui/Icons.css?t=<?=time()?>">
  <link rel="stylesheet" href="./src/ui/ServiceBlocks.css?t=<?=time()?>">
  <link rel="stylesheet" href="./src/ui/ServiceOwner.css?t=<?=time()?>">
</head>

<body>
  <main class="container container-narrow py-4">
    <header class="mb-4">
      <h1 class="h3 mb-1"><i class="bi bi-speedometer2 me-2"></i>Health-Check Dashboard</h1>
      <p class="text-secondary mb-0">Übersicht div. Systeme bei ELK/KAMPA</p>
    </header>

    <!-- Wartungshinweis -->
    <div id="maintenanceBanner" class="alert alert-warning d-none align-items-center gap-2" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-5"></i>
      <div>
        <strong id="maintenanceTitle">Wartungshinweis:</strong>
        <span id="maintenanceMessage">–</span>
      </div>
    </div>

<section class="border rounded status-banner mb-4" id="overallCard" style="--status-color: var(--bs-warning);">
  <!-- Statuszeile mit Toggle rechts -->
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 my-3 mx-3">
    <div>
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-activity text-warning fs-4" id="overallIcon"></i>
        <h2 class="h5 mb-0" id="overallTitle">Prüfe..</h2>
      </div>
      <small class="text-secondary d-block mt-1">
        Letztes Update: <time id="lastUpdated">-</time>
      </small>
    </div>

    <!-- EIN Icon-Button für Collapse -->
    <button class="btn btn-xl btn-outline-secondary ms-auto"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#optionsCollapse"
            aria-expanded="true"
            aria-controls="optionsCollapse">
      <i class="bi bi-sliders"></i>
      <span class="visually-hidden">Optionen und Funktionen ein-/ausklappen</span>
    </button>
  </div>

  <!-- Gemeinsamer Collapse für Optionen + Funktionen -->
  <div id="optionsCollapse" class="bg-light collapse show mt-3 px-3 py-3">
    <div class="row g-3">
       
      <!-- Optionen links -->
      <div class="col-12 col-lg-6">
        <div class="mb-2 d-flex align-items-center gap-2">
          <i class="bi bi-sliders"></i>
          <span class="fw-semibold">Optionen</span>
        </div>

        <div class="d-flex flex-column gap-2 align-items-start">

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="optShowUrls">
            <label class="form-check-label" for="optShowUrls">URLs anzeigen</label>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="optShowAttr">
            <label class="form-check-label" for="optShowAttr">Response-Attribute anzeigen</label>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="optShowHeaders">
            <label class="form-check-label" for="optShowHeaders">Response-Header anzeigen</label>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="optShowLatency">
            <label class="form-check-label" for="optShowLatency">Server-Reaktionszeit anzeigen</label>
          </div>

          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="optOpenOptionsOnLoad">
            <label class="form-check-label" for="optOpenOptionsOnLoad">
              Optionsblock beim Laden geöffnet
            </label>
          </div>

          <div class="d-flex align-items-center gap-2">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="optAutoRefresh">
              <label class="form-check-label" for="optAutoRefresh">Auto-Refresh</label>
            </div>
            <select id="optRefreshInterval" class="form-select form-select-sm" style="width:auto">
              <option value="300">10min</option>
              <option value="1800" selected>30min</option>
              <option value="3600">60min</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Funktionen rechts -->
      <div class="col-12 col-lg-3">
        <div class="mb-2 fw-semibold">
          <i class="bi bi-gear"></i>
          Funktionen
        </div>
        <div class="d-grid gap-2">
          <div class="btn-group-vertical w-100">

            <a class="btn btn-light text-start"
               href="https://helpdesk.elkkampa.com/Helpdesk" target="_blank">
              <i class="bi bi-stack me-1"></i> Ticketsystem
            </a>

            <button id="listServicesBtn" class="btn btn-light text-start">
              <i class="bi bi-gear-fill me-1"></i> Liste Services
            </button>

            <button id="listOwnersBtn" class="btn btn-light text-start">
              <i class="bi bi-person-fill-gear me-1"></i> Liste ServiceOwner
            </button>
            
            <button class="btn btn-light text-start" id="expandAllBtn">
              <i class="bi bi-arrows-expand me-1"></i> Alle aufklappen
            </button>
            
            <button class="btn btn-light text-start" id="collapseAllBtn">
              <i class="bi bi-arrows-collapse me-1"></i> Alle zuklappen
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>
        
    <div id="groupsContainer" class="d-flex flex-column gap-4"></div>
    
    <section class="mt-4">
      <div class="small text-secondary">
        <span class="me-3"><i class="bi bi-check-circle-fill text-success me-1"></i>OK</span>
        <span class="me-3"><i class="bi bi-x-circle-fill text-danger me-1"></i>NOK</span>
        <span class="me-3"><i class="bi bi-dash-circle-fill text-secondary me-1"></i>N/A</span>
	<span class="legend-item"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Warnung</span>
      </div>
    </section>
  </main>

  <script src="<?= htmlspecialchars($BOOTSTRAP_JS) ?>"></script>
  <script type="module" src="./src/app/Main.js?t=<?=time()?>"></script>
</body>
</html>
