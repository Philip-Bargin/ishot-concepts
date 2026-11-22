import { test } from 'node:test';
import assert from 'node:assert/strict';
import { PNG } from 'pngjs';
import { compareStates, evaluateGate, flaggedRegions } from './compare.mjs';

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
  assert.equal(r.geometryFailures[0].field, 'count');
  assert.equal(r.geometryFailures[0].expected, 1);
  assert.equal(r.geometryFailures[0].actual, 0);
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

const cards = (n) => Array.from({ length: n }, (_, i) => lm({ name: `card[${i}]`, y: 100 + i * 80 }));
const cardStyles = (n, color = 'rgb(1, 1, 1)') => Array.from({ length: n }, (_, i) => st({ name: `card[${i}]`, styles: { color } }));

test('an extra landmark that exists only in the actual page is reported', () => {
  const r = compareStates(
    { png: null, geometry: cards(3), styles: cardStyles(3), counts: { card: 3 } },
    { png: null, geometry: cards(4), styles: cardStyles(4), counts: { card: 4 } }
  );
  assert.equal(r.geometryFailures.length, 1);
  assert.deepEqual([r.geometryFailures[0].name, r.geometryFailures[0].field, r.geometryFailures[0].expected, r.geometryFailures[0].actual], ['card', 'count', 3, 4]);
});

test('a missing middle item is one count failure, with no index-shifted cascade', () => {
  const actual = cards(4).filter((_, i) => i !== 1).map((l, i) => ({ ...l, name: `card[${i}]` })); // 3 left, shifted up
  const r = compareStates(
    { png: null, geometry: cards(4), styles: cardStyles(4), counts: { card: 4 } },
    { png: null, geometry: actual, styles: cardStyles(3, 'rgb(9, 9, 9)'), counts: { card: 3 } }
  );
  assert.equal(r.geometryFailures.length, 1);
  assert.equal(r.geometryFailures[0].field, 'count');
  assert.deepEqual(r.styleFailures, []); // styles are not compared past a count mismatch either
});

test('a spec that matched nothing on both sides is recorded as 0 and is not a failure', () => {
  const r = compareStates(
    { png: null, geometry: [], styles: [], counts: { form: 0 } },
    { png: null, geometry: [], styles: [], counts: { form: 0 } }
  );
  assert.deepEqual(r.geometryFailures, []);
  const appeared = compareStates(
    { png: null, geometry: [], styles: [], counts: { form: 0 } },
    { png: null, geometry: [lm({ name: 'form' })], styles: [st({ name: 'form' })], counts: { form: 1 } }
  );
  assert.equal(appeared.geometryFailures.length, 1);
  assert.equal(appeared.geometryFailures[0].field, 'count');
});

test('region status says not-run when a PNG is missing, with a note', () => {
  const none = compareStates(state(), state());
  assert.equal(none.regionStatus, 'not-run');
  assert.ok(none.regionNote);
  const stale = compareStates({ ...state(), regionNote: 'reference PNGs are stale' }, state());
  assert.equal(stale.regionStatus, 'not-run');
  assert.match(stale.regionNote, /stale/);
  const png = makePng(50, 50, () => [1, 2, 3]);
  assert.equal(compareStates(state({ png }), state({ png })).regionStatus, 'compared');
});

test('gate: false makes geometry and style failures review-only', () => {
  const failing = { geometryFailures: [{ name: 'x' }], styleFailures: [{ name: 'y' }], regionDiffs: [] };
  assert.deepEqual(evaluateGate(failing), { pass: false, reviewOnly: false, failures: { geometry: 1, style: 1, alignment: 0 } });
  assert.deepEqual(evaluateGate(failing, { gate: false }), { pass: true, reviewOnly: true, failures: { geometry: 1, style: 1, alignment: 0 } });
  assert.equal(evaluateGate({ geometryFailures: [], styleFailures: [], regionDiffs: [] }).pass, true);
});

test('flaggedRegions: review-only (gate: false) states are exempt from the region assertion', () => {
  const result = { regionDiffs: [{ name: 'a', ratio: 0.5, flagged: true }, { name: 'b', ratio: 0, flagged: false }] };
  assert.deepEqual(flaggedRegions(result, { gate: true }).map((r) => r.name), ['a']);
  assert.deepEqual(flaggedRegions(result), flaggedRegions(result, { gate: true }));
  assert.deepEqual(flaggedRegions(result, { gate: false }), []);
});

test('evaluateGate: alignment failures fail a gated state and are counted separately', () => {
  const result = { geometryFailures: [], styleFailures: [], alignmentFailures: [{ name: 'header-container' }] };
  const gate = evaluateGate(result, { gate: true });
  assert.equal(gate.pass, false);
  assert.deepEqual(gate.failures, { geometry: 0, style: 0, alignment: 1 });
  assert.equal(evaluateGate(result, { gate: false }).pass, true);
  assert.equal(evaluateGate({ geometryFailures: [], styleFailures: [] }).failures.alignment, 0);
});

test('regions wholly above the page (the off-screen skip link) are not pixel-compared', () => {
  const png = makePng(200, 200, (x, y) => [x % 256, y % 256, 90]);
  const skip = lm({ name: 'skip-link', x: 16, y: -80, width: 100, height: 44 });
  const r = compareStates(state({ png, geometry: [skip], styles: [st({ name: 'skip-link' })] }), state({ png, geometry: [{ ...skip, y: -60 }], styles: [st({ name: 'skip-link' })] }));
  assert.deepEqual(r.regionDiffs, []);
});
