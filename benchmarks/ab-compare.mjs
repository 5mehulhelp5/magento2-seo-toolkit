// Aggregates the interleaved A/B runs (stage-ab-{off,on}-r*.json): median per page/form factor across rounds.
import fs from 'node:fs';

const median = a => { const s = a.filter(x => x != null).sort((x, y) => x - y); return s.length ? s[Math.floor(s.length / 2)] : null; };
const files = fs.readdirSync('.').filter(f => /^stage-ab-(off|on)-r\d+\.json$/.test(f));
const arms = { off: [], on: [] };
for (const f of files) arms[f.match(/ab-(off|on)/)[1]].push(...JSON.parse(fs.readFileSync(f, 'utf8')).results);

const metrics = ['performance', 'lcp', 'fcp', 'tbt', 'cls', 'mainThread', 'totalBytes'];
const fmt = (k, v) => v == null ? '-' : k === 'performance' ? Math.round(v) : k === 'cls' ? v.toFixed(3)
  : k === 'totalBytes' ? Math.round(v / 1024) + 'KB' : (v / 1000).toFixed(2) + 's';

const rounds = Math.min(...Object.values(arms).map(a => a.length)) / 5;
console.log(`rounds per arm: ${rounds}`);
for (const ff of ['mobile', 'desktop']) {
  console.log(`\n## ${ff} (median of rounds; off -> on)\npage | ${metrics.join(' | ')}`);
  for (const page of [...new Set(arms.off.map(r => r.name))]) {
    const cell = k => {
      const [a, b] = ['off', 'on'].map(arm => median(arms[arm].filter(r => r.name === page).map(r => r[ff]?.[k])));
      return `${fmt(k, a)} -> ${fmt(k, b)}`;
    };
    console.log([page, ...metrics.map(cell)].join(' | '));
  }
}
