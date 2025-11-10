export class TicketModal {
  constructor() {
    this.modalId = 'ticketsModal';
    this._loaded = { mantis: false, glpi: false };
    this.ensureModal();
  }

  ensureModal() {
    if (document.getElementById(this.modalId)) return;

    const html = `
<div class="modal fade" id="${this.modalId}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="${this.modalId}-title">Tickets</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <style>
          #${this.modalId} table tbody tr:nth-child(even) { background: #f5f5f5; }
          #${this.modalId} .ticket-meta { opacity: .85; }
          #${this.modalId} .ticket-summary { text-decoration: none; }
          #${this.modalId} .ticket-summary:hover { text-decoration: underline; }
        </style>

        <ul class="nav nav-tabs" id="${this.modalId}-tabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="${this.modalId}-tab-mantis" data-bs-toggle="tab" data-bs-target="#${this.modalId}-pane-mantis" type="button" role="tab" aria-controls="${this.modalId}-pane-mantis" aria-selected="true">Mantis</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="${this.modalId}-tab-glpi" data-bs-toggle="tab" data-bs-target="#${this.modalId}-pane-glpi" type="button" role="tab" aria-controls="${this.modalId}-pane-glpi" aria-selected="false">GLPI</button>
          </li>
        </ul>

        <div class="tab-content pt-3">
          <!-- Mantis -->
          <div class="tab-pane fade" id="${this.modalId}-pane-mantis" role="tabpanel" aria-labelledby="${this.modalId}-tab-mantis">
            <div id="${this.modalId}-status-mantis" class="text-secondary mb-2"></div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Summary</th></tr></thead>
                <tbody id="${this.modalId}-tbody-mantis"></tbody>
              </table>
            </div>
          </div>

          <!-- GLPI -->
          <div class="tab-pane fade" id="${this.modalId}-pane-glpi" role="tabpanel" aria-labelledby="${this.modalId}-tab-glpi">
            <div id="${this.modalId}-status-glpi" class="text-secondary mb-2"></div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Summary</th></tr></thead>
                <tbody id="${this.modalId}-tbody-glpi"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <span class="me-auto text-secondary" id="${this.modalId}-footerStatus"></span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);

    // Tabs: Lazy-Load beim Aktivieren
    const tabsEl = document.getElementById(`${this.modalId}-tabs`);
    tabsEl?.addEventListener('shown.bs.tab', (ev) => {
      const id = ev.target?.id || '';
      if (id.endsWith('-tab-mantis') && !this._loaded.mantis && this._urls?.mantis) {
        this._fetchAndRender('mantis', this._urls.mantis);
      }
      if (id.endsWith('-tab-glpi') && !this._loaded.glpi && this._urls?.glpi) {
        this._fetchAndRender('glpi', this._urls.glpi);
      }
    });
  }

  _showModalSafely() {
    const el = document.getElementById(this.modalId);
    const tryShow = () => {
      try {
        if (!window.bootstrap?.Modal) return;
        const inst = window.bootstrap.Modal.getOrCreateInstance(el, { keyboard: true, backdrop: true, focus: true });
        inst.show();
      } catch {}
    };
    queueMicrotask(tryShow);
    setTimeout(tryShow, 0);
  }

  formatDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d)) return '—';
    const p = (n) => String(n).padStart(2, '0');
    return `${p(d.getDate())}.${p(d.getMonth() + 1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
  }

  _statusEl(sys)      { return document.getElementById(`${this.modalId}-status-${sys}`); }
  _tbodyEl(sys)       { return document.getElementById(`${this.modalId}-tbody-${sys}`); }
  _setCount(sys, n)   { const f = document.getElementById(`${this.modalId}-footerStatus`); if (f) f.textContent = `Anzahl (${sys}): ${n}`; }

  _linkFor(system, item) {
    // Mantis: Anforderung – link = https://example.com?view.php?id=<id>
    if (system === 'mantis') {
      const id = item?.id ?? '';
      return id ? `https://mantis.elkschrems.co.at/view.php?id=${encodeURIComponent(id)}` : '#';
    }
    // GLPI: wenn im Datensatz eine URL existiert, nutzen; sonst kein Link
    if (system === 'glpi') {
      return item?.url || '#';
    }
    return '#';
  }

  _render(system, issues) {
    const tbody = this._tbodyEl(system);
    const statusEl = this._statusEl(system);
    const list = Array.isArray(issues) ? issues : [];

    if (!tbody) return;
    if (list.length === 0) {
      tbody.innerHTML = `<tr><td class="text-secondary py-4">Keine Tickets gefunden.</td></tr>`;
    } else {
      tbody.innerHTML = list.map(item => {
        const href = this._linkFor(system, item);
        const summary = (item.summary ?? '').toString();
        const reporter = item.reporter?.real_name || item.reporter?.name || '';
        const status = item.status?.label || item.status?.name || '';
        const resolution = item.resolution?.label || item.resolution?.name || '';
        const created = this.formatDateTime(item.created_at);

        return `
          <tr>
            <td>
              <a class="ticket-summary" href="${href}" target="_blank" rel="noopener">
                ${this._esc(summary)}
              </a>
              <div class="ticket-meta small mt-1">
                <strong>Reporter:</strong> ${this._esc(reporter) || '—'}
                &nbsp;·&nbsp;
                <strong>Status:</strong> ${this._esc(status) || '—'}
                &nbsp;·&nbsp;
                <strong>Resolution:</strong> ${this._esc(resolution) || '—'}
                &nbsp;·&nbsp;
                <strong>Erstellt:</strong> ${this._esc(created)}
              </div>
            </td>
          </tr>`;
      }).join('');
    }

    if (statusEl) statusEl.textContent = `Anzahl: ${list.length}`;
    this._setCount(system, list.length);

    if (!window.bootstrap?.Modal) {
      alert(`${system.toUpperCase()} Tickets (${list.length})\n` + list.map(i => `${i.summary}`).join('\n'));
    }
  }

  async _fetchAndRender(system, url) {
    const statusEl = this._statusEl(system);
    const tbody = this._tbodyEl(system);
    if (statusEl) statusEl.textContent = 'Lade Ergebnisse…';
    if (tbody) tbody.innerHTML = '';

    let data = null;
    try {
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      data = await res.json();
    } catch (e) {
      if (statusEl) statusEl.textContent = 'Fehler beim Laden';
      this._render(system, []);
      return;
    }

    const issues = Array.isArray(data?.issues) ? data.issues : [];
    this._render(system, issues);
    this._loaded[system] = true;
  }

  open(urls, titleSuffix = '') {
    // urls = { mantis: string|null, glpi: string|null }
    this._urls = urls || {};
    this._loaded = { mantis: false, glpi: false };

    const titleEl = document.getElementById(`${this.modalId}-title`);
    if (titleEl) titleEl.textContent = titleSuffix ? `Tickets – ${titleSuffix}` : 'Tickets';

    // Tabs aktivieren/ausblenden je nach Verfügbarkeit
    const tabM = document.getElementById(`${this.modalId}-tab-mantis`);
    const tabG = document.getElementById(`${this.modalId}-tab-glpi`);
    const paneM = document.getElementById(`${this.modalId}-pane-mantis`);
    const paneG = document.getElementById(`${this.modalId}-pane-glpi`);

    // visuell deaktivieren, wenn URL fehlt
    const hasM = !!this._urls.mantis;
    const hasG = !!this._urls.glpi;
    tabM.parentElement.style.display = hasM ? '' : 'none';
    tabG.parentElement.style.display = hasG ? '' : 'none';
    paneM.style.display = hasM ? '' : 'none';
    paneG.style.display = hasG ? '' : 'none';

    // Modal öffnen
    this._showModalSafely();

    // Standard-Tab wählen & laden
    const preferred = hasM ? 'mantis' : (hasG ? 'glpi' : null);
    if (preferred === 'mantis') {
      tabM.classList.add('active'); paneM.classList.add('show','active');
      this._fetchAndRender('mantis', this._urls.mantis);
    } else if (preferred === 'glpi') {
      tabG.classList.add('active'); paneG.classList.add('show','active');
      this._fetchAndRender('glpi', this._urls.glpi);
    } else {
      // keine URLs -> Leermeldung
      const f = document.getElementById(`${this.modalId}-footerStatus`);
      if (f) f.textContent = 'Keine Endpunkte konfiguriert';
    }
  }

  _esc(s) {
    return String(s)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
}
