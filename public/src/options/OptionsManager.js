import { Collapse } from '../ui/Collapse.js';

const OPTIONS_COOKIE = 'statusOptions';
const OPTIONS_DEFAULT = {
  showHeaders: false,
  showUrls: false,
  showAttr: false,
  showLatency: false,
  autoRefresh: false,
  refreshInterval: 1800,
  openOptionsOnLoad: false,
};

export class OptionsManager {
  constructor(app) {
    this.app = app; // Zugriff auf refreshAll & Co.
    this.options = this.read();
    this.autoTimer = null;
  }

  read() {
    const m = document.cookie.match(new RegExp('(?:^|; )' + OPTIONS_COOKIE + '=([^;]*)'));
    if (!m) return { ...OPTIONS_DEFAULT };
    try {
      const parsed = JSON.parse(decodeURIComponent(m[1]));
      return { ...OPTIONS_DEFAULT, ...parsed };
    } catch {
      return { ...OPTIONS_DEFAULT };
    }
  }

  save() {
    const value = encodeURIComponent(JSON.stringify(this.options));
    const days = 365;
    const expires = new Date(Date.now() + days*24*60*60*1000).toUTCString();
    document.cookie = `${OPTIONS_COOKIE}=${value}; Expires=${expires}; Path=/; SameSite=Lax`;
  }

  apply() {
    const opts = this.options;

    const byId = id => document.getElementById(id);
    const show = byId('optShowUrls');
    const auto = byId('optAutoRefresh');
    const intv = byId('optRefreshInterval');
    const open = byId('optOpenOptionsOnLoad');
    const attr = byId('optShowAttr');
    const head = byId('optShowHeaders');
    const latency = byId('optShowLatency');

    if (show) show.checked = !!opts.showUrls;
    if (auto) auto.checked = !!opts.autoRefresh;
    if (intv) intv.value = String(opts.refreshInterval);
    if (open) open.checked = !!opts.openOptionsOnLoad;
    if (attr) attr.checked = !!opts.showAttr;
    if (head) head.checked = !!opts.showHeaders;
    if (latency) latency.checked = !!opts.showLatency;

    document.body.classList.toggle('hide-urls', !opts.showUrls);
    document.body.classList.toggle('hide-attr', !opts.showAttr);
    document.body.classList.toggle('hide-headers', !opts.showHeaders);
    document.body.classList.toggle('hide-latency', !opts.showLatency);

    if (this.autoTimer) { clearInterval(this.autoTimer); this.autoTimer = null; }
    if (opts.autoRefresh) {
      const ms = Math.max(5, Number(opts.refreshInterval) || 30) * 1000;
      this.autoTimer = setInterval(() => this.app.refreshAll(), ms);
    }

    if (opts.openOptionsOnLoad) {
      Collapse.showById('optionsCollapse');
    } else {
      Collapse.hideById('optionsCollapse');
    }

    this.save();
  }
}
