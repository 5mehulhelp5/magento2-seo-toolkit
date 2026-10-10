// Audits /sitemap.xml against what the site actually serves: status, redirects, robots, canonical per URL.
// Usage: node sitemap-check.mjs [sample-size]
import https from 'node:https';

const HOST = 'magento-seo.test';
const get = path => new Promise((resolve, reject) => {
  https.get({ host: '127.0.0.1', port: 8443, path, servername: HOST, rejectUnauthorized: false,
    headers: { Host: `${HOST}:8443` } }, res => {
    let body = ''; res.on('data', c => body += c); res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
  }).on('error', reject);
});
const local = url => url.replace(/^https?:\/\/[^/]+/, '');

const index = await get('/sitemap.xml');
console.log(`/sitemap.xml -> HTTP ${index.status}, ${index.body.length} bytes`);
if (index.status !== 200) process.exit(0);

let xml = index.body;
const subs = [...xml.matchAll(/<sitemap>\s*<loc>([^<]+)<\/loc>/g)].map(m => m[1]);
for (const s of subs) xml += (await get(local(s))).body;

const entries = [...xml.matchAll(/<url>([\s\S]*?)<\/url>/g)].map(m => ({
  loc: (m[1].match(/<loc>([^<]+)<\/loc>/) || [])[1],
  images: (m[1].match(/<image:image>/g) || []).length,
  hreflang: (m[1].match(/hreflang=/g) || []).length,
}));
const robotsTxt = (await get('/robots.txt')).body;
console.log(`sub-sitemaps: ${subs.length}, URLs: ${entries.length}, with images: ${entries.filter(e => e.images).length}, with hreflang: ${entries.filter(e => e.hreflang).length}`);
console.log(`robots.txt references sitemap: ${/^sitemap:/im.test(robotsTxt)}`);

const kinds = { cms: 0, category: 0, product: 0, other: 0 };
for (const e of entries) {
  const p = local(e.loc);
  if (/\.html$/.test(p)) (p.split('/').length > 2 ? kinds.category++ : kinds.product++);
  else if (p === '/' || !p.includes('.')) kinds.cms++;
  else kinds.other++;
}
console.log('rough split (cms / multi-segment .html / single .html / other):', kinds);

// Every sitemap URL should be a 200, indexable, self-canonical page.
const sample = Number(process.argv[2] || 60);
const step = Math.max(1, Math.floor(entries.length / sample));
const problems = [];
for (let i = 0; i < entries.length; i += step) {
  const url = entries[i].loc;
  const r = await get(local(url));
  const robots = (r.body.match(/<meta name="robots" content="([^"]*)"/i) || [])[1] || '';
  const canonical = (r.body.match(/<link[^>]+rel="canonical"[^>]+href="([^"]*)"/i) || [])[1];
  const issue = r.status !== 200 ? `HTTP ${r.status}${r.headers.location ? ' -> ' + local(r.headers.location) : ''}`
    : /noindex/i.test(robots) ? `robots ${robots}`
    : canonical && local(canonical) !== local(url) ? `canonical points to ${local(canonical)}` : null;
  if (issue) problems.push(`${local(url)}: ${issue}`);
}
console.log(`checked ${Math.ceil(entries.length / step)} sampled URLs, problems: ${problems.length}`);
for (const p of problems) console.log('  ' + p);
// The CMS entries are few and the most error-prone, so list them all.
console.log('CMS-like entries:', entries.map(e => local(e.loc)).filter(p => p === '/' || !p.includes('.')).join('  '));
