// Summarises an ab-setting.ps1 run: every round per arm plus the median, so outlier runs stay visible.
// Usage: node ab-setting-compare.mjs <label>
import fs from 'node:fs';

const label = process.argv[2];
const median = a => { const s = a.filter(x => x != null).sort((x, y) => x - y); return s.length ? s[Math.floor(s.length / 2)] : null; };
const runs = { 0: [], 1: [] };
for (const f of fs.readdirSync('.').filter(f => new RegExp(`^ab-${label}-[01]-r\\d+\\.json$`).test(f))) {
  runs[f.match(/-([01])-r/)[1]].push(...JSON.parse(fs.readFileSync(f, 'utf8')).results);
}
const show = {
  performance: v => Math.round(v), lcp: v => (v / 1000).toFixed(2) + 's', tbt: v => Math.round(v) + 'ms',
  cls: v => v.toFixed(3), totalBytes: v => Math.round(v / 1024) + 'KB',
};
for (const ff of ['mobile', 'desktop']) {
  console.log(`\n## ${label}, ${ff}: value 0 -> 1 (median; individual rounds in brackets)`);
  for (const page of [...new Set(runs[0].map(r => r.name))]) {
    const cells = Object.entries(show).map(([k, f]) => {
      const arm = v => runs[v].filter(r => r.name === page).map(r => r[ff]?.[k]);
      const [a, b] = [arm(0), arm(1)];
      return `${k} ${f(median(a))} [${a.map(f).join(' ')}] -> ${f(median(b))} [${b.map(f).join(' ')}]`;
    });
    const html = v => runs[v].filter(r => r.name === page).map(r => r.tags.htmlBytes)[0];
    console.log(`${page}: html ${html(0)} -> ${html(1)} bytes\n  ` + cells.join('\n  '));
  }
}
