// Prints the baseline as tables; also checks the cart/checkout runs had a filled cart.
import fs from 'node:fs';

const rows = ['baseline.json', 'baseline-checkout.json']
  .filter(f => fs.existsSync(f)).flatMap(f => JSON.parse(fs.readFileSync(f, 'utf8')).results);

const lhr = name => {
  const h = fs.readFileSync(`reports/${name}.html`, 'utf8');
  return JSON.parse(h.match(/window.__LIGHTHOUSE_JSON__ = (\{.*?\});<\/script>/s)[1]);
};

console.log('page | form | perf | a11y | bp | seo | LCP ms | FCP ms | TBT ms | CLS | TTFB ms | KB | requests | failed SEO audits');
for (const r of rows) for (const ff of ['mobile', 'desktop']) {
  const m = r[ff];
  if (!m) { console.log(`${r.name} | ${ff} | ${r.lighthouse}`); continue; }
  console.log([r.name, ff, m.performance, m.accessibility, m.bestPractices, m.seo, Math.round(m.lcp), Math.round(m.fcp),
    Math.round(m.tbt), m.cls.toFixed(3), Math.round(m.ttfb), Math.round(m.totalBytes / 1024), m.requests,
    m.failedSeoAudits.join(',')].join(' | '));
}

console.log('\npage | status | title | metaDesc | metaRobots | canonical | hreflang | prev/next | h1 | JSON-LD | microdata');
for (const r of rows) {
  const t = r.tags;
  console.log([r.name, t.status, t.title, t.metaDescription ? 'yes' : 'NONE', t.metaRobots, t.canonical.join(' ') || 'NONE',
    t.hreflang.length, t.relPrevNext.join(',') || '-', t.h1Count, t.jsonLdTypes.join(',') || 'NONE',
    t.microdataTypes.join(',') || 'NONE'].join(' | '));
}

for (const name of ['cart-mobile', 'checkout-mobile'].filter(n => fs.existsSync(`reports/${n}.html`))) {
  const l = lhr(name);
  const requests = l.audits['network-requests'].details.items.map(i => i.url);
  console.log(`\n${name}: final URL ${l.finalDisplayedUrl}; product image requested: ${requests.some(u => /mb01-blue/i.test(u))}`);
}
