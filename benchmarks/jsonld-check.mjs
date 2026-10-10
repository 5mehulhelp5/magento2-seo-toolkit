// Extracts JSON-LD and leftover microdata per page and checks Google's required/recommended fields.
// Usage: node jsonld-check.mjs [out.json]
import fs from 'node:fs';
import https from 'node:https';

const HOST = 'magento-seo.test';
const pages = [
  ['home', '/'],
  ['category', '/gear/bags.html'],
  ['product-simple', '/joust-duffle-bag.html'],
  ['product-configurable', '/olivia-1-4-zip-light-jacket.html'],
];

const get = path => new Promise((resolve, reject) => {
  https.get({ host: '127.0.0.1', port: 8443, path, servername: HOST, rejectUnauthorized: false,
    headers: { Host: `${HOST}:8443` } }, res => {
    let body = ''; res.on('data', c => body += c); res.on('end', () => resolve(body));
  }).on('error', reject);
});

// Google rich-result requirements (Product snippets / merchant listings, Breadcrumb, Organization)
const checks = {
  Product: s => [
    !s.name && 'name missing',
    !s.offers && !s.aggregateRating && 'needs offers or aggregateRating',
    s.offers && !(s.offers.price ?? s.offers.lowPrice) && 'offer price missing',
    s.offers && !s.offers.priceCurrency && 'priceCurrency missing',
    !s.image && 'image recommended',
    s.offers && !s.offers.availability && 'availability recommended',
    s.aggregateRating && !(s.aggregateRating.ratingValue && s.aggregateRating.reviewCount) && 'rating incomplete',
  ],
  BreadcrumbList: s => s.itemListElement.flatMap((i, n) => [
    i.position !== n + 1 && `position ${n + 1} wrong`, !i.name && `item ${n + 1} name missing`, !i.item && `item ${n + 1} url missing`,
  ]),
  Organization: s => [!s.name && 'name missing', !s.url && 'url missing', !s.logo && 'logo recommended'],
  WebSite: s => [!s.potentialAction?.target && 'search target missing'],
};

const results = [];
for (const [name, path] of pages) {
  const html = await get(path);
  const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map(m => {
    try { return JSON.parse(m[1]); } catch (e) { return { '@type': 'INVALID JSON', error: e.message }; }
  });
  const microdata = [...html.matchAll(/itemtype="https?:\/\/schema\.org\/([A-Za-z]+)"/g)].map(m => m[1]);
  console.log(`\n== ${name} ${path}\n   leftover microdata: ${microdata.join(', ') || 'none'}`);
  for (const s of blocks) {
    const problems = (checks[s['@type']]?.(s) ?? ['unknown type']).filter(Boolean);
    const summary = s['@type'] === 'Product'
      ? `${s.name} | ${s.offers?.['@type']} ${s.offers?.price ?? `${s.offers?.lowPrice}-${s.offers?.highPrice}`} ${s.offers?.priceCurrency} | ${s.image?.length ?? 0} images | rating ${s.aggregateRating?.ratingValue ?? '-'} (${s.aggregateRating?.reviewCount ?? 0})`
      : s['@type'] === 'BreadcrumbList' ? s.itemListElement.map(i => i.name).join(' > ')
      : s.name ?? '';
    console.log(`   ${s['@type'].padEnd(15)} ${problems.length ? 'PROBLEMS: ' + problems.join('; ') : 'ok'}  ${summary}`);
  }
  results.push({ name, path, microdata, jsonld: blocks });
}
if (process.argv[2]) fs.writeFileSync(process.argv[2], JSON.stringify(results, null, 2));
