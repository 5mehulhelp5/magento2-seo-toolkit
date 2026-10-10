// Before/after table across optimisation stages (stage-*.json, real throttling).
// Main-thread time and TTI come from the stage JSON when recorded, otherwise from the first-run report.
import fs from 'node:fs';

const stages = fs.readdirSync('.').filter(f => /^stage-.*\.json$/.test(f)).sort()
  .map(f => ({ name: f.slice(6, -5), results: JSON.parse(fs.readFileSync(f, 'utf8')).results }));

function fromReport(stage, page, ff) {
  const file = `reports/stage-${stage}-${page}-${ff}.html`;
  if (!fs.existsSync(file)) return {};
  const l = JSON.parse(fs.readFileSync(file, 'utf8').match(/window.__LIGHTHOUSE_JSON__ = (\{.*?\});<\/script>/s)[1]);
  return { mainThread: l.audits['mainthread-work-breakdown'].numericValue, tti: l.audits.interactive?.numericValue };
}

const s = ms => ms == null ? '-' : (ms / 1000).toFixed(1) + 's';
for (const ff of ['mobile', 'desktop']) {
  console.log(`\n## ${ff}\npage | stage | perf | LCP | FCP | TBT | CLS | TTI | main thread | KB`);
  for (const page of stages[0].results.map(r => r.name)) {
    for (const st of stages) {
      const m = st.results.find(r => r.name === page)?.[ff];
      if (!m) continue;
      const rep = m.mainThread == null ? fromReport(st.name, page, ff) : m;
      console.log([page, st.name, m.performance, s(m.lcp), s(m.fcp), Math.round(m.tbt) + 'ms', m.cls.toFixed(3),
        s(rep.tti), s(rep.mainThread), Math.round(m.totalBytes / 1024)].join(' | '));
    }
  }
}
