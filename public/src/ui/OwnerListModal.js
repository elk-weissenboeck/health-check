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
        <h5 class="modal-title">ServiceOwner – Übersicht</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th style="width:40%">Service</th>
                <th style="width:30%">Gruppe</th>
                <th style="width:30%">Owner (UPN)</th>
              </tr>
            </thead>
            <tbody id="${this.modalId}-tbody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <span class="me-auto text-secondary" id="${this.modalId}-count"></span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);
  }

  _showModal() {
    const el = document.getElementById(this.modalId);
    const tryShow = () => {
      try {
        if (!window.bootstrap?.Modal) return;
        const inst = window.bootstrap.Modal.getOrCreateInstance(el, { keyboard: true });
        inst.show();
      } catch {}
    };
    queueMicrotask(tryShow);
    setTimeout(tryShow, 0);
  }

  open(rows, onClickOwner) {
    // rows: [{group, service, label, upn}]
    const tbody = document.getElementById(`${this.modalId}-tbody`);
    const cnt = document.getElementById(`${this.modalId}-count`);
    if (tbody) {
      tbody.innerHTML = rows.length
        ? rows.map(r => `
            <tr>
              <td>${this._esc(r.label || r.service || '')}</td>
              <td>${this._esc(r.group || '')}</td>
              <td>
                ${r.upn ? `<a href="#" data-upn="${this._esc(r.upn)}" class="owner-link">${this._esc(r.upn)}</a>` : '<span class="text-secondary">—</span>'}
              </td>
            </tr>
          `).join('')
        : `<tr><td colspan="3" class="text-secondary py-4">Keine Owner hinterlegt.</td></tr>`;
    }
    if (cnt) cnt.textContent = `Einträge: ${rows.length}`;

    // Delegate clicks on owner links to open the single Owner modal:
    if (tbody) {
      tbody.onclick = (ev) => {
        const a = ev.target.closest('.owner-link');
        if (!a) return;
        ev.preventDefault();
        const upn = a.getAttribute('data-upn');
        if (onClickOwner) onClickOwner(upn);
      };
    }

    this._showModal();
  }

  _esc(s) {
    return String(s)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }
}
