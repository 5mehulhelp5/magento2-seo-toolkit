// One PageSpeed Insights run (Google's servers, real network) via the public PSI API.
// Usage: node psi.mjs <url> <mobile|desktop> <label> [out.jsonl]   -- appends one JSON line per run
import fs from 'node:fs';

const [url, strategy, label, out = 'psi-results.jsonl'] = process.argv.slice(2);
const api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' + new URLSearchParams({ url, strategy })
  + ['performance', 'accessibility', 'best-practices', 'seo'].map(c => `&category=${c}`).join('');

let data;
for (let attempt = 1; attempt <= 3; attempt++) {
  const res = await fetch(api);
  data = await res.json();
  if (res.ok) break;
  console.error(`attempt ${attempt}: HTTP ${res.status} ${data.error?.message?.slice(0, 120)}`);
  if (attempt === 3) process.exit(1);
  await new Promise(r => setTimeout(r, 20000 * attempt));
}

const l = data.lighthouseResult, a = l.audits, c = l.categories;
const failing = Object.values(c).flatMap(cat => cat.auditRefs
  .filter(r => r.weight > 0 && a[r.id].score !== null && a[r.id].score < 0.9)
  .map(r => `${cat.id}:${r.id}`));
const row = {
  label, strategy, url, at: new Date().toISOString(), lighthouse: l.lighthouseVersion,
  performance: Math.round(c.performance.score * 100), accessibility: Math.round(c.accessibility.score * 100),
  bestPractices: Math.round(c['best-practices'].score * 100), seo: Math.round(c.seo.score * 100),
  ttfb: Math.round(a['server-response-time']?.numericValue ?? 0), fcp: Math.round(a['first-contentful-paint'].numericValue),
  lcp: Math.round(a['largest-contentful-paint'].numericValue), tbt: Math.round(a['total-blocking-time'].numericValue),
  cls: +a['cumulative-layout-shift'].numericValue.toFixed(3), si: Math.round(a['speed-index'].numericValue),
  requests: a['network-requests']?.details?.items?.length, kb: Math.round(a['total-byte-weight'].numericValue / 1024),
  chainDepth: a['critical-request-chains']?.details?.longestChain?.length ?? null,
  failing,
};
fs.appendFileSync(out, JSON.stringify(row) + '\n');
console.log(`${label.padEnd(18)} ${strategy.padEnd(7)} perf ${row.performance} a11y ${row.accessibility} bp ${row.bestPractices} seo ${row.seo} | TTFB ${row.ttfb}ms FCP ${row.fcp} LCP ${row.lcp} TBT ${row.tbt} CLS ${row.cls} | ${row.requests} req ${row.kb}KB chain ${row.chainDepth}`);
