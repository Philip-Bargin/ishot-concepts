import { test } from 'node:test';
import assert from 'node:assert/strict';
import { assertPrototypeIntegrity, checkPrototypeIntegrity, PROTOTYPE_COMMIT } from './integrity.mjs';

const clean = () => ({ status: 0, stdout: '' });

test('passes when the diff is clean and there are no untracked files', () => {
  const calls = [];
  const run = (args) => (calls.push(args), clean());
  assert.equal(checkPrototypeIntegrity(run).ok, true);
  assert.deepEqual(calls[0].slice(0, 3), ['diff', '--quiet', PROTOTYPE_COMMIT]);
  assert.ok(calls[0].includes('src') && calls[0].includes('design-system'));
  assert.deepEqual(calls[1].slice(0, 3), ['ls-files', '--others', '--exclude-standard']);
});

test('fails with a clear message when a prototype file differs', () => {
  const run = (args) => (args.includes('--quiet') ? { status: 1 } : { status: 0, stdout: ' src/App.jsx | 2 +-\n' });
  const res = checkPrototypeIntegrity(run);
  assert.equal(res.ok, false);
  assert.match(res.message, /integrity check failed/);
  assert.match(res.message, /src\/App\.jsx/);
  assert.throws(() => assertPrototypeIntegrity(run), /integrity check failed/);
});

test('fails closed on an untracked file under a prototype path', () => {
  const run = (args) => (args[0] === 'ls-files' ? { status: 0, stdout: 'public/new-image.png\n' } : clean());
  const res = checkPrototypeIntegrity(run);
  assert.equal(res.ok, false);
  assert.match(res.message, /untracked/i);
  assert.match(res.message, /public\/new-image\.png/);
});

test('fails closed when git itself fails', () => {
  assert.equal(checkPrototypeIntegrity(() => ({ status: 128, stdout: '' })).ok, false);
  const run = (args) => (args[0] === 'ls-files' ? { status: 128, stdout: '' } : clean());
  assert.equal(checkPrototypeIntegrity(run).ok, false);
});
