<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AktSpeichern – Client Übersicht</title>

  <style>
    :root {
      --bg: #0b0f14;
      --card: #121824;
      --text: #e6edf3;
      --muted: #9fb0c0;
      --ok: #17c964;
      --warn: #f5a524;
      --bad: #f31260;
      --line: #233044;
      --mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      --sans: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
      --select-bg: #0f1520;
      --select-bg-hover: #111a28;
      --btn: #0f1520;
      --btn-hover: #172133;
      --btn-danger: #2a0f17;
      --btn-danger-hover: #3a1320;
    }
    body {
      margin: 0;
      font-family: var(--sans);
      background: var(--bg);
      color: var(--text);
    }
    header {
      padding: 18px 22px;
      border-bottom: 1px solid var(--line);
      display: flex;
      gap: 12px;
      align-items: center;
      justify-content: space-between;
      background: linear-gradient(180deg, #0b0f14, #0b0f14 70%, #0c1119);
      position: sticky;
      top: 0;
      z-index: 10;
    }
    header h1 {
      margin: 0;
      font-size: 18px;
      font-weight: 650;
      letter-spacing: .2px;
    }
    header .meta {
      font-size: 13px;
      color: var(--muted);
      display: flex;
      gap: 14px;
      align-items: center;
      white-space: nowrap;
    }
    .wrap { padding: 18px 22px; }

    .controls {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 12px;
    }
    .pill {
      background: var(--card);
      border: 1px solid var(--line);
      color: var(--text);
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 13px;
      display: inline-flex;
      gap: 6px;
      align-items: center;
    }

    .pill select {
      appearance: none;
      background-color: var(--select-bg);
      color: var(--text);
      border: 1px solid var(--line);
      padding: 4px 22px 4px 8px;
      border-radius: 999px;
      font-size: 13px;
      outline: none;
      cursor: pointer;
      line-height: 1.2;
    }
    .pill select:hover { background-color: var(--select-bg-hover); }
    .pill select option { background: var(--select-bg); color: var(--text); }

    .pill input {
      background: transparent;
      color: var(--text);
      border: none;
      outline: none;
      font-size: 13px;
      min-width: 220px;
    }

    .btn {
      background: var(--btn);
      border: 1px solid var(--line);
      color: var(--text);
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 13px;
      cursor: pointer;
      transition: .12s ease;
    }
    .btn:hover { background: var(--btn-hover); }
    .btn.danger { background: var(--btn-danger); }
    .btn.danger:hover { background: var(--btn-danger-hover); }

    .toast {
      margin-left: auto;
      font-size: 13px;
      color: var(--muted);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 12px;
    }
    thead th {
      text-align: left;
      font-size: 12px;
      color: var(--muted);
      font-weight: 600;
      padding: 10px 12px;
      border-bottom: 1px solid var(--line);
      background: #0f1520;
      position: sticky;
      top: 58px;
      z-index: 5;
    }
    tbody td {
      padding: 10px 12px;
      border-bottom: 1px solid var(--line);
      font-size: 14px;
      vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }

    .status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-weight: 650;
    }
    .dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    .dot.ok { background: var(--ok); }
    .dot.warn { background: var(--warn); }
    .dot.bad { background: var(--bad); }

    .mono { font-family: var(--mono); font-size: 13px; color: #c9d4df; }
    .muted { color: var(--muted); }
    .right { text-align: right; }
    .small { font-size: 12px; }

    .empty {
      padding: 30px;
      text-align: center;
      color: var(--muted);
      background: var(--card);
      border: 1px dashed var(--line);
      border-radius: 12px;
    }
  </style>
</head>
<body>

<header>
  <h1>AktSpeichern – Client Übersicht</h1>
  <div class="meta">
    <div>Auto-Refresh: <strong>10s</strong></div>
    <div>Letztes Update: <span id="lastUpdate">–</span></div>
  </div>
</header>

<div class="wrap">
  <div class="controls">
    <label class="pill">
      Filter:
      <select id="stateFilter">
        <option value="">Alle</option>
        <option value="running">Running</option>
        <option value="stopped">Stopped</option>
      </select>
    </label>

    <label class="pill">
      Suche:
      <input id="searchBox" type="text" placeholder="Machine/User/SessionId/Version">
    </label>

    <button class="btn danger" id="btnClearAll" title="Komplett leeren">
      DB komplett leeren
    </button>

    <button class="btn" id="btnClearStale" title="Stale-Einträge löschen">
      Stale löschen (älter 1h)
    </button>

    <div class="pill">Online: <span id="onlineCount">0</span></div>
    <div class="pill">Gesamt: <span id="totalCount">0</span></div>

    <div class="toast" id="toast"></div>
  </div>

  <div id="content">
    <div class="empty">Lade Daten…</div>
  </div>
</div>

<script>
  const API_URL = "/aktspeichern/api/presence";
  const API_DELETE_ALL = "/aktspeichern/api/presence";
  const API_DELETE_STALE = "/aktspeichern/api/presence/stale";
  const REFRESH_MS = 10_000;

  const stateFilterEl = document.getElementById("stateFilter");
  const searchBoxEl   = document.getElementById("searchBox");
  const toastEl       = document.getElementById("toast");

  const btnClearAll   = document.getElementById("btnClearAll");
  const btnClearStale = document.getElementById("btnClearStale");

  // ---------------- URL Param Handling ----------------
  const urlParams = new URLSearchParams(window.location.search);

  function readParamsIntoUI() {
    const state = urlParams.get("state") || "";
    const search = urlParams.get("search") || "";
    stateFilterEl.value = state;
    searchBoxEl.value = search;
  }

  function writeUIIntoUrl() {
    const state = stateFilterEl.value;
    const search = (searchBoxEl.value || "").trim();

    if (state) urlParams.set("state", state);
    else urlParams.delete("state");

    if (search) urlParams.set("search", search);
    else urlParams.delete("search");

    const newQs = urlParams.toString();
    const newUrl = newQs ? `${window.location.pathname}?${newQs}` : window.location.pathname;
    history.replaceState(null, "", newUrl);
  }

  function toast(msg, ok=true) {
    toastEl.textContent = msg;
    toastEl.style.color = ok ? "var(--muted)" : "#ffb3b3";
    setTimeout(() => { if (toastEl.textContent === msg) toastEl.textContent = ""; }, 4000);
  }

  readParamsIntoUI();

  // ---------------- Rendering ----------------
  function fmtDate(iso) {
    try { return new Date(iso).toLocaleString(); }
    catch { return iso; }
  }

  function statusBadge(row) {
    if (row.isOnline) return {text: "Online", cls: "ok"};
    if (row.state === "running") return {text: "Stale", cls: "warn"};
    return {text: "Stopped", cls: "bad"};
  }

  function renderTable(rows) {
    if (!rows || rows.length === 0) {
      document.getElementById("content").innerHTML =
        `<div class="empty">Keine Einträge vorhanden.</div>`;
      document.getElementById("onlineCount").textContent = "0";
      document.getElementById("totalCount").textContent = "0";
      return;
    }

    const online = rows.filter(r => r.isOnline).length;
    document.getElementById("onlineCount").textContent = online;
    document.getElementById("totalCount").textContent = rows.length;

    let html = `
      <table>
        <thead>
          <tr>
            <th>Status</th>
            <th>Machine</th>
            <th>User</th>
            <th>Version</th>
            <th class="right">PID</th>
            <th>SessionId</th>
            <th>LastSeen (UTC)</th>
            <th class="right">Age (s)</th>
          </tr>
        </thead>
        <tbody>
    `;

    for (const r of rows) {
      const st = statusBadge(r);
      html += `
        <tr>
          <td>
            <span class="status">
              <span class="dot ${st.cls}"></span>
              ${st.text}
            </span>
            <div class="small muted">state: ${r.state}</div>
          </td>
          <td>${r.machine}</td>
          <td>${r.user}</td>
          <td class="mono">${r.version || "-"}</td>
          <td class="right mono">${r.pid ?? "-"}</td>
          <td class="mono">${r.sessionId}</td>
          <td>${fmtDate(r.lastSeenUtc)}</td>
          <td class="right mono">${r.ageSeconds ?? "-"}</td>
        </tr>
      `;
    }

    html += `</tbody></table>`;
    document.getElementById("content").innerHTML = html;
  }

  function applyClientFilters(rows) {
    const search = (searchBoxEl.value || "").trim().toLowerCase();
    if (!search) return rows;

    return rows.filter(r => {
      const hay = [r.machine, r.user, r.sessionId, r.version, String(r.pid ?? "")]
        .join(" ").toLowerCase();
      return hay.includes(search);
    });
  }

  async function fetchPresence() {
    try {
      writeUIIntoUrl();

      const state = stateFilterEl.value;
      const url = state ? `${API_URL}?state=${encodeURIComponent(state)}` : API_URL;

      const res = await fetch(url, {cache: "no-store"});
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const data = await res.json();
      let rows = applyClientFilters(data.presence || []);

      renderTable(rows);
      document.getElementById("lastUpdate").textContent = new Date().toLocaleTimeString();
    } catch (e) {
      document.getElementById("content").innerHTML =
        `<div class="empty">Fehler beim Laden: ${e.message}</div>`;
    }
  }

  // ---------------- Delete actions ----------------
  btnClearAll.addEventListener("click", async () => {
    if (!confirm("Wirklich ALLE Presence-Einträge löschen?")) return;

    try {
      const res = await fetch(API_DELETE_ALL, { method: "DELETE" });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || res.status);

      toast(`DB geleert. Gelöschte Zeilen: ${data.deletedRows ?? 0}`);
      fetchPresence();
    } catch (e) {
      toast(`Fehler beim Leeren: ${e.message}`, false);
    }
  });

  btnClearStale.addEventListener("click", async () => {
    const ageSeconds = 3600; // 1h default wie Backend
    if (!confirm(`Stale Einträge löschen (älter als ${ageSeconds/60} Minuten)?`)) return;

    try {
      const res = await fetch(`${API_DELETE_STALE}?ageSeconds=${ageSeconds}`, {
        method: "DELETE"
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || res.status);

      toast(`Stale gelöscht. Gelöschte Zeilen: ${data.deletedRows ?? 0}`);
      fetchPresence();
    } catch (e) {
      toast(`Fehler beim Stale-Löschen: ${e.message}`, false);
    }
  });

  // initial load + refresh loop
  fetchPresence();
  setInterval(fetchPresence, REFRESH_MS);

  stateFilterEl.addEventListener("change", fetchPresence);
  let searchDebounce;
  searchBoxEl.addEventListener("input", () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(fetchPresence, 200);
  });
</script>

</body>
</html>
