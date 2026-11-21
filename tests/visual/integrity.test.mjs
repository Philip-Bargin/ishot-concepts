import { test } from 'node:test';
import assert from 'node:assert/strict';
import { assertPrototypeIntegrity, checkPrototypeIntegrity, PROTOTYPE_COMMIT } from './integrity.mjs';

test('passes when git diff --quiet exits 0', () => {
  const calls = [];
  const run = (args) => (calls.push(args), { status: 0, stdout: '' });
  assert.equal(checkPrototypeIntegrity(run).ok, true);
  assert.deepEqual(calls[0].slice(0, 3), ['diff', '--quiet', PROTOTYPE_COMMIT]);
  assert.ok(calls[0].includes('src') && calls[0].includes('design-system'));
});

test('fails with a clear message when a prototype file differs', () => {
  const run = (args) => (args.includes('--quiet') ? { status: 1 } : { status: 0, stdout: ' src/App.jsx | 2 +-\n' });
  const res = checkPrototypeIntegrity(run);
  assert.equal(res.ok, false);
  assert.match(res.message, /integrity check failed/);
  assert.match(res.message, /src\/App\.jsx/);
  assert.throws(() => assertPrototypeIntegrity(run), /integrity check failed/);
});
