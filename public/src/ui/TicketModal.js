export class TicketModal {
  constructor() {
    this.modalId = 'ticketsModal';
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
        <div id="${this.modalId}-status" class="text-secondary mb-2"></div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th style="width:90px">Id</th>
                <th>Summary</th>
                <th style="width:220px">Reporter</th>
                <th style="width:140px">Status</th>
                <th style="width:140px">Resolution</th>
              </tr>
            </thead>
            <tbody id="${this.modalId}-tbody"></tbody>
          </table>
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

  open(issues, titleSuffix = '') {
    const titleEl = document.getElementById(`${this.modalId}-title`);
    const statusEl = document.getElementById(`${this.modalId}-status`);
    const footerEl = document.getElementById(`${this.modalId}-footerStatus`);
    const tbody = document.getElementById(`${this.modalId}-tbody`);

    if (titleEl) titleEl.textContent = titleSuffix ? `Tickets – ${titleSuffix}` : 'Tickets';
    if (statusEl) statusEl.textContent = 'Lade Ergebnisse…';
    if (footerEl) footerEl.textContent = '';
    if (tbody) tbody.innerHTML = '';

    this._showModalSafely();

    const list = Array.isArray(issues) ? issues : [];
    if (tbody) {
      if (list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="text-secondary py-4">Keine Tickets gefunden.</td></tr>`;
      } else {
        tbody.innerHTML = list.map(item => {
          const id = item.id ?? '';
          const summary = (item.summary ?? '').toString();
          const reporter = item.reporter?.real_name || item.reporter?.name || '';
          const status = item.status?.label || item.status?.name || '';
          const resolution = item.resolution?.label || item.resolution?.name || '';
          return `
            <tr>
              <td><code>${id}</code></td>
              <td>${this._esc(summary)}</td>
              <td>${this._esc(reporter)}</td>
              <td>${this._esc(status)}</td>
              <td>${this._esc(resolution)}</td>
            </tr>`;
        }).join('');
      }
    }
    if (statusEl) statusEl.textContent = `Anzahl: ${list.length}`;
    if (!window.bootstrap?.Modal) {
      // Minimaler Fallback
      alert(`Tickets (${list.length})\n` + list.map(i => `#${i.id} ${i.summary}`).join('\n'));
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
