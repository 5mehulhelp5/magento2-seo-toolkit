// Lists the elements that shifted (layout-shift-elements) for the given reports.
// Usage: node cls.mjs <report-name-without-.html> [...]
import fs from 'node:fs';

for (const name of process.argv.slice(2)) {
  const l = JSON.parse(fs.readFileSync(`reports/${name}.html`, 'utf8').match(/window.__LIGHTHOUSE_JSON__ = (\{.*?\});<\/script>/s)[1]);
  console.log(`\n== ${name}  CLS ${l.audits['cumulative-layout-shift'].numericValue.toFixed(3)}`);
  for (const i of l.audits['layout-shift-elements']?.details?.items || []) {
    console.log(`  ${(i.score ?? 0).toFixed(3)}  ${i.node?.selector}  |  ${(i.node?.snippet || '').slice(0, 140)}`);
  }
}
