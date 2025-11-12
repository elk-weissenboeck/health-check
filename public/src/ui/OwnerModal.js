export class OwnerModal {
  constructor() {
    this.modalId = 'serviceOwnerModal';
    this.ensureModal();
  }

  ensureModal() {
    if (document.getElementById(this.modalId)) return;

    const html = `
<div class="modal fade" id="${this.modalId}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="${this.modalId}-title">Service Owner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-start gap-3">
          <div id="${this.modalId}-avatar" class="rounded-circle flex-shrink-0"
               style="width:48px;height:48px;background:#e9ecef;display:flex;align-items:center;justify-content:center;font-weight:600;"></div>
          <div class="w-100">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div><strong id="${this.modalId}-name">—</strong></div>
                <div><a href="#" id="${this.modalId}-email">—</a></div>
                <div class="text-secondary"><small id="${this.modalId}-upn">—</small></div>
              </div>
              <div class="text-end">
                <span class="badge text-bg-secondary" id="${this.modalId}-oof-badge" title="Abwesenheitsstatus">OOF</span>
                <div><small class="text-secondary" id="${this.modalId}-oof-period">—</small></div>
              </div>
            </div>
            <div class="mt-2">
              <small><a id="${this.modalId}-ext" href="#">—</a></small>
            </div>
            <div class="mt-2 text-secondary">
              <small id="${this.modalId}-source">Quelle: —</small>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <span class="me-auto text-secondary" id="${this.modalId}-status"></span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
        <a id="${this.modalId}-mailto" class="btn btn-primary d-none" href="#">E-Mail schreiben</a>
      </div>
    </div>
  </div>
</div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);
  }

  formatPeriod(startIso, endIso) {
    if (!startIso && !endIso) return '—';
    try {
      const start = startIso ? new Date(startIso) : null;
      const end = endIso ? new Date(endIso) : null;
      const fmt = (d) => d ? d.toLocaleString() : '';
      if (start && end) return `${fmt(start)} – ${fmt(end)}`;
      if (start) return `ab ${fmt(start)}`;
      if (end) return `bis ${fmt(end)}`;
    } catch (_) {}
    return '—';
  }

  setOofBadge(el, status) {
    const st = (status || '').toLowerCase();
    let cls = 'text-bg-secondary';
    let text = 'Unbekannt';
    if (st === 'disabled' || st === 'none' || st === '') { cls = 'text-bg-success'; text = 'verfügbar'; }
    else if (st === 'scheduled') { cls = 'text-bg-warning text-dark'; text = 'abwesend (geplant)'; }
    else if (st === 'enabled' || st === 'always') { cls = 'text-bg-danger'; text = 'abwesend'; }
    el.className = `badge ${cls}`;
    el.textContent = text;
  }

  initials(text) {
    return String(text || 'U').split(/\s+/).map(s => s[0]).slice(0,2).join('').toUpperCase();
  }

  _showModalSafely() {
    const modalEl = document.getElementById(this.modalId);
    const tryShow = () => {
      try {
        if (!window.bootstrap?.Modal) return;
        const inst = window.bootstrap.Modal.getOrCreateInstance(modalEl, {
          keyboard: true,
          backdrop: true,
          focus: true,
        });
        inst.show();
      } catch (_) { /* noop, Fallback unten */ }
    };
    // nach DOM-Update + im nächsten Tick versuchen
    queueMicrotask(tryShow);
    setTimeout(tryShow, 0);
  }

  async open(upn, serviceLabel) {
    const nameEl = document.getElementById(`${this.modalId}-name`);
    const mailEl = document.getElementById(`${this.modalId}-email`);
    const titleEl = document.getElementById(`${this.modalId}-title`);
    const avatarEl = document.getElementById(`${this.modalId}-avatar`);
    const mailtoBtn = document.getElementById(`${this.modalId}-mailto`);
    const upnEl = document.getElementById(`${this.modalId}-upn`);
    const statusEl = document.getElementById(`${this.modalId}-status`);
    const oofBadgeEl = document.getElementById(`${this.modalId}-oof-badge`);
    const oofPeriodEl = document.getElementById(`${this.modalId}-oof-period`);
    const extEl = document.getElementById(`${this.modalId}-ext`);
    const srcEl = document.getElementById(`${this.modalId}-source`);

    if (titleEl) titleEl.textContent = serviceLabel ? `Service Owner – ${serviceLabel}` : 'Service Owner';
    if (upnEl) upnEl.textContent = upn || '—';
    if (statusEl) statusEl.textContent = 'lädt…';
    if (avatarEl) { avatarEl.textContent = '…'; avatarEl.style.background = '#e9ecef'; }
    if (extEl) { extEl.textContent = '—'; extEl.removeAttribute('href'); }
    if (oofBadgeEl) this.setOofBadge(oofBadgeEl, null);
    if (oofPeriodEl) oofPeriodEl.textContent = '—';
    if (srcEl) srcEl.textContent = 'Quelle: —';

    // Modal robust öffnen (auch wenn Bootstrap minimal später ready ist)
    this._showModalSafely();

    let api = null;
    try {
      if (upn) {
        const res = await fetch(`entra/oop.php?nocache=1&upn=${encodeURIComponent(upn)}`, { cache: 'no-store' });
        if (res.ok) api = await res.json();
      }
    } catch (_) {}

    // JSON-Struktur: { users: [ { user:{name,email,mobileExt}, oof:{status,period{start,end}}, source:{user}, ... } ] }
    const first = api?.users?.[0] || null;
    const u = first?.user || null;
    const oof = first?.oof || null;
    const name = u?.name || upn || 'Unbekannt';
    const email = u?.email || '';
    const mobileExt = u?.mobileExt || '';
    const mobilePhone = u?.mobilePhone || '';
    const source = first?.source?.user || '';
    const oofStatus = oof?.status || null;
    const period = oof?.period || null;

    if (nameEl) nameEl.textContent = name;
    if (mailEl) {
      if (email) { mailEl.textContent = email; mailEl.href = `mailto:${email}`; }
      else { mailEl.textContent = '—'; mailEl.removeAttribute('href'); }
    }
    if (mailtoBtn) {
      mailtoBtn.href = email ? `mailto:${email}` : '#';
      mailtoBtn.classList.toggle('disabled', !email);
    }
    if (avatarEl) {
      avatarEl.textContent = this.initials(name);
      avatarEl.style.background = '#e9ecef';
    }
    if (oofBadgeEl) this.setOofBadge(oofBadgeEl, oofStatus);
    if (oofPeriodEl) oofPeriodEl.textContent = this.formatPeriod(period?.start, period?.end);
    if (extEl) {
      if (mobileExt) { extEl.textContent = mobileExt; extEl.href = `${mobileExt}`; }
      if (mobilePhone) { extEl.textContent = mobilePhone; extEl.href = `${mobilePhone}`; }
    }
    if (srcEl) srcEl.textContent = `Quelle: ${source || '—'}`;

    if (statusEl) statusEl.textContent = api ? '' : 'Details nicht verfügbar';

    // Fallback ohne Bootstrap (falls Modal nicht angezeigt wurde)
    if (!window.bootstrap?.Modal) {
      const lines = [name, email || upn || '', mobileExt ? `${mobileExt}` : '', source ? `Quelle: ${source}` : '']
        .filter(Boolean).join('\n');
      alert(lines);
    }
  }
}
