// Prints the concrete items behind each failing audit of a saved Lighthouse 13 JSON report.
// Usage: node details.mjs reports/<file>.json [auditId,...]
import fs from 'node:fs';

const [file, only] = process.argv.slice(2);
const l = JSON.parse(fs.readFileSync(file, 'utf8'));
const wanted = only ? only.split(',') : null;
const cell = v => typeof v === 'object' && v !== null
  ? (v.snippet || v.url || v.text || v.selector || v.nodeLabel || v.value || JSON.stringify(v).slice(0, 80)) : String(v);
for (const [id, a] of Object.entries(l.audits)) {
  if (wanted ? !wanted.includes(id) : !(a.score !== null && a.score < 0.9 && a.scoreDisplayMode !== 'informative')) continue;
  console.log(`\n## ${id}  score=${a.score} ${a.displayValue || ''}\n   ${a.title}`);
  const d = a.details || {};
  const items = d.items || d.chains || [];
  for (const it of (Array.isArray(items) ? items : []).slice(0, 8)) {
    if (it.items && Array.isArray(it.items)) { for (const sub of it.items.slice(0, 6)) console.log('   - ' + Object.values(sub).map(cell).join(' | ').slice(0, 220)); continue; }
    console.log('   - ' + Object.entries(it).filter(([k]) => !['subItems', 'entity'].includes(k)).map(([k, v]) => `${k}=${cell(v)}`).join(' | ').slice(0, 260));
  }
}
console.log(`\nbenchmarkIndex=${l.environment?.benchmarkIndex}  cpuSlowdown=${l.configSettings?.throttling?.cpuSlowdownMultiplier}`);
