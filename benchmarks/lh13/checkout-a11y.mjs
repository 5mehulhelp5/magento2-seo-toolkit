// Checkout details for the remaining audits: non-<li> children of the steps list, the sidebar dialog, console issues.
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';
import * as chromeLauncher from 'chrome-launcher';

const BASE = (process.env.STORE_URL || 'https://magento-seo.test:8443').replace(/\/$/, ''), HOST = new URL(BASE).hostname;
const chrome = await chromeLauncher.launch({ chromeFlags: ['--headless=new', `--host-resolver-rules=MAP ${HOST} ${process.env.RESOLVE_IP || '127.0.0.1'}`, '--ignore-certificate-errors'] });
const browser = await puppeteer.connect({ browserURL: `http://127.0.0.1:${chrome.port}`, defaultViewport: { width: 1366, height: 900 } });
const page = await browser.newPage();
const cookie = execSync('node ../cart-session.mjs').toString().split('; ').filter(c => !c.startsWith('mage-messages=')).join('; ');
await page.setExtraHTTPHeaders({ Cookie: cookie });
const msgs = [];
page.on('console', m => { if (['error', 'warning'].includes(m.type())) msgs.push(`${m.type()}: ${m.text().slice(0, 200)}`); });
await page.goto(BASE + '/checkout/', { waitUntil: 'networkidle0', timeout: 90000 });
await new Promise(r => setTimeout(r, 3000));
console.log(JSON.stringify(await page.evaluate(() => ({
  stepsChildren: [...document.querySelectorAll('#checkoutSteps > *')].map(e => `${e.tagName.toLowerCase()}#${e.id}[role=${e.getAttribute('role')}]`),
  dialog: (document.querySelector('aside.opc-sidebar')?.outerHTML || '').slice(0, 260),
  sidebarTitle: document.querySelector('.opc-block-summary > .title, .opc-sidebar .title')?.outerHTML.slice(0, 160) || null,
})), null, 1));
console.log('console:', msgs.length ? '\n  ' + msgs.join('\n  ') : 'none');
const l = JSON.parse(fs.readFileSync('reports/checkout3-warm-checkout-desktop.json', 'utf8'));
for (const r of l.categories['best-practices'].auditRefs) {
  const a = l.audits[r.id];
  if (a.score !== null && a.score < 1) console.log('BP fail:', r.id, JSON.stringify(a.details?.items?.slice(0, 2) || '').slice(0, 300));
}
await browser.disconnect(); await chrome.kill();
