import test from 'node:test';
import assert from 'node:assert/strict';
import { metaMergeProblem, referenceChildEnv, serialiseComponents } from './reference-args.mjs';

test('reference mode refuses --states and a frame scope, from the flags or the environment', () => {
  assert.match(referenceChildEnv({ flags: { states: 'about' }, env: {} }).error, /--states/);
  assert.match(referenceChildEnv({ flags: {}, env: { VISUAL_STATES: 'about' } }).error, /--states/);
  assert.match(referenceChildEnv({ flags: { scope: 'frame' }, env: {} }).error, /--scope frame/);
  assert.match(referenceChildEnv({ flags: {}, env: { VISUAL_SCOPE: 'frame' } }).error, /--scope frame/);
});

test('reference mode forwards only component scope and the component list', () => {
  assert.deepEqual(referenceChildEnv({ flags: {}, env: {} }), { env: { VISUAL_STATES: '', VISUAL_SCOPE: '' }, error: '' });
  assert.deepEqual(referenceChildEnv({ flags: { scope: 'component', components: 'strip' }, env: {} }).env, { VISUAL_STATES: '', VISUAL_SCOPE: 'component', VISUAL_COMPONENTS: 'strip' });
  assert.equal(referenceChildEnv({ flags: { components: 'strip' }, env: {} }).env.VISUAL_SCOPE, 'component');
});

test('a component-only merge is refused when the browser or channel differs', () => {
  const prev = { browser: 'chromium 1', channel: 'bundled', components: 6 };
  assert.equal(metaMergeProblem(prev, { browser: 'chromium 1', channel: 'bundled' }), '');
  assert.equal(metaMergeProblem(null, { browser: 'x', channel: 'y' }), '');
  assert.match(metaMergeProblem(prev, { browser: 'chromium 2', channel: 'bundled' }), /browser "chromium 1".*"chromium 2".*full reference/);
  assert.match(metaMergeProblem(prev, { browser: 'chromium 1', channel: 'chrome' }), /channel/);
});

test('components.json serialisation is deterministic whatever the insertion order', () => {
  const meta = { browser: 'b', channel: 'c', components: 2 };
  const a = serialiseComponents(meta, { 'x@1': { n: 1 }, 'a@1': { n: 2 } });
  const b = serialiseComponents(meta, { 'a@1': { n: 2 }, 'x@1': { n: 1 } });
  assert.equal(a, b);
  assert.ok(a.indexOf('a@1') < a.indexOf('x@1'));
  assert.ok(a.endsWith('\n'));
});
