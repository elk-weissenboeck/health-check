export class ConfigLoader {
  static async load() {
    const res = await fetch('../config/status.config.json', { cache: 'no-store' });
    if (!res.ok) throw new Error('Konfiguration konnte nicht geladen werden.');
    const cfg = await res.json();
    if (!cfg || !Array.isArray(cfg.groups)) throw new Error('Ungültige Konfiguration (groups fehlen).');
    return cfg;
  }
}
