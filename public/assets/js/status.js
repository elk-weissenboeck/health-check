// =======================
// CONFIGURATION
// =======================
const GROUPS = [
  {
    key: "hybridforms",
    title: "HybridForms Pipelines",
    description: "Übersicht aller Pipelines",
    services: [{
        key: "hf-enterprised-full",
        label: "Unternehmensdaten alle Stages",
        url: "proxy.php?key=hf-enterprise-full",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" },
          { label: "Aktiv", path: "building", format: "bool" },
        ]
    },{
        key: "hf-enterprised-quick",
        label: "Unternehmensdaten für S5+S6",
        url: "proxy.php?key=hf-enterprise-quick",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" },
          { label: "Aktiv", path: "building", format: "bool" },
        ]
    },{
        key: "hf-hauseubergabe-ninox",
        label: "Hauseubergabe Ninox",
        url: "proxy.php?key=hf-hauseubergabe-ninox",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    },{
        key: "hf-hauseubergabe-documents",
        label: "Hauseubergabe Dokumente",
        url: "proxy.php?key=hf-hauseubergabe-documents",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    },{
        key: "hf-hauseubergabe-pictures",
        label: "Hauseubergabe Fotos",
        url: "proxy.php?key=hf-hauseubergabe-pictures",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    },{
        key: "hf-maengelkostenanzeige",
        label: " Maengel-Kosten-Anzeige",
        url: "proxy.php?key=hf-maengelkostenanzeige",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    },{
        key: "hf-planbesprechung",
        label: "Planbesprechung",
        url: "proxy.php?key=hf-planbesprechung",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    },{
        key: "hf-protokolle",
        label: "Alle Protokolle",
        url: "proxy.php?key=hf-protokolle",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
     },{
        key: "hf-qualitaetsmanagement",
        label: "Qualitaetsmanagement",
        url: "proxy.php?key=hf-qualitaetsmanagement",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
     },{
        key: "hf-regieschein",
        label: "Regieschein",
        url: "proxy.php?key=hf-regieschein",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    },{
        key: "hf-wochenbericht",
        label: "Wochenbericht",
        url: "proxy.php?key=hf-wochenbericht",
        method: "GET",
        expect: { jsonPath: "result", equals: "SUCCESS" },
        fields: [
          { label: "Letzter Lauf", path: "timestamp", format: "datetime" },
          { label: "Dauer", path: "duration", format: "minutes" }
        ]
    }]
 },{
    key: "bemusterung",
    title: "Online-Bemusterung (Web-Tool)",
    description: "Kernfunktionen der Online-Bemusterung",
    services: [{ 
        key: "bmu-frontend",
        label: "Frontend",
        url: "https://bemusterung.elk.at/login/",
        method: "HEAD"
    },{ 
        key: "getArticles",
        label: "API Artikeln",
        url: "https://bemu-api.elk.at/getArticles.php",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    }, {
        key: "getArticlesById",
        label: "API Artikeldetail",
        url: "https://bemu-api.elk.at/getArticleById.php?id=AL-VISAAPP-00000",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    }, {
        key: "getGroups",
        label: "API Gruppen",
        url: "https://bemu-api.elk.at/getGroups.php",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    }, {
        key: "getStyleGroups",
        label: "API Designgruppen",
        url: "https://bemu-api.elk.at/getStyleGroups.php",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    }, {
        key: "getPriceGroups",
        label: "API Preisgruppen",
        url: "https://bemu-api.elk.at/getPriceGroups.php",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    }]
  },{
    key: "odoo",
    title: "Odoo",
    description: "Kernfunktionen der Odoo-API",
    services: [{
        key: "odoo-frontend",
        label: "Frontend",
        url: "https://odoo-elk.elkschrems.co.at/web/login",
        method: "HEAD"
    },{
        key: "odoo-status",
        label: "API Status",
        url: "https://odoo-api.elk.at/status.php",
        method: "GET",
        expect: { jsonPath: "status", equals: "up" },
        fields: [
          { label: "Version", path: "odoo" }
        ]
    },{
        key: "odoo-getJobs",
        label: "API Jobs",
        url: "https://odoo-api.elk.at/getJobs.php?v2",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    },{
        key: "odoo-getReferences",
        label: "API Referenzen",
        url: "https://odoo-api.elk.at/getReferences.php?v2",
        method: "GET",
        expect: { jsonPath: "results", minLen: 1 }
    }]
  },{
    key: "html5app",
    title: "Html5App",
    description: "Schnittstelle für Hausfinder im XP-Center",
    services: [{
        key: "html5app-api-status",
        label: "API Status",
        url: "https://html5app-prod.elk.at/",
        method: "GET",
        expect: { jsonPath: "status", equals: "up" }
    },{
        key: "html5app-allHouses",
        label: "API Sortiment",
        url: "https://html5app-prod.elk.at/v1/all-houses?v2",
        method: "GET",
        warning: null,
        expect: { jsonPath: "results", minLen: 1 }
    },{
        key: "html5app-houseById",
        label: "API Detailseite",
        url: "https://html5app-prod.elk.at/v1/houses/317",
        method: "GET",
        expect: { jsonPath: "title", equals: "Now 129" }
    }]
  },{
    key: "webseiten",
    title: "Webseiten",
    description: "Öffentliche und interne Seiten",
    services: [{
        key: "elk.at",
        label: "elk.at",
        url: "https://www.elk.at",
        method: "HEAD",
        warning: null
    }]
  },{
    key: "elkbau-calc",
    title: "Elkbau Kalkulations Tool",
    description: "Tool von Robert Sachetti",
    services: [{
        key: "elkbau-api",
        label: "API Status",
        url: "https://api-projekte.elkbau.at/",
        method: "GET",
        expect: { jsonPath: "status", equals: "up" }
    },{
        key: "elkbau-frontend",
        label: "Frontend",
        url: "https://projekte.elkbau.at/login",
        method: "HEAD"
    }]
  }

];

