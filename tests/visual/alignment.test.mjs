import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { ALIGNMENT_STATES, checkAlignment } from './alignment.mjs';

const box = (name, x, width) => ({ name, x, y: 0, width, height: 10 });
const ref = [box('container-edge', 100, 1240)];
const good = [box('header-container', 100, 1240), box('footer-grid', 100.8, 1239.4), box('footer-legal-container', 100, 1240)];

test('alignment states are home, about, creative-direction and 404', () => {
  assert.deepEqual(ALIGNMENT_STATES, ['home', 'about', 'creative-direction', '404']);
});

test('aligned containers (within 1 px) produce no failures', () => {
  assert.deepEqual(checkAlignment(ref, good), []);
});

test('a container off by more than 1 px reports left and right separately', () => {
  const bad = [box('header-container', 98, 1244), ...good.slice(1)];
  const f = checkAlignment(ref, bad);
  assert.deepEqual(f.map((x) => [x.name, x.field, x.expected, x.actual]), [
    ['header-container', 'left', 100, 98],
    ['header-container', 'right', 1340, 1342],
  ]);
});

test('a frame container missing from the actual page is a failure', () => {
  const f = checkAlignment(ref, good.slice(0, 2));
  assert.deepEqual(f.map((x) => [x.name, x.field]), [['footer-legal-container', 'missing']]);
});

test('a missing reference edge is reported, not silently passed', () => {
  const f = checkAlignment([], good);
  assert.ok(f.length > 0 && f.every((x) => x.field === 'missing-reference'));
});

test('when the page has its own main .fo-container, containers are compared with it too', () => {
  const f = checkAlignment(ref, [...good, box('container-edge', 140, 1160)]);
  assert.equal(f.length, 6); // 3 containers x left/right, each off by 40 px against the page's own edge
  assert.ok(f.every((x) => x.against === 'page container-edge'));
});

const geometryFile = new URL('./reference/geometry.json', import.meta.url);
test('prototype reference: its own frame containers are aligned with its content edge', { skip: !fs.existsSync(geometryFile) }, () => {
  const { states } = JSON.parse(fs.readFileSync(geometryFile, 'utf8'));
  for (const name of ALIGNMENT_STATES) {
    for (const key of Object.keys(states).filter((k) => k.startsWith(`${name}@`))) {
      const g = states[key].landmarks;
      assert.deepEqual(checkAlignment(g, g), [], key);
    }
  }
});
