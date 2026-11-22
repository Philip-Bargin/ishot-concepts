// Summarises the per-state *.result.json files of a visual run (default folder tests/visual/.output/current) as a table:
// pass counts, footer-only y failures, the largest footer y offset and the largest footer-RELATIVE offset
// (each footer-* y delta minus the footer's own delta, which shows the frame shifts as one block).
//   node tests/visual/summarise-frame.mjs [currentDir] [--report]
// --report also writes tests/visual/.output/compare-report.html through the harness. Run from the repo root.
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const dirArg = process.argv.slice(2).find((a) => !a.startsWith('--'));
const dir = path.resolve(dirArg ?? 'tests/visual/.output/current');
const files = fs.readdirSync(dir).filter((f) => f.endsWith('.result.json')).sort();
const rows = {};
const classes = {};
let total = 0;
for (const f of files) {
  const r = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
  total += 1;
  const [state, width, theme] = r.key.split('@');
  const row = (rows[state] ??= { states: 0, pass: 0, footerYOnly: 0, other: 0, geom: 0, style: 0, align: 0, regionsFlagged: 0, regionsCompared: 0, maxRel: 0, maxFooterDelta: 0, otherDetail: new Set(), gate: r.gate });
  row.states += 1;
  const g = r.geometryFailures;
  const footerDelta = g.find((x) => x.name === 'footer' && x.field === 'y')?.delta ?? 0;
  const isFooterY = (x) => x.field === 'y' && /^footer/.test(x.name);
  const nonFooterY = g.filter((x) => !isFooterY(x));
  for (const x of g.filter(isFooterY)) row.maxRel = Math.max(row.maxRel, Math.abs(x.delta - footerDelta));
  row.maxFooterDelta = Math.max(row.maxFooterDelta, Math.abs(footerDelta));
  row.geom += g.length;
  row.style += r.styleFailures.length;
  row.align += r.alignmentFailures.length;
  if (r.regionStatus === 'compared') row.regionsCompared += 1;
  const flagged = r.regionDiffs.filter((d) => d.flagged);
  row.regionsFlagged += flagged.length;
  for (const d of flagged) row.otherDetail.add(`region ${d.name} ${(d.ratio * 100).toFixed(2)}%@${width}@${theme}`);
  if (!g.length && !r.styleFailures.length && !r.alignmentFailures.length) row.pass += 1;
  else if (!nonFooterY.length && !r.styleFailures.length && !r.alignmentFailures.length) row.footerYOnly += 1;
  else {
    row.other += 1;
    for (const x of nonFooterY) row.otherDetail.add(`geom ${x.name}.${x.field} Δ${x.delta}@${width}@${theme}`);
    for (const x of r.styleFailures) row.otherDetail.add(`style ${x.name}.${x.property ?? x.field} ${JSON.stringify(x.expected)}→${JSON.stringify(x.actual)}@${width}@${theme}`);
    for (const x of r.alignmentFailures) row.otherDetail.add(`align ${JSON.stringify(x)}`);
  }
}
console.log(`states: ${total}`);
console.log('| State | Gate | States | A+C pass | Footer y only | Other fail | Geometry fails | Style fails | Alignment fails | Max footer Δy px | Max footer-relative Δ px | Regions compared | Regions >1 % (review) |');
console.log('|---|---|---|---|---|---|---|---|---|---|---|---|---|');
for (const [s, r] of Object.entries(rows)) {
  console.log(`| ${s} | ${r.gate ? 'yes' : 'review-only'} | ${r.states} | ${r.pass} | ${r.footerYOnly} | ${r.other} | ${r.geom} | ${r.style} | ${r.align} | ${r.maxFooterDelta.toFixed(2)} | ${r.maxRel.toFixed(2)} | ${r.regionsCompared} | ${r.regionsFlagged} |`);
}
for (const [s, r] of Object.entries(rows)) if (r.otherDetail.size) console.log(`DETAIL ${s}: ${[...r.otherDetail].slice(0, 40).join('; ')}`);
if (process.argv.includes('--report')) {
  const { writeReport } = await import(pathToFileURL(path.resolve('tests/visual/compare.mjs')).href);
  const entries = files.map((f) => {
    const r = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
    return { ...r, refImage: `reference/${r.key}.png`, actualImage: `current/${r.key}.png` };
  });
  writeReport(path.resolve('tests/visual/.output/compare-report.html'), entries);
  console.log('report written');
}
