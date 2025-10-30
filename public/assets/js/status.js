// =======================
// CONFIGURATION
// =======================
let GROUPS = [];
window.MAINTENANCE = { active: false };

async function loadConfig() {
  const res = await fetch('assets/config/status.config.json', { cache: 'no-store' });
  if (!res.ok) throw new Error('Konfiguration konnte nicht geladen werden.');
  const cfg = await res.json();
  if (!cfg || !Array.isArray(cfg.groups)) throw new Error('Ungültige Konfiguration (groups fehlen).');
  return cfg;
}

async function bootstrap() {
  try {
    const cfg = await loadConfig();
    GROUPS = cfg.groups;                 // direkt übernehmen, NICHT normalisieren
    window.MAINTENANCE = cfg.maintenance || { active: false };

    renderAllGroups();
    refreshAll();
    if (typeof showMaintenanceBanner === 'function') showMaintenanceBanner();
  } catch (e) {
    console.error(e);
    document.getElementById('groupsContainer').innerHTML =
      '<div class="alert alert-danger">Konfiguration konnte nicht geladen werden.</div>';
  }
}

bootstrap();


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
                <div class="service-headers mt-1" id="headers-${group.key}-${s.key}"></div>
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

    // Header einsammeln (immer klein schreiben)
    const headers = {};
    res.headers.forEach((v, k) => { headers[k.toLowerCase()] = v; });

    // HTTP-Fehlerstatus (z. B. 500/404) → NOK, aber Header mitsenden
    if (!res.ok) return { ok: false, ms, count: null, value: null, data: null, headers };

    // --- GET-Requests: Body auswerten ---
    if (method === "GET") {
      const raw = await res.text();
      if (!raw) return { ok: false, ms, count: null, value: null, data: null, headers };

      let data;
      try {
        data = JSON.parse(raw);
      } catch {
        return { ok: false, ms, count: null, value: null, data: null, headers };
      }

      if (data === null) return { ok: false, ms, count: null, value: null, data: null, headers };

      const count = Array.isArray(data?.results) ? data.results.length : null;

      // --- einfache Expect-Prüfung ---
      if (expect) {
        const v = expect.jsonPath ? getByPath(data, expect.jsonPath) : data;
        let pass = v !== undefined && v !== null;

        if ("equals" in expect)  pass = v === expect.equals;
        if ("truthy" in expect)  pass = !!v === !!expect.truthy;
        if ("minLen" in expect)  pass = Array.isArray(v) ? v.length >= expect.minLen : false;
        if ("in" in expect && Array.isArray(expect.in)) pass = expect.in.includes(v);

        return { ok: pass, ms, count, value: v, data, headers };
      }

      // kein Expect → OK
      return { ok: true, ms, count, value: null, data, headers };
    }

    // --- HEAD oder andere Methoden ---
    return { ok: true, ms, count: null, value: null, data: null, headers };
  } catch {
    const ms = Math.round(performance.now() - start);
    return { ok: false, ms, count: null, value: null, data: null, headers: null };
  }
}


function collectHeaders(res) {
  const out = {};
  res.headers.forEach((v, k) => { out[k.toLowerCase()] = v; });
  return out;
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
      renderServiceHeaders(g.key, s, r.headers);  
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

function renderServiceHeaders(groupKey, serviceDef, headers) {
  const containerId = `headers-${groupKey}-${serviceDef.key}`;
  const container = document.getElementById(containerId);
  if (!container) return;
  const defs = serviceDef.headers || [];
  if (!headers || defs.length === 0) { container.innerHTML = ""; return; }

  const parts = defs.map(h => {
    const name = String(h.name || '').toLowerCase();
    const raw  = headers[name];
    if (raw == null) return ''; // Header fehlt -> nichts anzeigen

    // Formatter wiederverwenden
    let val = raw;
    if (h.format && FORMATTERS[h.format]) val = FORMATTERS[h.format](isFinite(+raw) ? +raw : raw);

    // Badge-Farbe bestimmen
    let badgeClass = null;
    if (h.badge) badgeClass = `text-bg-${h.badge}`;
    if (h.badgeByValue && raw in h.badgeByValue) badgeClass = `text-bg-${h.badgeByValue[raw]}`;

    const labelHtml = h.label ? `<span class="sh-label">${escapeHtml(h.label)}:</span>` : "";
    const valueHtml = badgeClass
      ? `<span class="badge ${badgeClass}">${escapeHtml(String(val))}</span>`
      : `<span class="sh-value">${escapeHtml(String(val))}</span>`;

    return `<span class="sh-item">${labelHtml} ${valueHtml}</span>`;
  }).filter(Boolean);

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
