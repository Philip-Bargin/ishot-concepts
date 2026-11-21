// Orchestrates the visual harness on the prototype.
//   node scripts/visual-reference.mjs           regenerate tests/visual/reference/{geometry,styles}.json
//   node scripts/visual-reference.mjs --check   re-capture and compare with the committed reference
// Order: integrity check (aborts), prototype build, Playwright (its webServer runs vite preview), merge/report.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { assertPrototypeIntegrity } from '../tests/visual/integrity.mjs';
import { referenceFingerprint, writeReport } from '../tests/visual/compare.mjs';

const check = process.argv.includes('--check');
const root = path.resolve(import.meta.dirname, '..');
const out = path.join(root, 'tests/visual/.output');
const refDir = path.join(root, 'tests/visual/reference');

try {
  console.log(assertPrototypeIntegrity().message);
} catch (err) {
  console.error(`\n${err.message}\n`);
  process.exit(2);
}

// One command string (no args array): works through the shell on Windows without Node's DEP0190 warning.
const run = (command, env = {}) => spawnSync(command, { cwd: root, stdio: 'inherit', shell: true, env: { ...process.env, ...env } }).status ?? 1;

if (run('npm run build') !== 0) process.exit(1);

for (const dir of check ? ['current', 'results'] : ['partials', 'current', 'results', 'reference']) fs.rmSync(path.join(out, dir), { recursive: true, force: true });
const status = run('npx playwright test', check ? {} : { VISUAL_MODE: 'reference' });

if (check) {
  const dir = path.join(out, 'current');
  const entries = fs.existsSync(dir)
    ? fs
        .readdirSync(dir)
        .filter((f) => f.endsWith('.result.json'))
        .sort()
        .map((f) => {
          const r = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
          return { ...r, refImage: `reference/${r.key}.png`, actualImage: `current/${r.key}.png` };
        })
    : [];
  writeReport(path.join(out, 'compare-report.html'), entries);
  const notRun = entries.filter((e) => e.regionStatus !== 'compared');
  if (notRun.length) console.warn(`WARNING: region comparison did NOT run for ${notRun.length} of ${entries.length} states (${notRun[0].regionNote}).`);
  console.log(`Side-by-side report: ${path.join(out, 'compare-report.html')}`);
  process.exit(status);
}

if (status !== 0) process.exit(status);

const partialsDir = path.join(out, 'partials');
const partials = fs
  .readdirSync(partialsDir)
  .filter((f) => f.endsWith('.json'))
  .sort()
  .map((f) => JSON.parse(fs.readFileSync(path.join(partialsDir, f), 'utf8')));
if (partials.length !== 96) {
  console.error(`Expected 96 states, captured ${partials.length}.`);
  process.exit(1);
}
const meta = { ...partials[0].meta, states: partials.length };
const sorted = (pick) => Object.fromEntries(partials.map((p) => [p.key, pick(p)]).sort(([a], [b]) => a.localeCompare(b)));
fs.mkdirSync(refDir, { recursive: true });
const write = (name, states) => fs.writeFileSync(path.join(refDir, name), `${JSON.stringify({ meta, states }, null, 1)}\n`);
write('geometry.json', sorted((p) => ({ counts: p.counts, landmarks: p.geometry })));
write('styles.json', sorted((p) => p.styles));
fs.writeFileSync(
  path.join(out, 'reference', 'fingerprint.json'),
  JSON.stringify({ sha256: referenceFingerprint(path.join(refDir, 'geometry.json'), path.join(refDir, 'styles.json')) })
);
console.log(`Wrote tests/visual/reference/{geometry,styles}.json for ${partials.length} states (${meta.browser}, ${meta.channel}).`);
