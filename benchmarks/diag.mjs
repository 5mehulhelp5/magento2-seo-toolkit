// Explains a report: LCP element and phases, product/hero image requests, failed SEO audits.
// Usage: node diag.mjs <report-name-without-.html> [...]
import fs from 'node:fs';

for (const name of process.argv.slice(2)) {
  const l = JSON.parse(fs.readFileSync(`reports/${name}.html`, 'utf8').match(/window.__LIGHTHOUSE_JSON__ = (\{.*?\});<\/script>/s)[1]);
  console.log(`\n== ${name}`);
  const lcp = l.audits['largest-contentful-paint-element']?.details?.items || [];
  const node = lcp[0]?.items?.[0]?.node;
  console.log('LCP element:', node?.selector, '|', (node?.snippet || '').slice(0, 220));
  if (lcp[1]?.items) console.log('LCP phases:', lcp[1].items.map(p => `${p.phase} ${Math.round(p.timing)}`).join(', '));
  const t0 = l.audits['network-requests'].details.items[0]?.networkRequestTime ?? 0;
  for (const r of l.audits['network-requests'].details.items.filter(r => /catalog\/product|wysiwyg|\.woff2/.test(r.url))) {
    console.log(`  ${r.url.split('/').pop().slice(0, 40).padEnd(40)} prio=${r.priority} start=${Math.round(r.networkRequestTime - t0)} end=${Math.round(r.networkEndTime - t0)} KB=${Math.round(r.transferSize / 1024)}`);
  }
  const failed = l.categories.seo.auditRefs.filter(r => l.audits[r.id].score !== null && l.audits[r.id].score < 1);
  for (const r of failed) {
    const items = (l.audits[r.id].details?.items || []).slice(0, 3).map(i => (i.node?.snippet || i.source?.url || JSON.stringify(i)).slice(0, 160));
    console.log(`SEO fail: ${r.id} ${items.join(' || ')}`);
  }
}
