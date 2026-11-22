import { test } from 'node:test';
import assert from 'node:assert/strict';
import { applyMasks } from './capture.mjs';

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
