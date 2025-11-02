export class StatusView {
  setBadge(group, svc, ok, ms, count = null, value = null, serviceDef = null) {
    const badge   = document.getElementById(`badge-${group}-${svc}`);
    const bubble  = document.getElementById(`counter-${group}-${svc}`);
    const latency = document.getElementById(`latency-${group}-${svc}`);

    const hasWarning = !!serviceDef?.warning;

    let cls = ok ? 'text-bg-success' : 'text-bg-danger';
    let text = ok ? 'OK' : 'NOK';
    if (hasWarning && ok) { cls = 'text-bg-warning text-dark'; }

    if (badge) {
      badge.className = `badge px-3 ${cls}`;
      badge.textContent = text;
      if (hasWarning && serviceDef.warning) badge.title = serviceDef.warning;
    }
    if (latency) latency.textContent = `${ms} ms`;

    if (bubble) {
      if (count != null && !Number.isNaN(count)) {
        const n = Number(count);
        bubble.textContent = (Number.isFinite(n) && n > 99) ? '99+' : String(n);
        bubble.classList.remove('d-none');
      } else {
        bubble.classList.add('d-none');
      }
    }
  }

  setGroupStatus(groupKey, state /* 'ok' | 'warn' | 'nok' */) {
    const dot = document.getElementById(`${groupKey}-dot`);
    const summary = document.getElementById(`${groupKey}-summary`);

    if (dot) {
      if (state === 'nok') dot.className = 'status-dot bg-danger';
      else if (state === 'warn') dot.className = 'status-dot bg-warning';
      else dot.className = 'status-dot bg-success';
    }
    if (summary) {
      summary.textContent =
        state === 'nok' ? 'Eingeschränkter Betrieb'
        : state === 'warn' ? 'Alle Services OK (Warnungen vorhanden)'
        : 'Alle Services OK';
    }
  }

  setOverall(ok) {
    const card = document.getElementById('overallCard');
    const icon = document.getElementById('overallIcon');
    const title = document.getElementById('overallTitle');
    if (card) card.style.setProperty('--status-color', ok ? 'var(--bs-success)' : 'var(--bs-danger)');
    if (icon) icon.className = `bi ${ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'} fs-4`;
    if (title) title.textContent = ok ? 'Alle Services online' : 'Eingeschränkter Betrieb';
  }

  updateTimestamp() {
    const el = document.getElementById('lastUpdated');
    if (el) el.textContent = new Date().toLocaleString();
  }
}
