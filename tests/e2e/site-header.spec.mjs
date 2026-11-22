// Keyboard behaviour of the ishot/site-header block and the skip link (B4 subset), on the wp-env dev site.
// Matches the prototype's Header, MenuSheet and useFocusTrap (src/lib/hooks.js at 50e60f4): focus moves to
// the first menu link on open, Tab and Shift+Tab wrap, Escape and the close button close the sheet, focus
// returns to the toggle, body scroll is locked while open, and menu links close it. Read-only: the dev
// site's content is not changed (the menu comes from the "Primary" navigation or the built-in fallback).
import { test, expect } from '@playwright/test';

const LABELS = ['Home', 'About', 'Services', 'Portfolio', 'Creative direction', 'Journal', 'Contact', 'Book a session'];

const toggle = (page) => page.locator('button.fo-menu-toggle');
const sheet = (page) => page.locator('#site-menu');
const focused = (page) =>
  page.evaluate(() => {
    const el = document.activeElement;
    return el ? { tag: el.tagName, text: el.textContent.trim(), label: el.getAttribute('aria-label'), cls: el.className } : null;
  });
const overflow = (page) => page.evaluate(() => document.body.style.overflow);

let errors;
test.beforeEach(({ page }) => {
  errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
});
test.afterEach(() => {
  expect(errors).toEqual([]);
});

async function openWithKeyboard(page) {
  await toggle(page).focus();
  await page.keyboard.press('Enter');
  await expect(sheet(page)).toBeVisible();
  // The trap is in place once focus has moved to the first menu link.
  await expect(sheet(page).locator('[data-autofocus]')).toBeFocused();
}

test('the skip link is the only one and the first Tab stop, and it shows on focus', async ({ page }) => {
  await page.goto('/about/');
  await expect(page.locator('.skip-link')).toHaveCount(0);
  await expect(page.locator('a[href="#main"]')).toHaveCount(1);
  const first = await page.evaluate(() => document.body.firstElementChild?.outerHTML);
  expect(first).toBe('<a href="#main" class="ish-skip">Skip to content</a>');

  await page.keyboard.press('Tab');
  expect(await focused(page)).toMatchObject({ tag: 'A', text: 'Skip to content', cls: 'ish-skip' });
  const box = await page.locator('.ish-skip').boundingBox();
  expect(box.y).toBeGreaterThanOrEqual(0);
});

test('the sheet is closed on load: hidden, toggle aria-expanded false and controlling it', async ({ page }) => {
  await page.goto('/about/');
  await expect(sheet(page)).toBeHidden();
  await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
  await expect(toggle(page)).toHaveAttribute('aria-controls', 'site-menu');
  await expect(sheet(page)).toHaveAttribute('role', 'dialog');
  await expect(sheet(page)).toHaveAttribute('aria-modal', 'true');
  await expect(sheet(page)).toHaveAttribute('aria-label', 'Site menu');
  expect(await overflow(page)).toBe('');
});

test('opening moves focus to the first menu link, sets aria-expanded and locks body scroll', async ({ page }) => {
  await page.goto('/about/');
  await openWithKeyboard(page);

  await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true');
  expect(await focused(page)).toMatchObject({ tag: 'A', text: '01Home' });
  expect(await overflow(page)).toBe('hidden');
  await expect(sheet(page).locator('nav[aria-label="Main"] ol.fo-menu__list > li > a')).toHaveText(LABELS.map((l, i) => `${String(i + 1).padStart(2, '0')}${l}`));
});

test('Tab and Shift+Tab wrap inside the sheet', async ({ page }) => {
  await page.goto('/about/');
  await openWithKeyboard(page);

  // The same visible-focusable list the trap uses.
  const count = await sheet(page).evaluate((root) => {
    const sel = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return [...root.querySelectorAll(sel)].filter((el) => el.getClientRects().length > 0).length;
  });
  expect(count).toBeGreaterThan(10);

  // Home is item 3 (logo, close, Home, ...): Tab to the last item, then once more wraps to the logo.
  for (let i = 0; i < count - 3; i += 1) await page.keyboard.press('Tab');
  const last = await focused(page);
  await page.keyboard.press('Tab');
  expect(await sheet(page).evaluate((root) => root.contains(document.activeElement))).toBe(true);
  expect(await page.evaluate(() => document.activeElement.closest('.ish-menu__top .fo-logo') !== null)).toBe(true);

  await page.keyboard.press('Shift+Tab');
  expect(await focused(page)).toEqual(last);

  // A full lap of Tab never leaves the sheet.
  for (let i = 0; i < count + 2; i += 1) {
    await page.keyboard.press('Tab');
    expect(await sheet(page).evaluate((root) => root.contains(document.activeElement))).toBe(true);
  }
});

test('Escape closes the sheet, returns focus to the toggle and unlocks scroll', async ({ page }) => {
  await page.goto('/about/');
  await openWithKeyboard(page);
  await page.keyboard.press('Escape');

  await expect(sheet(page)).toBeHidden();
  await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
  await expect(toggle(page)).toBeFocused();
  await expect.poll(() => overflow(page)).toBe('');
});

test('the close button closes the sheet and returns focus to the toggle', async ({ page }) => {
  await page.goto('/about/');
  await openWithKeyboard(page);
  await page.getByRole('button', { name: 'Close menu' }).click();

  await expect(sheet(page)).toBeHidden();
  await expect(toggle(page)).toBeFocused();
  await expect.poll(() => overflow(page)).toBe('');

  // And it opens again (the entrance animation replays on every open, as in the prototype).
  await openWithKeyboard(page);
  expect(await focused(page)).toMatchObject({ text: '01Home' });
});

test('a menu link closes the sheet', async ({ page }) => {
  await page.goto('/about/');
  await openWithKeyboard(page);
  // Keep the page: the link's own navigation is cancelled, its close action still runs.
  await page.evaluate(() => document.addEventListener('click', (e) => e.preventDefault(), { capture: true }));
  await sheet(page).getByRole('link', { name: '04 Portfolio' }).click();

  await expect(sheet(page)).toBeHidden();
  await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false');
  await expect.poll(() => overflow(page)).toBe('');
  await expect(toggle(page)).toBeFocused();
});

test('aria-current marks the current page only', async ({ page }) => {
  for (const [path, label] of [['/', '01Home'], ['/about/', '02About'], ['/journal/a-post/', '06Journal']]) {
    await page.goto(path);
    const current = sheet(page).locator('[aria-current="page"]');
    await expect(current).toHaveCount(1);
    await expect(current).toHaveText(label);
  }
  await page.goto('/account/');
  await expect(sheet(page).locator('[aria-current]')).toHaveCount(0);
});

for (const width of [375, 768, 1440]) {
  test(`the header stays stuck to the top after scrolling 1500 px (${width} px)`, async ({ page }) => {
    await page.setViewportSize({ width, height: 900 });
    await page.goto('/about/');
    // Make the page tall enough to scroll; the layout around the header is untouched.
    await page.evaluate(() => {
      const spacer = document.createElement('div');
      spacer.style.height = '5000px';
      document.querySelector('main').append(spacer);
    });
    await page.evaluate(() => window.scrollTo(0, 1500));
    expect(await page.evaluate(() => window.scrollY)).toBe(1500);
    const top = await page.locator('header.fo-header').evaluate((el) => el.getBoundingClientRect().top);
    expect(top).toBe(0);
    expect(await page.locator('header.fo-header').evaluate((el) => getComputedStyle(el).position)).toBe('sticky');
  });
}
