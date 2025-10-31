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
    applyOptions();   // Optionen nach Render anwenden (URLs/Auto-Refresh)
    refreshAll();
    if (typeof showMaintenanceBanner === 'function') showMaintenanceBanner();
  } catch (e) {
    console.error(e);
    const gc = document.getElementById('groupsContainer');
    if (gc) gc.innerHTML = '<div class="alert alert-danger">Konfiguration konnte nicht geladen werden.</div>';
  }
}

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
          <span class="fw-semibold fs-5">${escapeHtml(group.title)}</span>
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
            <div class="list-group-item pb-3 pt-3 d-flex justify-content-between align-items-center">
              <div class="d-flex flex-column">
                <span class="fw-medium ">${escapeHtml(s.label)}</span>
                <small class="text-secondary svc-url">${escapeHtml(s.url)}</small>
                ${s.warning ? `<small class="service-warning mt-1">
                  <i class="bi bi-exclamation-triangle-fill service-warning-icon"></i>${escapeHtml(s.warning)}
                </small>` : ""}

                <small class="svc-attr">
                    <div class="service-fields mt-1" id="fields-${group.key}-${s.key}"></div>
                    <div class="service-headers mt-1" id="headers-${group.key}-${s.key}"></div>
                </small>
              </div>
                <div class="d-flex align-items-center gap-3">
                  <small class="text-secondary d-none d-sm-inline" id="latency-${group.key}-${s.key}">– ms</small>

                  <!-- Status + Overlay-Counter -->
                  <span class="position-relative d-inline-block" id="statusWrap-${group.key}-${s.key}">
                    <span class="badge text-bg-secondary px-3" id="badge-${group.key}-${s.key}">N/A</span>
                    <span class="position-absolute top-0 start-100 translate-middle counter-badge d-none"
                        id="counter-${group.key}-${s.key}">0</span>
                  </span>
                </div>

            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

