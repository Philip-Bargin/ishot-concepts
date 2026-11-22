// Night bootstrap and the ishot/theme-toggle block, on the wp-env dev site.
// A throwaway page holding only the block is created with wp-cli in beforeAll and deleted in afterAll,
// so the dev site's content is left unchanged.
import { execSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const KEY = 'ishot-theme';
const wp = (args) =>
  execSync(`npx wp-env run cli wp ${args}`, {
    encoding: 'utf8',
    env: { ...process.env, MSYS_NO_PATHCONV: '1' },
  }).trim();

let pageId;
test.beforeAll(() => {
  pageId = wp(`post create --post_type=page --post_status=publish --post_title="e2e theme toggle" --post_content="<!-- wp:ishot/theme-toggle /-->" --porcelain`)
    .split('\n')
    .pop();
});
test.afterAll(() => {
  if (pageId) wp(`post delete ${pageId} --force`);
});

const url = () => `/?page_id=${pageId}`;
const toggle = (page) => page.getByRole('button', { name: 'Night mode' });
const theme = (page) => page.evaluate(() => document.documentElement.dataset.theme);

test('a page starts in light with the button unpressed', async ({ page }) => {
  await page.goto(url());
  expect(await theme(page)).toBe('light');
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'false');
});

test('a stored dark choice is applied at DOMContentLoaded (no flash)', async ({ page }) => {
  await page.addInitScript((key) => {
    localStorage.setItem(key, 'dark');
    document.addEventListener('DOMContentLoaded', () => {
      window.__themeAtDcl = document.documentElement.dataset.theme;
    });
  }, KEY);
  await page.goto(url());
  expect(await page.evaluate(() => window.__themeAtDcl)).toBe('dark');
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');
});

test('clicking toggles the theme and aria-pressed, and the choice survives a reload', async ({ page }) => {
  await page.goto(url());
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'false');

  await toggle(page).click();
  expect(await theme(page)).toBe('dark');
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');
  expect(await page.evaluate((key) => localStorage.getItem(key), KEY)).toBe('dark');

  await page.reload();
  expect(await theme(page)).toBe('dark');
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');

  await toggle(page).click();
  expect(await theme(page)).toBe('light');
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'false');
  await page.reload();
  expect(await theme(page)).toBe('light');
});

test('with storage blocked the page loads in light and the toggle still works for the visit', async ({ page }) => {
  await page.addInitScript(() => {
    const blocked = () => {
      throw new DOMException('blocked', 'SecurityError');
    };
    Object.defineProperty(window, 'localStorage', { get: blocked, configurable: true });
  });
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(url());
  expect(await theme(page)).toBe('light');

  await toggle(page).click();
  expect(await theme(page)).toBe('dark');
  await expect(toggle(page)).toHaveAttribute('aria-pressed', 'true');
  expect(errors).toEqual([]);
});
