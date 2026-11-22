// Run scoping for the WordPress target: which landmarks and which states take part in a comparison.
import { ROUTES } from './routes.mjs';

// The theme frame. footer-grid and footer-legal-container exist for the alignment check (alignment.mjs).
export const FRAME_LANDMARKS = Object.freeze([
  'skip-link',
  'header',
  'header-container',
  'logo',
  'header-nav',
  'header-actions',
  'footer',
  'footer-column',
  'footer-grid',
  'footer-legal',
  'footer-legal-container',
]);

const baseName = (name) => name.replace(/\[\d+\]$/, '');

/** VISUAL_SCOPE: '' / 'full' / 'frame'. Anything else is an error, never a silent fallback to full. */
export function normalizeScope(value) {
  if (value === undefined || value === '' || value === 'full') return 'full';
  if (value === 'frame') return 'frame';
  throw new Error(`VISUAL_SCOPE must be "full" or "frame", got "${value}"`);
}

/** Drops every landmark outside the scope from geometry, styles and counts. Apply to both reference and actual. */
export function filterByScope(state, scope) {
  if (scope === 'full') return state;
  const keep = (name) => FRAME_LANDMARKS.includes(baseName(name));
  return {
    ...state,
    geometry: state.geometry.filter((l) => keep(l.name)),
    styles: state.styles.filter((s) => keep(s.name)),
    counts: Object.fromEntries(Object.entries(state.counts ?? {}).filter(([name]) => keep(name))),
  };
}

/** VISUAL_STATES="404,about": keeps route order, rejects unknown names. Empty / undefined = every route. */
export function selectRoutes(routes = ROUTES, list) {
  const wanted = (list ?? '').split(',').map((s) => s.trim()).filter(Boolean);
  if (!wanted.length) return routes;
  const unknown = wanted.filter((w) => !routes.some((r) => r.name === w));
  if (unknown.length) throw new Error(`VISUAL_STATES has unknown state(s): ${unknown.join(', ')}. Known: ${routes.map((r) => r.name).join(', ')}`);
  return routes.filter((r) => wanted.includes(r.name));
}
