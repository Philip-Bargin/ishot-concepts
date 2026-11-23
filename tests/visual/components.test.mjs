import { test } from 'node:test';
import assert from 'node:assert/strict';
import { COMPONENTS, ACTIONS, compareComponent, componentKey, regionGeometry, relativeToRoot } from './components.mjs';
import { COMPONENT_LANDMARKS, componentLandmarksFor } from './landmarks.mjs';
import { filterByScope, normalizeScope, resolveScope, selectComponents } from './scope.mjs';

const box = (name, x, y, width, height, extra = {}) => ({ name, x, y, width, height, ...extra });
const style = (name, styles) => ({ name, styles });

// A raw probe as geometry.mjs returns it for a component: absolute page boxes plus the root's own box and styles.
const probe = ({ rootY = 900, rootHeight = 300, childY = 950 } = {}) => ({
  root: { x: 100, y: rootY, width: 600, height: rootHeight, styles: { color: 'rgb(0, 0, 0)' } },
  geometry: [box('image[0]', 120, childY, 200, 400), box('heading[0]', 130, rootY + 10, 300, 40, { text: true })],
  styles: [style('image[0]', { color: 'rgb(1, 1, 1)', padding: '0px 0px 0px 0px' }), style('heading[0]', { color: 'rgb(2, 2, 2)', 'font-size': '32px' })],
  counts: { image: 1, heading: 1, card: 0 },
});

test('relative geometry: root at (100, 900) puts a child at (120, 950) at (20, 50)', () => {
  const state = relativeToRoot(probe(), { root: 'main .x', url: 'u' });
  assert.deepEqual(state.geometry.find((l) => l.name === 'image[0]'), box('image[0]', 20, 50, 200, 400));
  assert.equal(state.geometry.find((l) => l.name === 'heading[0]').text, true);
});

test('relative geometry: the component-root entry keeps absolute x and width, y is 0, height kept', () => {
  const state = relativeToRoot(probe(), { root: 'main .x', url: 'u' });
  assert.deepEqual(state.geometry[0], box('component-root', 100, 0, 600, 300));
  assert.deepEqual(state.styles[0], style('component-root', { color: 'rgb(0, 0, 0)' }));
  assert.equal(state.counts['component-root'], 1);
  assert.equal(state.counts.card, 0);
});

