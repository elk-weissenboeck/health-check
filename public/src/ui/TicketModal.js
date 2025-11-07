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
        <style>
          #${this.modalId} table tbody tr:nth-child(even) { background: #f5f5f5; }
          #${this.modalId} .ticket-meta { opacity: .8; }
          #${this.modalId} .ticket-summary { text-decoration: none; }
          #${this.modalId} .ticket-summary:hover { text-decoration: underline; }
        </style>
        <div id="${this.modalId}-status" class="text-secondary mb-2"></div>
        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead>
              <tr>
                <th>Summary</th>
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

  // NEW: dd.mm.yyyy HH:mm
  formatDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d)) return '—';
    const p = (n) => String(n).padStart(2, '0');
    return `${p(d.getDate())}.${p(d.getMonth() + 1)}.${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`;
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
        tbody.innerHTML = `<tr><td class="text-secondary py-4">Keine Tickets gefunden.</td></tr>`;
      } else {
        tbody.innerHTML = list.map(item => {
          const id = item.id ?? '';
          const href = id ? `https://mantis.elkschrems.co.at/view.php?id=${encodeURIComponent(id)}` : '#';

          const summary = (item.summary ?? '').toString();
          const reporter = item.reporter?.real_name || item.reporter?.name || '';
          const status = item.status?.label || item.status?.name || '';
          const resolution = item.resolution?.label || item.resolution?.name || '';
          const created = this.formatDateTime(item.created_at); // NEW

          return `
            <tr>
              <td>
                <a class="ticket-summary" href="${href}" target="_blank" rel="noopener">
                  ${this._esc(summary)}
                </a>
                <div class="ticket-meta small mt-1">
                  <strong>Erstellt:</strong> ${this._esc(created)}
                  &nbsp;·&nbsp;
                  <strong>Reporter:</strong> ${this._esc(reporter) || '—'}
                  &nbsp;·&nbsp;
                  <strong>Status:</strong> ${this._esc(status) || '—'}
                  &nbsp;·&nbsp;
                  <strong>Resolution:</strong> ${this._esc(resolution) || '—'}
                </div>
              </td>
            </tr>`;
        }).join('');
      }
    }

    if (statusEl) statusEl.textContent = `Anzahl: ${list.length}`;
    if (!window.bootstrap?.Modal) {
      alert(`Tickets (${list.length})\n` + list.map(i => `${i.summary}`).join('\n'));
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
