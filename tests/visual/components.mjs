// Component visual gate (plan §6 Stage 2, ruling R-2-C). Stage 2 builds no real pages, so each Stage 2 section is
// compared on its own: the prototype section on its route against the WordPress section on a dev-only fixture
// page (/ishot-fixtures/{component}/, created by wordpress/dev/component-fixtures-dev.php).
//
// Entry fields:
//   name           component name (VISUAL_COMPONENTS); also the fixture page slug unless wpPath says otherwise
//   task           the Stage 2 task that builds and gates it
//   prototypePath  prototype route;  prototypeRoot  selector of the component root there
//   wpPath         WordPress path;   wpRoot         selector of the component root there
//   landmarks      key into COMPONENT_EXTRAS (landmarks.mjs); 'component' = the generic COMPONENT_LANDMARKS
//   action         optional interaction run before the probe (ACTIONS below), e.g. the lightbox state
//   checks         optional, default ['A', 'C']; ['C'] = style gate only (count mismatches still fail)
//   exceptions     optional { skip?: landmark names, styles?: { landmark: [properties] }, reason } — each one
//                  needs the owner's agreement in the Stage 2 review pack
// Geometry is measured relative to the root (components.mjs relativeToRoot): the root keeps its absolute x and
// width (container alignment is still checked) and its height; its page y is ignored (E-1 page lengths).
// Entries are added by the task that builds the component.
import { compareGeometry, compareRegions, compareStyles, countMismatches, TOLERANCE } from './compare.mjs';

export const COMPONENTS = [
  {
    name: 'strip',
    task: '2.1b',
    prototypePath: '/',
    prototypeRoot: 'main .ish-strip',
    wpPath: '/ishot-fixtures/strip/',
    wpRoot: 'main .ish-strip',
    landmarks: 'component',
  },
];

export const ROOT = 'component-root';

/** Interactions run after the page settles and before the probe (captureComponent then waits for animations). */
export const ACTIONS = {
  'open-first-tile': async (page) => {
    await page.locator('main .ish-tile').first().click();
    await page.locator('.fo-lightbox').first().waitFor({ state: 'visible' });
  },
};

/** Report / reference / file key. The prefix keeps component keys apart from the page-state keys. */
export const componentKey = (name, width, theme) => `component.${name}@${width}@${theme}`;

const r2 = (n) => Math.round(n * 100) / 100;
const baseName = (name) => name.replace(/\[\d+\]$/, '');

/**
 * Turns a raw component probe (absolute page boxes, geometry.mjs probeLandmarks with a root) into the compared
 * state. Children are relative to the root's top-left corner; the root entry keeps absolute x and width, its
 * height, and y 0. A missing root fails the capture.
 */
export function relativeToRoot(raw, { root, url }) {
  if (!raw.root) throw Object.assign(new Error(`Component root "${root}" not found at ${url}`), { code: 'ROOT_MISSING' });
  const { x, y, width, height, styles } = raw.root;
  return {
    geometry: [
      { name: ROOT, x, y: 0, width, height },
      ...raw.geometry.map((l) => ({ ...l, x: r2(l.x - x), y: r2(l.y - y) })),
    ],
    styles: [{ name: ROOT, styles }, ...raw.styles],
    counts: { [ROOT]: 1, ...raw.counts },
  };
}

/** The screenshot is clipped to the root's box, so for region crops the root starts at x 0 as well. */
export const regionGeometry = (geometry) => geometry.map((l) => (l.name === ROOT ? { ...l, x: 0 } : l));

/** Applies exceptions.skip (whole landmarks) and exceptions.styles (single properties of one landmark). */
function applyExceptions(state, exceptions = {}) {
  const skip = new Set(exceptions.skip ?? []);
  const dropProps = exceptions.styles ?? {};
  const keep = (name) => !skip.has(baseName(name));
  return {
    ...state,
    geometry: state.geometry.filter((l) => keep(l.name)),
    styles: state.styles
      .filter((s) => keep(s.name))
      .map((s) => {
        const drop = dropProps[baseName(s.name)];
        return drop ? { ...s, styles: Object.fromEntries(Object.entries(s.styles).filter(([p]) => !drop.includes(p))) } : s;
      }),
    counts: Object.fromEntries(Object.entries(state.counts ?? {}).filter(([name]) => keep(name))),
  };
}

/**
 * Compares a component reference with an actual capture under the entry's checks and exceptions.
 * Returns the compare.mjs result shape, so the report and evaluateGate work unchanged.
 */
export function compareComponent(component, ref, actual, opts = {}) {
  const tolerance = { ...TOLERANCE, ...(opts.tolerance ?? {}) };
  const checks = component.checks ?? ['A', 'C'];
  const a = applyExceptions(ref, component.exceptions);
  const b = applyExceptions(actual, component.exceptions);
  const mismatches = countMismatches(a, b);
  const skip = new Set(mismatches.map((m) => m.name));
  const geometryFailures = [...mismatches, ...(checks.includes('A') ? compareGeometry(a.geometry, b.geometry, tolerance, skip) : [])];
  const styleFailures = checks.includes('C') ? compareStyles(a.styles, b.styles, skip) : [];
  const havePngs = Boolean(ref.png && actual.png);
  return {
    geometryFailures,
    styleFailures,
    regionDiffs: havePngs ? compareRegions(ref.png, actual.png, regionGeometry(a.geometry), regionGeometry(b.geometry), skip) : [],
    regionStatus: havePngs ? 'compared' : 'not-run',
    regionNote: havePngs ? undefined : ref.regionNote ?? 'reference or actual PNG missing',
    checks,
    exceptions: component.exceptions ?? null,
  };
}
