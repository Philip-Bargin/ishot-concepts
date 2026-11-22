// The iShot blocks in the real WordPress editors, on the wp-env dev site (admin / password).
// Each block has an editorScript (ServerSideRender), so the editors show the server markup instead of
// "Your site doesn't include support for ..." or an invalid-block warning. A throwaway page holding the
// blocks not yet placed in a template part is created with wp-cli in beforeAll and deleted in afterAll.
// Screenshots are attached to the test output (tests/e2e/.output, gitignored). The template and part checks
// also save named screenshots: to ISHOT_EVIDENCE_DIR when it is set (review evidence), else to the test's
// own output folder, so an ordinary run never overwrites a task's evidence.
import { execSync } from 'node:child_process';
import path from 'node:path';
import { test, expect } from '@playwright/test';

/** Saves a named screenshot to ISHOT_EVIDENCE_DIR, or to the test's output folder when it is unset. */
const saveShot = (page, testInfo, name) =>
  page.screenshot({ path: process.env.ISHOT_EVIDENCE_DIR ? path.join(process.env.ISHOT_EVIDENCE_DIR, name) : testInfo.outputPath(name) });

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

/** Names of blocks in the open editor that failed block validation (the "attempt recovery" state). */
const invalidBlocks = (page) =>
  page.evaluate(() => {
    const be = window.wp.data.select('core/block-editor');
    return be.getClientIdsWithDescendants().map((id) => be.getBlock(id)).filter((b) => b && !b.isValid).map((b) => b.name);
  });

/** No block in the canvas shows source instead of a preview (core/html textarea, code editor). */
async function expectNoCodeView(page, canvas) {
  const names = await page.evaluate(() => {
    const be = window.wp.data.select('core/block-editor');
    return be.getClientIdsWithDescendants().map((id) => be.getBlockName(id));
  });
  expect(names).not.toContain('core/html');
  await expect(canvas.locator('textarea, .block-editor-plain-text, .wp-block-html')).toHaveCount(0);
}

const siteEditor = (type, slug) => `/wp-admin/site-editor.php?postType=${type}&postId=ishot-concept%2F%2F${slug}&canvas=edit`;

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
  expect(await invalidBlocks(page)).toEqual([]);
  await expectNoCodeView(page, canvas);

  // The canvas has the design system's .fo-root base (editor-root.css, ruling R-1.4-4): the header
  // button has the front end's border-box height, not a taller content-box one.
  const editorBox = await header.getByText('Book now').boundingBox();
  await saveShot(page, testInfo, 'site-editor-part-header.png');
  await testInfo.attach('site-editor-header', { body: await page.screenshot(), contentType: 'image/png' });
  await page.goto('/about/');
  const frontBox = await page.locator('header.fo-header').getByText('Book now').boundingBox();
  expect(Math.round(editorBox.height)).toBe(Math.round(frontBox.height));
  expect(Math.round(editorBox.width)).toBe(Math.round(frontBox.width));
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
  expect(await invalidBlocks(page)).toEqual([]);
  await expectNoCodeView(page, canvas);

  // The spectrum previews as the design system's four bars, not as HTML source.
  await expect(footer.locator('.ish-footer__brand .fo-spectrum > i')).toHaveCount(4);
  const bars = await footer.locator('.ish-footer__brand .fo-spectrum').boundingBox();
  expect(bars.height).toBeGreaterThan(0);

  // The Visit lines lay out as on the front end: each on its own row, left-aligned with the eyebrow.
  const visit = footer.locator('.fo-footer__col', { hasText: 'Visit' });
  const rows = await visit.evaluate((col) =>
    [col.querySelector('.fo-eyebrow'), col.querySelector('span.small'), col.querySelector('a[href^="tel:"]'), col.querySelector('a[href^="mailto:"]')].map((el) => {
      const r = el.getBoundingClientRect();
      return { x: Math.round(r.left), top: Math.round(r.top), bottom: Math.round(r.bottom) };
    })
  );
  for (let i = 1; i < rows.length; i += 1) {
    expect(rows[i].x, `line ${i} left edge`).toBe(rows[0].x);
    expect(rows[i].top, `line ${i} starts below line ${i - 1}`).toBeGreaterThanOrEqual(rows[i - 1].bottom);
  }

  await saveShot(page, testInfo, 'site-editor-part-footer.png');
  await testInfo.attach('site-editor-footer', { body: await page.screenshot(), contentType: 'image/png' });
});

// The five base templates (Task 1.4): each opens with the header, main#main and the footer, every block
// valid and supported, and the design-system styles applied in the canvas.
for (const slug of ['index', 'page', 'single', '404', 'search']) {
  test(`the ${slug} template opens in the Site Editor with valid, supported blocks and the design-system styles`, async ({ page }, testInfo) => {
    await logIn(page);
    await page.goto(siteEditor('wp_template', slug));
    await dismissGuides(page);
    const canvas = page.frameLocator('iframe[name="editor-canvas"]');

    const root = canvas.locator('.fo-root.ish-app');
    await expect(root.locator('header.fo-header')).toBeVisible({ timeout: 30000 });
    await expect(root.locator('main')).toHaveCount(1);
    // The canvas does not print the anchor as an id; the saved block carries it (front end: main#main).
    const anchors = await page.evaluate(() => {
      const be = window.wp.data.select('core/block-editor');
      return be.getClientIdsWithDescendants().map((id) => be.getBlock(id)).filter((b) => b?.attributes?.tagName === 'main').map((b) => b.attributes.anchor);
    });
    expect(anchors).toEqual(['main']);
    await expect(root.locator('footer.fo-footer')).toBeVisible();
    await expectSupported(page, canvas);
    expect(await invalidBlocks(page)).toEqual([]);
    await expectNoCodeView(page, canvas);

    // Design-system styles in the canvas: tokens, bundle and app layer.
    const styles = await root.evaluate((el) => {
      const cs = getComputedStyle(el);
      const btn = el.querySelector('.fo-header .fo-btn');
      return { display: cs.display, font: cs.fontFamily, sizing: getComputedStyle(btn).boxSizing, btnHeight: btn.getBoundingClientRect().height };
    });
    expect(styles.display).toBe('flex'); // .ish-app (app.css)
    expect(styles.font).toMatch(/Space Grotesk/); // .fo-root (bundle.css, tokens.css)
    expect(styles.sizing).toBe('border-box');
    expect(styles.btnHeight).toBe(36);
    if (slug === '404') {
      await expect(root.locator('main h1.fo-h1')).toHaveText('Page not found');
      await expect(root.locator('main .ish-actions a.fo-btn')).toHaveCount(2);
    }

    await saveShot(page, testInfo, `site-editor-template-${slug}.png`);
    await testInfo.attach(`site-editor-template-${slug}`, { body: await page.screenshot(), contentType: 'image/png' });
  });
}
