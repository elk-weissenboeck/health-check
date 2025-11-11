import { PathUtils } from '../utils/PathUtils.js';
import { Formatters } from '../format/Formatters.js';

export class ServiceDetails {
  renderServiceFields(groupKey, serviceDef, data) {
    const container = document.getElementById(`fields-${groupKey}-${serviceDef.key}`);
    const box = document.getElementById(`fieldsbox-${groupKey}-${serviceDef.key}`);

    if (!container) return;
    const fields = serviceDef.fields || [];
    if (!data || fields.length === 0) { container.innerHTML = ''; return; }

    const parts = fields.map(f => {
      const raw = f.path ? PathUtils.getByPath(data, f.path) : data;
      let val = raw;

      if (f.format && Formatters.registry[f.format]) {
        val = Formatters.registry[f.format](raw);
      }

      let badgeClass = null;
      if (f.badge) badgeClass = `text-bg-${f.badge}`;
      if (f.badgeByValue && raw in f.badgeByValue) {
        badgeClass = `text-bg-${f.badgeByValue[raw]}`;
      }

      const labelHtml = f.label ? `<small class="sf-label">${this._esc(f.label)}:</small>` : '';
      const valueHtml = badgeClass
        ? `<small class="badge ${badgeClass}">${this._esc(String(val))}</small>`
        : `<small class="sf-value badge text-bg-secondary">${this._esc(String(val))}</small>`;

      return `<small class="sf-item">${labelHtml} ${valueHtml}</small>`;
    });

    container.innerHTML = parts.join('');
    
    // Leer nach Rendering? Dann Box ausblenden, sonst zeigen
    const isEmpty = !container.textContent.trim();
    if (box) box.classList.toggle('is-empty', isEmpty);
  }
  
  
  

  renderServiceHeaders(groupKey, serviceDef, headers) {
    const containerId = `headers-${groupKey}-${serviceDef.key}`;
    const container = document.getElementById(containerId);
    const box = document.getElementById(`headersbox-${groupKey}-${serviceDef.key}`);
    if (!container) return;

    const defs = serviceDef.headers || [];
    if (!headers) { container.innerHTML = ''; return; }

    const parts = (defs.length ? defs : []).map(h => {
      const name = String(h.name || '').toLowerCase();
      const raw  = headers[name];
      if (raw == null) return '';

      let val = raw;
      if (h.format && Formatters.registry[h.format]) {
        val = Formatters.registry[h.format](isFinite(+raw) ? +raw : raw);
      }

      let badgeClass = null;
      if (h.badge) badgeClass = `text-bg-${h.badge}`;
      if (h.badgeByValue && raw in h.badgeByValue) badgeClass = `text-bg-${h.badgeByValue[raw]}`;

      const labelHtml = h.label ? `<small class="sh-label">${this._esc(h.label)}:</small>` : '';
      const valueHtml = badgeClass
        ? `<small class="badge ${badgeClass}">${this._esc(String(val))}</small>`
        : `<small class="sh-value badge text-bg-secondary">${this._esc(String(val))}</small>`;

      return `<small class="sh-item">${labelHtml} ${valueHtml}</small>`;
    }).filter(Boolean);

    // automatischer Cache-Hinweis
    const cacheHeader = headers['x-proxy-cache'];
    const cacheTtlRaw = headers['x-proxy-cache-ttl'];
    if (cacheHeader != null) {
      const cacheState = String(cacheHeader).toUpperCase();
      const isPositive = cacheState === 'HIT';
      const badgeClass = isPositive ? 'text-bg-info' : 'text-bg-secondary';

      let ttlSuffix = '';
      if (cacheTtlRaw != null && cacheTtlRaw !== '') {
        const ttlNice = Formatters.formatTtlSeconds(cacheTtlRaw);
        ttlSuffix = ` · TTL ${this._esc(ttlNice)}`;
      }

      const title = isPositive
        ? 'Antwort wurde vom Proxy-Cache geliefert.'
        : 'Proxy-Cache-Status';

      parts.push(
        `<small class="sh-item">
           <small class="sh-label">Cache:</small>
           <small class="sh-value badge text-bg-secondary" title="${this._esc(title)}">
             ${this._esc(cacheState)}${ttlSuffix}
           </small>
         </small>`
      );
    }

    container.innerHTML = parts.join('');
    
    const isEmpty = !container.textContent.trim();
    if (box) box.classList.toggle('is-empty', isEmpty);
  }

  _esc(s) { return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;'); }
}
