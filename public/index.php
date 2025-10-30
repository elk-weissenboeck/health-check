<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

$BOOTSTRAP_CSS = '../vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
$BOOTSTRAP_JS  = '../vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js';
$BI_CSS        = '../vendor/twbs/bootstrap-icons/font/bootstrap-icons.css';
?><!doctype html>
<html lang="de" data-bs-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Statusübersicht</title>

  <!-- Bootstrap & Icons -->
  <link rel="stylesheet" href="<?= htmlspecialchars($BOOTSTRAP_CSS) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($BI_CSS) ?>">

  <!-- Eigenes CSS -->
  <link rel="stylesheet" href="./assets/css/status.css?t=<?=time()?>">
</head>

<body>
  <main class="container container-narrow py-4">
    <header class="mb-4">
      <h1 class="h3 mb-1"><i class="bi bi-speedometer2 me-2"></i>Statusübersicht</h1>
      <p class="text-secondary mb-0">Übersicht über den Status div. Systeme (Bemusterung, Webseiten, Schnittstellen …)</p>
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
  <!-- NEU: Expand/Collapse-All -->
  <div class="btn-group btn-group-sm" role="group" aria-label="Expand/Collapse all">
    <button class="btn btn-outline-primary" id="expandAllBtn">
      <i class="bi bi-arrows-expand me-1"></i> Alle aufklappen
    </button>
    <button class="btn btn-outline-secondary" id="collapseAllBtn">
      <i class="bi bi-arrows-collapse me-1"></i> Alle zuklappen
    </button>
  </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="autoRefresh">
            <label class="form-check-label" for="autoRefresh">Auto-Refresh</label>
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
  <script src="./assets/js/status.js?t=<?=time()?>"></script>
</body>
</html>
