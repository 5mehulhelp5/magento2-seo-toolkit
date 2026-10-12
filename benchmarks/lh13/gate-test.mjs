// Functional test of third-party script gating in a real browser:
// nothing gated runs on load; a mouse move starts the interaction scripts; "Accept all" starts the consent
// scripts (and the ones they load); on the next page consent is remembered; no page errors throughout.
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}`, defaultViewport: { width: 1366, height: 900 } });
const page = await browser.newPage();
const seen = new Set(), errors = [];
page.on('request', r => { const m = r.url().match(/demo-3p\/([\w-]+)\.js/); if (m) seen.add(m[1]); });
page.on('pageerror', e => errors.push(e.message.slice(0, 150)));
const wait = ms => new Promise(r => setTimeout(r, ms));
const state = label => console.log(`${label.padEnd(34)} loaded: ${[...seen].sort().join(', ') || '-'}`);
let ok = true;
const expect = (cond, msg) => { if (!cond) { ok = false; console.log('  FAIL: ' + msg); } };

await page.goto(BASE + '/', { waitUntil: 'networkidle0' });
await wait(3000);
state('after load (no interaction)');
expect(seen.has('consent'), 'consent banner script must load (it is not gated)');
expect(!seen.has('tag-manager') && !seen.has('analytics') && !seen.has('chat'), 'gated scripts must not load yet');

await page.mouse.move(200, 300);
await wait(2500);
state('after first mouse move');
expect(seen.has('chat'), 'chat must load on interaction');
expect(!seen.has('tag-manager'), 'tag manager must still wait for consent');

await page.click('#demo-consent button');
await wait(2500);
state('after "Accept all"');
expect(seen.has('tag-manager') && seen.has('analytics'), 'tag manager and the analytics it loads must run after consent');

seen.clear();
await page.goto(BASE + '/about-us', { waitUntil: 'networkidle0' });
await wait(2500);
state('next page (consent remembered)');
expect(seen.has('tag-manager'), 'consent must be remembered on the next page');

console.log(errors.length ? `page errors: ${errors.join(' | ')}` : 'page errors: none');
console.log(ok && !errors.length ? 'GATE TEST: ok' : 'GATE TEST: FAILED');
await browser.disconnect(); await chrome.kill();
