// In-page probes: getBoundingClientRect (page coordinates) and computed-style reads for each landmark.
import { STYLE_PROPS } from './landmarks.mjs';

/**
 * Returns { geometry: Landmark[], styles: StyleProbe[], counts: { [spec name]: rendered match count } }.
 * counts includes specs that matched nothing (0), so an absent landmark is never silently dropped.
 * With `root` (a selector; component gate), specs are resolved inside the first match only and the result also
 * has `root`: its page box and styles, or null when nothing matches (components.mjs relativeToRoot fails then).
 */
export async function probeLandmarks(page, landmarks, { root } = {}) {
  return page.evaluate(
    ({ specs, props, rootSelector }) => {
      const r2 = (n) => Math.round(n * 100) / 100;
      const sides = ['top', 'right', 'bottom', 'left'];
      const read = (cs, prop) => {
        if (prop === 'border') {
          return sides
            .map((s) => `${cs.getPropertyValue(`border-${s}-width`)} ${cs.getPropertyValue(`border-${s}-style`)} ${cs.getPropertyValue(`border-${s}-color`)}`)
            .join(' | ');
        }
        if (prop === 'padding') return sides.map((s) => cs.getPropertyValue(`padding-${s}`)).join(' ');
        return cs.getPropertyValue(prop);
      };
      const box = (el) => {
        const rect = el.getBoundingClientRect();
        return { x: r2(rect.left + window.scrollX), y: r2(rect.top + window.scrollY), width: r2(rect.width), height: r2(rect.height) };
      };
      const styleOf = (el) => {
        const cs = getComputedStyle(el);
        return Object.fromEntries(props.map((p) => [p, read(cs, p)]));
      };
      const scopeEl = rootSelector ? document.querySelector(rootSelector) : document;
      if (!scopeEl) return { root: null, geometry: [], styles: [], counts: {} };
      const geometry = [];
      const styles = [];
      const counts = {};
      for (const spec of specs) {
        const found = [...scopeEl.querySelectorAll(spec.selector)];
        const els = spec.all ? found : found.slice(0, 1);
        let i = 0;
        counts[spec.name] = 0;
        for (const el of els) {
          const rect = el.getBoundingClientRect();
          if (rect.width === 0 && rect.height === 0) continue; // not rendered (e.g. a closed menu)
          const name = spec.all ? `${spec.name}[${i}]` : spec.name;
          i += 1;
          counts[spec.name] = i;
          const entry = { name, ...box(el) };
          if (spec.text) entry.text = true;
          geometry.push(entry);
          styles.push({ name, styles: styleOf(el) });
        }
      }
      if (!rootSelector) return { geometry, styles, counts };
      return { root: { ...box(scopeEl), styles: styleOf(scopeEl) }, geometry, styles, counts };
    },
    { specs: landmarks, props: STYLE_PROPS, rootSelector: root ?? null }
  );
}