// =======================
// WARTUNG / WARNHINWEIS
// =======================

const MAINTENANCE = {
  active: false, 
  title: "Geplante Wartung",
  message: "Am 01.11.2025 von 22:00 – 23:00 Uhr. Einige Services sind währenddessen nicht verfügbar.",
  start: "2025-10-28T22:00:00+01:00",
  end: "2025-11-01T23:00:00+01:00"
};


// =======================
// RENDER LOGIC
// =======================
const groupsContainer = document.getElementById('groupsContainer');

function escapeHtml(str) {
  return String(str || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function groupCardTemplate(group) {
  const collapseId = `collapse-${group.key}`;
  const headerId = `header-${group.key}`;

  return `
    <section class="card card-group-status" id="group-${group.key}" aria-live="polite">
      <div class="card-header card-header-toggle"
           id="${headerId}"
           role="button"
           data-bs-toggle="collapse"
           data-bs-target="#${collapseId}"
           aria-expanded="false"
           aria-controls="${collapseId}">
        <div class="d-flex align-items-center gap-2">
          <span class="status-dot bg-success" id="${group.key}-dot" aria-hidden="true"></span>
          <span class="fw-semibold">${escapeHtml(group.title)}</span>
          <small class="text-secondary">— <span class="group-summary" id="${group.key}-summary">Prüfe…</span></small>
        </div>

        <div class="d-flex align-items-center gap-1 ms-3">
          <small class="text-secondary d-none d-md-inline">${escapeHtml(group.description || '')}</small>
          <span class="chevron-wrap" aria-hidden="true">
            <i class="bi bi-chevron-right chevron"></i>
          </span>
        </div>
      </div>

      <div id="${collapseId}" class="collapse" data-group-key="${group.key}" aria-labelledby="${headerId}">
        <div class="list-group list-group-flush list-status" id="${group.key}-list">
          ${group.services.map(s => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div class="d-flex flex-column">
                <span class="fw-medium">${escapeHtml(s.label)}</span>
                <small class="text-secondary">${escapeHtml(s.url)}</small>
                ${s.warning ? `<small class="service-warning mt-1">
                  <i class="bi bi-exclamation-triangle-fill service-warning-icon"></i>${escapeHtml(s.warning)}
                </small>` : ""}

                <div class="service-fields mt-1" id="fields-${group.key}-${s.key}"></div>
              </div>
              <div class="d-flex align-items-center gap-3">
                <small class="text-secondary d-none d-sm-inline" id="latency-${group.key}-${s.key}">– ms</small>
                <span class="badge text-bg-secondary" id="badge-${group.key}-${s.key}">N/A</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}


function renderAllGroups() {
  groupsContainer.innerHTML = GROUPS.map(g => groupCardTemplate(g)).join('');
}

// =======================
// STATUS CHECKING
// =======================
async function checkService(url, method = "HEAD", expect = null) {
  const start = performance.now();
  try {
    const res = await fetch(url, { method, cache: "no-store" });
    const ms = Math.round(performance.now() - start);
    if (!res.ok) return { ok: false, ms, count: null, value: null, data: null };

    if (method === "GET") {
      const raw = await res.text();
      if (!raw) return { ok: false, ms, count: null, value: null, data: null };
      let data; try { data = JSON.parse(raw); } catch { return { ok: false, ms, count: null, value: null, data: null }; }
      if (data === null) return { ok: false, ms, count: null, value: null, data: null };

      const count = Array.isArray(data?.results) ? data.results.length : null;

      if (expect) {
        const v = expect.jsonPath ? getByPath(data, expect.jsonPath) : data;
        let pass = v !== undefined && v !== null;
        if ("equals" in expect) pass = v === expect.equals;
        if ("truthy" in expect) pass = !!v === !!expect.truthy;
        if ("minLen" in expect)  pass = Array.isArray(v) ? v.length >= expect.minLen : false;
        if ("in" in expect && Array.isArray(expect.in)) pass = expect.in.includes(v);
        return { ok: pass, ms, count, value: v, data };
      }
      return { ok: true, ms, count, value: null, data };
    }
    return { ok: true, ms, count: null, value: null, data: null };
  } catch {
    const ms = Math.round(performance.now() - start);
    return { ok: false, ms, count: null, value: null, data: null };
  }
}


function setBadge(group, svc, ok, ms, count = null, value = null) {
  const badge = document.getElementById(`badge-${group}-${svc}`);
  const latency = document.getElementById(`latency-${group}-${svc}`);
  const service = GROUPS.find(gr => gr.key === group)?.services.find(s => s.key === svc);
  const hasWarning = !!service?.warning;

  let cls = ok ? 'text-bg-success' : 'text-bg-danger';
  let text = ok ? 'OK' : 'NOK';

  if (count !== null && ok) text = `OK (${count})`;
  if (hasWarning && ok) { cls = 'text-bg-warning'; text = 'OK'; }

  badge.className = `badge ${cls}`;
  badge.textContent = text;
  latency.textContent = `${ms} ms`;
  if (hasWarning && service.warning) badge.title = service.warning;
}


function getByPath(obj, path) {
  if (!path) return undefined;
  // Pfad wie "meta.status" oder "results[0].state"
  const parts = path
    .replace(/\[(\d+)\]/g, ".$1") // [0] -> .0
    .split(".")
    .filter(Boolean);
  return parts.reduce((acc, key) => (acc != null ? acc[key] : undefined), obj);
}


function setGroupStatus(groupKey, state /* 'ok' | 'warn' | 'nok' */) {
  const dot = document.getElementById(`${groupKey}-dot`);
  const summary = document.getElementById(`${groupKey}-summary`);

  if (state === 'nok') {
    dot.className = 'status-dot bg-danger';
    summary.textContent = 'Eingeschränkter Betrieb';
  } else if (state === 'warn') {
    dot.className = 'status-dot bg-warning';
    summary.textContent = 'Alle Services OK (Warnungen vorhanden)';
  } else {
    dot.className = 'status-dot bg-success';
    summary.textContent = 'Alle Services OK';
  }

  // Auto-Expand nur bei NOK
  const collapseEl = document.getElementById(`collapse-${groupKey}`);
  if (!collapseEl) return;
  const isShown = collapseEl.classList.contains('show');
  if (state === 'nok' && !isShown) {
    const c = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
    c.show();
  }
}


function setOverall(ok) {
  const card = document.getElementById("overallCard");
  const icon = document.getElementById("overallIcon");
  const title = document.getElementById("overallTitle");
  card.style.setProperty("--status-color", ok ? "var(--bs-success)" : "var(--bs-danger)");
  icon.className = `bi ${ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} fs-4`;
  title.textContent = ok ? "Alle Services online" : "Eingeschränkter Betrieb";
}

function updateTimestamp() {
  document.getElementById("lastUpdated").textContent = new Date().toLocaleString();
}

function expandAllSections() {
  GROUPS.forEach(g => {
    const el = document.getElementById(`collapse-${g.key}`);
    if (!el) return;
    const c = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
    c.show();
  });
}

function collapseAllSections() {
  GROUPS.forEach(g => {
    const el = document.getElementById(`collapse-${g.key}`);
    if (!el) return;
    const c = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
    c.hide();
  });
}

async function refreshAll() {
  const groupStates = await Promise.all(GROUPS.map(async (g) => {
    // Checks ausführen
    const results = await Promise.all(g.services.map(async (s) => {
      const r = await checkService(s.url, s.method, s.expect);
      setBadge(g.key, s.key, r.ok, r.ms, r.count, r.value);
      renderServiceFields(g.key, s, r.data);
      return r.ok;
    }));

    const anyNok = results.some(ok => !ok);
    const anyWarn = g.services.some(s => !!s.warning);

    const state = anyNok ? 'nok' : (anyWarn ? 'warn' : 'ok');
    setGroupStatus(g.key, state);
    return state;
  }));

  // Gesamtstatus: weiterhin grün, außer es gibt ein NOK.
  // (Optional: wenn du bei Warnungen auch gelb oben anzeigen willst, nimm die 3-Zustand-Logik auch hier.)
  const hasNok = groupStates.includes('nok');
  setOverall(!hasNok);
  updateTimestamp();
}

function showMaintenanceBanner() {
  const banner = document.getElementById("maintenanceBanner");
  const title = document.getElementById("maintenanceTitle");
  const msg = document.getElementById("maintenanceMessage");

  if (!MAINTENANCE.active) {
    banner.classList.add("d-none");
    return;
  }

  // Zeitprüfung
  const now = new Date();
  const start = MAINTENANCE.start ? new Date(MAINTENANCE.start) : null;
  const end = MAINTENANCE.end ? new Date(MAINTENANCE.end) : null;

  const isWithinWindow =
    (!start || now >= start) && (!end || now <= end);

  if (isWithinWindow) {
    title.textContent = MAINTENANCE.title + ":";
    msg.textContent = " " + MAINTENANCE.message;
    banner.classList.remove("d-none");
  } else {
    banner.classList.add("d-none");
  }
}

const FORMATTERS = {
  number: v => typeof v === "number" ? v.toLocaleString() : v,
  bytes:  v => typeof v === "number" ? formatBytes(v) : v,
  ms:     v => typeof v === "number" ? `${v} ms` : v,
  date:   v => v ? new Date(v).toLocaleDateString() : v,
  datetime: v => v ? new Date(v).toLocaleString() : v,
  bool:   v => (v ? "Ja" : "Nein"),
  upper:  v => (typeof v === "string" ? v.toUpperCase() : v),
  lower:  v => (typeof v === "string" ? v.toLowerCase() : v),
  minutes: v => {
    if (v == null || isNaN(v)) return v;
    const mins = v /1000 / 60;
    return `${mins.toFixed(1)} min`;
  }
};

function formatBytes(n){
  if (!Number.isFinite(n)) return n;
  const units = ["B","KB","MB","GB","TB"];
  let i=0; while (n>=1024 && i<units.length-1){ n/=1024; i++; }
  return `${n.toFixed(n<10&&i>0?1:0)} ${units[i]}`;
}

function renderServiceFields(groupKey, serviceDef, data) {
  const container = document.getElementById(`fields-${groupKey}-${serviceDef.key}`);
  if (!container) return;
  const fields = serviceDef.fields || [];
  if (!data || fields.length === 0) { container.innerHTML = ""; return; }

  const parts = fields.map(f => {
    const raw = f.path ? getByPath(data, f.path) : data;
    let val = raw;

    // Formatter anwenden
    if (f.format && FORMATTERS[f.format]) {
      val = FORMATTERS[f.format](raw);
    }

    // Badge-Farbe bestimmen
    let badgeClass = null;
    if (f.badge) badgeClass = `text-bg-${f.badge}`;
    if (f.badgeByValue && raw in f.badgeByValue) {
      badgeClass = `text-bg-${f.badgeByValue[raw]}`;
    }

    const labelHtml = f.label ? `<span class="sf-label">${escapeHtml(f.label)}:</span>` : "";
    const valueHtml = badgeClass
      ? `<span class="badge ${badgeClass}">${escapeHtml(String(val))}</span>`
      : `<span class="sf-value">${escapeHtml(String(val))}</span>`;

    return `<span class="sf-item">${labelHtml} ${valueHtml}</span>`;
  });

  container.innerHTML = parts.join("");
}


// =======================
// EVENT HANDLERS
// =======================
document.getElementById("refreshBtn").addEventListener("click", refreshAll);
document.getElementById("expandAllBtn").addEventListener("click", expandAllSections);
document.getElementById("collapseAllBtn").addEventListener("click", collapseAllSections);

let autoTimer = null;
document.getElementById("autoRefresh").addEventListener("change", (e) => {
  if (e.target.checked) {
    refreshAll();
    autoTimer = setInterval(refreshAll, 30000);
  } else {
    clearInterval(autoTimer);
  }
});

// Initial Render
renderAllGroups();
refreshAll();
showMaintenanceBanner();
