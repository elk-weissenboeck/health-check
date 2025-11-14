// src/ui/ServiceListModal.js
export class ServiceListModal {
  constructor() {
    this.modalId = 'serviceListModal';
    this._services = [];
    this._ensureModal();
    this._attachSearchHandler();
  }

  _ensureModal() {
    if (document.getElementById(this.modalId)) return;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
<div class="modal fade" id="${this.modalId}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Alle Services</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="flex-grow-1 me-3">
            <input type="search"
                   id="${this.modalId}-search"
                   class="form-control"
                   placeholder="Nach Keywords filtern (z.B. &quot;hybridforms&quot;, &quot;lieferant&quot;)">
          </div>
        </div>

        <div id="${this.modalId}-list" class="small"></div>
      </div>
      <div class="modal-footer justify-content-between">
        <span id="${this.modalId}-status" class="text-secondary small"></span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>`;
    document.body.appendChild(wrapper.firstElementChild);
  }

  _getModal() {
    return document.getElementById(this.modalId);
  }

  _showModal() {
    const el = this._getModal();
    if (!el) return;

    if (window.bootstrap?.Modal) {
      if (!this._bsModal) {
        this._bsModal = new window.bootstrap.Modal(el);
      }
      this._bsModal.show();
    } else {
      el.classList.add('show');
      el.style.display = 'block';
    }
  }

  _escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  _attachSearchHandler() {
    document.addEventListener('input', (ev) => {
      const target = ev.target;
      if (!(target instanceof HTMLInputElement)) return;
      if (target.id !== `${this.modalId}-search`) return;

      const term = target.value.trim().toLowerCase();
      const filtered = term
        ? this._services.filter(svc =>
            svc.keywords.some(k => k.includes(term))
          )
        : this._services;

      this._renderList(filtered);
    });
  }

  _collectServicesFromGroups(groups) {
    const result = [];

    (groups || []).forEach(group => {
      const groupKey   = group.key;
      const groupTitle = group.title || groupKey || '';

      (group.services || []).forEach(service => {
        const ownerUpn = service?.owner?.upn || '';
        const label    = service.label || service.key || '';
        const key      = service.key || '';
        const keywords = (service.keywords || [])
          .filter(Boolean)
          .map(k => String(k).toLowerCase());
        // Warnungen: String oder Array unterstützen
        const warnings = [];
        if (Array.isArray(service.warnings)) {
          service.warnings.forEach(w => { if (w) warnings.push(String(w)); });
        } else if (service.warning) {
          warnings.push(String(service.warning));
        }

        result.push({
          groupKey,
          groupTitle,
          key,
          label,
          ownerUpn,
          keywords,
          warnings
        });
      });
    });

    return result;
  }

    _renderList(services) {
      const listEl = document.getElementById(`${this.modalId}-list`);
      const statusEl = document.getElementById(`${this.modalId}-status`);
      if (!listEl) return;

      listEl.innerHTML = '';

      if (!services || services.length === 0) {
        listEl.innerHTML = '<div class="text-secondary">Keine Services gefunden.</div>';
        if (statusEl) statusEl.textContent = '0 Services';
        return;
      }

      // Sortierung: zuerst nach ServiceGroup-Titel, dann nach Servicelabel
      services.sort((a, b) => {
        const ga = (a.groupTitle || a.groupKey || '').toLowerCase();
        const gb = (b.groupTitle || b.groupKey || '').toLowerCase();
        if (ga !== gb) return ga.localeCompare(gb);
        const la = (a.label || '').toLowerCase();
        const lb = (b.label || '').toLowerCase();
        return la.localeCompare(lb);
      });

      const esc = (s) => this._escapeHtml(s);

      // Tabellen-Grundgerüst
      const tableHtml = `
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th style="width: 25%;">Gruppe</th>
          <th style="width: 35%;">Service</th>
          <th style="width: 25%;">Owner</th>
          <th style="width: 15%;">Hinweise</th>
        </tr>
      </thead>
      <tbody id="${this.modalId}-tbody"></tbody>
    </table>`;

      listEl.innerHTML = tableHtml;

      const tbody = document.getElementById(`${this.modalId}-tbody`);
      if (!tbody) return;

      services.forEach(service => {
        const ownerMailHtml = service.ownerUpn
          ? `<a href="mailto:${encodeURIComponent(service.ownerUpn)}">${esc(service.ownerUpn)}</a>`
          : '<span class="text-muted">kein Owner definiert</span>';

        let warningHtml = '';
        if (service.warnings && service.warnings.length > 0) {
          const text = service.warnings.join(' • ');
          warningHtml = `
            <div class="alert alert-warning py-1 px-2 small mb-0">
              ${esc(text)}
            </div>`;
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${esc(service.groupTitle || service.groupKey || '')}</td>
          <td>${esc(service.label || '')}</td>
          <td class="small">${ownerMailHtml}</td>
          <td>${warningHtml}</td>
        `;

        tbody.appendChild(tr);
      });

      if (statusEl) statusEl.textContent = `${services.length} Services`;
    }



  /**
   * Öffnet das Modal und rendert die Liste.
   * groups = this.groups (Config aus status.config.json)
   */
  open(groups) {
    this._showModal();

    const services = this._collectServicesFromGroups(groups);
    this._services = services;

    // Suchfeld zurücksetzen
    const search = document.getElementById(`${this.modalId}-search`);
    if (search) search.value = '';

    this._renderList(services);
  }
}
