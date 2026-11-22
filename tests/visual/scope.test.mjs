import { test } from 'node:test';
import assert from 'node:assert/strict';
import { FRAME_LANDMARKS, filterByScope, normalizeScope, selectRoutes } from './scope.mjs';
import { ROUTES } from './routes.mjs';

const state = () => ({
  png: null,
  geometry: [{ name: 'header' }, { name: 'header-nav[0]' }, { name: 'heading[0]' }, { name: 'skip-link' }, { name: 'footer-column[1]' }],
  styles: [{ name: 'header' }, { name: 'header-nav[0]' }, { name: 'heading[0]' }, { name: 'skip-link' }, { name: 'footer-column[1]' }],
  counts: { header: 1, 'header-nav': 1, heading: 1, 'skip-link': 1, 'footer-column': 3, card: 0 },
});

test('scope: default and "full" keep every landmark; unknown scope is rejected', () => {
  assert.equal(normalizeScope(undefined), 'full');
  assert.equal(normalizeScope(''), 'full');
  assert.equal(normalizeScope('full'), 'full');
  assert.equal(normalizeScope('frame'), 'frame');
  assert.throws(() => normalizeScope('header'), /VISUAL_SCOPE/);
  assert.deepEqual(filterByScope(state(), 'full'), state());
});

test('scope frame: keeps only frame landmarks in geometry, styles and counts', () => {
  const out = filterByScope(state(), 'frame');
  assert.deepEqual(out.geometry.map((l) => l.name), ['header', 'header-nav[0]', 'skip-link', 'footer-column[1]']);
  assert.deepEqual(out.styles.map((l) => l.name), ['header', 'header-nav[0]', 'skip-link', 'footer-column[1]']);
  assert.deepEqual(Object.keys(out.counts), ['header', 'header-nav', 'skip-link', 'footer-column']);
});

test('scope frame: the frame list is exactly the brief list plus the two alignment containers', () => {
  assert.deepEqual(
    [...FRAME_LANDMARKS].sort(),
    ['footer', 'footer-column', 'footer-grid', 'footer-legal', 'footer-legal-container', 'header', 'header-actions', 'header-container', 'header-nav', 'logo', 'skip-link'].sort()
  );
});

test('states: no filter returns every route; a list keeps route order; unknown names are rejected', () => {
  assert.equal(selectRoutes(ROUTES, undefined).length, 16);
  assert.equal(selectRoutes(ROUTES, '').length, 16);
  assert.deepEqual(selectRoutes(ROUTES, '404, about').map((r) => r.name), ['about', '404']);
  assert.throws(() => selectRoutes(ROUTES, 'about,nope'), /nope/);
});
