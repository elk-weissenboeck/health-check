import { ConfigLoader } from '../config/ConfigLoader.js';
import { Renderer } from '../ui/Renderer.js';
import { ServiceChecker } from '../services/ServiceChecker.js';
import { ServiceDetails } from '../ui/ServiceDetails.js';
import { StatusView } from '../ui/StatusView.js';
import { Collapse } from '../ui/Collapse.js';

export class App {
  constructor() {
    this.groups = [];
    this.maintenance = { active: false };
    this.healthResults = {};

    this.renderer = new Renderer('groupsContainer');
    this.checker = new ServiceChecker();
    this.details = new ServiceDetails();
    this.view = new StatusView();

    // bequem für Debugging
    window.__healthResults = this.healthResults;
    window.inspectService = (groupKey, serviceKey) => this.healthResults?.[groupKey]?.[serviceKey] ?? null;
  }

  async bootstrap() {
    try {
      const cfg = await ConfigLoader.load();
      this.groups = cfg.groups; // bewusst nicht normalisieren
      this.maintenance = cfg.maintenance || { active: false };

      this.renderer.renderGroups(this.groups);
      this.applyOptions?.(); // wird von OptionsManager gesetzt
      await this.refreshAll();
      this.showMaintenanceBanner();
    } catch (e) {
      console.error(e);
      const gc = document.getElementById('groupsContainer');
      if (gc) gc.innerHTML = '<div class="alert alert-danger">Konfiguration konnte nicht geladen werden.</div>';
    }
  }

  async refreshAll() {
    const groupStates = await Promise.all(this.groups.map(async (g) => {
      const results = await Promise.all(g.services.map(async (s) => {
        const r = await this.checker.check(s.url, s.method, s.expect);
        this.view.setBadge(g.key, s.key, r.ok, r.ms, r.count, r.value, s);
        this.details.renderServiceFields(g.key, s, r.data);
        this.details.renderServiceHeaders(g.key, s, r.headers);

        this.healthResults[g.key] ??= {};
        this.healthResults[g.key][s.key] = r;
        return r.ok;
      }));

      const anyNok = results.some(ok => !ok);
      const anyWarn = g.services.some(s => !!s.warning);
      const state = anyNok ? 'nok' : (anyWarn ? 'warn' : 'ok');
      this.view.setGroupStatus(g.key, state);
      if (state === 'nok') {
        const collapseId = `collapse-${g.key}`;
        const el = document.getElementById(collapseId);
        if (el && !el.classList.contains('show')) Collapse.showById(collapseId);
      }
      return state;
    }));

    const hasNok = groupStates.includes('nok');
    this.view.setOverall(!hasNok);
    this.view.updateTimestamp();
  }

  expandAll() { this.groups.forEach(g => Collapse.showById(`collapse-${g.key}`)); }
  collapseAll() { this.groups.forEach(g => Collapse.hideById(`collapse-${g.key}`)); }

  showMaintenanceBanner() {
    const banner = document.getElementById('maintenanceBanner');
    const title = document.getElementById('maintenanceTitle');
    const msg = document.getElementById('maintenanceMessage');

    if (!this.maintenance.active) {
      if (banner) banner.classList.add('d-none');
      return;
    }

    const now = new Date();
    const start = this.maintenance.start ? new Date(this.maintenance.start) : null;
    const end = this.maintenance.end ? new Date(this.maintenance.end) : null;
    const isWithinWindow = (!start || now >= start) && (!end || now <= end);

    if (banner) {
      if (isWithinWindow) {
        if (title) title.textContent = (this.maintenance.title || 'Wartung') + ':';
        if (msg) msg.textContent = ' ' + (this.maintenance.message || '');
        banner.classList.remove('d-none');
      } else {
        banner.classList.add('d-none');
      }
    }
  }
}
