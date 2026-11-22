// Shared axe settings and the open-menu precondition for tests/visual/a11y.spec.mjs (kept free of
// Playwright imports so node --test can cover them).
export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa', 'best-practice'];

/** What the open-menu variant does when the page has `count` menu toggles: in WP mode a missing toggle is a failure. */
export function menuToggleOutcome(count, target) {
  if (count > 0) return 'ok';
  return target === 'wp' ? 'fail' : 'skip';
}
