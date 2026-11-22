// Alignment check (ruling R-S1-1): the left and right edges of the frame containers must equal those of the
// content container. The prototype reference's container-edge (main .fo-container) is the stable target; where
// the WordPress page has its own main .fo-container it is compared too.
export const ALIGNMENT_STATES = Object.freeze(['home', 'about', 'creative-direction', '404']);
export const FRAME_CONTAINERS = Object.freeze(['header-container', 'footer-grid', 'footer-legal-container']);
export const ALIGNMENT_TOLERANCE = 1; // px

const edges = (l) => ({ left: l.x, right: l.x + l.width });

/** refGeometry / actualGeometry: Landmark[]. Returns failures shaped like geometry failures plus `against`. */
export function checkAlignment(refGeometry, actualGeometry, tolerance = ALIGNMENT_TOLERANCE) {
  const failures = [];
  const refEdge = refGeometry.find((l) => l.name === 'container-edge');
  const ownEdge = actualGeometry.find((l) => l.name === 'container-edge');
  const targets = [];
  if (refEdge) targets.push(['reference container-edge', refEdge]);
  if (ownEdge) targets.push(['page container-edge', ownEdge]);
  for (const name of FRAME_CONTAINERS) {
    const found = actualGeometry.find((l) => l.name === name);
    if (!found) {
      failures.push({ name, field: 'missing', expected: 'present', actual: 'absent', delta: null, tolerance, against: 'container-edge' });
      continue;
    }
    if (!refEdge) {
      failures.push({ name, field: 'missing-reference', expected: 'container-edge in reference', actual: 'absent', delta: null, tolerance, against: 'reference container-edge' });
    }
    for (const [against, target] of targets) {
      const want = edges(target);
      const got = edges(found);
      for (const field of ['left', 'right']) {
        const delta = Math.abs(got[field] - want[field]);
        if (delta > tolerance) failures.push({ name, field, expected: want[field], actual: got[field], delta, tolerance, against });
      }
    }
  }
  return failures;
}
