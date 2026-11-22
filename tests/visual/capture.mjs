// Controlled rendering (plan §5.1). One capture = one page state at one width and theme.
//   captureState(page, { url, width, theme, landmarks?, masks? }) -> { png, geometry, styles }
import { probeLandmarks } from './geometry.mjs';
import { findMissingRequired, landmarksFor } from './landmarks.mjs';
import { FIXED_TIME, MASKS } from './masks.mjs';

export const THEME_KEY = 'ishot-theme'; // src/lib/theme.js
export const VIEWPORTS = {
  375: { width: 375, height: 812 },
  768: { width: 768, height: 1024 },
  1440: { width: 1440, height: 900 },
};
export const THEMES = ['light', 'dark'];

/** Context options shared by every capture (prototype and WordPress). */
export const contextOptions = (width) => ({
  viewport: VIEWPORTS[width],
  deviceScaleFactor: 1,
  locale: 'en-US',
  timezoneId: 'Africa/Monrovia',
  reducedMotion: 'reduce',
  serviceWorkers: 'block',
});

// Every animation and transition at zero duration, caret hidden, scroll reveals shown, marquee frozen.
const FREEZE_CSS = `
*, *::before, *::after {
  animation-duration: 0s !important; animation-delay: 0s !important; animation-iteration-count: 1 !important;
  transition-duration: 0s !important; transition-delay: 0s !important;
  caret-color: transparent !important; scroll-behavior: auto !important;
}
.fo-reveal { opacity: 1 !important; transform: none !important; }
.fo-marquee__track { animation-play-state: paused !important; }
`;

const maskCss = (masks) => masks.map((m) => `${m.selector} { visibility: hidden !important; }`).join('\n');

/**
 * Hides the masked elements and waits until the browser has painted that: every match reports
 * visibility hidden, then two animation frames pass. Screenshotting straight after addStyleTag
 * sometimes caught the frame before the mask (Task 1.3: the footer year showed in ~21 of 96 states).
 */
export async function applyMasks(page, masks) {
  if (!masks.length) return;
  await page.addStyleTag({ content: maskCss(masks) });
  await page.evaluate(async (selectors) => {
    const frame = () => new Promise((r) => requestAnimationFrame(() => r()));
    const hidden = () => selectors.every((s) => [...document.querySelectorAll(s)].every((el) => getComputedStyle(el).visibility === 'hidden'));
    for (let i = 0; i < 60 && !hidden(); i += 1) await frame();
    await frame();
    await frame();
  }, masks.map((m) => m.selector));
}

/**
 * Waits until every animation on the page has finished, at most `timeoutMs`. The menu sheet's links rise
 * in with staggered delays (app.css ish-rise); reduced motion shortens the duration but not the delay, so
 * a check run straight after opening can see a link mid-fade (Task 1.4: an intermittent axe colour-contrast hit).
 */
export async function waitForAnimations(page, timeoutMs = 3000) {
  await page.evaluate(async (timeout) => {
    const all = Promise.all(document.getAnimations().map((a) => a.finished.catch(() => {})));
    await Promise.race([all, new Promise((r) => setTimeout(r, timeout))]);
    await new Promise((r) => requestAnimationFrame(() => r()));
  }, timeoutMs);
}

/** Runs before page scripts: theme, freeze stylesheet. */
function initScript({ theme, themeKey, css }) {
  try {
    localStorage.setItem(themeKey, theme);
  } catch {
    /* storage unavailable */
  }
  const style = document.createElement('style');
  style.setAttribute('data-visual-freeze', '');
  style.textContent = css;
  document.documentElement.appendChild(style);
  document.addEventListener('DOMContentLoaded', () => {
    document.documentElement.dataset.theme = theme;
  });
}

async function settle(page, viewportHeight) {
  await page.evaluate(async (step) => {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    // scroll through so lazy images and observers fire, then force every image eager and decode it
    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await sleep(40);
    }
    window.scrollTo(0, document.documentElement.scrollHeight);
    const imgs = [...document.images];
    imgs.forEach((img) => {
      img.loading = 'eager';
    });
    await Promise.all(
      imgs.map(async (img) => {
        if (!img.complete) await new Promise((r) => { img.addEventListener('load', r, { once: true }); img.addEventListener('error', r, { once: true }); });
        await img.decode().catch(() => {});
      })
    );
    await document.fonts.ready;
    window.scrollTo(0, 0);
    document.activeElement?.blur?.();
    await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
  }, viewportHeight);
}

/** Controlled rendering up to a settled page: viewport, media, fixed clock, theme, freeze stylesheet, load. */
export async function openState(page, { url, width, theme }) {
  const viewport = VIEWPORTS[width];
  if (!viewport) throw new Error(`Unsupported width ${width}`);
  await page.setViewportSize(viewport);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.clock.setFixedTime(FIXED_TIME);
  await page.addInitScript(initScript, { theme, themeKey: THEME_KEY, css: FREEZE_CSS });
  await page.goto(url, { waitUntil: 'load' });
  // prototype: wait for the lazy route chunk (Suspense fallback gone); harmless where the class is absent
  await page.waitForSelector('.ish-loading', { state: 'detached' });
  await page.waitForLoadState('networkidle');

  // slideshow must be on slide 1 (it starts paused under reduced motion)
  const slides = await page.$$eval('.ish-slide', (els) => els.map((e) => e.classList.contains('is-active')));
  if (slides.length && !slides[0]) throw new Error(`Slideshow is not on slide 1 at ${url}`);
  if (slides.slice(1).some(Boolean)) throw new Error(`More than one active slide at ${url}`);
  return viewport;
}

/**
 * requireLandmarks: false for the WordPress target, where a missing landmark is a comparison failure
 * (count mismatch) and must be reported, not thrown.
 */
export async function captureState(page, { url, width, theme, landmarks = 'common', masks = MASKS, requireLandmarks = true }) {
  const viewport = await openState(page, { url, width, theme });

  await settle(page, viewport.height);
  const specs = landmarksFor(landmarks);
  const { geometry, styles, counts } = await probeLandmarks(page, specs);
  const absent = findMissingRequired(specs, counts);
  if (requireLandmarks && absent.length) throw new Error(`Required landmarks not found at ${url} (${width}px, ${theme}): ${absent.join(', ')}`);
  await applyMasks(page, masks);
  await page.mouse.move(0, 0);
  const png = await page.screenshot({ fullPage: true, animations: 'disabled', caret: 'hide' });
  return { png, geometry, styles, counts };
}
