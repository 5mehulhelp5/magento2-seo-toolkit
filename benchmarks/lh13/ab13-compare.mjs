// Summarises ab13.ps1 runs: per page/device, every round per arm and the median.
// Usage: node ab13-compare.mjs <label>
import fs from 'node:fs';

const label = process.argv[2];
const median = a => [...a].sort((x, y) => x - y)[Math.floor(a.length / 2)];
const rows = { A: [], B: [] };
for (const f of fs.readdirSync('.').filter(f => f.startsWith(`ab-${label}-`) && f.endsWith('-warm.json'))) {
  rows[f.match(/-([AB])-r\d+-warm\.json$/)[1]].push(...JSON.parse(fs.readFileSync(f, 'utf8')).rows);
}
const fmt = { performance: v => v, FCP: v => Math.round(v), LCP: v => Math.round(v), TBT: v => Math.round(v), CLS: v => v.toFixed(3), SI: v => Math.round(v) };
for (const key of [...new Set(rows.A.map(r => `${r.page} ${r.device}`))]) {
  const [page, device] = key.split(' ');
  const cells = Object.entries(fmt).map(([k, f]) => {
    const [a, b] = ['A', 'B'].map(arm => rows[arm].filter(r => r.page === page && r.device === device).map(r => r[k]));
    return `${k} ${f(median(a))}->${f(median(b))} [${a.map(f).join(' ')} | ${b.map(f).join(' ')}]`;
  });
  console.log(`${key.padEnd(13)} ${cells.join('  ')}`);
}
