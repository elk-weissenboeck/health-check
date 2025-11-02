export class PathUtils {
  /**
   * Holt einen verschachtelten Wert, Pfad wie "meta.status" oder "results[0].state"
   */
  static getByPath(obj, path) {
    if (!path) return undefined;
    const parts = path
      .replace(/\[(\d+)\]/g, '.$1')
      .split('.')
      .filter(Boolean);
    return parts.reduce((acc, key) => (acc != null ? acc[key] : undefined), obj);
  }
}
