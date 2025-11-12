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
                    <div class="d-flex flex-column w-100">
                      <div class="service-title d-flex align-items-center justify-content-between mb-1">
                        <span class="service-label">
                          ${escapeHtml(s.label)}
                          ${s.isDevEnv ? `
                            <span class="dev-badge" title="Entwicklungsumgebung">
                              <i class="bi bi-code-square"></i>
                            </span>
                          ` : ``}
                        </span>
                        
                        <span class="btn-icon-group">
                          <!-- Owner -->
                          <button
                            type="button"
                            class="btn-icon owner-btn ${s.owner?.upn ? '' : 'is-disabled'}"
                            title="${s.owner?.upn ? 'Service Owner anzeigen' : 'Kein Service Owner hinterlegt'}"
                            aria-disabled="${s.owner?.upn ? 'false' : 'true'}"
                            data-owner-group="${group.key}"
                            data-owner-service="${s.key}">
                            <i class="bi bi-person-fill-gear"></i>
                          </button>

                          <!-- Tickets (Mantis/GLPI) -->
                          ${(() => {
                            const hasMantis = !!s.mantis?.url;
                            const hasGlpi   = !!s.glpiURL;
                            const enabled   = hasMantis || hasGlpi;
                            const title     = enabled ? 'Tickets anzeigen' : 'Keine Ticket-Endpunkte konfiguriert';
                            return `
                              <button
                                type="button"
                                class="btn-icon tickets-btn ${enabled ? '' : 'is-disabled'}"
                                title="${title}"
                                aria-disabled="${enabled ? 'false' : 'true'}"
                                data-tickets-group="${group.key}"
                                data-tickets-service="${s.key}">
                                <i class="bi bi-stack"></i>
                              </button>
                            `;
                          })()}

                          <!-- Status-Pill (OK grün, sonst hellgrau) -->
                          <span class="status-pill status-neutral" id="badge-${group.key}-${s.key}">N/A</span>
                          <span class="latency-chip svc-latency" id="latency-${group.key}-${s.key}">– ms</span>
                        </span>
                      </div>

                      <small class="text-secondary svc-url mb-1">${escapeHtml(s.url || '')}</small>

                      ${s.warning ? `
                        <div class="alert alert-warning alert-compact w-100 mt-2 mb-0 d-flex align-items-center gap-2">
                          <i class="bi bi-exclamation-triangle-fill"></i>
                          <small>${escapeHtml(s.warning)}</small>
                        </div>
                      ` : ''}
                  
                       <!-- FELDER -->
                       <div class="svc-section svc-attr is-empty" id="fieldsbox-${group.key}-${s.key}">
                          <div class="svc-section-title">Response-Attribute</div>
                          <div class="service-fields svc-attr" id="fields-${group.key}-${s.key}"></div>
                       </div>

                       <!-- HEADER -->
                       <div class="svc-section svc-header is-empty" id="headersbox-${group.key}-${s.key}">
                          <div class="svc-section-title">Response-Header</div>
                          <div class="service-headers" id="headers-${group.key}-${s.key}"></div>
                       </div>
                    </div>
                  </div>

                </div>
              `).join('')}
            </div>
          </div>
        </section>
      `;
    }


}
