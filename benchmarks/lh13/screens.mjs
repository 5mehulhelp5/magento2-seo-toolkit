// Full-page screenshots of key pages at phone and desktop width, plus pixel diff against another set.
// Usage: node screens.mjs <label> [compareToLabel]
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const [label, compareTo] = process.argv.slice(2);
const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const PAGES = { home: '/', cms: '/about-us', plp: '/women/tops-women/jackets-women.html', pdp: '/olivia-1-4-zip-light-jacket.html', search: '/catalogsearch/result/?q=bag' };
const VIEWPORTS = { mobile: { width: 412, height: 823, isMobile: true, deviceScaleFactor: 1 }, desktop: { width: 1366, height: 900, deviceScaleFactor: 1 } };
fs.mkdirSync(`screens/${label}`, { recursive: true });
const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors', '--hide-scrollbars'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}` });
for (const [name, path] of Object.entries(PAGES)) for (const [vp, viewport] of Object.entries(VIEWPORTS)) {
  const page = await browser.newPage();
  await page.setViewport(viewport);
  if (process.env.BLOCK_FULL_CSS) {   // only the inline used CSS applies: shows what the first render looks like
    await page.setRequestInterception(true);
    page.on('request', r => /\/css\/styles-[ml](\.min)?\.css|\/media\/styles\.css/.test(r.url()) ? r.abort() : r.continue());
  }
  await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 90000 });
  await page.evaluate(() => document.querySelector('#demo-consent')?.remove());   // synthetic banner: same in both sets
  await new Promise(r => setTimeout(r, 2000));
  const file = `screens/${label}/${name}-${vp}.png`;
  await page.screenshot({ path: file, clip: { x: 0, y: 0, width: viewport.width, height: viewport.height * 2 } });
  let diff = '';
  if (compareTo && fs.existsSync(`screens/${compareTo}/${name}-${vp}.png`)) {
    // compare in the browser: decode both PNGs on a canvas and count differing pixels
    const a = fs.readFileSync(file).toString('base64'), b = fs.readFileSync(`screens/${compareTo}/${name}-${vp}.png`).toString('base64');
    const ratio = await page.evaluate(async (a, b) => {
      const load = src => new Promise(r => { const i = new Image(); i.onload = () => r(i); i.src = 'data:image/png;base64,' + src; });
      const [ia, ib] = await Promise.all([load(a), load(b)]);
      const c = document.createElement('canvas'); c.width = ia.width; c.height = ia.height; const x = c.getContext('2d');
      x.drawImage(ia, 0, 0); const da = x.getImageData(0, 0, c.width, c.height).data;
      x.clearRect(0, 0, c.width, c.height); x.drawImage(ib, 0, 0); const db = x.getImageData(0, 0, c.width, c.height).data;
      let n = 0; for (let i = 0; i < da.length; i += 4) if (Math.abs(da[i] - db[i]) + Math.abs(da[i + 1] - db[i + 1]) + Math.abs(da[i + 2] - db[i + 2]) > 30) n++;
      return n / (da.length / 4);
    }, a, b);
    diff = ` diff vs ${compareTo}: ${(ratio * 100).toFixed(2)}% of pixels`;
  }
  console.log(`${name.padEnd(7)} ${vp.padEnd(8)} ${file}${diff}`);
  await page.close();
}
await browser.disconnect(); await chrome.kill();
