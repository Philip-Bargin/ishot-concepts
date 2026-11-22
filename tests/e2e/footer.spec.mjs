// Footer grid at 375 px, as the prototype lays it out: brand on its own row, Studio | Book side by side,
// Visit on its own row. In the degraded state (empty Studio details, or no ishot-booking) the server
// leaves the Visit column out (FooterPartTest); here it is removed in the page to measure the same
// markup without changing the dev site's settings. Studio and Book must still share a row.
import { test, expect } from '@playwright/test';

test.use({ viewport: { width: 375, height: 812 } });

const boxes = (page) =>
  page.evaluate(() =>
    [...document.querySelectorAll('.fo-footer .ish-footer__grid > *')].map((el) => {
      const r = el.getBoundingClientRect();
      return { cls: el.className, tag: el.tagName, x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width) };
    })
  );

test('normal state: brand row, Studio | Book side by side, Visit row', async ({ page }) => {
  await page.goto('/');
  const [brand, studio, book, visit] = await boxes(page);
  const grid = await page.locator('.fo-footer .ish-footer__grid').boundingBox();
  expect(Math.round(brand.w)).toBe(Math.round(grid.width));
  expect(studio.y).toBe(book.y);
  expect(studio.w).toBe(book.w);
  expect(book.x).toBeGreaterThan(studio.x);
  expect(visit.w).toBe(Math.round(grid.width));
  expect(visit.y).toBeGreaterThan(studio.y);
});

test('degraded state (no Visit column): Studio and Book stay side by side', async ({ page }) => {
  await page.goto('/');
  await page.evaluate(() => {
    const cols = document.querySelectorAll('.fo-footer .ish-footer__grid > .fo-footer__col');
    cols[cols.length - 1].remove();
  });
  const items = await boxes(page);
  expect(items.map((i) => i.tag)).toEqual(['DIV', 'NAV', 'NAV']);
  const [, studio, book] = items;
  expect(studio.y).toBe(book.y);
  expect(studio.w).toBe(book.w);
  expect(book.x).toBeGreaterThan(studio.x);
});
