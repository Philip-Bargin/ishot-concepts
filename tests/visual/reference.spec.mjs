// Captures every prototype state (16 states x 3 widths x 2 themes).
//   VISUAL_MODE=reference  writes partial reference data (scripts/visual-reference.mjs merges it into
//                          tests/visual/reference/*.json and keeps the PNGs in .output/reference/).
//   default (check)        captures again and compares with the committed reference.
import fs from 'node:fs';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { captureState, contextOptions, THEMES, VIEWPORTS } from './capture.mjs';
import { compareStates } from './compare.mjs';
import { assertPrototypeIntegrity } from './integrity.mjs';
import { ROUTES } from './routes.mjs';

const OUT = path.resolve('tests/visual/.output');
const REF_DIR = path.resolve('tests/visual/reference');
const MODE = process.env.VISUAL_MODE === 'reference' ? 'reference' : 'check';
const BASE = process.env.VISUAL_BASE_URL || `http://localhost:${process.env.VISUAL_PORT || 4173}`;

const readJson = (file) => (fs.existsSync(file) ? JSON.parse(fs.readFileSync(file, 'utf8')) : null);
const refGeometry = readJson(path.join(REF_DIR, 'geometry.json'));
const refStyles = readJson(path.join(REF_DIR, 'styles.json'));
const keyOf = (name, width, theme) => `${name}@${width}@${theme}`;

test.beforeAll(() => {
  assertPrototypeIntegrity();
});

async function capture(browser, route, width, theme) {
  const context = await browser.newContext(contextOptions(width));
  try {
    const page = await context.newPage();
    return await captureState(page, { url: `${BASE}${route.prototypePath}`, width, theme, landmarks: route.landmarks });
  } finally {
    await context.close();
  }
}

const browserMeta = (browser, testInfo) => ({
  browser: `${browser.browserType().name()} ${browser.version()}`,
  channel: testInfo.project.use.channel ?? 'bundled',
});

for (const route of ROUTES) {
  for (const width of Object.keys(VIEWPORTS).map(Number)) {
    for (const theme of THEMES) {
      const key = keyOf(route.name, width, theme);
      test(`${MODE}: ${key}`, async ({ browser }, testInfo) => {
        const shot = await capture(browser, route, width, theme);
        expect(shot.geometry.length, 'landmarks found').toBeGreaterThan(5);

        if (MODE === 'reference') {
          fs.mkdirSync(path.join(OUT, 'reference'), { recursive: true });
          fs.mkdirSync(path.join(OUT, 'partials'), { recursive: true });
          fs.writeFileSync(path.join(OUT, 'reference', `${key}.png`), shot.png);
          fs.writeFileSync(
            path.join(OUT, 'partials', `${key}.json`),
            JSON.stringify({ key, meta: browserMeta(browser, testInfo), geometry: shot.geometry, styles: shot.styles })
          );
          return;
        }

        expect(refGeometry?.states[key], `no committed reference for ${key}; run npm run visual:reference`).toBeTruthy();
        const refPngFile = path.join(OUT, 'reference', `${key}.png`);
        const ref = {
          png: fs.existsSync(refPngFile) ? fs.readFileSync(refPngFile) : null,
          geometry: refGeometry.states[key],
          styles: refStyles.states[key],
        };
        const result = compareStates(ref, shot);
        fs.mkdirSync(path.join(OUT, 'current'), { recursive: true });
        fs.writeFileSync(path.join(OUT, 'current', `${key}.png`), shot.png);
        fs.writeFileSync(path.join(OUT, 'current', `${key}.result.json`), JSON.stringify({ key, ...result }));
        expect(result.geometryFailures, 'geometry').toEqual([]);
        expect(result.styleFailures, 'styles').toEqual([]);
        expect(result.regionDiffs.filter((r) => r.flagged), 'regions over 1 %').toEqual([]);
      });
    }
  }
}

test('determinism: two captures of the same state are identical', async ({ browser }) => {
  const route = ROUTES.find((r) => r.name === 'home');
  const a = await capture(browser, route, 1440, 'dark');
  const b = await capture(browser, route, 1440, 'dark');
  expect(b.geometry).toEqual(a.geometry);
  expect(b.styles).toEqual(a.styles);
  const result = compareStates(a, b);
  expect(result.geometryFailures).toEqual([]);
  expect(result.styleFailures).toEqual([]);
  expect(result.regionDiffs.filter((r) => r.pixels !== 0)).toEqual([]);
});
