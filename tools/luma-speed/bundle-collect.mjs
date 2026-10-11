// Step 1 of JS bundling: load each page type in headless Chrome and record which RequireJS modules and
// text templates it fetched (id + the URL RequireJS used). Output: bundle-modules.json
// Usage: node bundle-collect.mjs
import fs from 'node:fs';
import { execSync } from 'node:child_process';
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const PAGES = {
  home: ['/'], cms: ['/about-us'],
  plp: ['/women/tops-women/jackets-women.html', '/gear/bags.html'],
  pdp: ['/joust-duffle-bag.html', '/olivia-1-4-zip-light-jacket.html'],
  search: ['/catalogsearch/result/?q=bag'],
  cart: ['/checkout/cart/'], checkout: ['/checkout/'],
};
const cookie = execSync('node ' + new URL('./cart-session.mjs', import.meta.url).pathname.replace(/^\/(\w:)/, '$1')).toString().split('; ')
  .filter(c => !c.startsWith('mage-messages=')).join('; ');

const chrome = await chromeLauncher.launch({ chromePath: process.env.CHROME_PATH,
  chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors', '--no-first-run'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}`, defaultViewport: { width: 412, height: 823 } });

const out = { collectedAt: new Date().toISOString(), base: BASE, pages: {} };
for (const [type, paths] of Object.entries(PAGES)) {
  const modules = new Map();
  for (const path of paths) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    if (type === 'cart' || type === 'checkout') await page.setExtraHTTPHeaders({ Cookie: cookie });
    await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 90000 });
    await new Promise(r => setTimeout(r, 3000));
    const found = await page.evaluate(() => {
      const ctx = window.require?.s?.contexts?._;
      if (!ctx) return [];
      const fetched = new Set(Object.keys(ctx.urlFetched || {}));
      return Object.keys(ctx.defined).map(id => {
        const bang = id.indexOf('!');
        if (bang > -1) {   // plugin resource, e.g. text!Magento_Ui/templates/x.html
          const plugin = id.slice(0, bang), resource = id.slice(bang + 1);
          return /(^|\/)text$/.test(plugin) ? { id, type: 'text', url: window.require.toUrl(resource) } : null;
        }
        const url = ctx.nameToUrl(id);
        return fetched.has(url) ? { id, type: 'js', url } : null;   // only modules loaded from their own file
      }).filter(Boolean);
    });
    for (const m of found) modules.set(m.id, m);
    console.log(`${type.padEnd(8)} ${path.padEnd(38)} ${found.length} modules, page errors: ${errors.length ? errors.join(' | ').slice(0, 200) : 'none'}`);
    await page.close();
  }
  out.pages[type] = [...modules.values()];
}
await browser.disconnect();
await chrome.kill();
fs.writeFileSync('bundle-modules.json', JSON.stringify(out, null, 1));
const all = new Set(Object.values(out.pages).flat().map(m => m.id));
console.log(`distinct modules across page types: ${all.size}`);
