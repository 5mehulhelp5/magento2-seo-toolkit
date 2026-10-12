// PageSpeed-equivalent runs on the local store: Lighthouse 13.5 (same as PSI), default simulated throttling,
// mobile (Moto G Power) and desktop, all categories. Median of RUNS per page/device; reports saved for diag.
// Usage: node psi-local.mjs <label> [cold|warm]   env: RUNS (default 3), PAGES (comma list of names)
import fs from 'node:fs';
import https from 'node:https';
import { execSync } from 'node:child_process';
import lighthouse from 'lighthouse';
import desktopConfig from 'lighthouse/core/config/desktop-config.js';
import * as chromeLauncher from 'chrome-launcher';

const [label = 'run', cache = 'cold'] = process.argv.slice(2);
const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const RUNS = Number(process.env.RUNS || 3);
const ALL = { home: '/', plp: '/women/tops-women/jackets-women.html', pdp: '/joust-duffle-bag.html', cms: '/about-us',
  cart: '/checkout/cart/', checkout: '/checkout/' };
// cart and checkout need a guest session with a product in the cart (otherwise /checkout redirects to the cart)
const needsCart = name => name === 'cart' || name === 'checkout';
let cartCookie = null;
const cookieFor = name => {
  if (!needsCart(name)) return null;
  cartCookie ??= execSync('node ../cart-session.mjs').toString().split('; ').filter(c => !c.startsWith('mage-messages=')).join('; ');
  return cartCookie;
};
const pages = (process.env.PAGES ? process.env.PAGES.split(',') : Object.keys(ALL)).map(n => [n, ALL[n]]);
fs.mkdirSync('reports', { recursive: true });

const flush = () => execSync(process.env.FLUSH_FPC_CMD || 'wsl -d Ubuntu -- bash -c "cd ~/Sites/magento-seo-baseline && bin/clinotty bin/magento cache:clean full_page </dev/null >/dev/null"');
const median = a => [...a].sort((x, y) => x - y)[Math.floor(a.length / 2)];
// Warm-up request straight to the local store (Node can't resolve the .test host itself; Chrome maps it).
const warm = path => new Promise(resolve => {
  https.get({ host: process.env.RESOLVE_IP || '127.0.0.1', port: new URL(BASE).port || 443, path, servername: HOST, headers: { Host: new URL(BASE).host }, rejectUnauthorized: false },
    res => { res.resume(); res.on('end', resolve); }).on('error', e => { console.error('warm-up failed:', e.message); resolve(); });
});
const chrome = await chromeLauncher.launch({ chromePath: process.env.CHROME_PATH,
  chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors', '--no-first-run'] });

const rows = [];
for (const [name, path] of pages) {
  for (const device of ['mobile', 'desktop']) {
    const runs = [];
    for (let i = 0; i < RUNS; i++) {
      if (cache === 'cold') flush();
      else { await warm(path); await warm(path); }
      const cookie = cookieFor(name);
      const r = await lighthouse(BASE + path, { port: chrome.port, output: 'json', logLevel: 'error',
        ...(cookie && { extraHeaders: { Cookie: cookie }, disableStorageReset: true }) },
        device === 'desktop' ? desktopConfig : undefined);
      const l = r.lhr, a = l.audits, c = l.categories;
      if (i === 0) fs.writeFileSync(`reports/${label}-${cache}-${name}-${device}.json`, r.report);
      runs.push({
        performance: c.performance.score * 100, accessibility: c.accessibility.score * 100,
        bestPractices: c['best-practices'].score * 100, seo: c.seo.score * 100,
        FCP: a['first-contentful-paint'].numericValue, LCP: a['largest-contentful-paint'].numericValue,
        TBT: a['total-blocking-time'].numericValue, CLS: a['cumulative-layout-shift'].numericValue,
        SI: a['speed-index'].numericValue, TTFB: a['server-response-time']?.numericValue ?? null,
        failing: Object.values(c).flatMap(cat => cat.auditRefs
          .filter(ref => a[ref.id] && a[ref.id].score !== null && a[ref.id].score < 0.9 && a[ref.id].scoreDisplayMode !== 'informative')
          .map(ref => ref.id)),
      });
    }
    const m = { page: name, device, cache };
    for (const k of Object.keys(runs[0])) m[k] = typeof runs[0][k] === 'number' ? Math.round(median(runs.map(r => r[k])) * 1000) / 1000 : runs[0][k];
    m.failing = [...new Set(runs.flatMap(r => r.failing))];
    rows.push(m);
    console.log(`${name.padEnd(5)} ${device.padEnd(7)} ${cache} | perf ${m.performance} a11y ${m.accessibility} bp ${m.bestPractices} seo ${m.seo} | FCP ${Math.round(m.FCP)} LCP ${Math.round(m.LCP)} TBT ${Math.round(m.TBT)} CLS ${m.CLS.toFixed(3)} SI ${Math.round(m.SI)} TTFB ${Math.round(m.TTFB)}`);
  }
}
await chrome.kill();
fs.writeFileSync(`${label}-${cache}.json`, JSON.stringify({ at: new Date().toISOString(), runsPerCell: RUNS, rows }, null, 2));
console.log('failing audits (any page):', [...new Set(rows.flatMap(r => r.failing))].join(', '));
