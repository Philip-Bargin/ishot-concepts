// axe (WCAG 2.1 A/AA + best practice) on each state at 3 widths x 2 themes, menu closed and open.
// Target and states follow the visual harness: VISUAL_TARGET=wp, VISUAL_BASE_URL, VISUAL_STATES. 0 violations is the gate.
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openState, contextOptions, THEMES, VIEWPORTS, waitForAnimations } from './capture.mjs';
import { ACTIVE_ROUTES, BASE_URL, TARGET, pathFor } from './target.mjs';

export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'];

const summarise = (violations) =>
  violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.length} node(s), e.g. ${v.nodes[0]?.target.join(' ')}`);

for (const route of ACTIVE_ROUTES) {
  for (const width of Object.keys(VIEWPORTS).map(Number)) {
    for (const theme of THEMES) {
      for (const menu of ['closed', 'open']) {
        test(`axe [${TARGET}] ${route.name}@${width}@${theme} menu ${menu}`, async ({ browser }) => {
          const context = await browser.newContext(contextOptions(width));
          try {
            const page = await context.newPage();
            await openState(page, { url: `${BASE_URL}${pathFor(route)}`, width, theme });
            if (menu === 'open') {
              if (!(await page.locator('.fo-menu-toggle').count())) {
                test.info().annotations.push({ type: 'skipped', description: 'no .fo-menu-toggle on this page yet (menu arrives with Task 1.2)' });
                test.skip(true, 'no .fo-menu-toggle: open-menu variant skipped');
              }
              await page.locator('.fo-menu-toggle').click();
              await page.locator('#site-menu').waitFor({ state: 'visible' });
            }
            // Staggered menu and reveal animations must have finished, or axe can sample a link mid-fade.
            await waitForAnimations(page);
            const { violations } = await new AxeBuilder({ page }).withTags(AXE_TAGS).analyze();
            expect(summarise(violations), 'axe violations').toEqual([]);
          } finally {
            await context.close();
          }
        });
      }
    }
  }
}
