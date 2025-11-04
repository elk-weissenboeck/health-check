export class Templates {
  static escapeHtml(str) {
    return String(str || '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  static groupCard(group) {
    const escapeHtml = Templates.escapeHtml;
    const collapseId = `collapse-${group.key}`;
    const headerId = `header-${group.key}`;

    return `
      <section class="card card-group-status" id="group-${group.key}" aria-live="polite">
        <div class="card-header card-header-toggle"
             id="${headerId}"
             role="button"
             data-bs-toggle="collapse"
             data-bs-target="#${collapseId}"
             aria-expanded="false"
             aria-controls="${collapseId}">
          <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
              <span class="status-dot bg-success" id="${group.key}-dot" aria-hidden="true"></span>
              <span class="fw-semibold">${escapeHtml(group.title)}</span>
              <small class="text-secondary">— <span class="group-summary" id="${group.key}-summary">Prüfe…</span></small>
            </div>
            <div class="d-flex align-items-center gap-2">
              <small class="text-secondary d-none d-md-inline">${escapeHtml(group.description || '')}</small>
              <span class="chevron-wrap" aria-hidden="true"><i class="bi bi-chevron-right chevron"></i></span>
            </div>
          </div>
        </div>

        <div id="${collapseId}" class="collapse" data-group-key="${group.key}" aria-labelledby="${headerId}">
          <div class="list-group list-group-flush list-status" id="${group.key}-list">
            ${group.services.map(s => `
              <div class="list-group-item d-flex flex-column align-items-stretch">
                <div class="d-flex justify-content-between align-items-center w-100">
                  <div class="d-flex flex-column">
                    <span class="fw-medium mb-1">
                      ${escapeHtml(s.label)}
                      ${s.owner?.upn ? `
                        <button type="button" class="btn btn-sm btn-link p-0 align-baseline owner-btn"
                                title="Service Owner anzeigen"
                                data-owner-group="${group.key}" data-owner-service="${s.key}">
                          <i class="bi bi-person-badge"></i>
                        </button>` : ``}
                    </span>
                    <small class="text-secondary svc-url mb-1">${escapeHtml(s.url)}</small>

                    <div class="service-fields svc-attr mt-1" id="fields-${group.key}-${s.key}"></div>
                    <div class="service-headers svc-header mt-1" id="headers-${group.key}-${s.key}"></div>
                  </div>

                  <div class="d-flex align-items-center gap-3 me-3">
                    <small class="text-secondary svc-latency d-none d-sm-inline" id="latency-${group.key}-${s.key}">– ms</small>
                    
                    <span class="position-relative d-inline-block" id="statusWrap-${group.key}-${s.key}">
                      <span class="badge rounded-pill text-bg-secondary px-3" id="badge-${group.key}-${s.key}">N/A</span>
                      <span class="badge px-3 text-bg-light d-none d-block" id="counter-${group.key}-${s.key}">0</span>
                    </span>
                  </div>
                </div>

                ${s.warning ? `
                  <div class="alert alert-warning alert-compact w-100 mt-2 mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>${escapeHtml(s.warning)}</div>
                  </div>
                ` : ''}
              </div>
            `).join('')}
          </div>
        </div>
      </section>
    `;
  }
}
