// The base templates' layout groups on the wp-env dev site (needs the dev page shells, wordpress/dev/page-shells-dev.php).
// Core's flow layout adds a block gap above every child of a Group. The templates ask for a zero gap, so
// core's margin goes, but the design system's own margins on a child (app.css, bundle.css) still apply:
// Stage 2–3 page bodies rely on them (.ish-strip, .ish-count, .fo-heading ...).
import { test, expect } from '@playwright/test';

for (const width of [375, 1440]) {
  test(`a prototype class keeps its own margin inside a template group, and core adds no block gap (${width}px)`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/about/');
    const result = await page.evaluate(() => {
      const probe = (parent, html) => {
        parent.insertAdjacentHTML('beforeend', html);
        const el = parent.lastElementChild;
        const cs = getComputedStyle(el);
        const out = { top: cs.marginTop, bottom: cs.marginBottom };
        el.remove();
        return out;
      };
      // Inside the title block's container group (a core Group in main), after the h1.
      const group = document.querySelector('main .ish-title > .fo-container');
      // The same element outside any WordPress group: the design system's value alone.
      const free = document.createElement('div');
      free.className = 'fo-root';
      document.body.append(free);
      const expected = probe(free, '<div class="ish-strip"></div>');
      free.remove();
      return {
        isGroup: group.classList.contains('wp-block-group'),
        strip: probe(group, '<div class="ish-strip"></div>'),
        expected,
        lead: probe(group, '<p class="lead">x</p>'),
      };
    });
    expect(result.isGroup).toBe(true);
    expect(result.expected.top).not.toBe('0px');
    expect(result.strip).toEqual(result.expected);
    expect(result.lead).toEqual({ top: '0px', bottom: '0px' }); // app.css reset; no core block gap
  });
}
