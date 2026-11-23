// axe (WCAG 2.0/2.1/2.2 A/AA + best practice) on each state at 3 widths x 2 themes, menu closed and open.
// Target and states follow the visual harness: VISUAL_TARGET=wp, VISUAL_BASE_URL, VISUAL_STATES. 0 violations is the gate.
// VISUAL_SCOPE=component / VISUAL_COMPONENTS: axe on each component page instead (components.mjs; wpPath on wp,
// prototypePath on the prototype), at rest and, for a component with an `action`, in that interactive state.
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { openState, contextOptions, THEMES, VIEWPORTS, waitForAnimations } from './capture.mjs';
import { ACTIONS } from './components.mjs';
import { ACTIVE_COMPONENTS, ACTIVE_ROUTES, BASE_URL, SCOPE, TARGET, componentTarget, pathFor } from './target.mjs';
import { AXE_TAGS, menuToggleOutcome } from './axe-config.mjs';

const summarise = (violations) =>
  violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.length} node(s), e.g. ${v.nodes[0]?.target.join(' ')}`);

const COMPONENT_MODE = SCOPE === 'component';

for (const component of COMPONENT_MODE ? ACTIVE_COMPONENTS : []) {
  for (const width of Object.keys(VIEWPORTS).map(Number)) {
    for (const theme of THEMES) {
      for (const state of component.action ? ['rest', component.action] : ['rest']) {
        test(`axe [${TARGET}] component.${component.name}@${width}@${theme} ${state}`, async ({ browser }) => {
          const context = await browser.newContext(contextOptions(width));
          try {
            const page = await context.newPage();
            await openState(page, { url: `${BASE_URL}${componentTarget(component).path}`, width, theme });
            if (state !== 'rest') await ACTIONS[state](page);
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

for (const route of COMPONENT_MODE ? [] : ACTIVE_ROUTES) {
  for (const width of Object.keys(VIEWPORTS).map(Number)) {
    for (const theme of THEMES) {
      for (const menu of ['closed', 'open']) {
        test(`axe [${TARGET}] ${route.name}@${width}@${theme} menu ${menu}`, async ({ browser }) => {
          const context = await browser.newContext(contextOptions(width));
          try {
            const page = await context.newPage();
            await openState(page, { url: `${BASE_URL}${pathFor(route)}`, width, theme });
            if (menu === 'open') {
              const outcome = menuToggleOutcome(await page.locator('.fo-menu-toggle').count(), TARGET);
              // In WP mode the header block always prints the toggle, so a missing one is a failure, not a skip.
              expect(outcome, 'a .fo-menu-toggle must exist on the WordPress site').not.toBe('fail');
              if (outcome === 'skip') {
                test.info().annotations.push({ type: 'skipped', description: 'no .fo-menu-toggle on the prototype page' });
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
