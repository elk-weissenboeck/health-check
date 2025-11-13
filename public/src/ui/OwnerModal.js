export class OwnerModal {
  constructor() {
    this.modalId = 'serviceOwnerModal';
    this.ensureModal();
  }

  ensureModal() {
    if (document.getElementById(this.modalId)) return;

    const html = `
<div class="modal fade" id="${this.modalId}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="${this.modalId}-title">Service Owner</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body owner-modal">
        <div class="d-flex align-items-start">
          <!-- Avatar links -->
          <div id="${this.modalId}-avatar" class="owner-avatar me-3"></div>

          <!-- Inhalt rechts -->
          <div class="flex-grow-1">
            <!-- Kopfbereich: Name + UPN -->
            <div class="owner-header mb-2">
              <div id="${this.modalId}-name" class="owner-name fw-semibold"></div>
              <div id="${this.modalId}-upn" class="owner-upn text-muted small"></div>
            </div>

            <!-- Kontakt -->
            <div class="owner-contact mb-2">
              <a id="${this.modalId}-email"
                 class="owner-email d-block text-decoration-none mb-1"></a>

              <div id="${this.modalId}-email-alt"
                   class="owner-email-alt small text-muted"></div>

              <!-- NEU: mehrere Telefonnummern -->
              <div id="${this.modalId}-phones"
                   class="owner-phones d-flex flex-column gap-1 mt-2"></div>
            </div>

            <!-- Abwesenheit ganz unten im Body -->
            <div class="owner-oof-row mt-3 pt-2 border-top">
              <div class="small text-muted mb-1">Abwesenheit</div>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span id="${this.modalId}-oof-badge" class="badge"></span>
                <span id="${this.modalId}-oof-period" class="small text-muted"></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal-footer justify-content-between align-items-center">
        <span class="owner-source small text-muted">
          Quelle: <span id="${this.modalId}-source"></span>
        </span>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Schließen
        </button>
      </div>
    </div>
  </div>
</div>`;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap.firstElementChild);
  }

  formatPeriod(startIso, endIso) {
    if (!startIso && !endIso) return '';
    try {
      const start = startIso ? new Date(startIso) : null;
      const end = endIso ? new Date(endIso) : null;
      const fmt = (d) => d ? d.toLocaleString() : '';
      if (start && end) return `${fmt(start)} – ${fmt(end)}`;
      if (start) return `ab ${fmt(start)}`;
      if (end) return `bis ${fmt(end)}`;
    } catch (_) {}
    return '';
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

  async updateAvatar(avatarEl, upn, name) {
    if (!avatarEl) return;

    // Fallback: erstmal Initialen anzeigen
    avatarEl.innerHTML = '';
    const initialsSpan = document.createElement('span');
    initialsSpan.textContent = this.initials(name);
    avatarEl.appendChild(initialsSpan);
    avatarEl.style.background = '#e9ecef';

    if (!upn) return;

    try {
      const res = await fetch(`entra/photo.php?id=${encodeURIComponent(upn)}`, { cache: 'no-store' });

      // Bei 404 oder anderem Fehler: einfach Initialen lassen
      if (!res.ok) return;

      const ct = res.headers.get('Content-Type') || '';
      if (!ct.toLowerCase().startsWith('image/')) {
        return; // irgendwas Merkwürdiges → Initialen behalten
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);

      const img = document.createElement('img');
      img.src = url;
      img.alt = this.initials(name);
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      img.style.borderRadius = '50%';

      // Wenn Bild da ist → Initialen ersetzen
      avatarEl.innerHTML = '';
      avatarEl.appendChild(img);

      img.addEventListener('error', () => {
        // Wenn Bild nicht darstellbar ist → wieder Initialen anzeigen
        avatarEl.innerHTML = '';
        avatarEl.appendChild(initialsSpan);
        URL.revokeObjectURL(url);
      });
    } catch (_) {
      // Netzwerkfehler o.ä. → Initialen bleiben
    }
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
    
    const telExt      = u.mobileExt      || '';
    const telMobile   = u.mobilePhone    || '';
    const telBusiness = u.businessPhone  || '';
    
    const source = first?.source?.user || '';
    const oofStatus = oof?.status || null;
    const period = oof?.period || null;

    const phonesEl = document.getElementById(`${this.modalId}-phones`);
    if (phonesEl) {
      phonesEl.innerHTML = '';

      const addPhone = (number, label) => {
        if (!number) return;
        const a = document.createElement('a');
        const telHref = number.replace(/\s+/g, '');
        a.href = `tel:${telHref}`;
        a.className = 'owner-phone d-inline-flex align-items-baseline text-decoration-none';
        a.innerHTML = `
          <span>${number}</span>
          <span class="phone-label">(${label})</span>
        `;
        phonesEl.appendChild(a);
      };

      // Reihenfolge: Geschäft → Mobil → Durchwahl
      addPhone(telBusiness, 'Geschäft');
      addPhone(telMobile, 'Mobil');
      addPhone(telExt, 'Extern');

      if (!phonesEl.children.length) {
        phonesEl.classList.add('d-none');
      } else {
        phonesEl.classList.remove('d-none');
      }
    }


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
      this.updateAvatar(avatarEl, upn, name);
    }
    if (oofBadgeEl) this.setOofBadge(oofBadgeEl, oofStatus);
    if (oofPeriodEl) oofPeriodEl.textContent = this.formatPeriod(period?.start, period?.end);
    
    if (srcEl) srcEl.textContent = `${source || '—'}`;

    if (statusEl) statusEl.textContent = api ? '' : 'Details nicht verfügbar';

    // Fallback ohne Bootstrap (falls Modal nicht angezeigt wurde)
    if (!window.bootstrap?.Modal) {
      const lines = [name, email || upn || '', mobileExt ? `${mobileExt}` : '', source ? `Quelle: ${source}` : '']
        .filter(Boolean).join('\n');
      alert(lines);
    }
  }
}