test('relative geometry: a missing root fails the capture', () => {
  assert.throws(() => relativeToRoot({ ...probe(), root: null }, { root: 'main .ish-strip', url: 'http://x/' }), /Component root "main \.ish-strip" not found at http:\/\/x\//);
});

const strip = { name: 't', checks: ['A', 'C'] };

test('compare: the root page y is ignored (different page lengths), the root height is compared', () => {
  const ref = relativeToRoot(probe({ rootY: 900, childY: 950 }), { root: 'r', url: 'u' });
  const moved = relativeToRoot(probe({ rootY: 2400, childY: 2450 }), { root: 'r', url: 'u' });
  assert.deepEqual(compareComponent(strip, ref, moved).geometryFailures, []);
  const taller = relativeToRoot(probe({ rootY: 2400, childY: 2450, rootHeight: 310 }), { root: 'r', url: 'u' });
  assert.deepEqual(
    compareComponent(strip, ref, taller).geometryFailures.map((f) => [f.name, f.field]),
    [['component-root', 'height']]
  );
});

test('compare: a child moved inside the root fails A', () => {
  const ref = relativeToRoot(probe(), { root: 'r', url: 'u' });
  const shifted = relativeToRoot(probe({ childY: 960 }), { root: 'r', url: 'u' });
  assert.deepEqual(compareComponent(strip, ref, shifted).geometryFailures.map((f) => [f.name, f.field]), [['image[0]', 'y']]);
});

test('compare: exceptions.styles drops only the listed properties of the listed landmark', () => {
  const ref = relativeToRoot(probe(), { root: 'r', url: 'u' });
  const act = structuredClone(ref);
  act.styles.find((s) => s.name === 'image[0]').styles.color = 'rgb(9, 9, 9)';
  act.styles.find((s) => s.name === 'image[0]').styles.padding = '1px 0px 0px 0px';
  act.styles.find((s) => s.name === 'heading[0]').styles.color = 'rgb(9, 9, 9)';
  const component = { name: 't', exceptions: { styles: { image: ['color'] }, reason: 'test' } };
  assert.deepEqual(
    compareComponent(component, ref, act).styleFailures.map((f) => [f.name, f.property]),
    [['image[0]', 'padding'], ['heading[0]', 'color']]
  );
});

test('compare: exceptions.skip drops a landmark from geometry, styles and counts', () => {
  const ref = relativeToRoot(probe(), { root: 'r', url: 'u' });
  const act = relativeToRoot(probe({ childY: 990 }), { root: 'r', url: 'u' });
  act.counts.image = 2;
  const out = compareComponent({ name: 't', exceptions: { skip: ['image'], reason: 'test' } }, ref, act);
  assert.deepEqual(out.geometryFailures, []);
  assert.deepEqual(out.styleFailures, []);
});

test("compare: checks ['C'] skips A but keeps C and count mismatches", () => {
  const ref = relativeToRoot(probe(), { root: 'r', url: 'u' });
  const act = relativeToRoot(probe({ childY: 990, rootHeight: 999 }), { root: 'r', url: 'u' });
  act.styles.find((s) => s.name === 'heading[0]').styles.color = 'rgb(9, 9, 9)';
  const cOnly = compareComponent({ name: 't', checks: ['C'] }, ref, act);
  assert.deepEqual(cOnly.geometryFailures, []);
  assert.deepEqual(cOnly.styleFailures.map((f) => f.name), ['heading[0]']);
  act.counts.card = 1;
  assert.deepEqual(compareComponent({ name: 't', checks: ['C'] }, ref, act).geometryFailures.map((f) => [f.name, f.field]), [['card', 'count']]);
  // default (A and C): the moved child and the root height fail too
  assert.ok(compareComponent({ name: 't' }, ref, act).geometryFailures.length > 1);
});

test('regions: the clipped screenshot starts at the root, so the root region is cut at x 0', () => {
  const geo = [box('component-root', 100, 0, 600, 300), box('image[0]', 20, 50, 200, 400)];
  assert.deepEqual(regionGeometry(geo), [box('component-root', 0, 0, 600, 300), box('image[0]', 20, 50, 200, 400)]);
});

test('scope: "component" is a scope; VISUAL_COMPONENTS implies it and conflicts with another scope', () => {
  assert.equal(normalizeScope('component'), 'component');
  assert.equal(resolveScope(undefined, 'strip'), 'component');
  assert.equal(resolveScope('component', ''), 'component');
  assert.equal(resolveScope('frame', undefined), 'frame');
  assert.throws(() => resolveScope('frame', 'strip'), /VISUAL_COMPONENTS/);
  // page-state filtering never silently treats the component scope as frame
  assert.throws(() => filterByScope({ geometry: [], styles: [], counts: {} }, 'component'), /page states only/);
});

test('components: a list keeps table order; an unknown component name throws', () => {
  const list = [{ name: 'a' }, { name: 'b' }, { name: 'c' }];
  assert.deepEqual(selectComponents(list, 'c, a').map((c) => c.name), ['a', 'c']);
  assert.equal(selectComponents(list, undefined).length, 3);
  assert.throws(() => selectComponents(list, 'a,nope'), /VISUAL_COMPONENTS has unknown component\(s\): nope/);
  assert.throws(() => selectComponents(COMPONENTS, 'hero-that-does-not-exist'), /unknown component/);
});

test('components: every entry is complete and valid', () => {
  assert.ok(COMPONENTS.some((c) => c.name === 'strip'), 'the strip fixture proves the pipeline');
  const names = new Set();
  for (const c of COMPONENTS) {
    for (const field of ['name', 'task', 'prototypePath', 'prototypeRoot', 'wpPath', 'wpRoot', 'landmarks']) {
      assert.equal(typeof c[field], 'string', `${c.name}.${field}`);
      assert.notEqual(c[field], '', `${c.name}.${field}`);
    }
    assert.ok(!names.has(c.name), `duplicate ${c.name}`);
    names.add(c.name);
    assert.match(c.name, /^[a-z0-9-]+$/);
    assert.ok(c.wpPath.startsWith('/') && c.prototypePath.startsWith('/'));
    assert.doesNotThrow(() => componentLandmarksFor(c.landmarks), c.name);
    if (c.action !== undefined) assert.ok(Object.hasOwn(ACTIONS, c.action), `${c.name}: unknown action ${c.action}`);
    if (c.checks !== undefined) {
      assert.ok(c.checks.length > 0 && c.checks.every((k) => k === 'A' || k === 'C'), `${c.name}.checks`);
    }
    if (c.exceptions !== undefined) assert.ok(typeof c.exceptions.reason === 'string' && c.exceptions.reason.trim() !== '', `${c.name}: an exception needs a reason`);
  }
  const s = COMPONENTS.find((c) => c.name === 'strip');
  assert.deepEqual(
    { task: s.task, prototypePath: s.prototypePath, prototypeRoot: s.prototypeRoot, wpPath: s.wpPath },
    { task: '2.1b', prototypePath: '/', prototypeRoot: 'main .ish-strip', wpPath: '/ishot-fixtures/strip/' }
  );
});

test('landmarks: component specs are relative selectors for the listed kinds; unknown keys throw', () => {
  const names = COMPONENT_LANDMARKS.map((l) => l.name);
  for (const n of ['section', 'heading', 'eyebrow', 'button', 'image', 'card', 'tile', 'chip', 'feature', 'stat', 'quote', 'control']) {
    assert.ok(names.includes(n), n);
  }
  assert.ok(COMPONENT_LANDMARKS.every((l) => !/^\s*main\b/.test(l.selector)), 'resolved inside the root, never from main');
  assert.ok(!names.includes('component-root'), 'component-root is added by the capture');
  assert.deepEqual(componentLandmarksFor('component'), COMPONENT_LANDMARKS);
  assert.throws(() => componentLandmarksFor('nope'), /Unknown component landmark set "nope"/);
});

test('keys: component keys cannot collide with page-state keys and are safe file names', () => {
  assert.equal(componentKey('portfolio', 375, 'dark'), 'component.portfolio@375@dark');
});

test('actions: open-first-tile clicks the first tile and waits for the lightbox', async () => {
  const calls = [];
  const locator = (sel) => ({
    first: () => ({
      click: async () => calls.push(['click', sel]),
      waitFor: async (o) => calls.push(['waitFor', sel, o.state]),
    }),
  });
  await ACTIONS['open-first-tile']({ locator, evaluate: async () => calls.push(['evaluate']) });
  assert.deepEqual(calls.slice(0, 2), [['click', 'main .ish-tile'], ['waitFor', '.fo-lightbox', 'visible']]);
});
