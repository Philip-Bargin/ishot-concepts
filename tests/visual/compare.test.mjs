import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PNG } from 'pngjs';
import { compareStates } from './compare.mjs';

const lm = (over = {}) => ({ name: 'card[0]', x: 20, y: 100, width: 100, height: 60, ...over });
const st = (over = {}) => ({
  name: 'card[0]',
  styles: { 'font-size': '16px', color: 'rgb(10, 10, 10)', padding: '8px 8px 8px 8px' },
  ...over,
});

function makePng(width, height, paint) {
  const png = new PNG({ width, height });
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const [r, g, b] = paint(x, y);
      const i = (width * y + x) * 4;
      png.data[i] = r;
      png.data[i + 1] = g;
      png.data[i + 2] = b;
      png.data[i + 3] = 255;
    }
  }
  return PNG.sync.write(png);
}

const state = (over = {}) => ({ png: null, geometry: [lm()], styles: [st()], ...over });

test('identical inputs produce no failures', () => {
  const png = makePng(200, 200, (x, y) => [x % 256, y % 256, 90]);
  const a = state({ png });
  const b = state({ png });
  const r = compareStates(a, b);
  assert.deepEqual(r.geometryFailures, []);
  assert.deepEqual(r.styleFailures, []);
  assert.equal(r.regionDiffs.length, 1);
  assert.equal(r.regionDiffs[0].ratio, 0);
  assert.equal(r.regionDiffs[0].flagged, false);
});

test('a 3 px shift is a geometry failure, a 2 px shift is not', () => {
  const shifted = compareStates(state(), state({ geometry: [lm({ x: 23 })] }));
  assert.equal(shifted.geometryFailures.length, 1);
  assert.equal(shifted.geometryFailures[0].name, 'card[0]');
  assert.equal(shifted.geometryFailures[0].field, 'x');
  const within = compareStates(state(), state({ geometry: [lm({ x: 22, y: 98, width: 102 })] }));
  assert.deepEqual(within.geometryFailures, []);
});

test('text blocks allow 4 px in height, other boxes only 2 px', () => {
  const text = compareStates(state({ geometry: [lm({ text: true })] }), state({ geometry: [lm({ text: true, height: 64 })] }));
  assert.deepEqual(text.geometryFailures, []);
  const textTooFar = compareStates(state({ geometry: [lm({ text: true })] }), state({ geometry: [lm({ text: true, height: 65 })] }));
  assert.equal(textTooFar.geometryFailures.length, 1);
  const box = compareStates(state(), state({ geometry: [lm({ height: 64 })] }));
  assert.equal(box.geometryFailures.length, 1);
});

test('a landmark missing from the actual page is a geometry failure', () => {
  const r = compareStates(state(), state({ geometry: [] }));
  assert.equal(r.geometryFailures.length, 1);
  assert.equal(r.geometryFailures[0].field, 'missing');
});

test('a changed colour is a style failure', () => {
  const changed = state({ styles: [st({ styles: { 'font-size': '16px', color: 'rgb(10, 10, 11)', padding: '8px 8px 8px 8px' } })] });
  const r = compareStates(state(), changed);
  assert.equal(r.styleFailures.length, 1);
  assert.equal(r.styleFailures[0].property, 'color');
  assert.equal(r.styleFailures[0].expected, 'rgb(10, 10, 10)');
  assert.equal(r.styleFailures[0].actual, 'rgb(10, 10, 11)');
});

test('anti-aliasing-only noise stays under 1 % of the region', () => {
  const base = (x, y) => (x > 40 && x < 160 && y > 40 && y < 160 ? [30, 60, 120] : [240, 240, 240]);
  const a = makePng(200, 200, base);
  // sub-threshold colour noise on a sparse set of pixels: what resampling and hinting produce
  const b = makePng(200, 200, (x, y) => {
    const [r, g, bl] = base(x, y);
    return (x * 7 + y * 13) % 5 === 0 ? [r + 3, g - 3, bl + 3] : [r, g, bl];
  });
  const geometry = [lm({ x: 0, y: 0, width: 200, height: 200 })];
  const r = compareStates(state({ png: a, geometry }), state({ png: b, geometry }));
  assert.ok(r.regionDiffs[0].ratio < 0.01, `ratio ${r.regionDiffs[0].ratio}`);
  assert.equal(r.regionDiffs[0].flagged, false);
});

test('a real visual change flags the region', () => {
  const a = makePng(200, 200, () => [240, 240, 240]);
  const b = makePng(200, 200, (x) => (x < 100 ? [240, 240, 240] : [10, 10, 10]));
  const geometry = [lm({ x: 0, y: 0, width: 200, height: 200 })];
  const r = compareStates(state({ png: a, geometry }), state({ png: b, geometry }));
  assert.equal(r.regionDiffs[0].flagged, true);
  assert.ok(r.regionDiffs[0].ratio > 0.4);
});

test('a region outside the page image on both sides is skipped, not flagged', () => {
  const png = makePng(100, 100, () => [200, 200, 200]);
  const geometry = [lm({ x: 150, y: 10, width: 50, height: 50 })];
  const r = compareStates(state({ png, geometry }), state({ png, geometry }));
  assert.deepEqual(r.regionDiffs, []);
});
