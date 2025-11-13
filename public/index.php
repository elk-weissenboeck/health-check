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

    <section class="card status-banner mb-4" id="overallCard" style="--status-color: var(--bs-success);">
      <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill text-success fs-4" id="overallIcon"></i>
            <h2 class="h5 mb-0" id="overallTitle">Alle Services online</h2>
          </div>
          <small class="text-secondary d-block mt-1">
            Letztes Update: <time id="lastUpdated">–</time>
          </small>
        </div>
        
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" id="refreshBtn">
            <i class="bi bi-arrow-clockwise me-1"></i> Aktualisieren
          </button>
        </div>
      </div>
    </section>

    <!-- Options -->
    <section class="mb-3">
      <div class="row g-3">
        <!-- Optionen: 3/4 -->
        <div class="col-12 col-lg-9">
            <div class="card h-100">
              <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-gear"></i>
                  <span class="fw-semibold">Optionen</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#optionsCollapse"
                        aria-expanded="false" aria-controls="optionsCollapse">
                  <i class="bi bi-chevron-down"></i>
                </button>
              </div>
              <div id="optionsCollapse" class="collapse">
                <div class="card-body d-flex flex-column gap-2 align-items-start">

                  <!-- URLs zeigen/verstecken -->
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="optShowUrls">
                    <label class="form-check-label" for="optShowUrls">URLs anzeigen</label>
                  </div>

                  <!-- Attribute zeigen/verstecken -->
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="optShowAttr">
                    <label class="form-check-label" for="optShowAttr">Response-Attribute anzeigen</label>
                  </div>
                  
                  <!-- Header zeigen/verstecken -->
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="optShowHeaders">
                    <label class="form-check-label" for="optShowHeaders">Response-Header anzeigen</label>
                  </div>

                  <!-- Reaktionszeit zeigen/verstecken -->
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="optShowLatency">
                    <label class="form-check-label" for="optShowLatency">Server-Reaktionszeit anzeigen</label>
                  </div>

                  <!-- Optionsblock beim Laden automatisch öffnen -->
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="optOpenOptionsOnLoad">
                    <label class="form-check-label" for="optOpenOptionsOnLoad">
                      Optionsblock beim Laden geöffnet
                    </label>
                  </div>
                  
                  <!-- Auto-Refresh + Intervall -->
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
                  

                  <!-- Expand/Collapse -->
                  <div class="btn-group btn-group-sm" role="group" aria-label="Expand/Collapse all">
                    <button class="btn btn-outline-primary" id="expandAllBtn">
                      <i class="bi bi-arrows-expand me-1"></i> Alle aufklappen
                    </button>
                    <button class="btn btn-outline-secondary" id="collapseAllBtn">
                      <i class="bi bi-arrows-collapse me-1"></i> Alle zuklappen
                    </button>
                  </div>

                  <!-- Reserviert für künftige Optionen -->
                  <div id="optionsExtra" class="d-none"></div>
                </div>
              </div>
            </div>
        </div>
        
        <!-- Funktionen: 1/4 -->
        <div class="col-12 col-lg-3">
          <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
              <span>Funktionen</span>
              <button class="btn btn-sm btn-outline-secondary" type="button"
                      data-bs-toggle="collapse" data-bs-target="#functionsCollapse"
                      aria-expanded="true" aria-controls="functionsCollapse">
                <i class="bi bi-chevron-down"></i>
              </button>
            </div>
            <div id="functionsCollapse" class="collapse show">
              <div class="card-body py-3 d-grid gap-2">
                <button id="listOwnersBtn" class="btn btn-outline-secondary btn-sm">
                  Liste ServiceOwner
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
