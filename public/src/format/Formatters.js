export class Formatters {
  static number(v)  { return typeof v === 'number' ? v.toLocaleString() : v; }
  static bytes(v)   { return typeof v === 'number' ? Formatters.formatBytes(v) : v; }
  static ms(v)      { return typeof v === 'number' ? `${v} ms` : v; }
  static date(v)    { return v ? new Date(v).toLocaleDateString() : v; }
  static datetime(v){ return v ? new Date(v).toLocaleString() : v; }
  static bool(v)    { return (v ? 'Ja' : 'Nein'); }
  static upper(v)   { return (typeof v === 'string' ? v.toUpperCase() : v); }
  static lower(v)   { return (typeof v === 'string' ? v.toLowerCase() : v); }
  static minutes(v) {
    if (v == null || isNaN(v)) return v;
    const mins = v / 1000 / 60;
    return `${mins.toFixed(1)} min`;
  }
  static seconds(v) { return (v == null || isNaN(v)) ? v : `${v} s`; }
  static bytesHeader(v) { return Formatters.formatBytes(Number(v)); }

  static formatBytes(n){
    if (!Number.isFinite(n)) return n;
    const units = ['B','KB','MB','GB','TB'];
    let i=0; while (n>=1024 && i<units.length-1){ n/=1024; i++; }
    return `${n.toFixed(n<10&&i>0?1:0)} ${units[i]}`;
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
    bytesHeader: v => Formatters.bytesHeader(v),
  };
}
