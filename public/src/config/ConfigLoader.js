export class ConfigLoader {
  static getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
  }

  static async load() {
    const token = ConfigLoader.getCookie('UserToken') ?? '4f7d636cdcad582fb38afae99269c44c82baca01b73232b8514ca8b66b414ce6';

    const res = await fetch('/dashboard/config/status-config.php', {
      cache: 'no-store',
      credentials: 'include',
      headers: token ? {
        'Authorization': `Bearer ${token}`
      } : {}
    });

    if (!res.ok) {
      throw new Error('Konfiguration konnte nicht geladen werden.');
    }

    const cfg = await res.json();
    if (!cfg || !Array.isArray(cfg.groups)) {
      throw new Error('Ungültige Konfiguration (groups fehlen).');
    }

    return cfg;
  }
}
