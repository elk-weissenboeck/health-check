export class EventBinding {
  constructor(app, optionsManager) {
    this.app = app;
    this.optionsManager = optionsManager;
  }

  $(id) { return document.getElementById(id); }
  on(id, evt, handler) {
    const el = this.$(id);
    if (!el) {
      console.warn(`[status] control #${id} nicht gefunden – übersprungen`);
      return;
    }
    el.addEventListener(evt, handler);
  }

  wire() {
    this.on('refreshBtn', 'click', () => this.app.refreshAll());
    this.on('expandAllBtn', 'click', () => this.app.expandAll());
    this.on('collapseAllBtn', 'click', () => this.app.collapseAll());
    this.on('ownerListBtn', 'click', () => this.app.showOwnerList());

    this.on('optShowUrls', 'change', (e) => { this.optionsManager.options.showUrls = !!e.target.checked; this.optionsManager.apply(); });
    this.on('optShowAttr', 'change', (e) => { this.optionsManager.options.showAttr = !!e.target.checked; this.optionsManager.apply(); });
    this.on('optShowHeaders', 'change', (e) => { this.optionsManager.options.showHeaders = !!e.target.checked; this.optionsManager.apply(); });
    this.on('optShowLatency', 'change', (e) => { this.optionsManager.options.showLatency = !!e.target.checked; this.optionsManager.apply(); });
    this.on('optAutoRefresh', 'change', (e) => { this.optionsManager.options.autoRefresh = !!e.target.checked; this.optionsManager.apply(); });
    this.on('optRefreshInterval', 'change', (e) => { this.optionsManager.options.refreshInterval = parseInt(e.target.value, 10) || 30; this.optionsManager.apply(); });
    this.on('optOpenOptionsOnLoad', 'change', (e) => { this.optionsManager.options.openOptionsOnLoad = !!e.target.checked; this.optionsManager.apply(); });

    // Delegation für Icon-Gruppe (Owner + Tickets)
    const gc = document.getElementById('groupsContainer');
    if (gc) {
      gc.addEventListener('click', (ev) => {
        const btn = ev.target.closest('.btn-icon');
        if (!btn) return;

        // Deaktivierte Icons ignorieren
        if (btn.classList.contains('is-disabled') || btn.getAttribute('aria-disabled') === 'true') {
          return;
        }

        // Tickets
        if (btn.classList.contains('tickets-btn')) {
          const g = btn.getAttribute('data-tickets-group');
          const s = btn.getAttribute('data-tickets-service');
          this.app.showTickets(g, s);
          return;
        }

        // Owner
        if (btn.classList.contains('owner-btn')) {
          const g = btn.getAttribute('data-owner-group');
          const s = btn.getAttribute('data-owner-service');
          this.app.showOwner(g, s);
          return;
        }
      });
    }

  }
}
