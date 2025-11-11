export class StatusView {
    setBadge(group, svc, ok, ms) {
      const pill = document.getElementById(`badge-${group}-${svc}`);
      if (pill) {
        pill.className = `status-pill ${ok ? 'status-ok' : 'status-nok'}`;
        pill.textContent = ok ? 'OK' : 'NOK';
      }
      const latency = document.getElementById(`latency-${group}-${svc}`);
      if (latency) latency.textContent = `${ms} ms`;
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
