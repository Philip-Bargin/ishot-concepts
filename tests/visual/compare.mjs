// Plan §5.2: A (geometry gate), B (per-region pixel signal), C (style gate).
// compareStates(ref, actual) -> { geometryFailures, styleFailures, regionDiffs, regionStatus, regionNote }
//   ref / actual: { png: Buffer|null, geometry: Landmark[], styles: StyleProbe[], counts?: { [spec]: number } }
// Landmarks are compared in both directions. Each spec's match count must be equal; on a count mismatch
// the spec is reported once and its items are not compared index by index (no cascading failures).
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

export const TOLERANCE = Object.freeze({ position: 2, size: 2, textHeight: 4 });
export const PIXEL_THRESHOLD = 0.1; // pixelmatch colour threshold (anti-aliasing tolerant)
export const REGION_FLAG_RATIO = 0.01; // over 1 % of pixels flags a region for manual review

const baseName = (name) => name.replace(/\[\d+\]$/, '');

function countsOf(state) {
  if (state.counts) return state.counts;
  const counts = {};
  for (const l of state.geometry) counts[baseName(l.name)] = (counts[baseName(l.name)] ?? 0) + 1;
  return counts;
}

/** Specs whose match counts differ: [{ name: spec, field: 'count', expected, actual }]. */
export function countMismatches(ref, actual) {
  const a = countsOf(ref);
  const b = countsOf(actual);
  const out = [];
  for (const spec of new Set([...Object.keys(a), ...Object.keys(b)])) {
    const expected = a[spec] ?? 0;
    const got = b[spec] ?? 0;
    if (expected !== got) out.push({ name: spec, field: 'count', expected, actual: got, delta: Math.abs(expected - got), tolerance: 0 });
  }
  return out;
}

export function compareGeometry(refGeometry, actualGeometry, tolerance = TOLERANCE, skip = new Set()) {
  const failures = [];
  const byName = new Map(actualGeometry.map((l) => [l.name, l]));
  for (const exp of refGeometry) {
    if (skip.has(baseName(exp.name))) continue;
    const act = byName.get(exp.name);
    if (!act) {
      failures.push({ name: exp.name, field: 'missing', expected: 'present', actual: 'absent', delta: null, tolerance: null });
      continue;
    }
    const checks = [
      ['x', tolerance.position],
      ['y', tolerance.position],
      ['width', tolerance.size],
      ['height', exp.text ? tolerance.textHeight : tolerance.size],
    ];
    for (const [field, tol] of checks) {
      const delta = Math.abs(act[field] - exp[field]);
      if (delta > tol) failures.push({ name: exp.name, field, expected: exp[field], actual: act[field], delta, tolerance: tol });
    }
  }
  const refNames = new Set(refGeometry.map((l) => l.name));
  for (const act of actualGeometry) {
    if (!skip.has(baseName(act.name)) && !refNames.has(act.name)) {
      failures.push({ name: act.name, field: 'unexpected', expected: 'absent', actual: 'present', delta: null, tolerance: null });
    }
  }
  return failures;
}

export function compareStyles(refStyles, actualStyles, skip = new Set()) {
  const failures = [];
  const byName = new Map(actualStyles.map((s) => [s.name, s]));
  for (const exp of refStyles) {
    if (skip.has(baseName(exp.name))) continue;
    const act = byName.get(exp.name);
    if (!act) {
      failures.push({ name: exp.name, property: 'missing', expected: 'present', actual: 'absent' });
      continue;
    }
    for (const [property, expected] of Object.entries(exp.styles)) {
      if (act.styles[property] !== expected) {
        failures.push({ name: exp.name, property, expected, actual: act.styles[property] });
      }
    }
  }
  return failures;
}

function crop(png, box) {
  const x0 = Math.max(0, Math.round(box.x));
  const y0 = Math.max(0, Math.round(box.y));
  const w = Math.min(Math.round(box.width), png.width - x0);
  const h = Math.min(Math.round(box.height), png.height - y0);
  if (w <= 0 || h <= 0) return null;
  const out = new PNG({ width: w, height: h });
  PNG.bitblt(png, out, x0, y0, w, h, 0, 0);
  return out;
}

export function compareRegions(refPng, actualPng, refGeometry, actualGeometry, skip = new Set()) {
  const ref = PNG.sync.read(refPng);
  const act = PNG.sync.read(actualPng);
  const byName = new Map(actualGeometry.map((l) => [l.name, l]));
  const diffs = [];
  for (const exp of refGeometry) {
    if (skip.has(baseName(exp.name))) continue;
    const found = byName.get(exp.name);
    if (!found) continue;
    const a = crop(ref, exp);
    // the actual region is cut at its own position, at the reference size
    const b = crop(act, { x: found.x, y: found.y, width: exp.width, height: exp.height });
    if (!a && !b) continue; // region lies outside the page image on both sides (e.g. off-screen carousel item)
    if (!a || !b || a.width !== b.width || a.height !== b.height) {
      diffs.push({ name: exp.name, pixels: null, total: null, ratio: 1, flagged: true, note: 'region size mismatch' });
      continue;
    }
    const pixels = pixelmatch(a.data, b.data, null, a.width, a.height, { threshold: PIXEL_THRESHOLD });
    const total = a.width * a.height;
    const ratio = total ? pixels / total : 0;
    diffs.push({ name: exp.name, pixels, total, ratio, flagged: ratio > REGION_FLAG_RATIO });
  }
  return diffs;
}

