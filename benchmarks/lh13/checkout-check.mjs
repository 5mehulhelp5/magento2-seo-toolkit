// Checkout usability after the CLS fix: loader gone, form visible, fields typeable, shipping method selectable,
// "Next" goes to the payment step. Screenshot saved for a visual check.
import { execSync } from 'node:child_process';
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}`, defaultViewport: { width: 1366, height: 900 } });
const page = await browser.newPage();
const cookie = execSync('node ../cart-session.mjs').toString().split('; ').filter(c => !c.startsWith('mage-messages=')).join('; ');
await page.setExtraHTTPHeaders({ Cookie: cookie });
const errors = [];
page.on('pageerror', e => errors.push(e.message.slice(0, 150)));
page.on('response', async r => {
  if (/rest\/.*(shipping-information|estimate-shipping|payment-information)/.test(r.url())) {
    console.log(`     ${r.request().method()} ${r.url().replace(BASE, '').slice(0, 80)} -> ${r.status()} ${(await r.text().catch(() => '')).slice(0, 160)}`);
  }
});
const step = (ok, msg) => { console.log(`${ok ? 'ok  ' : 'FAIL'} ${msg}`); if (!ok) process.exitCode = 1; };

await page.goto(BASE + '/checkout/', { waitUntil: 'networkidle0', timeout: 90000 });
await page.waitForFunction(() => !document.querySelector('#checkout-loader'), { timeout: 30000 }).catch(() => {});
step(!(await page.$('#checkout-loader')), 'Magento checkout loader removed');
step(await page.$eval('.opc-wrapper', e => getComputedStyle(e).visibility === 'visible'), 'checkout form visible');
await page.waitForSelector('#customer-email', { visible: true, timeout: 20000 });
const fill = async (sel, v) => { await page.click(sel, { clickCount: 3 }); await page.type(sel, v); };
await fill('#customer-email', 'test@example.com');
await fill('input[name="firstname"]', 'Test');
await fill('input[name="lastname"]', 'Kunde');
await fill('input[name="street[0]"]', 'Testvej 1');
await fill('input[name="city"]', 'Austin');
await page.select('select[name="region_id"]', await page.$eval('select[name="region_id"] option:nth-child(2)', o => o.value)).catch(() => {});
await fill('input[name="postcode"]', '78701');
await fill('input[name="telephone"]', '12345678');
// leave the last field so Knockout picks up the change and re-estimates shipping before a method is chosen
await page.keyboard.press('Tab');
await new Promise(r => setTimeout(r, 2500));
step(true, 'shipping address filled in');
await page.waitForSelector('input[type="radio"][name^="ko_unique"], #checkout-shipping-method-load input[type="radio"]', { timeout: 20000 })
  .then(async r => { await r.click(); step(true, 'shipping method selected'); }).catch(() => step(false, 'shipping method selectable'));
// the (synthetic) cookie banner covers the Next button; accept it like a visitor would
await page.evaluate(() => document.querySelector('#demo-consent button')?.click());
console.log('     Next button:', await page.$$eval('button[data-role="opc-continue"]', b => b.map(x => `${x.textContent.trim()} visible=${!!x.offsetParent} disabled=${x.disabled}`).join(' ; ')));
console.log('     element at the button position:', await page.$eval('button[data-role="opc-continue"]', b => {
  b.scrollIntoView({ block: 'center' });
  const r = b.getBoundingClientRect(), hit = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
  return hit === b || b.contains(hit) ? 'the button itself' : (hit?.outerHTML || 'nothing').slice(0, 140);
}));
page.on('console', m => { if (['warning', 'error'].includes(m.type())) console.log('     console:', m.text().slice(0, 140)); });
await page.click('button[data-role="opc-continue"]');
await page.waitForSelector('#checkout-payment-method-load', { visible: true, timeout: 30000 })
  .then(() => step(true, 'reached the payment step')).catch(async () => {
    step(false, 'reach the payment step');
    console.log('     url:', page.url(), '| messages:', await page.evaluate(() =>
      [...document.querySelectorAll('.mage-error, .message-error, .field-error, [role="alert"]')].map(e => e.textContent.trim()).filter(Boolean).slice(0, 5).join(' | ') || 'none'));
  });
await page.screenshot({ path: 'screens/checkout-payment-step.png' });
step(!errors.length, 'no page errors' + (errors.length ? ': ' + errors.join(' | ') : ''));
await browser.disconnect(); await chrome.kill();
