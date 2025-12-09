export class ConfigLoader {
  static getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
  }

  /**
   * Holt zunächst die Fähigkeiten (ladbare Config-Files) von
   * /dashboard/config/capability.php und lädt dann:
   *
   * - public.config.json (Pflicht)
   * - alle von capability.php gemeldeten Config-Files (optional)
   *
   * @returns {Promise<object>} Gemergte Konfiguration
   */
  static async load() {
    const token =
      ConfigLoader.getCookie('UserToken') ??
      '4f7d636cdcad582fb38afae99269c44c82baca01b73232b8514ca8b66b414ce6'; // Fallback, falls gewünscht

    const requiredFile = 'public.config.json';

    // 1) Capabilities vom Server holen
    const capability = await ConfigLoader.fetchCapabilities(token);

    // erwartete Struktur: { configs: ['it.config.json', 'editor.config.json', ...], ... }
    const capabilityFiles = Array.isArray(capability?.configs)
      ? capability.configs.filter((f) => typeof f === 'string')
      : [];

    // Duplikate vermeiden, public.config.json sicherstellen
    const allFiles = Array.from(
      new Set([requiredFile, ...capabilityFiles])
    );

    const configs = [];

    // 2) Pflicht-Config laden (darf NICHT fehlschlagen)
    const publicCfg = await ConfigLoader.fetchConfigFile(
      requiredFile,
      token,
      true // required
    );
    configs.push(publicCfg);

    // 3) Optionale Configs laden (Fehler werden ignoriert)
    const optionalFiles = allFiles.filter((f) => f !== requiredFile);

    if (optionalFiles.length > 0) {
      const optionalConfigs = await Promise.all(
        optionalFiles.map((file) =>
          ConfigLoader.fetchConfigFile(
            file,
            token,
            false // not required
          ).catch(() => null) // zusätzliche Absicherung
        )
      );

      for (const cfg of optionalConfigs) {
        if (cfg && typeof cfg === 'object') {
          configs.push(cfg);
        }
      }
    }

    // 4) Mergen wie bisher
    const merged = ConfigLoader.mergeConfigs(configs);

    if (!merged || !Array.isArray(merged.groups)) {
      throw new Error('Ungültige Konfiguration (groups fehlen).');
    }

    return merged;
  }

  /**
   * Fragt /dashboard/config/capability.php ab, um herauszufinden,
   * welche Config-Files für den aktuellen Client ladbar sind.
   *
   * Erwartet ein JSON-Objekt, idealerweise mit Feld "configs": string[]
   *
   * @param {string|null} token
   * @returns {Promise<{configs?: string[]}>}
   */
  static async fetchCapabilities(token) {
    const res = await fetch('/dashboard/config/capability.php', {
      cache: 'no-store',
      credentials: 'include',
      headers: token
        ? {
            Authorization: `Bearer ${token}`,
          }
        : {},
    });

    if (!res.ok) {
      console.warn(
        `Capabilities konnten nicht geladen werden (Status ${res.status}). ` +
          'Es werden nur public.config.json geladen.'
      );
      // Fallback: keine optionalen Configs
      return { configs: [] };
    }

    let data;
    try {
      data = await res.json();
    } catch (e) {
      console.warn('Ungültige JSON-Antwort von capability.php:', e);
      return { configs: [] };
    }

    if (!data || typeof data !== 'object') {
      console.warn('Capability-Antwort ist kein Objekt:', data);
      return { configs: [] };
    }

    return data;
  }

  /**
   * Lädt eine einzelne Config-Datei.
   *
   * @param {string} file
   * @param {string|null} token
   * @param {boolean} required - true = Fehler -> Exception, false = Fehler -> null
   * @returns {Promise<object|null>}
   */
  static async fetchConfigFile(file, token, required = false) {
    const res = await fetch(
      `/dashboard/config/config.php?file=${encodeURIComponent(file)}`,
      {
        cache: 'no-store',
        credentials: 'include',
        headers: token
          ? {
              Authorization: `Bearer ${token}`,
            }
          : {},
      }
    );

    if (!res.ok) {
      if (!required) {
        // optionales File: bei Fehler einfach ignorieren
        return null;
      } 

      // Nur für Pflicht-Config (public.config.json) wird geworfen
      throw new Error(
        `Konfiguration "${file}" konnte nicht geladen werden (Status ${res.status}).`
      );
    }

    const cfg = await res.json();

    if (!cfg || typeof cfg !== 'object') {
      if (!required) {
        return null;
      }
      throw new Error(`Ungültige Konfiguration in "${file}".`);
    }

    return cfg;
  }

  /**
   * Merged mehrere Config-Objekte.
   * - Top-Level Properties werden (shallow) von links nach rechts überschrieben
   * - groups-Arrays werden zusammengeführt (konkateniert).
   *
   * @param {object[]} configs
   * @returns {object}
   */
  static mergeConfigs(configs) {
    const merged = {};

    for (const cfg of configs) {
      if (!cfg || typeof cfg !== 'object') continue;

      const { groups, ...rest } = cfg;

      // andere Keys: späteres überschreibt früheres
      Object.assign(merged, rest);

      // groups werden gesammelt
      if (Array.isArray(groups)) {
        if (!Array.isArray(merged.groups)) {
          merged.groups = [];
        }
        merged.groups.push(...groups);
      }
    }

    return merged;
  }
}
