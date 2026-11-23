// Component visual gate (plan §6 Stage 2, ruling R-2-C): each entry of components.mjs at 3 widths x 2 themes.
// Runs when VISUAL_SCOPE=component (or VISUAL_COMPONENTS is set), and in VISUAL_MODE=reference, where it captures
// the prototype side into partials that scripts/visual-reference.mjs merges into tests/visual/reference/components.json.
//   prototype target: prototypePath / prototypeRoot      wp target: wpPath / wpRoot (fixture pages)
// Geometry is relative to the component root (components.mjs relativeToRoot); A and C gate, B (regions) is a
// review signal on the wp target. The page-state gate is reference.spec.mjs.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { captureComponent, contextOptions, THEMES, VIEWPORTS } from './capture.mjs';
import { componentReferenceFingerprint, evaluateGate, flaggedRegions, regionsGate } from './compare.mjs';
import { ROOT, compareComponent, componentKey } from './components.mjs';
import { assertPrototypeIntegrity } from './integrity.mjs';
import { ACTIVE_COMPONENTS, BASE_URL, IS_WP, SCOPE, TARGET, componentTarget } from './target.mjs';

const OUT = path.resolve('tests/visual/.output');
const REF_FILE = path.resolve('tests/visual/reference/components.json');
const FP_FILE = path.join(OUT, 'reference', 'components-fingerprint.json');
const MODE = process.env.VISUAL_MODE === 'reference' ? 'reference' : 'check';
const ACTIVE = MODE === 'reference' || SCOPE === 'component';

const ref = MODE === 'check' && ACTIVE && fs.existsSync(REF_FILE) ? JSON.parse(fs.readFileSync(REF_FILE, 'utf8')) : null;

function regionAvailability() {
  if (!ref || !fs.existsSync(FP_FILE)) return { ok: false, note: 'component reference PNGs missing: run npm run visual:reference to enable region diffs' };
  return JSON.parse(fs.readFileSync(FP_FILE, 'utf8')).sha256 === componentReferenceFingerprint(REF_FILE)
    ? { ok: true }
    : { ok: false, note: 'component reference PNGs are stale (fingerprint differs from components.json): run npm run visual:reference' };
}
const regions = MODE === 'check' && ACTIVE ? regionAvailability() : { ok: false };

test.beforeAll(() => {
  assertPrototypeIntegrity();
});

async function capture(browser, component, width, theme) {
  const { path: p, root } = componentTarget(component);
  const context = await browser.newContext(contextOptions(width));
  try {
    const page = await context.newPage();
    const url = `${BASE_URL}${p}`;
    try {
      return await captureComponent(page, { url, width, theme, root, landmarks: component.landmarks, action: component.action });
    } catch (err) {
      // WordPress: a missing root is a reported gate failure (with a full-page shot for the report), not a crash.
      if (!IS_WP || err.code !== 'ROOT_MISSING') throw err;
      const png = await page.screenshot({ fullPage: true, animations: 'disabled', caret: 'hide' });
      return { png, rootMissing: err.message, geometry: [], styles: [], counts: { [ROOT]: 0 } };
    }
  } finally {
    await context.close();
  }
}

const browserMeta = (browser, testInfo) => ({
  browser: `${browser.browserType().name()} ${browser.version()}`,
  channel: testInfo.project.use.channel ?? 'bundled',
});

for (const component of ACTIVE ? ACTIVE_COMPONENTS : []) {
  for (const width of Object.keys(VIEWPORTS).map(Number)) {
    for (const theme of THEMES) {
      const key = componentKey(component.name, width, theme);
      test(`${MODE}: ${key}`, async ({ browser }, testInfo) => {
        test.skip(MODE === 'reference' && IS_WP, 'the reference is the prototype');
        const shot = await capture(browser, component, width, theme);

        if (MODE === 'reference') {
          fs.mkdirSync(path.join(OUT, 'reference'), { recursive: true });
          fs.mkdirSync(path.join(OUT, 'component-partials'), { recursive: true });
          fs.writeFileSync(path.join(OUT, 'reference', `${key}.png`), shot.png);
          fs.writeFileSync(
            path.join(OUT, 'component-partials', `${key}.json`),
            JSON.stringify({ key, meta: browserMeta(browser, testInfo), geometry: shot.geometry, counts: shot.counts, styles: shot.styles })
          );
          return;
        }

        const entry = ref?.components?.[key];
        expect(entry, `no committed reference for ${key}; run npm run visual:reference`).toBeTruthy();
        const refPngFile = path.join(OUT, 'reference', `${key}.png`);
        const havePng = regions.ok && fs.existsSync(refPngFile);
        const fullRef = {
          png: havePng ? fs.readFileSync(refPngFile) : null,
          regionNote: regions.ok ? `reference PNG missing for ${key}` : regions.note,
          geometry: entry.landmarks,
          counts: entry.counts,
          styles: entry.styles,
        };
        const result = shot.rootMissing
          ? {
              geometryFailures: [{ name: ROOT, field: 'count', expected: 1, actual: 0, note: shot.rootMissing }],
              styleFailures: [],
              regionDiffs: [],
              regionStatus: 'not-run',
              regionNote: shot.rootMissing,
              checks: component.checks ?? ['A', 'C'],
              exceptions: component.exceptions ?? null,
            }
          : compareComponent(component, fullRef, shot);
        const gate = evaluateGate(result, { gate: true });
        fs.mkdirSync(path.join(OUT, 'current'), { recursive: true });
        fs.writeFileSync(path.join(OUT, 'current', `${key}.png`), shot.png);
        fs.writeFileSync(path.join(OUT, 'current', `${key}.result.json`), JSON.stringify({ key, gate: true, component: component.name, task: component.task, ...result }));
        if (result.regionStatus !== 'compared') testInfo.annotations.push({ type: 'warning', description: `region comparison not run: ${result.regionNote}` });
        if (component.exceptions) testInfo.annotations.push({ type: 'exception', description: `needs owner agreement: ${component.exceptions.reason}` });
        if (!gate.pass) {
          expect(result.geometryFailures, 'geometry').toEqual([]);
          expect(result.styleFailures, 'styles').toEqual([]);
        }
        const flagged = flaggedRegions(result, { gate: true });
        if (regionsGate({ gate: true, target: TARGET })) {
          expect(flagged, 'regions over 1 %').toEqual([]);
        } else if (flagged.length) {
          testInfo.annotations.push({ type: 'regions-over-1%', description: `${flagged.length} region(s) for review: ${flagged.slice(0, 10).map((r) => r.name).join(', ')}` });
        }
      });
    }
  }
}

test('determinism: two captures of the same component are identical', async ({ browser }) => {
  test.skip(!ACTIVE || IS_WP || MODE === 'reference', 'runs in a prototype component check');
  const component = ACTIVE_COMPONENTS[0];
  const a = await capture(browser, component, 1440, 'dark');
  const b = await capture(browser, component, 1440, 'dark');
  expect(b.geometry).toEqual(a.geometry);
  expect(b.styles).toEqual(a.styles);
  expect(compareComponent(component, a, b).regionDiffs.filter((r) => r.pixels !== 0)).toEqual([]);
});
