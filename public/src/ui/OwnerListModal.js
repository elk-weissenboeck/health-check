// src/ui/OwnerListModal.js
export class OwnerListModal {
  constructor() {
    this.modalId = 'ownerListModal';
    this._owners = [];
    this._ensureModal();
    this._attachSearchHandler();
    this._attachKeywordToggleHandler();
  }

  _ensureModal() {
    if (document.getElementById(this.modalId)) return;

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
<div class="modal fade" id="${this.modalId}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Service Owner – Übersicht</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div id="${this.modalId}-loading" class="d-flex align-items-center gap-2">
          <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
          <span>Lade Service Owner…</span>
        </div>

        <div id="${this.modalId}-content" class="d-none">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="flex-grow-1 me-3">
                <input type="search"
                       id="${this.modalId}-search"
                       class="form-control form-control-sm"
                       placeholder="Nach Stichworten filtern (z.B. &quot;hybridforms&quot;, &quot;levatis&quot;)">
              </div>
              <div class="form-check ms-2">
                <input class="form-check-input"
                       type="checkbox"
                       id="${this.modalId}-toggle-keywords">
                <label class="form-check-label small" for="${this.modalId}-toggle-keywords">
                  Keywords anzeigen
                </label>
              </div>
            </div>
          <div id="${this.modalId}-list" class="list-group small"></div>
        </div>
      </div>
      <div class="modal-footer">
        <span class="me-auto text-secondary" id="${this.modalId}-status"></span>
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
      // einfacher Fallback ohne Bootstrap-JS
      el.classList.add('show');
      el.style.display = 'block';
    }
  }

  _setLoading(isLoading, message = '') {
    const loadingEl = document.getElementById(`${this.modalId}-loading`);
    const contentEl = document.getElementById(`${this.modalId}-content`);
    const statusEl = document.getElementById(`${this.modalId}-status`);
    if (loadingEl) loadingEl.classList.toggle('d-none', !isLoading);
    if (contentEl) contentEl.classList.toggle('d-none', isLoading);
    if (statusEl) statusEl.textContent = message || '';
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
      this._renderList(
        term
          ? this._owners.filter(o =>
              o.keywords.some(k => k.includes(term)) ||
              (o.details.name || '').toLowerCase().includes(term) ||
              o.upn.toLowerCase().includes(term)
            )
          : this._owners
      );
    });
  }

    _attachKeywordToggleHandler() {
      document.addEventListener('change', (ev) => {
        const target = ev.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (target.id !== `${this.modalId}-toggle-keywords`) return;

        const show = target.checked;
        const listEl = document.getElementById(`${this.modalId}-list`);
        if (!listEl) return;

        listEl.querySelectorAll('.owner-keywords').forEach(el => {
          el.classList.toggle('d-none', !show);
        });
      });
    }

  _collectOwnersFromGroups(groups) {
    const map = new Map();

    (groups || []).forEach(group => {
      (group.services || []).forEach(svc => {
        const upn = svc?.owner?.upn;
        if (!upn) return;

        const key = upn.toLowerCase();
        if (!map.has(key)) {
          map.set(key, {
            upn,
            services: [],
            keywords: new Set(),
            details: {},
            oof: null,
            source: ''
          });
        }
        const owner = map.get(key);

        // Service-Info für Anzeige
        owner.services.push({
          groupKey: group.key,
          groupTitle: group.title,
          serviceKey: svc.key,
          label: svc.label || svc.key || ''
        });

        // Keywords aus status.config.json
        (svc.keywords || []).forEach(k => {
          if (k) owner.keywords.add(String(k).toLowerCase());
        });
      });
    });

    return Array.from(map.values()).map(o => ({
      ...o,
      keywords: Array.from(o.keywords)
    }));
  }

  async _loadDetailsForOwners(owners) {
    // Details pro Owner über entra/oop.php?upn=<owner.upn> laden
    await Promise.all(owners.map(async (owner) => {
      try {
        if (!owner.upn) return;
        const res = await fetch(`entra/oop.php?upn=${encodeURIComponent(owner.upn)}`, { cache: 'no-store' });
        if (!res.ok) return;
        const api = await res.json(); // Struktur wie user_result.json

        const first = api?.users?.[0] || {};
        const u = first.user || {};
        const oof = first.oof || null;
        const source = first.source?.user || '';

        owner.details = {
          name: u.name || owner.upn,
          email: u.email || owner.upn,
          mobileExt: u.mobileExt || '',
          mobilePhone: u.mobilePhone || '',
          businessPhone: u.businessPhone || ''
        };
        owner.oof = oof;
        owner.source = source;
      } catch (e) {
        // Fehler bei einzelnen Ownern einfach ignorieren,
        // damit das Modal trotzdem geladen wird
      }
    }));
  }

  _formatOofPeriod(period) {
    try {
      if (!period) return '—';
      const start = period.start ? new Date(period.start) : null;
      const end = period.end ? new Date(period.end) : null;
      const fmt = d => d.toLocaleString();
      if (start && end) return `${fmt(start)} – ${fmt(end)}`;
      if (start) return `ab ${fmt(start)}`;
      if (end) return `bis ${fmt(end)}`;
    } catch (_) {}
    return '—';
  }

  _renderList(owners) {
    const listEl = document.getElementById(`${this.modalId}-list`);
    const statusEl = document.getElementById(`${this.modalId}-status`);
    if (!listEl) return;

    listEl.innerHTML = '';
    if (!owners || owners.length === 0) {
      listEl.innerHTML = '<div class="text-secondary">Keine Service Owner gefunden.</div>';
      if (statusEl) statusEl.textContent = '0 Treffer';
      return;
    }

    owners.sort((a, b) => {
      const an = (a.details.name || a.upn || '').toLowerCase();
      const bn = (b.details.name || b.upn || '').toLowerCase();
      return an.localeCompare(bn);
    });

    const esc = (s) => this._escapeHtml(s);

    owners.forEach(owner => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'list-group-item list-group-item-action text-start';

      const name = owner.details.name || owner.upn;
      const email = owner.details.email || owner.upn;
      const telExt = owner.details.mobileExt || '';
      const telPhone = owner.details.mobilePhone || '';
      const telBusiness = owner.details.businessPhone || '';
      const oof = owner.oof;
      const oofStatus = (oof?.status || '').toLowerCase();
      const oofPeriod = this._formatOofPeriod(oof?.period);
      const keywords = owner.keywords || [];
      const services = owner.services || [];

      let oofCls = 'text-bg-secondary';
      let oofText = 'Unbekannt';
      if (!oofStatus || oofStatus === 'disabled' || oofStatus === 'none') {
        oofCls = 'text-bg-success';
        oofText = 'verfügbar';
      } else if (oofStatus === 'scheduled') {
        oofCls = 'text-bg-warning text-dark';
        oofText = 'abwesend (geplant)';
      } else if (oofStatus === 'enabled' || oofStatus === 'always') {
        oofCls = 'text-bg-danger';
        oofText = 'abwesend';
      }

      const serviceLines = services.map(s =>
        `${esc(s.groupTitle || s.groupKey || '')} – ${esc(s.label || s.serviceKey || '')}`
      ).join('<br>');

      const keywordBadges = keywords.map(k =>
        `<span class="badge rounded-pill text-bg-light me-1 mb-1">${esc(k)}</span>`
      ).join('');


      const contactParts = [];
      if (email) {
        contactParts.push(`<a href="mailto:${encodeURIComponent(email)}">${esc(email)}</a>`);
      }

      // Reihenfolge: Dienstnummer → Mobil → Durchwahl
      if (telBusiness) {
        contactParts.push(`<a href="tel:${telBusiness}">${esc(telBusiness)}</a>`);
      }
      if (telPhone) {
        contactParts.push(`<a href="tel:${telPhone}">${esc(telPhone)}</a>`);
      }
      if (telExt && !telPhone && !telBusiness) {
        // Durchwahl nur separat anzeigen, wenn sonst keine Nummer da ist
        contactParts.push(`<a href="tel:${telExt}">${esc(telExt)}</a>`);
      }

    item.innerHTML = `
<div class="d-flex justify-content-between align-items-start">
  <div class="me-3">
    <div class="fw-semibold">${esc(name)}</div>
    <div class="small text-secondary">${esc(owner.upn)}</div>
    <div class="small mt-1">${contactParts.join(' · ')}</div>
    <div class="mt-2 owner-keywords">
      ${keywordBadges}
    </div>
    <div class="d-none mt-2 small text-secondary">
      ${serviceLines}
    </div>
  </div>
  <div class="text-end">
    <span class="badge ${oofCls}" title="Abwesenheitsstatus">${esc(oofText)}</span>
    <div class="small text-secondary mt-1">${esc(oofPeriod)}</div>
    ${owner.source ? `<div class="small text-secondary mt-1">Quelle: ${esc(owner.source)}</div>` : ''}
  </div>
</div>`;

      listEl.appendChild(item);
    });

    if (statusEl) statusEl.textContent = `${owners.length} Service Owner`;
    
    const toggle = document.getElementById(`${this.modalId}-toggle-keywords`);
    if (toggle) {
      const show = toggle.checked;
      listEl.querySelectorAll('.owner-keywords').forEach(el => {
        el.classList.toggle('d-none', !show);
      });
    }
  }

  /**
   * Öffnet das Modal und lädt die Daten.
   * groups ist das gleiche Array wie this.groups in App (Config aus status.config.json)
   */
  async open(groups) {
    this._setLoading(true, 'Lade Service Owner…');
    this._showModal();

    // Besitzer inkl. Keywords aus der Config sammeln
    const owners = this._collectOwnersFromGroups(groups);
    this._owners = owners;

    try {
      await this._loadDetailsForOwners(owners);
      this._setLoading(false, `${owners.length} Service Owner geladen`);
      this._renderList(owners);
      // Suchfeld zurücksetzen
      const search = document.getElementById(`${this.modalId}-search`);
      if (search) search.value = '';
    } catch (e) {
      console.error('[owner-list] Fehler beim Laden der Owner', e);
      this._setLoading(false, 'Fehler beim Laden der Service Owner');
      this._renderList([]);
    }
  }
}
