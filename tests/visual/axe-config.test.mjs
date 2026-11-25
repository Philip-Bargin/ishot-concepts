import test from 'node:test';
import assert from 'node:assert/strict';
import { AXE_TAGS, menuToggleOutcome } from './axe-config.mjs';

test('axe runs WCAG 2.2 A and AA as well as 2.0, 2.1 and best practice', () => {
  for (const tag of ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa', 'best-practice']) assert.ok(AXE_TAGS.includes(tag), tag);
});

test('a missing menu toggle fails in WP mode and only skips on the prototype', () => {
  assert.equal(menuToggleOutcome(0, 'wp'), 'fail');
  assert.equal(menuToggleOutcome(0, 'prototype'), 'skip');
  assert.equal(menuToggleOutcome(1, 'wp'), 'ok');
  assert.equal(menuToggleOutcome(1, 'prototype'), 'ok');
});

test('componentRenderProblem: a 2xx page with the root passes; a bad status or a missing root is named', async () => {
  const { componentRenderProblem } = await import('./axe-config.mjs');
  assert.equal(componentRenderProblem({ status: 200, rootCount: 1, root: 'main .x', url: 'u' }), '');
  assert.match(componentRenderProblem({ status: 404, rootCount: 1, root: 'main .x', url: 'u' }), /HTTP 404/);
  assert.match(componentRenderProblem({ status: null, rootCount: 1, root: 'main .x', url: 'u' }), /no HTTP response/);
  assert.match(componentRenderProblem({ status: 200, rootCount: 0, root: 'main .x', url: 'u' }), /component root missing.*main \.x/);
});
