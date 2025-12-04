export class ConfigLoader {
  static getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
  }

  /**
   * Lädt mehrere Config-Files, merged sie und gibt eine gemeinsame
   * Konfiguration zurück.
   *
   * - public.config.json ist Pflicht
   * - alle anderen (z.B. it.config.json) sind optional
   *
   * @param {string[]} files - Liste zusätzlicher Config-Dateien
   * @returns {Promise<object>} Gemergte Konfiguration
   */
  static async load(files = ['it.config.json']) {
    const token =
      ConfigLoader.getCookie('UserToken') ??
      '4f7d636cdcad582fb38afae99269c44c82baca01b73232b8514ca8b66b414ce6';

    const requiredFile = 'public.config.json';

    // Duplikate vermeiden, public.config.json immer vorne
    const allFiles = Array.from(
      new Set([requiredFile, ...files])
    );

    const configs = [];

    // 1) Pflicht-Config laden (darf NICHT fehlschlagen)
    const publicCfg = await ConfigLoader.fetchConfigFile(
      requiredFile,
      token,
      true // required
    );
    configs.push(publicCfg);

    // 2) Optionale Configs laden (Fehler werden ignoriert)
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

    const merged = ConfigLoader.mergeConfigs(configs);

    if (!merged || !Array.isArray(merged.groups)) {
      throw new Error('Ungültige Konfiguration (groups fehlen).');
    }

    return merged;
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
      // it.config.json (und alle anderen optionalen) werden bei Fehler ignoriert
      if (!required) {
        // Beispiel: it.config.json mit 401 wegen anonymous/fehlendem Token
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
