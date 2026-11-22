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
