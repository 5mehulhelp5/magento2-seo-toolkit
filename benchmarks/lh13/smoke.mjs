// Functional smoke test of the storefront in headless Chrome (mobile viewport): page errors, failed requests,
// script request count, and whether Luma's widgets initialised; on a PDP also adds to cart and checks the minicart.
// Usage: node smoke.mjs [label]
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const CHECKS = {
  '/': ['nav.navigation ul[role="menu"]', '[data-block="minicart"]'],
  '/about-us': ['nav.navigation ul[role="menu"]'],
  '/women/tops-women/jackets-women.html': ['nav.navigation ul[role="menu"]', '.swatch-option', '#layered-filter-block [data-role="collapsible"][role]'],
  '/gear/bags.html': ['nav.navigation ul[role="menu"]', '.toolbar-sorter'],
  '/catalogsearch/result/?q=bag': ['nav.navigation ul[role="menu"]'],
  '/olivia-1-4-zip-light-jacket.html': ['.fotorama__stage', '.swatch-attribute.size .swatch-option', '.product.data.items [role="tablist"][aria-owns]', '.product.data.items > [role="tab"]'],
  '/joust-duffle-bag.html': ['.fotorama__stage', '#product-addtocart-button'],
};
const chrome = await chromeLauncher.launch({ chromePath: process.env.CHROME_PATH,
  chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors', '--no-first-run'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}`, defaultViewport: { width: 412, height: 823 } });
let problems = 0;
for (const [path, selectors] of Object.entries(CHECKS)) {
  const page = await browser.newPage();
  const errors = [], failed = [];
  let scripts = 0;
  page.on('pageerror', e => errors.push(e.message.slice(0, 160)));
  page.on('console', m => { if (m.type() === 'error') errors.push('console: ' + m.text().slice(0, 160)); });
  page.on('requestfailed', r => failed.push(r.url().split('/').pop()));
  page.on('response', r => { if (r.request().resourceType() === 'script') scripts++; if (r.status() >= 400) failed.push(`${r.status()} ${r.url().split('/').pop()}`); });
  await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 90000 });
  await new Promise(r => setTimeout(r, 2500));
  const missing = [];
  for (const s of selectors) if (!(await page.$(s))) missing.push(s);
  let cart = '';
  if (path === '/joust-duffle-bag.html') {
    // the (synthetic) cookie banner covers the button on mobile; accept it like a visitor would
    await page.evaluate(() => document.querySelector('#demo-consent button')?.click());
    await page.click('#product-addtocart-button');
    await page.waitForFunction(() => Number(document.querySelector('.minicart-wrapper .counter-number')?.textContent || 0) > 0, { timeout: 20000 })
      .then(() => { cart = 'add-to-cart ok'; }).catch(() => { cart = 'ADD-TO-CART FAILED'; problems++; });
  }
  const bundles = await page.evaluate(() => Object.keys(window.require?.s?.contexts?._?.config?.bundles || {}).length);
  const bad = errors.length || failed.length || missing.length;
  if (bad) problems++;
  console.log(`${bad ? 'FAIL' : 'ok  '} ${path.padEnd(38)} scripts ${String(scripts).padStart(3)}  bundles ${bundles}  ${cart}`
    + (missing.length ? `\n     missing: ${missing.join(', ')}` : '') + (errors.length ? `\n     errors: ${errors.slice(0, 3).join(' || ')}` : '')
    + (failed.length ? `\n     failed: ${failed.slice(0, 5).join(', ')}` : ''));
  await page.close();
}
await browser.disconnect();
await chrome.kill();
console.log(problems ? `SMOKE: ${problems} page(s) with problems` : 'SMOKE: all pages ok');
