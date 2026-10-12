// Records every layout shift on a page (PerformanceObserver) with the moved elements and their old/new boxes.
// Usage: node cls-trace.mjs <path> [desktop|mobile]   (cart/checkout get a filled guest cart)
import { execSync } from 'node:child_process';
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const [path = '/checkout/', device = 'desktop'] = process.argv.slice(2);
const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}`,
  defaultViewport: device === 'mobile' ? { width: 412, height: 823, isMobile: true } : { width: 1366, height: 900 } });
const page = await browser.newPage();
if (/checkout/.test(path)) {
  const cookie = execSync('node ../cart-session.mjs').toString().split('; ').filter(c => !c.startsWith('mage-messages=')).join('; ');
  await page.setExtraHTTPHeaders({ Cookie: cookie });
}
await page.evaluateOnNewDocument(() => {
  window.__shifts = [];
  const name = n => !n ? '?' : (n.id ? '#' + n.id : n.nodeName.toLowerCase() + (n.className && typeof n.className === 'string' ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
  new PerformanceObserver(list => {
    for (const e of list.getEntries()) {
      if (e.hadRecentInput) continue;
      window.__shifts.push({ t: Math.round(e.startTime), value: +e.value.toFixed(4), sources: (e.sources || []).map(s => ({
        node: name(s.node) + (s.node?.parentElement ? ' < ' + name(s.node.parentElement) : ''),
        from: [s.previousRect.y, s.previousRect.height].map(Math.round), to: [s.currentRect.y, s.currentRect.height].map(Math.round) })) });
    }
  }).observe({ type: 'layout-shift', buffered: true });
});
await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 90000 });
await new Promise(r => setTimeout(r, 3000));
const shifts = await page.evaluate(() => window.__shifts);
console.log(`${path} ${device}: CLS ${shifts.reduce((a, s) => a + s.value, 0).toFixed(3)} from ${shifts.length} shifts`);
for (const s of shifts) {
  console.log(`  t=${s.t}ms value=${s.value}`);
  for (const src of s.sources) console.log(`     ${src.node.slice(0, 90)}  y,h ${src.from.join(',')} -> ${src.to.join(',')}`);
}
await browser.disconnect(); await chrome.kill();
