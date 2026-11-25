// Shared axe settings and the open-menu precondition for tests/visual/a11y.spec.mjs (kept free of
// Playwright imports so node --test can cover them).
export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22a', 'wcag22aa', 'best-practice'];

/** What the open-menu variant does when the page has `count` menu toggles: in WP mode a missing toggle is a failure. */
export function menuToggleOutcome(count, target) {
  if (count > 0) return 'ok';
  return target === 'wp' ? 'fail' : 'skip';
}

/**
 * Why a component page cannot be axe-checked, or '' when it can: a non-2xx (or missing) response, or no element
 * for the component root. Without this the run passes on a 404 or an empty page.
 */
export function componentRenderProblem({ status, rootCount, root, url }) {
  if (status === null || status === undefined) return `no HTTP response for ${url}`;
  if (status < 200 || status > 299) return `HTTP ${status} for ${url}`;
  if (!(rootCount >= 1)) return `component root missing: "${root}" not found at ${url}`;
  return '';
}
