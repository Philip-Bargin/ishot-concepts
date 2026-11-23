// Orchestrates the visual harness on the prototype.
//   node scripts/visual-reference.mjs           regenerate tests/visual/reference/{geometry,styles}.json
//   node scripts/visual-reference.mjs --check   re-capture and compare with the committed reference
//   node scripts/visual-reference.mjs --check --target wp [--scope frame] [--states 404,about]
//                                               capture the WordPress dev site (VISUAL_BASE_URL, default
//                                               http://localhost:8888) and compare it with the same reference
//   node scripts/visual-reference.mjs --check [--target wp] --scope component [--components strip,hero]
//                                               the component gate (components.spec.mjs); --components implies
//                                               --scope component
//   node scripts/visual-reference.mjs --scope component [--components strip]
//                                               regenerate only those components in reference/components.json
//                                               (other components and the page states are left as they are)
// A plain reference run regenerates the 96 page states and every component.
// Order: integrity check (aborts), prototype build (not for wp), Playwright (prototype: its webServer runs
// vite preview), merge/report.
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { assertPrototypeIntegrity } from '../tests/visual/integrity.mjs';
import { componentReferenceFingerprint, referenceFingerprint, writeReport } from '../tests/visual/compare.mjs';
import { COMPONENTS, componentKey } from '../tests/visual/components.mjs';
import { resolveScope, selectComponents } from '../tests/visual/scope.mjs';

const argv = process.argv.slice(2);
const check = argv.includes('--check');
const flag = (name) => (argv.includes(`--${name}`) ? argv[argv.indexOf(`--${name}`) + 1] : undefined);
const target = flag('target') ?? process.env.VISUAL_TARGET ?? 'prototype';
const wp = target === 'wp';
if (wp && !check) {
  console.error('--target wp compares against the committed prototype reference: use it with --check.');
  process.exit(2);
}
// flags win over the environment; the child Playwright process reads the environment
const childEnv = { VISUAL_TARGET: target };
for (const [name, key] of [['scope', 'VISUAL_SCOPE'], ['states', 'VISUAL_STATES'], ['components', 'VISUAL_COMPONENTS'], ['base-url', 'VISUAL_BASE_URL']]) if (flag(name)) childEnv[key] = flag(name);
// validated here, before any build or capture (unknown names and scope conflicts are errors)
let componentScope;
let selected;
try {
  componentScope = resolveScope(childEnv.VISUAL_SCOPE ?? process.env.VISUAL_SCOPE, childEnv.VISUAL_COMPONENTS ?? process.env.VISUAL_COMPONENTS) === 'component';
  selected = selectComponents(COMPONENTS, childEnv.VISUAL_COMPONENTS ?? process.env.VISUAL_COMPONENTS);
} catch (err) {
  console.error(err.message);
  process.exit(2);
}
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

if (!wp && run('npm run build') !== 0) process.exit(1);

const componentsFile = path.join(refDir, 'components.json');
const refPngDir = path.join(out, 'reference');
if (check) {
  for (const dir of ['current', 'results']) fs.rmSync(path.join(out, dir), { recursive: true, force: true });
} else if (componentScope) {
  // component-only reference: keep the page-state PNGs and every other component's PNGs
  fs.rmSync(path.join(out, 'component-partials'), { recursive: true, force: true });
  for (const c of selected) for (const f of fs.existsSync(refPngDir) ? fs.readdirSync(refPngDir) : []) if (f.startsWith(`component.${c.name}@`)) fs.rmSync(path.join(refPngDir, f));
} else {
  for (const dir of ['partials', 'component-partials', 'current', 'results', 'reference']) fs.rmSync(path.join(out, dir), { recursive: true, force: true });
}
const specs = componentScope ? 'tests/visual/components.spec.mjs' : check ? 'tests/visual/reference.spec.mjs' : 'tests/visual/reference.spec.mjs tests/visual/components.spec.mjs';
const status = run(`npx playwright test ${specs}`, check ? childEnv : { ...childEnv, VISUAL_MODE: 'reference' });

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

/** Merges component partials into components.json: a full run replaces it, a component-only run updates its keys. */
function writeComponents({ replace }) {
  const dir = path.join(out, 'component-partials');
  const partials = fs.existsSync(dir) ? fs.readdirSync(dir).filter((f) => f.endsWith('.json')).sort().map((f) => JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'))) : [];
  const expected = selected.flatMap((c) => [375, 768, 1440].flatMap((w) => ['light', 'dark'].map((t) => componentKey(c.name, w, t))));
  const missing = expected.filter((k) => !partials.some((p) => p.key === k));
  if (missing.length) {
    console.error(`Component reference incomplete, missing: ${missing.join(', ')}`);
    process.exit(1);
  }
  const previous = !replace && fs.existsSync(componentsFile) ? JSON.parse(fs.readFileSync(componentsFile, 'utf8')).components : {};
  const merged = { ...previous };
  for (const p of partials) merged[p.key] = { counts: p.counts, landmarks: p.geometry, styles: p.styles };
  const components = Object.fromEntries(Object.entries(merged).sort(([a], [b]) => a.localeCompare(b)));
  const meta = { ...partials[0].meta, components: Object.keys(components).length };
  fs.writeFileSync(componentsFile, `${JSON.stringify({ meta, components }, null, 1)}
`);
  fs.mkdirSync(refPngDir, { recursive: true });
  fs.writeFileSync(path.join(refPngDir, 'components-fingerprint.json'), JSON.stringify({ sha256: componentReferenceFingerprint(componentsFile) }));
  console.log(`Wrote tests/visual/reference/components.json: ${partials.length} captured, ${meta.components} in total (${meta.browser}, ${meta.channel}).`);
}

if (componentScope) {
  writeComponents({ replace: false });
  process.exit(0);
}

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
writeComponents({ replace: true });
