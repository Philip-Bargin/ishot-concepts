import { test } from 'node:test';
import assert from 'node:assert/strict';
import { applyMasks, waitForAnimations } from './capture.mjs';

// A stand-in for a Playwright page that records the calls applyMasks makes.
function fakePage({ paintDelay = 20 } = {}) {
  const calls = [];
  return {
    calls,
    async addStyleTag({ content }) {
      calls.push(['addStyleTag', content]);
    },
    async evaluate(fn, arg) {
      calls.push(['evaluate', arg]);
      await new Promise((r) => setTimeout(r, paintDelay));
      calls.push(['painted']);
    },
  };
}

test('masks: the mask style is added, then the page waits until it is painted', async () => {
  const page = fakePage();
  await applyMasks(page, [{ selector: '.fo-footer__legal', reason: 'year' }]);
  assert.deepEqual(page.calls.map((c) => c[0]), ['addStyleTag', 'evaluate', 'painted']);
  assert.match(page.calls[0][1], /\.fo-footer__legal \{ visibility: hidden !important; \}/);
  assert.deepEqual(page.calls[1][1], ['.fo-footer__legal']);
});

test('masks: nothing to mask adds no style and does not wait', async () => {
  const page = fakePage();
  await applyMasks(page, []);
  assert.deepEqual(page.calls, []);
});

test('animations: the page waits for every running animation to finish, bounded by a timeout', async () => {
  const page = fakePage();
  await waitForAnimations(page, 1500);
  assert.deepEqual(page.calls.map((c) => c[0]), ['evaluate', 'painted']);
  assert.equal(page.calls[0][1], 1500);
});

test('animations: the in-page wait resolves once animations finish or the bound passes', async () => {
  // Run the function waitForAnimations hands to the page against a stand-in document.
  let fn;
  await waitForAnimations({ evaluate: async (f) => { fn = f; } }, 50);
  let finished = false;
  const anim = { finished: new Promise((r) => setTimeout(() => { finished = true; r(); }, 10)) };
  globalThis.document = { getAnimations: () => [anim] };
  globalThis.requestAnimationFrame = (cb) => setTimeout(cb, 0);
  try {
    await fn(50);
    assert.equal(finished, true, 'waited for the animation');
    const never = { finished: new Promise(() => {}) };
    globalThis.document = { getAnimations: () => [never] };
    const t0 = Date.now();
    await fn(50);
    assert.ok(Date.now() - t0 < 1000, 'an endless animation does not hang the run');
  } finally {
    delete globalThis.document;
    delete globalThis.requestAnimationFrame;
  }
});
