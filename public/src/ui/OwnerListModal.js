export class OwnerListModal {
  constructor() {
    this.modalId = 'ownerListModal';
    this.ensureModal();
  }

  ensureModal() {
    if (document.getElementById(this.modalId)) return;

    const html = `
<div class="modal fade" id="${this.modalId}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Service Owner – Übersicht</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="text-secondary" id="${this.modalId}-status">Lade…</div>
          <div class="input-group input-group-sm" style="max-width: 320px">
            <span class="input-group-text">Services filtern</span>
            <input type="text" class="form-control" id="${this.modalId}-search" placeholder="Suchtext…">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm table-bordered table-striped align-middle mb-0">
            <thead>
              <tr>
                <th style="width:32%">Owner</th>
                <th>Services</th>
              </tr>
            </thead>
            <tbody id="${this.modalId}-tbody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <span class="me-auto text-secondary" id="${this.modalId}-footer"></span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);

    // Suche: Live-Filter auf Services-Spalte
    const search = document.getElementById(`${this.modalId}-search`);
    const tbody  = document.getElementById(`${this.modalId}-tbody`);
    search?.addEventListener('input', () => {
      const q = (search.value || '').trim().toLowerCase();
      const rows = tbody?.querySelectorAll('tr') || [];
      rows.forEach(tr => {
        const svc = tr.querySelector('[data-col="services"]')?.textContent?.toLowerCase() || '';
        tr.style.display = !q || svc.includes(q) ? '' : 'none';
      });
    });
  }

  _show() {
    const el = document.getElementById(this.modalId);
    const show = () => {
      try {
        if (!window.bootstrap?.Modal) return;
        window.bootstrap.Modal.getOrCreateInstance(el, { keyboard: true }).show();
      } catch {}
    };
    queueMicrotask(show); setTimeout(show, 0);
  }

  _esc(s) {
    return String(s ?? '')
      .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
      .replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }
  
  render(rows){
    const tbody = document.getElementById(`${this.modalId}-tbody`);
    const status= document.getElementById(`${this.modalId}-status`);
    const footer= document.getElementById(`${this.modalId}-footer`);
    if (tbody) tbody.innerHTML = rows.map(r => {
      // Owner-Zelle: Name (ohne ServiceKey), darunter UPN/E-Mail in <code>, darunter Durchwahl (kein tel:)
      const parts = [];
      if (r.name) parts.push(`<div><strong>${this._esc(r.name)}</strong></div>`);
      const codes = [];
      if (r.upn)   codes.push(`<code>${this._esc(r.upn)}</code>`);
      if (r.email) codes.push(`<code>${this._esc(r.email)}</code>`);
      if (codes.length) parts.push(`<div class="text-secondary small d-flex gap-2 flex-wrap">${codes.join('')}</div>`);
      if (r.durchwahl) parts.push(`<div class="small">Durchwahl: ${this._esc(r.durchwahl)}</div>`);

      const ownerCell = parts.join('');

      // Services als Liste / Chips
      const servicesHtml = (r.services || [])
        .map(s => `<span class="badge rounded-pill text-bg-light border me-1 mb-1">${this._esc(s)}</span>`)
        .join('');

      return `<tr>
        <td data-col="owner">${ownerCell}</td>
        <td data-col="services">${servicesHtml}</td>
      </tr>`;
    }).join('');

    if (status) status.textContent = `Einträge: ${rows.length}`;
    if (footer) footer.textContent = '';
    this._show();
  }
}
