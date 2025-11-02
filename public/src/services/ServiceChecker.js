import { PathUtils } from '../utils/PathUtils.js';

export class ServiceChecker {
  async check(url, method = 'HEAD', expect = null) {
    const start = performance.now();
    try {
      const res = await fetch(url, { method, cache: 'no-store' });
      const ms = Math.round(performance.now() - start);

      const headers = {};
      res.headers.forEach((v, k) => { headers[k.toLowerCase()] = v; });

      if (!res.ok) return { ok: false, ms, count: null, value: null, data: null, headers };

      if (method === 'GET') {
        const raw = await res.text();
        if (!raw) return { ok: false, ms, count: null, value: null, data: null, headers };

        let data;
        try { data = JSON.parse(raw); }
        catch { return { ok: false, ms, count: null, value: null, data: null, headers }; }
        if (data === null) return { ok: false, ms, count: null, value: null, data: null, headers };

        const count = Array.isArray(data?.results) ? data.results.length : null;

        if (expect) {
          const v = expect.jsonPath ? PathUtils.getByPath(data, expect.jsonPath) : data;
          let pass = v !== undefined && v !== null;

          if ('equals' in expect)  pass = v === expect.equals;
          if ('truthy' in expect)  pass = !!v === !!expect.truthy;
          if ('minLen' in expect)  pass = Array.isArray(v) ? v.length >= expect.minLen : false;
          if ('in' in expect && Array.isArray(expect.in)) pass = expect.in.includes(v);
          if ('regex' in expect)   pass = typeof v === 'string' && new RegExp(expect.regex).test(v);

          return { ok: pass, ms, count, value: v, data, headers };
        }

        return { ok: true, ms, count, value: null, data, headers };
      }

      return { ok: true, ms, count: null, value: null, data: null, headers };
    } catch {
      const ms = Math.round(performance.now() - start);
      return { ok: false, ms, count: null, value: null, data: null, headers: null };
    }
  }
}
