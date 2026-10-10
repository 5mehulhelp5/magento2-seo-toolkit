// Deterministic SEO check: status, redirect, canonical and robots per page type, plus robots.txt.
// Usage: node seo-check.mjs [out.json]   (cart/checkout use a fresh guest cart session)
import fs from 'node:fs';
import https from 'node:https';
import { execSync } from 'node:child_process';

const HOST = 'magento-seo.test';
const pages = [
  ['home', '/'],
  ['cms', '/about-us'],
  ['category', '/women/tops-women/jackets-women.html'],
  ['category-filtered', '/women/tops-women/jackets-women.html?price=50-60'],
  ['category-filtered-color', '/women/tops-women/jackets-women.html?color=50'],
  ['category-page2', '/gear/bags.html?p=2'],
  ['category-sorted', '/gear/bags.html?product_list_order=price'],
  ['product', '/joust-duffle-bag.html'],
  ['product-via-category', '/gear/bags/joust-duffle-bag.html'],
  ['product-via-category-query', '/gear/bags/joust-duffle-bag.html?utm_source=test'],
  ['search', '/catalogsearch/result/?q=jacket'],
  ['account-login', '/customer/account/login/'],
  ['cart', '/checkout/cart/', true],
  ['checkout', '/checkout/', true],
  ['404', '/this-page-does-not-exist'],
];

const cookie = execSync('node cart-session.mjs').toString().split('; ').filter(c => !c.startsWith('mage-messages=')).join('; ');

function get(path, withCookie) {
  return new Promise((resolve, reject) => {
    https.get({ host: '127.0.0.1', port: 8443, path, servername: HOST, rejectUnauthorized: false,
      headers: { Host: `${HOST}:8443`, ...(withCookie && { Cookie: cookie }) } }, res => {
      let body = ''; res.on('data', c => body += c);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
    }).on('error', reject);
  });
}

const rows = [];
for (const [name, path, withCookie] of pages) {
  const r = await get(path, withCookie);
  const all = re => [...r.body.matchAll(re)].map(m => m[1]);
  rows.push({
    name, path, status: r.status,
    location: r.headers.location?.replace(`https://${HOST}:8443`, '') ?? null,
    canonical: all(/<link[^>]+rel="canonical"[^>]+href="([^"]*)"/gi).map(u => u.replace(`https://${HOST}:8443`, '')),
    robots: (r.body.match(/<meta name="robots" content="([^"]*)"/i) || [])[1] ?? null,
  });
}
const robotsTxt = (await get('/robots.txt')).body.trim();

const out = process.argv[2];
if (out) fs.writeFileSync(out, JSON.stringify({ checkedAt: new Date().toISOString(), rows, robotsTxt }, null, 2));
for (const r of rows) {
  console.log(`${r.name.padEnd(27)} ${r.status} ${(r.location ? '-> ' + r.location : '').padEnd(28)} canonical=${r.canonical.join(',') || 'NONE'}  robots=${r.robots}`);
}
console.log(`\nrobots.txt (${robotsTxt.split('\n').filter(Boolean).length} lines):\n${robotsTxt || '(empty)'}`);
