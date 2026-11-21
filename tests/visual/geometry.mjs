// In-page probes: getBoundingClientRect (page coordinates) and computed-style reads for each landmark.
import { STYLE_PROPS } from './landmarks.mjs';

/**
 * Returns { geometry: Landmark[], styles: StyleProbe[], counts: { [spec name]: rendered match count } }.
 * counts includes specs that matched nothing (0), so an absent landmark is never silently dropped.
 */
export async function probeLandmarks(page, landmarks) {
  return page.evaluate(
    ({ specs, props }) => {
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
      const geometry = [];
      const styles = [];
      const counts = {};
      for (const spec of specs) {
        const found = [...document.querySelectorAll(spec.selector)];
        const els = spec.all ? found : found.slice(0, 1);
        let i = 0;
        counts[spec.name] = 0;
        for (const el of els) {
          const rect = el.getBoundingClientRect();
          if (rect.width === 0 && rect.height === 0) continue; // not rendered (e.g. a closed menu)
          const name = spec.all ? `${spec.name}[${i}]` : spec.name;
          i += 1;
          counts[spec.name] = i;
          const entry = {
            name,
            x: r2(rect.left + window.scrollX),
            y: r2(rect.top + window.scrollY),
            width: r2(rect.width),
            height: r2(rect.height),
          };
          if (spec.text) entry.text = true;
          geometry.push(entry);
          const cs = getComputedStyle(el);
          styles.push({ name, styles: Object.fromEntries(props.map((p) => [p, read(cs, p)])) });
        }
      }
      return { geometry, styles, counts };
    },
    { specs: landmarks, props: STYLE_PROPS }
  );
}
