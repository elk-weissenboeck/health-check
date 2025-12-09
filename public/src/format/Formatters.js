export class Formatters {
  static number(v)  { return typeof v === 'number' ? v.toLocaleString() : v; }
  static bytes(v)   { return Formatters.formatBytes(v); }
  static ms(v)      { return typeof v === 'number' ? `${v} ms` : v; }
  static date(v)    { return v ? new Date(v).toLocaleDateString() : v; }
  static datetime(v){ return v ? new Date(v).toLocaleString() : v; }
  static bool(v)    { return (v ? 'Ja' : 'Nein'); }
  static upper(v)   { return (typeof v === 'string' ? v.toUpperCase() : v); }
  static lower(v)   { return (typeof v === 'string' ? v.toLowerCase() : v); }
  static seconds(v) { return (v == null || isNaN(v)) ? v : `${v} s`; }
  static bytesHeader(v) { return Formatters.formatBytes(Number(v)); }
  static minutes(v) {
    if (v == null || isNaN(v)) return v;
    const mins = v / 1000 / 60;
    return `${mins.toFixed(1)} min`;
  }
  
  static formatPercent(x) {
      const n = Number(x);
      if (!Number.isFinite(n)) return x;      // Falls kein gültiger Zahlenwert
      return `${n.toFixed(0)}%`;
    }

    static formatBytes(n) {
      // Falls n ein String ist, versuchen in eine Zahl zu casten
      const num = typeof n === 'string' ? Number(n) : n;

      // Wenn das Ergebnis keine endliche Zahl ist, einfach original zurückgeben
      if (!Number.isFinite(num)) return n;

      const units = ['B', 'KB', 'MB', 'GB', 'TB'];
      let value = num;
      let i = 0;

      while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
      }

      const decimals = value < 10 && i > 0 ? 1 : 0;
      return `${value.toFixed(decimals)} ${units[i]}`;
    }


  static formatTtlSeconds(sec) {
    const n = Number(sec);
    if (!Number.isFinite(n) || n < 0) return sec;
    const h = Math.floor(n / 3600);
    const m = Math.floor((n % 3600) / 60);
    const s = Math.floor(n % 60);
    return [
      h ? `${h}h` : null,
      m ? `${m}m` : null,
      (h === 0 && m === 0) ? `${s}s` : (s ? `${s}s` : null)
    ].filter(Boolean).join(' ');
  }

  static registry = {
    number:   v => Formatters.number(v),
    bytes:    v => Formatters.bytes(v),
    ms:       v => Formatters.ms(v),
    date:     v => Formatters.date(v),
    datetime: v => Formatters.datetime(v),
    bool:     v => Formatters.bool(v),
    upper:    v => Formatters.upper(v),
    lower:    v => Formatters.lower(v),
    minutes:  v => Formatters.minutes(v),
    seconds:  v => Formatters.seconds(v),
    percent:  v => Formatters.formatPercent(v),
    bytesHeader: v => Formatters.bytesHeader(v),
  };
}