export function compareStates(ref, actual, opts = {}) {
  const tolerance = { ...TOLERANCE, ...(opts.tolerance ?? {}) };
  const mismatches = countMismatches(ref, actual);
  const skip = new Set(mismatches.map((m) => m.name));
  const geometryFailures = [...mismatches, ...compareGeometry(ref.geometry, actual.geometry, tolerance, skip)];
  const styleFailures = compareStyles(ref.styles, actual.styles, skip);
  const havePngs = Boolean(ref.png && actual.png);
  return {
    geometryFailures,
    styleFailures,
    regionDiffs: havePngs ? compareRegions(ref.png, actual.png, ref.geometry, actual.geometry, skip) : [],
    // never silent: 'not-run' means no pixel comparison happened for this state
    regionStatus: havePngs ? 'compared' : 'not-run',
    regionNote: havePngs ? undefined : ref.regionNote ?? 'reference or actual PNG missing',
  };
}

/**
 * Applies the gate. A route with gate: false (review-only, e.g. the search state, whose prototype
 * counterpart is the 404 page) still reports its A and C results but does not fail.
 */
export function evaluateGate(result, { gate = true } = {}) {
  const failures = { geometry: result.geometryFailures.length, style: result.styleFailures.length };
  const failing = failures.geometry + failures.style > 0;
  return { pass: gate ? !failing : true, reviewOnly: !gate, failures };
}

/** Fingerprint of the committed reference JSON; stored beside the reference PNGs to detect stale PNGs. */
export function referenceFingerprint(geometryFile, stylesFile) {
  const hash = crypto.createHash('sha256');
  hash.update(fs.readFileSync(geometryFile));
  hash.update(fs.readFileSync(stylesFile));
  return hash.digest('hex');
}

const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

/**
 * HTML side-by-side report. entries: [{ key, refImage, actualImage, geometryFailures, styleFailures,
 * regionDiffs, regionStatus, regionNote, gate }]. Image paths are relative to the report file.
 */
export function renderReport(entries) {
  const rows = entries
    .map((e) => {
      const flagged = e.regionDiffs.filter((r) => r.flagged);
      const failing = e.geometryFailures.length || e.styleFailures.length;
      const status = failing ? (e.gate === false ? 'REVIEW-ONLY' : 'FAIL') : flagged.length ? 'REVIEW' : 'PASS';
      const list = (title, items, fmt) =>
        items.length ? `<h4>${title} (${items.length})</h4><ul>${items.slice(0, 40).map((i) => `<li>${esc(fmt(i))}</li>`).join('')}</ul>` : '';
      const notRun =
        e.regionStatus && e.regionStatus !== 'compared'
          ? `<p class="warn">Region comparison NOT RUN: ${esc(e.regionNote ?? e.regionStatus)}</p>`
          : '';
      return `<section id="${esc(e.key)}"><h3>${esc(e.key)} <span class="s ${status}">${status}</span></h3>${notRun}
${list('Geometry failures', e.geometryFailures, (f) => `${f.name} ${f.field}: expected ${f.expected}, got ${f.actual}`)}
${list('Style failures', e.styleFailures, (f) => `${f.name} ${f.property}: expected ${f.expected}, got ${f.actual}`)}
${list('Regions over 1 %', flagged, (r) => `${r.name}: ${(r.ratio * 100).toFixed(2)} %`)}
<div class="pair"><figure><figcaption>Reference</figcaption><img loading="lazy" src="${esc(e.refImage)}"></figure>
<figure><figcaption>Actual</figcaption><img loading="lazy" src="${esc(e.actualImage)}"></figure></div></section>`;
    })
    .join('\n');
  return `<!doctype html><meta charset="utf-8"><title>Visual comparison</title>
<style>body{font:14px system-ui;margin:1rem}.pair{display:flex;gap:1rem;align-items:flex-start}figure{margin:0;flex:1;min-width:0}img{max-width:100%;border:1px solid #ccc}
.s{font-size:12px;padding:2px 6px;border-radius:3px;color:#fff}.PASS{background:#2a7}.REVIEW,.REVIEW-ONLY{background:#c80}.FAIL{background:#c33}.warn{color:#a50;font-weight:600}</style>
<h1>Visual comparison</h1>${rows}`;
}

export function writeReport(file, entries) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, renderReport(entries));
}
