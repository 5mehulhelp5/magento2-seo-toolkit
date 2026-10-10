// Lighthouse + raw SEO-tag baseline for a vanilla Magento store.
// Usage: node measure.mjs [pages.json] [out.json]
//   COOKIE env: sent on every request (e.g. a guest session with a filled cart)
//   THROTTLE=devtools: really slow CPU/network instead of Lighthouse's simulated estimate
import fs from 'node:fs';
import https from 'node:https';
import lighthouse from 'lighthouse';
import * as chromeLauncher from 'chrome-launcher';

const HOST = 'magento-seo.test';
const BASE = `https://${HOST}:8443`;
const RUNS = Number(process.env.RUNS || 3); // median per page/form factor
const [pagesFile = 'pages.json', outFile = 'baseline.json'] = process.argv.slice(2);
const pages = JSON.parse(fs.readFileSync(pagesFile, 'utf8'));
const COOKIE = process.env.COOKIE || '';
const THROTTLE = process.env.THROTTLE ? { throttlingMethod: process.env.THROTTLE } : {};
const REPORT_PREFIX = process.env.REPORT_PREFIX || '';
fs.mkdirSync('reports', { recursive: true });

const chrome = await chromeLauncher.launch({
  chromePath: process.env.CHROME_PATH, // default: chrome-launcher finds the installed Chrome
  chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} 127.0.0.1`,
    '--ignore-certificate-errors', '--no-first-run'],
});

const median = a => [...a].sort((x, y) => x - y)[Math.floor(a.length / 2)];

function fetchRaw(path) {
  return new Promise((resolve, reject) => {
    https.get({ host: '127.0.0.1', port: 8443, path, servername: HOST,
      headers: { Host: `${HOST}:8443`, ...(COOKIE && { Cookie: COOKIE }) }, rejectUnauthorized: false }, res => {
      let body = ''; res.on('data', c => body += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
    }).on('error', reject);
  });
}

function seoTags({ status, headers, body }) {
  const all = (re) => [...body.matchAll(re)].map(m => m[1]);
  const jsonLdTypes = all(/<script[^>]*application\/ld\+json[^>]*>([\s\S]*?)<\/script>/gi)
    .flatMap(s => { try { const j = JSON.parse(s); return [].concat(j['@graph'] || j).map(x => x['@type']); } catch { return ['(invalid)']; } });
  return {
    status,
    xRobotsTag: headers['x-robots-tag'] || null,
    title: (body.match(/<title>([\s\S]*?)<\/title>/i) || [])[1]?.trim() || null,
    metaDescription: (body.match(/<meta name="description" content="([^"]*)"/i) || [])[1] ?? null,
    metaRobots: (body.match(/<meta name="robots" content="([^"]*)"/i) || [])[1] ?? null,
    canonical: all(/<link[^>]+rel="canonical"[^>]+href="([^"]*)"/gi),
    hreflang: all(/<link[^>]+hreflang="([^"]*)"/gi),
    relPrevNext: all(/<link[^>]+rel="(prev|next)"/gi),
    h1Count: (body.match(/<h1[\s>]/gi) || []).length,
    jsonLdTypes,
    microdataTypes: [...new Set(all(/itemtype="https?:\/\/schema\.org\/([A-Za-z]+)"/gi))],
    imgMissingAlt: (body.match(/<img(?![^>]*\balt=)[^>]*>/gi) || []).length,
    htmlBytes: body.length,
  };
}

// Saved after every page; rerunning skips pages already measured.
const save = results => fs.writeFileSync(outFile, JSON.stringify(
  { measuredAt: new Date().toISOString(), base: BASE, runsPerCell: RUNS, results }, null, 2));
const results = fs.existsSync(outFile) ? JSON.parse(fs.readFileSync(outFile, 'utf8')).results : [];
for (const p of pages) {
  if (results.some(r => r.name === p.name)) continue;
  const url = BASE + p.path;
  const row = { name: p.name, path: p.path, tags: seoTags(await fetchRaw(p.path)) };
  // Lighthouse refuses non-2xx documents (runtimeError), so error pages get tags only.
  if (row.tags.status >= 400) {
    row.lighthouse = `skipped: HTTP ${row.tags.status}`;
    results.push(row); save(results);
    console.log(p.name, `tags only (HTTP ${row.tags.status})`);
    continue;
  }
  for (const ff of ['mobile', 'desktop']) {
    const runs = [];
    for (let i = 0; i < RUNS; i++) {
      const config = ff === 'desktop'
        ? (await import('lighthouse/core/config/desktop-config.js')).default : undefined;
      const r = await lighthouse(url, { port: chrome.port, output: 'html', logLevel: 'error', ...THROTTLE,
        ...(COOKIE && { extraHeaders: { Cookie: COOKIE }, disableStorageReset: true }) }, config);
      if (r.lhr.runtimeError) throw new Error(`${p.name} ${ff}: ${r.lhr.runtimeError.message}`);
      if (i === 0) fs.writeFileSync(`reports/${REPORT_PREFIX}${p.name}-${ff}.html`, r.report);
      const c = r.lhr.categories, a = r.lhr.audits;
      runs.push({
        performance: c.performance.score * 100, accessibility: c.accessibility.score * 100,
        bestPractices: c['best-practices'].score * 100, seo: c.seo.score * 100,
        lcp: a['largest-contentful-paint'].numericValue, cls: a['cumulative-layout-shift'].numericValue,
        tbt: a['total-blocking-time'].numericValue, fcp: a['first-contentful-paint'].numericValue,
        ttfb: a['server-response-time'].numericValue,
        mainThread: a['mainthread-work-breakdown'].numericValue, tti: a['interactive']?.numericValue ?? null,
        totalBytes: a['total-byte-weight'].numericValue, requests: a['network-requests'].details?.items?.length,
        failedSeoAudits: c.seo.auditRefs.filter(ref => a[ref.id].score !== null && a[ref.id].score < 1 && ref.weight > 0).map(ref => ref.id),
      });
    }
    const m = {};
    for (const k of Object.keys(runs[0])) m[k] = typeof runs[0][k] === 'number' ? median(runs.map(r => r[k])) : runs[0][k];
    row[ff] = m;
    console.log(p.name, ff, `perf ${m.performance} seo ${m.seo} LCP ${Math.round(m.lcp)}ms TBT ${Math.round(m.tbt)}ms CLS ${m.cls.toFixed(3)}`);
  }
  results.push(row); save(results);
}
await chrome.kill();
console.log(`wrote ${outFile}`);
