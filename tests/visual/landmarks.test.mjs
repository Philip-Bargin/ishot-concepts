import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { findMissingRequired, landmarksFor } from './landmarks.mjs';
import { ROUTES } from './routes.mjs';

test('a required spec that matched nothing is reported, optional ones are not', () => {
  const specs = [
    { name: 'steps', required: true },
    { name: 'summary', required: false },
    { name: 'logo', required: true },
  ];
  assert.deepEqual(findMissingRequired(specs, { steps: 0, summary: 0, logo: 1 }), ['steps']);
  assert.deepEqual(findMissingRequired(specs, { steps: 2, logo: 1 }), []);
});

test('routes: gate defaults to true, only search is review-only', () => {
  assert.equal(ROUTES.length, 16);
  assert.deepEqual(ROUTES.filter((r) => r.gate === false).map((r) => r.name), ['search']);
});

const geometryFile = new URL('./reference/geometry.json', import.meta.url);
test('committed reference: every route probes something and every required spec matches in every state', { skip: !fs.existsSync(geometryFile) }, () => {
  const { states } = JSON.parse(fs.readFileSync(geometryFile, 'utf8'));
  for (const route of ROUTES) {
    const specs = landmarksFor(route.landmarks);
    const keys = Object.keys(states).filter((k) => k.startsWith(`${route.name}@`));
    assert.equal(keys.length, 6, `${route.name} has 6 states`);
    for (const key of keys) {
      const { counts } = states[key];
      assert.ok(counts, `${key} records counts`);
      assert.deepEqual(findMissingRequired(specs, counts), [], `${key} required landmarks`);
    }
    const extras = specs.filter((s) => landmarksFor('common').every((c) => c.name !== s.name));
    for (const spec of extras) {
      assert.ok(keys.some((k) => states[k].counts[spec.name] > 0), `${route.name}: page-specific spec "${spec.name}" matches nothing in any state`);
    }
  }
});