function renderAllGroups() {
  if (!groupsContainer) return;
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
        if ("regex" in expect)   pass = typeof v === "string" && new RegExp(expect.regex).test(v);

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

function setBadge(group, svc, ok, ms, count = null, value = null) {
  const badge   = document.getElementById(`badge-${group}-${svc}`);
  const bubble  = document.getElementById(`counter-${group}-${svc}`);
  const latency = document.getElementById(`latency-${group}-${svc}`);

  const service = GROUPS.find(gr => gr.key === group)?.services.find(s => s.key === svc);
  const hasWarning = !!service?.warning;

  // Status-Pill
  let cls = ok ? 'text-bg-success' : 'text-bg-danger';
  let text = ok ? 'OK' : 'NOK';
  if (hasWarning && ok) { cls = 'text-bg-warning text-dark'; }

  if (badge) {
    badge.className = `badge fs-6 px-3 ${cls}`;
    badge.textContent = text;
    if (hasWarning && service.warning) badge.title = service.warning;
  }
  if (latency) latency.textContent = `${ms} ms`;

  // Counter-Bubble (immer grau & rund)
  if (bubble) {
    if (count != null && !Number.isNaN(count)) {
      const n = Number(count);
      bubble.textContent = (Number.isFinite(n) && n > 99) ? '99+' : String(n);
      bubble.classList.remove('d-none');
      // keinerlei Farbumschaltung mehr – CSS hält es immer grau
    } else {
      bubble.classList.add('d-none');
    }
  }
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

// =======================
// SAFE COLLAPSE HELPERS (mit Fallback, kein Crash wenn bootstrap fehlt)
// =======================
function collapseApiAvailable() {
  return !!(window.bootstrap && window.bootstrap.Collapse);
}

function showCollapseById(id) {
  const el = document.getElementById(id);
  if (!el) return;
  if (collapseApiAvailable()) {
    window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
  } else {
    el.classList.add('show');
    el.style.height = 'auto';
    const header = document.querySelector(`[data-bs-target="#${id}"]`);
    if (header) header.setAttribute('aria-expanded', 'true');
  }
}

function hideCollapseById(id) {
  const el = document.getElementById(id);
  if (!el) return;
  if (collapseApiAvailable()) {
    window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
  } else {
    el.classList.remove('show');
    el.style.height = '';
    const header = document.querySelector(`[data-bs-target="#${id}"]`);
    if (header) header.setAttribute('aria-expanded', 'false');
  }
}

function setGroupStatus(groupKey, state /* 'ok' | 'warn' | 'nok' */) {
  const dot = document.getElementById(`${groupKey}-dot`);
  const summary = document.getElementById(`${groupKey}-summary`);

  if (dot) {
    if (state === 'nok') dot.className = 'status-dot bg-danger';
    else if (state === 'warn') dot.className = 'status-dot bg-warning';
    else dot.className = 'status-dot bg-success';
  }
  if (summary) {
    summary.textContent =
      state === 'nok' ? 'Eingeschränkter Betrieb'
      : state === 'warn' ? 'Alle Services OK (Warnungen vorhanden)'
      : 'Alle Services OK';
  }

  // Auto-Expand nur bei NOK – sicher & mit Fallback
  const collapseId = `collapse-${groupKey}`;
  const el = document.getElementById(collapseId);
  if (el && state === 'nok' && !el.classList.contains('show')) {
    showCollapseById(collapseId);
  }
}

function setOverall(ok) {
  const card = document.getElementById("overallCard");
  const icon = document.getElementById("overallIcon");
  const title = document.getElementById("overallTitle");
  if (card) card.style.setProperty("--status-color", ok ? "var(--bs-success)" : "var(--bs-danger)");
  if (icon) icon.className = `bi ${ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} fs-4`;
  if (title) title.textContent = ok ? "Alle Services online" : "Eingeschränkter Betrieb";
}

function updateTimestamp() {
  const el = document.getElementById("lastUpdated");
  if (el) el.textContent = new Date().toLocaleString();
}

function expandAllSections() {
  GROUPS.forEach(g => showCollapseById(`collapse-${g.key}`));
}
function collapseAllSections() {
  GROUPS.forEach(g => hideCollapseById(`collapse-${g.key}`));
}

// Globale Debug-Ablage
window.__healthResults = {};
window.inspectService = (groupKey, serviceKey) =>
  window.__healthResults?.[groupKey]?.[serviceKey] ?? null;

async function refreshAll() {
  const groupStates = await Promise.all(GROUPS.map(async (g) => {
    // Checks ausführen
    const results = await Promise.all(g.services.map(async (s) => {
      const r = await checkService(s.url, s.method, s.expect);
      setBadge(g.key, s.key, r.ok, r.ms, r.count, r.value);
      renderServiceFields(g.key, s, r.data);
      renderServiceHeaders(g.key, s, r.headers);

      window.__healthResults[g.key] ??= {};
      window.__healthResults[g.key][s.key] = r;

      return r.ok;
    }));

    const anyNok = results.some(ok => !ok);
    const anyWarn = g.services.some(s => !!s.warning);

    const state = anyNok ? 'nok' : (anyWarn ? 'warn' : 'ok');
    setGroupStatus(g.key, state);
    return state;
  }));

  const hasNok = groupStates.includes('nok');
  setOverall(!hasNok);
  updateTimestamp();
}

function showMaintenanceBanner() {
  const banner = document.getElementById("maintenanceBanner");
  const title = document.getElementById("maintenanceTitle");
  const msg = document.getElementById("maintenanceMessage");

  if (!MAINTENANCE.active) {
    if (banner) banner.classList.add("d-none");
    return;
  }

  const now = new Date();
  const start = MAINTENANCE.start ? new Date(MAINTENANCE.start) : null;
  const end = MAINTENANCE.end ? new Date(MAINTENANCE.end) : null;

  const isWithinWindow =
    (!start || now >= start) && (!end || now <= end);

  if (banner) {
    if (isWithinWindow) {
      if (title) title.textContent = (MAINTENANCE.title || 'Wartung') + ":";
      if (msg) msg.textContent = " " + (MAINTENANCE.message || "");
      banner.classList.remove("d-none");
    } else {
      banner.classList.add("d-none");
    }
  }
}

// =======================
// FORMATTERS
// =======================
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
    const mins = v /1000 / 60; // Jenkins: Dauer in ms -> Minuten
    return `${mins.toFixed(1)} min`;
  },
  seconds: v => (v == null || isNaN(v)) ? v : `${v} s`,
  bytesHeader: v => formatBytes(Number(v))
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

    const labelHtml = f.label ? `<small class="sf-label">${escapeHtml(f.label)}:</small>` : "";
    const valueHtml = badgeClass
      ? `<small class="badge ${badgeClass}">${escapeHtml(String(val))}</small>`
      : `<small class="sf-value">${escapeHtml(String(val))}</small>`;

    return `<small class="sf-item">${labelHtml} ${valueHtml}</small>`;
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
// OPTIONS (URLs zeigen/verstecken, Auto-Refresh) + Cookies
// =======================
const OPTIONS_COOKIE = 'statusOptions';
const OPTIONS_DEFAULT = {
  showUrls: true,
  autoRefresh: false,
  refreshInterval: 30, // Sekunden
  openOptionsOnLoad: false
};

function readOptions() {
  const m = document.cookie.match(new RegExp('(?:^|; )' + OPTIONS_COOKIE + '=([^;]*)'));
  if (!m) return { ...OPTIONS_DEFAULT };
  try {
    const parsed = JSON.parse(decodeURIComponent(m[1]));
    return { ...OPTIONS_DEFAULT, ...parsed };
  } catch {
    return { ...OPTIONS_DEFAULT };
  }
}

function saveOptions(opts) {
  const value = encodeURIComponent(JSON.stringify(opts));
  const days = 365;
  const expires = new Date(Date.now() + days*24*60*60*1000).toUTCString();
  document.cookie = `${OPTIONS_COOKIE}=${value}; Expires=${expires}; Path=/; SameSite=Lax`;
}

let OPTIONS = readOptions();
let autoTimer = null;

function applyOptions() {
  // Controls spiegeln (falls vorhanden)
  const show = document.getElementById('optShowUrls');
  const auto = document.getElementById('optAutoRefresh');
  const intv = document.getElementById('optRefreshInterval');
  const open = document.getElementById('optOpenOptionsOnLoad');
  const attr = document.getElementById('optShowAttr');
  
  if (show) show.checked = !!OPTIONS.showUrls;
  if (auto) auto.checked = !!OPTIONS.autoRefresh;
  if (intv) intv.value = String(OPTIONS.refreshInterval);
  if (open) open.checked = !!OPTIONS.openOptionsOnLoad;
  if (attr) attr.checked = !!OPTIONS.showAttr;
  
  // URLs ein-/ausblenden
  document.body.classList.toggle('hide-urls', !OPTIONS.showUrls);

  // URLs ein-/ausblenden
  document.body.classList.toggle('hide-attr', !OPTIONS.showAttr);
  
  // Auto-Refresh
  if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
  if (OPTIONS.autoRefresh) {
    const ms = Math.max(5, Number(OPTIONS.refreshInterval) || 30) * 1000;
    autoTimer = setInterval(refreshAll, ms);
  }

    // 🟡 Optionsblock öffnen/schließen bei Seitenstart
    if (OPTIONS.openOptionsOnLoad) {
      showCollapseById('optionsCollapse');
    } else {
      hideCollapseById('optionsCollapse');
    }

  
  saveOptions(OPTIONS);
}

// =======================
// EVENT BINDING (robust)
// =======================
function $(id) { return document.getElementById(id); }
function on(id, evt, handler) {
  const el = $(id);
  if (!el) {
    console.warn(`[status] control #${id} nicht gefunden – übersprungen`);
    return;
  }
  el.addEventListener(evt, handler);
}

function wireOptionsUI() {
  // Kopf-Buttons (falls vorhanden)
  on("refreshBtn", "click", refreshAll);
  on("expandAllBtn", "click", () => expandAllSections());
  on("collapseAllBtn", "click", () => collapseAllSections());

  // Options-Block (neue IDs, optional vorhanden)
  on("optShowUrls", "change", (e) => { OPTIONS.showUrls = !!e.target.checked; applyOptions(); });
  on("optShowAttr", "change", (e) => { OPTIONS.showAttr = !!e.target.checked; applyOptions(); });
  on("optAutoRefresh", "change", (e) => { OPTIONS.autoRefresh = !!e.target.checked; applyOptions(); });
  on("optRefreshInterval", "change", (e) => {
    OPTIONS.refreshInterval = parseInt(e.target.value, 10) || OPTIONS_DEFAULT.refreshInterval;
    applyOptions();
  });
  
  // Optionsblock beim Laden automatisch öffnen
  on("optOpenOptionsOnLoad", "change", (e) => {
    OPTIONS.openOptionsOnLoad = !!e.target.checked;
    applyOptions();
  });
}

// Starte erst, wenn der DOM bereit ist (verhindert addEventListener auf null)
document.addEventListener('DOMContentLoaded', () => {
  wireOptionsUI();
  bootstrap();
});
