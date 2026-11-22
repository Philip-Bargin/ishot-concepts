// The iShot blocks in the real WordPress editors, on the wp-env dev site (admin / password).
// Each block has an editorScript (ServerSideRender), so the editors show the server markup instead of
// "Your site doesn't include support for ..." or an invalid-block warning. A throwaway page holding the
// blocks not yet placed in a template part is created with wp-cli in beforeAll and deleted in afterAll.
// Screenshots are attached to the test output (tests/e2e/.output, gitignored).
import { execSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const wp = (args) =>
  execSync(`npx wp-env run cli wp ${args}`, {
    encoding: 'utf8',
    env: { ...process.env, MSYS_NO_PATHCONV: '1' },
  }).trim();

const UNSUPPORTED = /doesn.t include support|This block contains unexpected or invalid content|has encountered an error/i;

let pageId;
test.beforeAll(() => {
  // No quotes inside the content: it has to pass through the shell of every platform unchanged.
  const content = '<!-- wp:ishot/logo /--><!-- wp:ishot/social-links /--><!-- wp:ishot/theme-toggle /-->';
  pageId = wp(`post create --post_type=page --post_status=draft --post_title="e2e editor blocks" --post_content="${content}" --porcelain`)
    .split('\n')
    .pop();
});
test.afterAll(() => {
  if (pageId) wp(`post delete ${pageId} --force`);
});

test.use({ viewport: { width: 1440, height: 900 } });

async function logIn(page) {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
}

/** Hides the editors' welcome guides for this user so they don't cover the canvas. */
async function dismissGuides(page) {
  await page.waitForFunction(() => window.wp?.data?.select('core/preferences'));
  await page.evaluate(() => {
    const prefs = window.wp.data.dispatch('core/preferences');
    prefs.set('core/edit-site', 'welcomeGuide', false);
    prefs.set('core/edit-post', 'welcomeGuide', false);
    prefs.set('core', 'welcomeGuide', false);
  });
}

async function expectSupported(page, canvas) {
  await expect(canvas.locator('body')).not.toContainText(UNSUPPORTED);
  await expect(canvas.locator('.block-editor-warning, .wp-block-missing')).toHaveCount(0);
  await expect(page.getByText(UNSUPPORTED)).toHaveCount(0);
}

test('the Header template part shows the site header in the Site Editor', async ({ page }, testInfo) => {
  await logIn(page);
  await page.goto('/wp-admin/site-editor.php?postType=wp_template_part&postId=ishot-concept%2F%2Fheader&canvas=edit');
  await dismissGuides(page);
  const canvas = page.frameLocator('iframe[name="editor-canvas"]');

  const header = canvas.locator('[data-type="ishot/site-header"] header.fo-header');
  await expect(header).toBeVisible({ timeout: 30000 });
  await expect(header.locator('.fo-logo img.ish-logo__full')).toHaveAttribute('alt', 'iShot Concept');
  await expect(header.locator('nav.fo-header__social .fo-icon-btn')).toHaveCount(3);
  await expect(header.getByText('Book now')).toBeVisible();
  await expect(header.locator('button.fo-menu-toggle')).toBeVisible();
  // The closed sheet is in the markup but not shown.
  await expect(canvas.locator('#site-menu')).toBeHidden();
  await expectSupported(page, canvas);

  await testInfo.attach('site-editor-header', { body: await page.screenshot(), contentType: 'image/png' });
  await page.goto('/about/');
  await testInfo.attach('frontend-header', { body: await page.locator('header.fo-header').screenshot(), contentType: 'image/png' });
});

test('logo, social links and the Night toggle render in the block editor', async ({ page }, testInfo) => {
  await logIn(page);
  await page.goto(`/wp-admin/post.php?post=${pageId}&action=edit`);
  await dismissGuides(page);
  const canvas = page.frameLocator('iframe[name="editor-canvas"]');

  const logo = canvas.locator('[data-type="ishot/logo"]');
  await expect(logo.locator('img.ish-logo__full')).toBeVisible({ timeout: 30000 });
  await expect(logo.locator('img.ish-logo__rev')).toHaveCount(1);
  await expect(canvas.locator('[data-type="ishot/social-links"] .fo-icon-btn')).toHaveCount(3);
  await expect(canvas.locator('[data-type="ishot/theme-toggle"] button.ish-linkbtn')).toHaveText('Night mode');
  await expectSupported(page, canvas);

  await testInfo.attach('block-editor-blocks', { body: await page.screenshot(), contentType: 'image/png' });
});

test('the Footer template part opens in the Site Editor with valid, editable core link columns', async ({ page }, testInfo) => {
  await logIn(page);
  await page.goto('/wp-admin/site-editor.php?postType=wp_template_part&postId=ishot-concept%2F%2Ffooter&canvas=edit');
  await dismissGuides(page);
  const canvas = page.frameLocator('iframe[name="editor-canvas"]');

  const footer = canvas.locator('footer.fo-footer');
  await expect(footer).toBeVisible({ timeout: 30000 });
  await expect(footer.locator('[data-type="ishot/logo"] img.ish-logo__full')).toHaveAttribute('alt', 'iShot Concept');
  await expect(footer.locator('[data-type="ishot/social-links"] .fo-icon-btn')).toHaveCount(3);
  await expect(footer.locator('[data-type="ishot/contact-details"] a[href^="mailto:"]')).toHaveCount(1);
  await expect(footer.locator('[data-type="ishot/theme-toggle"] button.ish-linkbtn')).toHaveText('Night mode');
  // The bound legal paragraph shows the line from the client bindings source, not the source label.
  const year = new Date().getFullYear();
  await expect(footer.locator('.ish-footer__legal [data-type="core/paragraph"]')).toHaveText(`© ${year} iShot Concept · Photography, creative direction, visual storytelling`);

  // The six link columns' items are core paragraphs the owner can edit in place.
  const links = footer.locator('nav.fo-footer__col [data-type="core/paragraph"][contenteditable="true"] a');
  await expect(links).toHaveText(['About', 'Services', 'Portfolio', 'Book a session', 'My bookings', 'Contact']);
  await expectSupported(page, canvas);

  // Every block of the part passed block validation (no "attempt recovery").
  const invalid = await page.evaluate(() => {
    const be = window.wp.data.select('core/block-editor');
    return be.getClientIdsWithDescendants().map((id) => be.getBlock(id)).filter((b) => b && !b.isValid).map((b) => b.name);
  });
  expect(invalid).toEqual([]);

  await testInfo.attach('site-editor-footer', { body: await page.screenshot(), contentType: 'image/png' });
});
