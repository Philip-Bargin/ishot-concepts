# WordPress Phase 3b/3c Implementation Plan (templates, template parts, blocks)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status (revision 4, 2026-11-22):** The owner approved the strategy and decisions D1–D7 with refinements (recorded in §3), accepted Stage 0, approved the Phase 1 prototype visuals, and accepted Stage 1 (`bb5ad6a`) with decisions on exceptions E-1 to E-5 (recorded at the start of §6 Stage 2). **Stage 2 is authorised** (content blocks, block styles, section patterns). Revision 4 expands Stage 2 into tasks 2.0–2.6 with rulings `R-2-*`. Stop at the Stage 2 checkpoint and ask for approval before Stage 3. Phase 3e, A1/A2, merging PR #1 and deleting either Vercel deployment all need separate explicit authorisation.

*Revision history:* r1 draft; r2 owner-approved strategy (D1–D7); r3 Stage 1 task level (`d0739b7`); **r4 Stage 2 task level (this revision)**.

**Goal:** Turn the `ishot-concept` block theme into a faithful WordPress version of the approved React prototype (commit `50e60f4`, preview `lbmvj4229`). Every page is built from templates, template parts, patterns, core blocks and a small, justified set of custom blocks. The owner manages all of the content from the dashboard, and the site is ready for the Phase 3e booking blocks.

**Architecture:** The theme loads the design system's `tokens.css` and `bundle.css` unchanged, so the same `fo-`/`ish-` markup produces the same pixels. Native blocks and patterns are used wherever they reproduce the design exactly. Custom blocks are server-rendered (`render.php`), and the few interactive ones use Interactivity API script modules. Code is split by responsibility into three packages (§4.1): the **theme** (frame and presentation), a new **`ishot-blocks` companion plugin** (reusable content blocks, portable across themes), and the existing **`ishot-booking` plugin** (data model, settings, booking and, in 3e, the booking blocks).

**Tech stack:** WordPress 6.7 (wp-env pin), PHP 8.2, block theme + `theme.json` v3 (generated), `@wordpress/scripts` (pinned exact) with committed build output, Interactivity API, PHPUnit in wp-env, Playwright and axe-core.

**Spec:** the approved master plan (`~/.claude/plans/pasted-content-id-2081-review-the-elegant-unicorn.md`, §2.2, §2.6 and §7 Phase 3); the owner's approval of 2026-11-21 (§3 below); `docs/phase-3e-integration.md`; `docs/outstanding-acceptance.md`; `docs/phase-1-verification.md`; `design-system/`.

---

## 1. Global constraints

- **Branch:** all 3b/3c work goes on `redesign/wp-phase-3b-3c`, cut from `redesign/design-system` at `d14c387` (the approved baseline). Nothing unrelated is added. PR #1 (`redesign/design-system` → `main`) stays an open draft and is not merged. Any PR for this branch is opened only with the owner's agreement.
- **Deployments:** both Vercel deployments (`lbv7fljde`, `lbmvj4229`) are kept. Nothing is deployed to Vercel from this branch.
- **Design source of truth:** `design-system/` (version `1795143900-037f`), synced by `npm run sync:ds`. Generated files are never edited by hand. Hand-written CSS uses tokens only (`npm run lint:tokens`).
- **Visual reference:** the prototype source at `50e60f4`. The files under `src/`, `public/`, `index.html`, `vite.config.js` and `design-system/` must stay byte-identical to that commit. The reference harness checks this (Task 0.1).
- **No design changes.** A significant layout or component deviation is a defect whatever its numerical score (§5).
- **Preserve completed work:** the booking backend, REST API, content model, wp-admin screens and their 319 tests are reused and not rebuilt. Plugin changes are additive (Task 0.3 in Stage 0; in Stage 1, the `Importer` navigation seed in Task 1.2 and the guarded dev classes accepted as exception E-5; in Stage 2, `ishot-booking` gets tests only, Task 2.0).
- **No front-end jQuery** and no page builders. Front-end JavaScript is limited to Interactivity API modules.
- **Content honesty:** no invented business content. Placeholders keep visible notices. Testimonials appear only when there are 3 or more consented, approved entries. Empty Facebook = inactive icon. The WhatsApp number stays "to be confirmed" in the checklist. The outstanding content checklist (`docs/phase-1-verification.md`, `docs/content-inventory.md`) is kept and updated, never dropped.
- **Social links:** exactly Instagram, Facebook and WhatsApp, with the prototype's `BrandIcon` glyphs.
- **URLs kept:** `/`, `/about`, `/services`, `/portfolio`, `/portfolio/{category}`, `/work/{project}`, `/creative-direction`, `/journal`, `/journal/{post}`, `/contact`, `/book`, `/account` and `/manage-booking/`.
- **Accessibility:** WCAG 2.2 AA. axe reports 0 violations at 375/768/1440 in light and Night. Real-device, screen-reader and OS reduced-motion checks (B1–B3) are **mandatory before production approval**.
- **CI stays green:** build and token lint; generated and built files current; PHPCS (WPCS); PHPStan level 6; all PHPUnit suites.
- **Every task** is small enough to review on its own, ends with a separate review (code quality, design fidelity, accessibility, compatibility), and records its verification evidence in `docs/phase-3b-3c-progress.md`.

---

## 2. What already exists and what still needs building

### 2.1 Completed and reused as-is

| Area | What exists | Where | Reuse in 3b/3c |
|---|---|---|---|
| Design tokens → WordPress | `theme.json` generated from `tokens.json`: palette via `var(--token)` (so Night works), fluid type, spacing, 720/1280 layout | `scripts/sync-design-system.mjs`, `theme.base.json` | All styles and patterns use presets only |
| Design-system CSS | `tokens.css` and `bundle.css` enqueued unchanged; fonts self-hosted and preloaded | theme `assets/`, `functions.php` | Blocks print prototype `fo-` markup; no new component CSS |
| Core block overrides | Square buttons and images, outline button, form controls, separators, gutters; editor styles | theme `assets/css/theme.css` | Core blocks already on-brand |
| Owner guardrails | No custom colours, sizes, gradients, duotones or radii; remote and core patterns off | `theme.base.json`, `functions.php` | Every editor choice is a token |
| Content model | `ishot_service`, `ishot_project` (`/work/`), `ishot_testimonial` (consent), `ishot_category` (`/portfolio/`, aperture), attachment `focus`, `content_status`, placeholder publish guard | `ishot-booking` `src/Content/`, `src/Catalog/` | Data blocks read these; no new content types |
| Dashboard editing | Details boxes, gallery picker, `edit_ishot_content`, roles v2 | `ishot-booking` `ContentAdmin` | Content management for services, projects and testimonials is done |
| Prototype import | `npm run wp:import` (content and images with unique alts, marked placeholder) | `ImportCommand`, `Importer` | Seeds identical content for visual comparison |
| Booking backend | Domain, schema, slot locks, REST `ishot/v1`, notifications, ICS, privacy, retention | `ishot-booking` `src/` (319 tests) | 3e builds on it; untouched here |
| Photographer and staff experience | wp-admin: list, detail with audit timeline, week calendar, block time, hours, settings, emails, enquiries, dashboard widget | `ishot-booking` `src/Admin/` | **Complete.** No staff UI in 3b/3c. |
| Manage-link security | Fragment tokens, header-only token, CSP, no-store, legacy redirects | `Frontend/ManageUrl` | The manage-page shell stays within this CSP |
| Tooling | wp-env (dev :8888, tests :8889), Mailpit, DNS shim, `npm run qa`, GitHub CI | repo | Extended in Task 0.2 |

### 2.2 Still to build

| Area | Gap | Stage |
|---|---|---|
| Visual comparison harness | None in the repo (the Phase 1 harness was in the gitignored scratchpad) | 0 |
| Block build and test suites | No `@wordpress/scripts`, no `ishot-blocks` plugin, no theme tests | 0 |
| Studio details | No central contact or social settings; the prototype hard-codes them in `src/data.js` | 0 |
| Shared render helpers | No PHP equivalent of the prototype's `Photo`/`Icon`/`PrototypeNotice` | 0 |
| Template parts | header, footer | 1 |
| Templates | Only a placeholder `index.html` | 1, 3 |
| Custom blocks | 15 (§4.2) + `ishot/spectrum` (Stage 1, exception E-3) | 1, 2 |
| Block styles and patterns | §4.3, §4.4 (revised by R-2-2, R-2-5, R-2-9) | 2 (section), 3 (page) |
| Founder, "Why us" and stats content | No WordPress source yet | 2 |
| Journal permalinks | `/journal/{post}` structure and posts page | 3 |

### 2.3 Later phases (named so nothing is lost; not authorised)

3e booking blocks (outline in §7), 3f owner guide and lock review, 3g SEO, Phase 4 hardening and launch.

---

## 3. Decisions (owner-approved 2026-11-21)

| # | Decision | Final position |
|---|---|---|
| D1 | Block architecture | **Approved with refinement.** Theme: templates, parts, patterns, presentation styling and frame-only blocks. `ishot-booking`: booking functionality and booking blocks (3e), Studio details settings. **Companion plugin `ishot-blocks`** for reusable content blocks (evaluation in §4.1). Native blocks wherever they reproduce the design accurately; every custom block justified in §4.2 (20 proposed → **15**). |
| D2 | Build tool | **Approved.** `@wordpress/scripts` pinned to an exact version, `package-lock.json` committed, `npm ci` only, builds committed to `build/` so the host needs no Node. CI rebuilds and fails on any difference. |
| D3 | Studio details | **Approved.** A "Studio details" section in the `ishot-booking` Settings screen, with validation, the `manage_ishot_settings` capability, a nonce, escaping on output, and defined behaviour for every empty field (Task 0.3). |
| D4 | Founder | **Approved.** An editable `ishot/founder` block, not a content type. Placeholder state is visibly labelled. |
| D5 | Visual fidelity | **Approved with modification.** A reproducible method combining controlled rendering, a **layout geometry comparison** (pass/fail), per-region pixel comparison (a signal for review, not the sole gate) and a **manual side-by-side review**. Significant deviations are fixed whatever the score. Full method in §5. |
| D6 | Editing restrictions | **Approved.** `contentOnly` locking on page sections and templates. **Controlled layout changes:** only administrators (`edit_theme_options`) can unlock (`canLockBlocks`) and edit templates in the Site Editor; managers and editors can't. Layout changes made in the Site Editor are saved as user templates, which can be reset to the theme version with "Reset" (documented in the 3f owner guide). |
| D7 | Branch | **Modified.** Dedicated branch `redesign/wp-phase-3b-3c` from `d14c387` (the `redesign/` prefix keeps the existing CI push trigger). PR #1 stays an open draft; no unrelated changes. |

---

## 4. Finalised block inventory

### 4.1 Companion plugin evaluation (D1)

| Option | Portability | Maintainability | Verdict |
|---|---|---|---|
| Content blocks in the **theme** | Poor. Blocks saved in page content (hero, founder, services…) render nothing after a theme change ("This block is not supported"). | One less package | Rejected for content blocks; kept for frame-only blocks used solely in theme templates and parts |
| Content blocks in **`ishot-booking`** | Good | Mixes presentation into the booking plugin, which then needs a JS build, editor assets and design-system markup. Booking releases become tied to design changes. | Rejected |
| **Companion plugin `ishot-blocks`** (depends on `ishot-booking` via the `Requires Plugins` header) | Good: content survives a theme change | Clear boundaries: booking logic and data in `ishot-booking`, reusable display in `ishot-blocks`, look and layout in the theme. One more package, but it follows the existing plugin's structure (Composer autoload, PHPUnit, PHPCS/PHPStan). | **Chosen** |

The theme declares both plugins as required in its README. Without them, it shows only the frame and an admin notice. Shared helpers (Media, Icons, Notice) live in `ishot-blocks`, so the theme's three frame blocks call `IShot\Blocks\…` behind a `class_exists` guard.

### 4.2 Custom blocks: 15, each justified

| Block | Package | Why a native block or pattern can't do it | Interactive |
|---|---|---|---|
| `ishot/logo` | theme | `core/site-logo` holds one image; the design needs the full-colour or reversed-white variant chosen by Night theme and background (`band`/photo), at fixed design-system heights | — |
| `ishot/site-header` | theme | `core/navigation`'s overlay can't reproduce the numbered menu-sheet design or its dialog behaviour (focus trap, focus return, scroll lock, reduced-motion) exactly. Links still come from a core Navigation entity, so the owner edits the menu natively. | yes |
| `ishot/theme-toggle` | theme | No core equivalent (Night theme switch with `aria-pressed` and persistence) | yes |
| `ishot/social-links` | ishot-blocks | `core/social-links` uses its own icon artwork and per-instance URLs. The design needs the approved line-style glyphs, a single source (Studio details) and an **inactive** Facebook state ("link not added yet"). | — |
| `ishot/hero-slideshow` | ishot-blocks | No core slideshow. Needs a 6 s cross-fade, pause/play, dots, a 5-minute stop and reduced-motion behaviour. | yes |
| `ishot/service-tiles` | ishot-blocks | Query Loop + block bindings can't format the price with the Settings currency, show the icon enum, hide a zero price or filter on `content_status` | — |
| `ishot/service-list` | ishot-blocks | Same as above, plus the inclusions array and the "Book" link per service | — |
| `ishot/category-cards` | ishot-blocks | Core has no taxonomy-term loop in 6.7; cards need the term cover image and aperture colour | — |
| `ishot/project-gallery` | ishot-blocks | Masonry from project gallery meta, link-based category filters, "Load more" and a gallery lightbox with navigation. Core's image lightbox has no next/previous or focus management across images. | yes |
| `ishot/marquee` | ishot-blocks | The seamless loop needs a duplicated, `aria-hidden` copy and reduced-motion pause, which core can't mark up | — (CSS) |
| `ishot/testimonials` | ishot-blocks | Must enforce the rules "approved + consented + published" and "hidden if fewer than 3", which a Query Loop can't | yes (if the prototype slider is kept) |
| `ishot/founder` | ishot-blocks | D4: one editable section with a placeholder state and notice | — |
| `ishot/share-links` | ishot-blocks | Builds WhatsApp/Facebook share URLs for the current post and a copy-link button with a live status; core has neither | yes |
| `ishot/notice` | ishot-blocks | Shows automatically from the post's `content_status`; a static paragraph can't follow the field | — |
| `ishot/contact-details` | ishot-blocks | Reads Studio details (phone, WhatsApp, email, address) with defined empty-field behaviour; a static paragraph would duplicate the data | — |

**Removed from the first draft (native instead):**

| Draft block | Native replacement |
|---|---|
| `page-title` | Pattern: group `ish-title` with eyebrow paragraph, H1 and lead paragraph |
| `stats` | Pattern of groups with the `fo-stats`/`fo-stat` classes. Not inserted until sourced figures exist. |
| `spectrum` | Empty group with the `fo-spectrum` class inside patterns (decorative and not announced) |
| `photo-strip` | `core/gallery` with block style `strip`. A small theme `render_block` filter adds `role="region"`, `aria-label` and `tabindex="0"` (keyboard scrolling), which core can't set. |
| `post-meta` | `core/post-terms` (style `eyebrow`) + `core/post-date` |

**Revision 4 notes on this table** (rulings in §6 Stage 2): `spectrum` became the frame block `ishot/spectrum` (E-3, owner-accepted). The prototype, not the "why" column, defines each block's output: no price, deposit or inclusions on the service blocks (R-2-15); share links are WhatsApp, email and copy link (R-2-20); `ishot/marquee` is interactive (R-2-6); `ishot/hero-slideshow` also has a `static` variant for the Creative hero (R-2-8); the category card cover is the category's first portfolio photo (R-2-17); the gallery's "project" mode moves to Stage 3 (R-2-12).

### 4.3 Core blocks with design-system block styles (theme `inc/BlockStyles.php`)

paragraph `eyebrow` · heading `statement` · button `primary` / `outline` / `text` · ~~list `features`~~ · image `portrait` / `landscape` / `square` · gallery `strip` · ~~quote `fo-quote`~~ · group `band` / `mist` · post-terms `eyebrow`.

Revision 4 (R-2-2): a style adds the design-system class itself (front-end filter, editor wrapper class), so no design-system declaration is duplicated; button `text` is a token-only `theme.css` alias. List `features` and quote `fo-quote` are dropped because a core list or quote can't carry that markup; the `feature-list` pattern and `ishot/testimonials` cover them.

### 4.4 Patterns (theme `patterns/`)

Section patterns: `page-title`, `section-heading`, `stats`, `feature-list`, `why-us`, `cta-band` (**unsynced**, R-2-5: every page has its own CTA copy), `blog-card` (post template; **moved to Task 3.5** with the Journal, R-2-9).
Page patterns (locked `contentOnly`): `home`, `about`, `services`, `creative-direction`, `contact`, `portfolio`.

### 4.5 Plugin-based functionality

| Function | Package | Stage |
|---|---|---|
| Studio details settings and `Studio` API | `ishot-booking` | 0 |
| Content types, fields, import, placeholder guard | `ishot-booking` (exists) | — |
| Booking-flow, my-bookings, manage-booking, contact-form blocks | `ishot-booking` | 3e (not authorised) |
| Staff and photographer screens | `ishot-booking` (exists) | — |

---

## 5. Visual comparison methodology (D5)

The goal is a result that is repeatable on any machine and in CI, and that separates real layout defects from rendering noise.

**5.1 Controlled rendering (both prototype and WordPress)**
- The same Playwright version and bundled Chromium (pinned in `package-lock.json`), device scale factor 1, fixed viewports 375×812, 768×1024 and 1440×900, locale `en-US`, timezone `Africa/Monrovia`.
- Fonts: both sides use the same self-hosted woff2 files. Capture waits for `document.fonts.ready`.
- Motion: `reducedMotion: 'reduce'`, plus an injected stylesheet that sets every animation and transition duration to 0 and hides the caret. The slideshow is frozen on slide 1; marquee paused.
- Images: lazy images are forced to load and awaited (`img.decode()`) before capture.
- Content: WordPress is seeded with `npm run wp:import` from the same `src/data.js` and image files, so the content is identical.
- Dynamic content (current date, "today" in calendars, the footer year, booking availability) is fixed with Playwright's clock (`2026-11-23T09:00Z`) or masked, with each mask listed in `tests/visual/masks.js`.
- Prototype integrity: the harness refuses to run if `git diff --quiet 50e60f4 -- src public index.html vite.config.js design-system` fails.

**5.2 Three comparisons per page state, at 3 widths × 2 themes**

| Check | What | Role |
|---|---|---|
| A. Layout geometry | Bounding boxes (x, y, width, height) of a named list of landmarks per page: header, logo, nav, each section, section headings, buttons, cards, images, footer columns, container edges. Read with `getBoundingClientRect` on both sides. | **Gate.** Each box within ±2 px in position and size (±4 px in height for text blocks whose wrapping may differ by font hinting). Any failure must be fixed. |
| B. Per-region pixel comparison | Screenshots cropped to each landmark region, compared with `pixelmatch` (anti-aliasing tolerant, threshold 0.1) | **Signal.** Over 1 % of pixels in a region flags it for manual review. A small number is not proof of fidelity, and a large number from proven noise (e.g. image resampling) can be accepted with a recorded reason. |
| C. Style probes | Computed `font-family`, `font-size`, `font-weight`, `line-height`, `letter-spacing`, `color`, `background-color`, `border`, `padding` for each landmark | **Gate.** Must equal the prototype exactly (colours compared after resolving tokens) |

**5.3 Manual visual review**
- Each stage gate's review pack contains, per page state: the prototype screenshot, the WordPress screenshot, an overlay (50 % blend) and the diff heat-map, at all 6 width/theme combinations.
- The reviewer (subagent at each task, the owner at each stage gate) checks a fixed list: alignment to the container, spacing rhythm, type hierarchy, colours in both themes, image crops and focus points, icon set, interactive states (hover, focus-visible, open menu, lightbox), and empty and placeholder states.
- **Any significant layout or component deviation is corrected whatever the numerical scores.** Accepted exceptions are written into the review pack with a reason and need the owner's agreement.

---

## 6. Roadmap

**Process for every task (owner-approved method):**
1. Review the task's requirements and the relevant skills (`superpowers:test-driven-development`; `design-superpowers:ds-consumer` for markup; Context7 for WordPress 6.7 APIs).
2. Implement test-first (failing test → implement → pass).
3. Run the applicable automated tests: the task's own, then `npm run build`, `npm run lint:tokens`, `npm run qa` and the affected PHPUnit suites.
4. A **separate reviewer subagent** checks code quality, design fidelity, accessibility and compatibility, as two reviews: spec compliance, then code quality.
5. Resolve every finding and re-review until clean.
6. Record the work and its evidence (commands, results, review findings and fixes) in `docs/phase-3b-3c-progress.md`, then commit.

### Stage 0: Foundations (AUTHORISED)

**Task 0.1: Visual comparison harness and prototype references** *(S)*
- Files: create `playwright.config.mjs`, `tests/visual/routes.mjs`, `tests/visual/landmarks.mjs`, `tests/visual/masks.mjs`, `tests/visual/capture.mjs` (controlled rendering, §5.1), `tests/visual/geometry.mjs` (getBoundingClientRect and computed-style probes), `tests/visual/compare.mjs` (§5.2 A–C; outputs JSON plus an HTML side-by-side report), `tests/visual/reference.spec.mjs`, `scripts/visual-reference.mjs`. Modify `package.json` (exact-pinned devDependencies `@playwright/test`, `@axe-core/playwright`, `pixelmatch`, `pngjs`; scripts `visual:reference`, `test:visual`) and `.gitignore` (`tests/visual/.output/`).
- `routes.mjs` exports `ROUTES: Array<{name: string, prototypePath: string, wpPath: string, landmarks: string}>` for the 16 page states.
- Produces: `captureState(page, {url, width, theme}) → {png: Buffer, geometry: Landmark[], styles: StyleProbe[]}` and `compareStates(ref, actual) → {geometryFailures, styleFailures, regionDiffs}`.
- References are **regenerated on demand** from the prototype (the integrity check in §5.1 guarantees it equals `50e60f4`). Only `tests/visual/reference/geometry.json` and `styles.json` are committed (small, reviewable). Screenshots go to the gitignored output folder.
- Tests: `compare` unit tests (`node --test tests/visual/compare.test.mjs`): identical inputs → no failures; a 3 px shift → a geometry failure; a changed colour → a style failure; anti-aliasing-only noise → under 1 % region diff. Determinism: capturing the prototype twice gives identical geometry and styles, with a region diff of 0.
- Accept: `npm run visual:reference` succeeds for 16 states × 3 widths × 2 themes. Two consecutive runs are identical. The integrity check fails when a file under `src/` is touched.

**Task 0.2: `ishot-blocks` scaffold, block build pipeline and test suites** *(M)*
- Create the `wordpress/wp-content/plugins/ishot-blocks/` package:
  - `ishot-blocks.php`: header with `Requires at least: 6.7`, `Requires PHP: 8.1`, `Requires Plugins: ishot-booking`, text domain `ishot-blocks`, and a missing-vendor notice like `ishot-booking`.
  - `composer.json`: PSR-4 `IShot\Blocks\` → `src/`, the same dev dependencies as `ishot-booking`.
  - `phpunit.xml.dist`: suites `unit`, `integration`, and `theme` → `../../themes/ishot-concept/tests`.
  - `tests/bootstrap.php`: loads `ishot-booking` then `ishot-blocks`, and switches the theme to `ishot-concept`.
  - `src/Plugin.php`: registers every `build/blocks/*/block.json` with `register_block_type`.
  - `blocks/` (source) and `build/` (committed output).
- Create `scripts/build-blocks.mjs`: finds `block.json` files under the theme's and `ishot-blocks`' `blocks/` folders and runs `wp-scripts build` per package with explicit entries. With no blocks, it exits 0 and says so. Add a `--check` mode for CI (rebuild into a temp folder and compare byte-for-byte).
- Modify:
  - `package.json`: `@wordpress/scripts` pinned to an exact version; scripts `build:blocks`, `build:blocks:check`, `test:blocks`, `test:theme`.
  - `.wp-env.json`: add the `ishot-blocks` plugin.
  - `.github/workflows/ci.yml`: block build check; composer install and PHPUnit for `ishot-blocks`, including the theme suite; Playwright browsers install.
  - `tools/qa/phpcs.xml.dist` and `phpstan.neon.dist`: include `ishot-blocks` and the full theme; text domain `ishot-blocks`.
  - `scripts/qa.mjs`, only if it needs the new vendor path.
- Theme: create `themes/ishot-concept/tests/ThemeSetupTest.php`; README "Requires plugins" section.
- Tests:
  - `PluginTest::test_registers_every_built_block()` (fixture block under `tests/fixtures`);
  - `ThemeSetupTest::test_design_system_styles_enqueued_in_order()` (tokens → bundle → theme);
  - `ThemeSetupTest::test_theme_active_in_test_env()`;
  - a `node --test` test of `build-blocks.mjs` against a temp fixture package (builds; zero-blocks path exits 0; `--check` detects a stale build).
- Accept:
  - `npm ci && npm run build:blocks:check` is reproducible.
  - All PHPUnit suites pass (319 existing + new).
  - PHPCS and PHPStan level 6 are clean.
  - CI is green on the branch.
  - The `ishot-blocks` plugin activates on dev and tests and shows the notice when `ishot-booking` is inactive.

**Task 0.3: Studio details (ishot-booking, additive)** *(M)*
- Files:
  - Create `src/Studio.php` and `tests/Integration/StudioDetailsTest.php`.
  - Modify `src/Settings.php`: keys `studio_phone`, `studio_whatsapp`, `studio_instagram_url`, `studio_facebook_url`, `studio_address`, `studio_public_email`, all default `''`; there is no schema change, as they live in the existing settings option.
  - Modify `src/Admin/SettingsForm.php` and `SettingsScreen.php`: a "Studio details" fieldset on the existing settings form, saved by the existing `POST_POLICY` handler, or by a new `POST_STUDIO` handler if the form is separate.
  - Modify `src/Content/Importer.php`: seed these values from the prototype's `contact` object only when they're empty, recorded as placeholders.
- Interfaces produced:
  - `Studio::details(): array{phone: string, phone_href: ?string, whatsapp_url: ?string, instagram_url: ?string, facebook_url: ?string, email: string, address: string}`;
  - `Studio::socials(): list<array{id: 'instagram'|'facebook'|'whatsapp', label: string, url: ?string}>` (always all three, in that order; `url` null = inactive).
- Validation and permissions:
  - saving requires `manage_ishot_settings` and a valid nonce;
  - phone and WhatsApp accept digits, spaces, `+`, `-` and parentheses (max 30) and are normalised to E.164 digits for links when written internationally (`https://wa.me/<digits>`, `tel:+<digits>`); a local phone number links as typed, digits only (`tel:0881405259`, owner decision 2026-11-22); WhatsApp stays international-only;
  - the Instagram and Facebook URLs must be `https` and on `instagram.com` / `facebook.com` (including `www.` and `m.`);
  - the email must pass `is_email`;
  - the address is plain text, max 300 characters, newlines kept;
  - invalid input is rejected with a field-level error and the previous value is kept;
  - every value is escaped on output by the consumers (`esc_url`, `esc_html`).
- Tests:
  - an empty Facebook gives `url === null`;
  - `"+231 88 140 5259"` gives `https://wa.me/231881405259`;
  - `http://instagram.com/x` is rejected;
  - `https://evil.example/instagram.com` is rejected;
  - a user without `manage_ishot_settings` can't save (403/redirect with an error);
  - a bad nonce is rejected;
  - `socials()` always returns 3 items in order;
  - the importer doesn't overwrite owner-entered values;
  - the existing settings tests still pass.
- Accept: all tests pass. The owner can edit the details on the Settings screen. No value is invented, and the WhatsApp number remains flagged "to confirm" in the checklist.

**Task 0.4: Shared render helpers (ishot-blocks)** *(S)*
- Files: create `ishot-blocks/src/Media.php`, `src/Icons.php` (Lucide subset used by the prototype plus the brand glyphs copied byte-for-byte from `src/components/SocialLinks.jsx`), `src/Notice.php`; tests `tests/Integration/MediaTest.php`, `tests/Unit/IconsTest.php`, `tests/Integration/NoticeTest.php`.
- Interfaces produced:
  - `Media::photo(int $attachment_id, string $ratio = 'portrait', string $sizes = '100vw', bool $priority = false, ?string $alt = null): string`, with ratio one of `portrait|landscape|square|tall|wide|strip|video|blog`;
  - `Media::placeholder(string $ratio): string`;
  - `Icons::svg(string $name, string $label = ''): string`;
  - `Notice::render(string $label = 'Sample content.'): string`;
  - `Notice::forPost(int $post_id): string` (returns '' unless `content_status` is placeholder).
- Tests:
  - `photo()` prints `srcset`, `sizes` and the `ish-r-{ratio}` class;
  - `object-position` comes from the attachment `focus` meta;
  - `fetchpriority="high"` + `loading="eager"` only when `$priority`, otherwise `loading="lazy"` + `decoding="async"`;
  - a missing or 0 id gives the placeholder frame;
  - `alt=""` when `$alt === ''`;
  - an unknown icon gives '' with no warning;
  - a labelled icon gets `role="img"` + `aria-label`, an unlabelled one `aria-hidden="true"`;
  - the markup matches the prototype's `Photo`, `Placeholder` and `PrototypeNotice` output (snapshot strings taken from the built prototype).
- Accept: tests pass; PHPCS and PHPStan clean.

**Stage 0 gate:** summary, files, test results, review findings, remaining risks, and confirmation that the prototype, booking backend and design are unaffected. **Stop for owner approval.**

### Stage 1: Global frame (3b template parts). AUTHORISED 2026-11-22.

Revision 3 (2026-11-22) expands the four Stage 1 bullets of revision 2 into tasks, adding two items the owner required (`--experimental-modules` and the duplicate block-registration test fix), and two gaps found while preparing the stage: the prototype's app stylesheet is not yet in the theme, and the visual harness can't yet capture WordPress. Header, footer and base templates only. No page content (Stage 2/3), no 3e, A1/A2 not applied.

**Rules for every Stage 1 task**
- Markup is the prototype's markup: the same elements, `fo-`/`ish-` classes, ARIA and text as `Header.jsx`, `MenuSheet.jsx`, `Footer.jsx`, `Logo.jsx`, `SocialLinks.jsx` and `App.jsx` at `50e60f4`. No new component CSS. Where WordPress wrappers change the layout, `theme.css` neutralises the wrapper using tokens only.
- Dynamic values come from existing sources only: `IShot\Booking\Studio::details()` / `socials()` for contact and social data, a core `wp_navigation` entity for the menu, and the shared helpers `IShot\Blocks\Icons` / `Media` / `Notice`. The theme calls plugin classes behind `class_exists`. Without the plugins it prints the frame without those parts, and never a fatal error.
- Server-rendered markup is identical for guests and logged-in users (page-cache safe). The only per-viewer state is the Night choice, applied client-side.
- Interactive behaviour uses Interactivity API script modules (`viewScriptModule`) only, with no jQuery.
- Evidence (commands, counts, screenshots) goes in `docs/phase-3b-3c-progress.md`.

#### Task 1.0a: Block build modules, theme block registration, app stylesheet, duplicate-registration test fix *(S)*

- `scripts/build-blocks.mjs`: pass `--experimental-modules` to every `wp-scripts build` call (so `viewScriptModule` entries are built as ES modules with a `.asset.php`), and add a `--watch` mode that runs `wp-scripts start --experimental-modules` for each package (development). `package.json`: new script `start:blocks` = `node scripts/build-blocks.mjs --watch`. `--check` keeps working and uses the same flag.
- `tests/blocks/*.test.mjs`: the spawned `wp-scripts` arguments include `--experimental-modules` in build, check and watch modes. A fixture block with a `viewScriptModule` builds a `view.js` module and a `view.asset.php`.
- **Duplicate block-registration test fix:** `ishot-blocks/tests/Integration/PluginTest.php::test_blocks_register_without_the_theme_being_consulted()` calls `Plugin::registerBuiltBlocks()` and then `Plugin::registerBlocks($build)` again. Once real blocks exist in `build/blocks`, the second call registers the same names twice, WordPress rejects them, `$skipped` fills and the test fails. Fix: unregister what the first call registered (or assert on the first call's result only) and clear the skipped list in `set_up`/`tear_down`, so every test starts with a clean registry. Add a regression test proving that two calls in one request, with a real block in the build folder, report `missing === []` the second time and leave no admin notice. Do not change `Plugin`'s production behaviour, unless the regression test shows that a second registration in one request reports a false problem (then registration must skip names already registered by this plugin).
- Theme block registration: `functions.php` registers each `themes/ishot-concept/build/blocks/*/block.json` on `init` (theme frame blocks only: `ishot/logo`, `ishot/site-header`, `ishot/theme-toggle`). A folder without `block.json` is skipped silently on the front end. `ThemeSetupTest` covers this with a fixture.
- **Prototype app stylesheet:** the prototype's app layer `src/index.css` (the `ish-` classes for header, menu, footer, skip link, title blocks…) is part of the approved design but is not in the theme. `npm run sync:ds` additionally copies it, byte-for-byte after a generated-file header comment, to `themes/ishot-concept/assets/css/app.css`, and copies `public/brand/*.png` to `themes/ishot-concept/assets/brand/`. Enqueue order becomes tokens → bundle → app → theme, on the front end and in the editor (`add_editor_style`). `lint:tokens` scans it. A CI check (the existing generated-files check, or `sync:ds --check` if none exists) fails when `app.css` is stale. `ThemeSetupTest::test_design_system_styles_enqueued_in_order()` is updated to the four handles.
- Accept: node tests pass; `npm run build:blocks:check` passes; `test:blocks` and `test:theme` pass; `npm run qa` is clean; the prototype is byte-identical to `50e60f4`.

#### Task 1.0b: WordPress capture target for the visual and accessibility harness *(M)*

- `tests/visual`: `VISUAL_TARGET=wp` captures `wpPath` on `VISUAL_BASE_URL` (default `http://localhost:8888`) with the same controlled rendering (§5.1) and compares it with the committed prototype reference. Playwright starts no Vite server in this mode. The prototype integrity check still runs.
- `VISUAL_SCOPE=frame` limits landmarks to the frame (`header`, `header-container`, `logo`, `header-nav`, `header-actions`, `footer`, `footer-column`, `footer-legal`, plus a new `skip-link`). The default scope `full` keeps every landmark. `VISUAL_STATES=404,about` limits the states. Review-only states (`gate: false`) are exempt from the region assertion (deferred minor from Task 0.1).
- Alignment check: in every captured state, the left and right content edges of `header-container`, the footer grid container and the legal band equal those of `main .fo-container` (±1 px). This is reported as `alignment` failures (24/24 = 4 states × 3 widths × 2 themes in the Stage 1 gate).
- `tests/visual/a11y.spec.mjs`: axe (WCAG 2.1 A/AA + best practice, `@axe-core/playwright`) on the given states at 3 widths × 2 themes, with the menu closed and open. 0 violations is the gate.
- npm scripts: `test:visual:wp` (`node scripts/visual-reference.mjs --check --target wp`, passing scope and states through) and `test:a11y:wp`. The HTML side-by-side report also gets an overlay (50 % blend) column (§5.3).
- Tests: node unit tests for scope filtering, state filtering, the alignment computation and the gate:false region exemption.
- Accept: node tests pass. `test:visual:wp` runs end to end against the dev site (failures are expected until Tasks 1.1–1.4 land) and writes the report. The prototype reference mode is unchanged (`npm run test:visual` still passes).

#### Task 1.1: Night bootstrap + `ishot/theme-toggle` *(S)*

- Theme `wp_head` (priority 0): a tiny inline script that reads `localStorage['ishot-theme']` (`light`|`dark`, guarded with try/catch, default `light`) and sets `document.documentElement.dataset.theme` before any stylesheet is applied. `<html>` gets `data-theme="light"` server-side through `language_attributes`. The same key and values as `src/lib/theme.js`. The manage page's CSP already allows inline scripts (`script-src 'self' 'unsafe-inline'`): assert that, don't change the CSP.
- `ishot/theme-toggle` (theme block, `viewScriptModule`, Interactivity API): renders `<button type="button" class="ish-linkbtn" aria-pressed="false|true">Night mode</button>` exactly as `Footer.jsx`'s `ThemeToggle`. Clicking flips the theme, persists it (guarded), and updates `aria-pressed`. The initial `aria-pressed` is synced from the document on hydration.
- Tests: PHPUnit (the head script is printed before the theme's stylesheets; `data-theme="light"` on `<html>`; the block's markup). Playwright on wp-env: a stored `dark` preference has `data-theme="dark"` at `DOMContentLoaded` (no flash); clicking toggles and persists across reload; `aria-pressed` is correct; works with storage blocked.

#### Task 1.2: Header part, `ishot/logo`, `ishot/social-links`, `ishot/site-header`, skip link *(L)*

- `ishot/logo` (theme): prints the official files from `assets/brand/` exactly as `Logo.jsx` (`auto` = full-colour + reversed images with `ish-logo__full`/`ish-logo__rev`; `reversed` = the white file only). The alt is the site name (`iShot Concept`). It's wrapped in `<a class="fo-logo" href="/">` by the parent block or part.
- `ishot/social-links` (ishot-blocks, the first real block in that plugin): Instagram, Facebook and WhatsApp from `Studio::socials()`, always in that order, with the `BrandIcon` glyphs from `Icons`. A URL gives `<a class="fo-icon-btn" … target="_blank" rel="noopener noreferrer" aria-label="{Label} (opens in a new tab)">`. A null URL gives the prototype's inactive element and label ("link not added yet"), not focusable. Output matches `SocialLinks.jsx`. Without `ishot-booking`, the block prints nothing.
- `ishot/site-header` (theme, `viewScriptModule`, Interactivity API): `Header.jsx` + `MenuSheet.jsx` markup. Logo; `nav.fo-header__social[aria-label=Social]` with the social links; actions ("My bookings" → `/account/`, "Book now" → `/book/`, `button.fo-menu-toggle` with the Lucide `menu` icon at size 20, `aria-expanded`, `aria-controls="site-menu"`). The menu sheet is `div#site-menu.fo-menu.fo-band[role=dialog][aria-modal=true][aria-label="Site menu"]` with the reversed logo, the close button (`aria-label="Close menu"`, Lucide `x`), `nav[aria-label=Main] > ol.fo-menu__list` (each `li` with `style="--i:N"`, link `<span>NN</span>Label`, `aria-current="page"` on the current URL; A1 NOT applied), and the foot ("Book a session" button; `address · <a href=phone_href>phone</a>` from Studio details, each part omitted when empty; social links). The sheet is server-rendered `hidden` and shown by the module. Behaviour matches `useFocusTrap`: focus goes to the first menu link on open; Tab and Shift+Tab wrap; Escape and the close button close it; focus returns to the toggle; `body` scroll is locked while open; menu links close it. Reduced motion follows the design-system CSS (no JS animation).
- Menu items: from a core `wp_navigation` entity (block attribute `ref`; without one, the first published `wp_navigation`). When none exists, the block falls back to the eight prototype `navItems` (site structure, not business content). The `wp ishot import-prototype` command (ishot-booking `Importer`, additive) creates a "Primary" `wp_navigation` with those eight links when none exists, and never overwrites one.
- Header template part `parts/header.html`, area `header`. The `.fo-header` must stay `position: sticky` relative to the page, as in the prototype: the template-part wrapper must not become the sticky containing block (test: after scrolling 1500 px, the header's top is 0).
- Skip link: the prototype's `<a href="#main" class="ish-skip">Skip to content</a>` (text exactly as `App.jsx`) is printed first in `<body>`. Core's block-theme skip link is removed so there is only one. `main` has `id="main"` and `tabindex="-1"` (Task 1.4 templates).
- Tests: PHPUnit for each block's markup (snapshot-normalised against the prototype, as in Task 0.4), empty Studio details, a missing navigation entity, identical header HTML for a guest and a logged-in user, and a single skip link. Playwright keyboard spec (B4 subset): skip link, menu focus trap, Escape, focus return, scroll lock, `aria-expanded`, `aria-current`.

#### Task 1.3: Footer part and `ishot/contact-details` (footer variant) *(M)*

- `parts/footer.html`, area `footer`: `Footer.jsx` structure. Brand column (`ishot/logo` in `.fo-logo`, `.fo-spectrum` group, the tagline paragraph `small`, `.ish-social` with `ishot/social-links`); `nav.fo-footer__col[aria-labelledby=footer-studio]` "Studio" (About, Services, Portfolio); `nav…[aria-labelledby=footer-book]` "Book" (Book a session, My bookings, Contact); "Visit" column; legal band. These are the same three 3-link columns as the corrected prototype (`379d4e7`), and nothing else.
- Link columns: core blocks in the template part (editable by administrators in the Site Editor). Paragraph and link wrappers are neutralised in `theme.css` (tokens only) so that geometry equals the prototype.
- `ishot/contact-details` (ishot-blocks, from the §4.2 inventory), attribute `variant` (`footer` in this task; the Contact-page variant comes in Stage 2): `<span class="small">{address}</span>`, `<a href="{phone_href}">{phone}</a>` and `<a href="mailto:{email}">{email}</a>` from `Studio::details()`. Each line is omitted when its value is empty. A phone without a `phone_href` prints as text, with no link.
- Year: a theme block-bindings source (`ishot-concept/site`, key `year`) supplies the current year to the legal paragraph, so no custom block is needed. The visual harness mask for the year stays.
- Legal band: `© {year} iShot Concept · Photography, creative direction, visual storytelling` + `ishot/theme-toggle`, in `.fo-footer__legal.ish-footer__outer > .fo-container.ish-footer__legal`. Full-width backgrounds, inner content in `.fo-container` (the `0eff339` rule).
- Tests: PHPUnit (contact-details variants and empty fields; the bindings source; footer part parses with no invalid blocks). Frame geometry for every state passes at 3 widths × 2 themes. Alignment passes.

#### Task 1.4: Base templates, `page-title` pattern and `ishot/notice` *(M)*

- Templates: `index`, `page`, `single`, `404` and `search`. Each is `header part` + `<main id="main" tabindex="-1">` + `footer part`, inside one wrapper group with the prototype's `fo-root ish-app` classes, so that `.ish-app > main` applies. `page` uses the `page-title` pattern bound to the post title, then the post content. `404` reproduces the prototype's `NotFound` exactly: `PageTitle` eyebrow "404", "Page not found", the lead text, and the "Go home" and "View gallery" buttons. `search` uses the title block plus a result list (review-only state).
- Pattern `patterns/page-title.php`: group `ish-title` with an eyebrow paragraph, H1 and lead paragraph, the same markup as the prototype's `PageTitle` (`ui.jsx`).
- `ishot/notice` (ishot-blocks): prints `Notice::forPost()` for the current post (nothing unless `content_status` is placeholder). Used in the `page` and `single` templates.
- Editor: the templates and parts open in the Site Editor with no invalid-block warnings, and the editor canvas shows the design-system styles (verified with a logged-in Playwright check that also saves screenshots).
- Tests: PHPUnit (each template parses; 404 status and markup; the notice shows and hides by `content_status`). The visual harness: `404` passes in **full** scope; `about`, `home` and `journal-article` pass in **frame** scope; `search` is review-only.

#### Task 1.5: Stage 1 gate, verification and review pack *(S)*

- Run: frame scope on all 16 states and full scope on `404`, at 3 widths × 2 themes (geometry A and styles C as gates, regions B reviewed); alignment 24/24; axe 0 violations on `home`, `about` and `404` with the menu closed and open; the keyboard spec; the editor check; all PHPUnit suites (booking, blocks, theme); `npm run build`, `lint:tokens`, `build:blocks:check`, `qa`; the prototype integrity check.
- Write `docs/phase-3b-3c-stage-1-review.md`: the side-by-side, overlay and diff images for header, menu sheet and footer at the 6 combinations (images in `docs/phase-3b-3c-evidence/stage-1/`), the results, every accepted exception with its reason, and what's left for Stages 2–3. Update `docs/phase-3b-3c-progress.md`.
- Preview: the local wp-env site (`http://localhost:8888`) with the prototype content imported, plus the review pack. Nothing is deployed to Vercel or anywhere else.
- **Stop for the owner checkpoint.** Stage 2 needs the owner's approval.

### Stage 2: Content blocks, styles and patterns (3c). AUTHORISED 2026-11-22.

Revision 4 (2026-11-22) expands the five Stage 2 outline bullets of revision 3 into tasks. It adds the items the owner required at the Stage 1 checkpoint: Task 2.0 (exception E-5), the home h1 (E-2, by a minimal `front-page.html` in Task 2.2), a per-component visual gate (Task 2.1a), and a Stage 2 gate (Task 2.6). Where the prototype at `50e60f4` and the revision 3 wording disagree, the prototype wins (§1 "no design changes"). Each such decision is listed as a ruling `R-2-*` at the end of this stage. Section patterns only: page patterns, page templates and the Journal stay in Stage 3. No 3e, A1/A2 not applied, PR #1 not merged.

**Stage 1 owner decisions (2026-11-22), binding on Stage 2**
- **E-1** footer `y`: accepted temporarily. Full-page comparisons are repeated once each page body exists (Stage 3/4), with **no artificial spacing** added to match the prototype's page length.
- **E-2** home h1: resolved in Task 2.2 (hero on a minimal `front-page.html`). `home` must then pass axe with 0 violations.
- **E-3** `ishot/spectrum`: kept.
- **E-4** degraded-footer override: accepted. Re-verify once real Studio contact details are configured.
- **E-5** dev fixtures in `ishot-booking`: conditionally accepted. Task 2.0 adds the automated proof that no dev fixture can run or write in staging or production.

**Rules for every Stage 2 task**
- **Markup is the prototype's.** Same elements, `fo-`/`ish-` classes, ARIA, ids and copy as the cited `src/` lines at `50e60f4`. No new component CSS. `theme.css` may only neutralise WordPress wrappers, or alias a design-system state hook a native element cannot carry. It uses tokens only, and each rule names the design-system rule it mirrors.
- **Data from existing sources only:** `IShot\Booking\Studio`, the content model (`ishot_service`, `ishot_project`, `ishot_testimonial`, `ishot_category` with `aperture`, attachment `focus` and category terms, `content_status`), and the shared helpers `Media`/`Icons`/`Notice`. No new content types, no new post or term meta. `ishot-booking` gets tests only (no `src/` change) in Stage 2.
- **Every new block:**
  - `block.json` `apiVersion: 3`, `render.php` that delegates to an `IShot\Blocks\*` class with a pure `render()` and a data method, and escapes at output;
  - an `editorScript` using the Stage 1 `ServerSideRender` + `Disabled` pattern, or a real edit UI where the owner edits text or images (hero, founder);
  - `supports.inserter` set deliberately, with the reason in `block.json` `description`: section blocks are `true`; blocks that are part of a section are `false` and arrive through patterns and fixtures (Stage 1 lesson D1);
  - interactivity only through an Interactivity API `viewScriptModule`;
  - PHPUnit in `ishot-blocks` `tests/Integration` (blocks) or the theme suite (patterns, styles, templates);
  - built output committed (`npm run build:blocks:check` green);
  - an e2e editor check: the block or pattern is valid, supported and renders in the post editor or Site Editor, with a screenshot in `ISHOT_EVIDENCE_DIR`;
  - degradation guards: without `ishot-booking` a block prints nothing (never a fatal error), and the theme never calls a plugin class without `class_exists`.
- **Server-rendered motion starts in its safe state.** The slideshow, marquee and reveal print paused or visible markup. Motion starts only after the module hydrates, and never under reduced motion. Without JavaScript, nothing moves and nothing is hidden.
- **Spacing:** `settings.spacing.blockGap` stays `null` theme-wide (Stage 1 ruling). Patterns space content with the prototype's classes only. Section patterns' outer group carries `templateLock: "contentOnly"` (D6).
- **Content honesty (§1):** pattern and fixture copy is the prototype's own. Placeholders stay visibly labelled. Testimonials render only with 3 or more published, consented and approved entries. No price, rating or claim the prototype does not show.
- **Per task, before review:** the task's tests; `npm run build`, `lint:tokens`, `build:blocks:check`, `qa`; `test:php` (booking, as the regression guard), `test:blocks` and `test:theme`; the task's components through the component gate below; `npm run test:e2e` (Playwright `--workers=1` on a low-memory host); prototype integrity. Evidence goes in `docs/phase-3b-3c-progress.md`.

**Component visual gate (R-2-C), used by Tasks 2.1a–2.5**

Stage 2 builds no real pages, so components are compared **section by section** with the prototype, not page by page.
- **WordPress side:** dev-only component fixture pages at `/ishot-fixtures/{component}/`. Each page holds the prototype section rebuilt from Stage 2 blocks and patterns, with the prototype's ids, copy and photos (attachments found by the importer key). The hero is compared on the real front page `/` (Task 2.2).
- **Prototype side:** the same section on the prototype route.
- **Geometry:** measured relative to the component root. The root's own `x` and `width` stay absolute, so container alignment is still checked. The root's page `y` is ignored, so differences in page length (E-1) cannot mask or fake a result.
- **Checks:** A (±2 px; ±4 px height for text) and C (exact) are gates at 375/768/1440 × light/Night. B (regions) is reviewed. axe runs on each fixture page (and its interactive state) at the same 6 combinations, with 0 violations.
- **Exceptions:** an exception is allowed only when listed in the component's `exceptions` entry with a reason, and it needs owner agreement in the Stage 2 review pack.

| Component | Task | Prototype route → root (source) | WordPress path |
|---|---|---|---|
| `style-*` (eyebrow, statement, band, mist, image ratios, button text, post-terms eyebrow): **C only** | 2.1b | `/` and `/journal` elements carrying the same design-system class | `/ishot-fixtures/block-styles/` (a post, so post-terms render) |
| `strip` | 2.1b | `/` → `main .ish-strip` (`Home.jsx:17-23, 71-75`) | `/ishot-fixtures/strip/` |
| `statement` | 2.1c | `/` → `main section:has(#statement-title)` (`Home.jsx:55-78`) | `/ishot-fixtures/statement/` |
| `why-us` | 2.1c | `/` → `main section:has(#why-title)` (`Home.jsx:142-150`) | `/ishot-fixtures/why-us/` |
| `process` | 2.1c | `/services` → `main section:has(#process-title)` (`Services.jsx:49-56`) | `/ishot-fixtures/process/` |
| `cta-band`, `cta-band-no-text` | 2.1c | `/` and `/about` → `main section:has(#cta-title)` (`ui.jsx:76-98`) | `/ishot-fixtures/cta-band/`, `…/cta-band-no-text/` |
| `marquee` | 2.1c | `/` → `.fo-marquee` (`ui.jsx:173-201`) | `/ishot-fixtures/marquee/` |
| `contact-list` | 2.1c | `/contact` → `main .ish-contact-list` (`Contact.jsx:200-218`) | `/ishot-fixtures/contact-list/` |
| `hero` | 2.2 | `/` → `.fo-hero` (`Home.jsx:36-52`, `HeroSlideshow.jsx`) | `/` (front page) |
| `hero-static` | 2.2 | `/creative-direction` → `.fo-hero` (`Creative.jsx:16-42`) | `/ishot-fixtures/hero-static/` |
| `recent-work` | 2.3a | `/` → `main section:has(#recent-title)` (`Home.jsx:119-139`) | `/ishot-fixtures/recent-work/` |
| `portfolio`, `portfolio-portraits` | 2.3a | `/portfolio`, `/portfolio/portraits` → `main section:has(#gallery-title)` (`Portfolio.jsx:29-64`) | `/ishot-fixtures/portfolio/`, `…/portfolio-portraits/` |
| `lightbox` (state: first tile opened) | 2.3b | `/` → `.fo-lightbox` (`Lightbox.jsx`) | `/ishot-fixtures/recent-work/` |
| `services-band` | 2.4a | `/` → `main section:has(#services-title)` (`Home.jsx:81-95`) | `/ishot-fixtures/services-band/` |
| `service-details` | 2.4a | `/services` → `main section:has(#details-title)` (`Services.jsx:21-47`) | `/ishot-fixtures/service-details/` |
| `explore-work` | 2.4a | `/` → `main section:has(#categories-title)` (`Home.jsx:98-116`) | `/ishot-fixtures/explore-work/` |
| `testimonials` | 2.4b | `/` → `main section:has(#testimonials-title)` (`Testimonials.jsx`) | `/ishot-fixtures/testimonials/` |
| `founder` | 2.5 | `/` → `main section:has(#founder-title)` (`Founder.jsx`) | `/ishot-fixtures/founder/` |
| `share` | 2.5 | `/journal/what-to-wear-for-your-portrait-session` → the Share stack (`Article.jsx:26-56`) | `/ishot-fixtures/share/` |

#### Task 2.0: Dev-fixture environment proof (E-5) and Stage 1 owner decisions in the docs *(S)*

- **Guard every dev script.**
  - Create `wordpress/dev/lib/guard.php`, which exposes `ishot_dev_guard_or_exit(): void`:
    - if `IShot\Booking\Dev\DevGuard` is missing, print "Refused: the ishot-booking plugin is not active." and exit 1 (fail closed);
    - otherwise call `DevGuard::blocked( wp_get_environment_type(), defined( 'ISHOT_DEV_FIXTURES' ) ? constant( 'ISHOT_DEV_FIXTURES' ) : null, home_url() )` and, on a non-empty reason, print "Refused, nothing changed: {reason}" to STDERR and exit 1.
  - Every `wordpress/dev/*.php` script, read-only probes included, calls it as its first statement after the docblock: `content-smoke`, `notification-smoke`, `privacy-smoke`, `me-bookings-probe`, `style-check`, `studio-details-dev`, `page-shells-dev`. Today `content-smoke`, `notification-smoke` and `style-check` write data with no guard.
- `.wp-env.json`: add the mapping `"wp-content/ishot-dev": "./wordpress/dev"` to `env.tests.mappings`, so the tests container runs the real scripts. `ISHOT_DEV_FIXTURES` stays defined in `env.development` only.
- **Test** `ishot-booking/tests/Integration/DevFixtureEnvironmentTest.php` runs each script as a real `wp eval-file` subprocess inside the tests container (`proc_open`, `wp --path=/var/www/html`). `wp_get_environment_type()` is never stubbed. wp-env defines `WP_ENVIRONMENT_TYPE` as `local` in `wp-config.php`, so the test sets the type with `wp --exec='define("WP_ENVIRONMENT_TYPE","staging");'`, which runs before `wp-config.php` loads, so the constant takes that value. The implementer confirms this order first and records it. If it doesn't hold, use `--require` with a file that defines the constants.
  - `test_every_dev_script_refuses_staging_and_production_with_the_opt_in_set`: a data provider covers every `wordpress/dev/*.php` × {`staging`, `production`}, with `ISHOT_DEV_FIXTURES` defined `true` and the local host. Expected: exit code 1, STDERR contains `Environment type is "staging"` (or `"production"`), and a before/after snapshot is identical.
  - `test_every_dev_script_refuses_without_the_opt_in_constant`: type `development`, no `ISHOT_DEV_FIXTURES`. Expected: exit 1, STDERR contains `ISHOT_DEV_FIXTURES`, snapshot identical.
  - `test_the_fixture_classes_refuse_through_their_scripts`: `studio-details-dev.php` and `page-shells-dev.php` under `staging` leave the settings option, the pages and `permalink_structure` byte-identical.
  - The snapshot is taken by a read-only `wp eval` subprocess: md5 of the `ishot_booking_settings` option, `permalink_structure`, `blogname`, post counts per type and status (including attachments, `wp_navigation`, `ishot_*`), and the row count of `{prefix}ishot_bookings`.
- **Static check** `tests/dev/dev-scripts-guard.test.mjs` (added to `test:scripts` and so to CI): every `wordpress/dev/*.php` calls `ishot_dev_guard_or_exit()` before any other statement. A new script without the guard fails CI.
- **Docs:**
  - In `docs/phase-3b-3c-progress.md`, add a "Stage 1 owner decisions" section: E-1 to E-5 with each decision, and the deferred verifications (E-1 full-page re-run with no artificial spacing; E-4 re-verify with real Studio details; B1–B3 mandatory before production; business content still outstanding: Facebook URL, WhatsApp to be confirmed, founder details, at least 3 consented testimonials).
  - Record the known limitation `blockGap: null`.
  - Correct the Stage 1 review pack §11 line "prose spacing for post content" to "Stage 3 (Task 3.5)" (ruling R-2-9).
- **Accept:** the new test passes in the tests container and in CI. The static check passes, and fails on a fixture script without the guard (RED shown). The existing `StudioDevFixturesTest` and `PageShellFixturesTest` still pass. The dev site still runs `studio-details-dev.php` and `page-shells-dev.php` (idempotent, "none" created). The booking suite count goes up only by the new tests.

#### Task 2.1a: Component comparison harness and component fixtures *(M)*

- **Harness** (`tests/visual`):
  - Create `components.mjs` with `COMPONENTS: Array<{name: string, task: string, prototypePath: string, prototypeRoot: string, wpPath: string, wpRoot: string, landmarks: string, action?: 'open-first-tile', checks?: Array<'A'|'C'>, exceptions?: {skip?: string[], styles?: Record<string, string[]>, reason: string}}>`. It holds the table above. Entries for later tasks are added by those tasks.
  - Add `COMPONENT_LANDMARKS` in `landmarks.mjs`. Its specs are resolved inside the root: section, headings, eyebrows, buttons, images, cards, tiles, chips, features, stats, quotes, controls.
  - Extend `geometry.mjs`/`capture.mjs` with `captureComponent(page, {url, width, theme, root, landmarks, action}) → {png, geometry, styles, counts}`. Geometry is relative to the root's box. The `component-root` entry keeps absolute `x`/`width` plus `height`. A missing root fails the capture.
  - `scope.mjs`: `VISUAL_SCOPE=component` plus `VISUAL_COMPONENTS=a,b` (unknown names are an error, as with `VISUAL_STATES`).
  - Reference: `tests/visual/reference/components.json` (geometry and styles), written by `npm run visual:reference` from the prototype.
  - npm: `test:visual:wp -- --scope component --components hero`; `test:a11y:wp -- --components …` runs axe on each `wpPath` (after `action`) at 3 widths × 2 themes.
  - Reports go into the existing HTML side-by-side report.
- **Fixtures:**
  - Create `wordpress/dev/lib/ComponentFixtures.php` (namespace `IShot\Dev`, in no plugin's autoloaded `src`, ruling R-2-1) and `wordpress/dev/component-fixtures-dev.php` (guarded by Task 2.0's `ishot_dev_guard_or_exit()`, plus the same `DevGuard` check inside `ComponentFixtures::run()`).
  - `run(): array{ok: bool, reason: string, created: list<string>, skipped: list<string>}`: creates a published parent page `ishot-fixtures` and one published child page per entry in `ComponentFixtures::PAGES` (`slug => block markup`). Every item gets `content_status` placeholder (so the notice shows) and the marker meta `_ishot_dev_fixture`. An existing slug is never touched.
  - `remove(): array{ok: bool, removed: list<string>}` (positional argument `remove`, not `--remove`: `wp eval-file` rejects unknown flags and passes only positional arguments) deletes only marker-carrying items and restores what the fixture changed (see 2.4a and 2.4b).
  - Image ids come from `IShot\Booking\Content\Importer::KEY_META` (`photo:{key}`).
  - Add `wordpress/dev/lib` to PHPCS and PHPStan (closes the deferred M-f for the code that is now real).
- This task ships the harness, the fixture framework and one fixture: `strip` (filled in 2.1b) is enough to prove the pipeline. Each later task adds its own `PAGES` entries and `COMPONENTS` rows.
- **Tests:**
  - node: relative geometry (root at (100, 900) → child at (120, 950) is (20, 50)); root `y` ignored but `height` compared; `exceptions.styles` drops only the listed properties of the listed landmark; an unknown component name throws; `checks: ['C']` skips A.
  - Determinism: two prototype reference runs of the components are byte-identical.
  - PHPUnit `ishot-blocks/tests/Integration/ComponentFixturesTest.php` (requires the dev file through the tests mapping from 2.0): each guard alone blocks; a pass creates the pages with the marker and placeholder status; a second run creates nothing; existing owner content with a fixture slug is left alone; `remove()` deletes only marked items.
- **Accept:** node and PHPUnit tests pass. `npm run visual:reference` regenerates the page states unchanged (insertions only, in `components.json`). `test:visual:wp -- --scope component --components strip` runs end to end and writes the report (failures are expected until 2.1b lands). The fixture script refuses on the tests container.

#### Task 2.1b: Block styles, gallery strip and scroll reveal (theme) *(M)*

- **`inc/BlockStyles.php`** (§4.3, revised by R-2-2) registers, with `register_block_style`:
  - paragraph `eyebrow` → `fo-eyebrow`;
  - heading `statement` → `fo-statement`;
  - button `text` → `fo-btn--text` (`primary` is core's default button, already matched by `theme.json`; `outline` is core's, already in `theme.css`);
  - image `portrait`, `landscape`, `square` → `fo-img ish-r-{ratio}`;
  - gallery `strip` → `ish-strip`;
  - group `band` → `fo-band`, `mist` → `fo-mist`;
  - post-terms `eyebrow` → `fo-eyebrow`.
- **How a style applies:** the design-system class is added to the element the design-system rule targets, so the real design-system CSS applies and no declaration is duplicated:
  - on the front end, by one `render_block` filter (HTML API);
  - in the editor canvas, by one small editor script (`assets/js/block-styles-editor.js`, plain ES module with no build step, using `wp.hooks` `editor.BlockListBlock` to add the same class to the block wrapper; enqueued on `enqueue_block_editor_assets`).
  - Button `text`: `theme.css` aliases `.wp-block-button.is-style-text > .wp-block-button__link` to the `fo-btn--text` declarations (tokens only), as the existing outline rule does.
- **Text-link arrows** (`inc/buttons.php`, theme): every rendered `a.fo-btn--text` (a paragraph link or a `text`-style button) gets the Lucide `arrow-right` from `IShot\Blocks\Icons` appended (`aria-hidden`, as `Home.jsx:66-68`). A link with `target="_blank"` instead gets `<span aria-hidden="true">↗</span><span class="fo-sr"> (opens in a new tab)</span>` (`Home.jsx:130-133`). Nothing is added if already present. Without the plugin, no icon is added.
- **Gallery `strip`** (§4.2 "photo-strip", `Home.jsx:71-75`, `index.css:169-175`):
  - the filter makes `figure.wp-block-gallery.is-style-strip` carry `ish-strip`, `role="region"`, `aria-label="Studio photographs, scroll sideways"` and `tabindex="0"`;
  - each image figure gets `fo-img ish-r-strip`, and the middle one of five gets `ish-r-tall`;
  - images are `alt=""` (decorative in the prototype); `sizes="(min-width: 768px) 20vw, 42vw"`.
  - `theme.css` neutralises core gallery flex sizing inside `.ish-strip` (tokens only).
  - The style is designed for 5 images. With another count it still scrolls, and this is documented in the block style description.
- **Scroll reveal** (`Reveal.jsx`; R-2-3):
  - `ishot-blocks` registers the script module `@ishot/reveal` (`assets/reveal.js`, plain ES module depending on `@wordpress/interactivity`, unbuilt).
  - Markup with the hook class `ish-reveal` gets `data-wp-interactive="ishot/reveal" data-wp-init="callbacks.observe"`. This is done by the theme group filter for groups with class `ish-reveal`, and by the blocks that the prototype wraps in `Reveal`.
  - On init, an element below the fold gets `fo-reveal`, then `is-in` at 15 % visibility (IntersectionObserver, once). Under reduced motion it gets `fo-reveal is-in` at once.
  - Without JavaScript or the module, the content stays visible (no `fo-reveal` is printed server-side). The theme adds the directives only when the module is registered (the theme degrades to static content).
- **Tests:**
  - Theme PHPUnit `BlockStylesTest`: every style is registered with its label; the filter adds the exact classes for each; unknown styles are unchanged; the strip filter produces the prototype attributes and ratio classes; arrows and new-tab markers are added once (idempotent); nothing is added without `Icons`.
  - e2e `block-styles.spec.mjs`: each style applied in the post editor shows the design-system class on the canvas element and is valid after a reload; reveal adds `is-in` on scroll, at once under `reducedMotion: 'reduce'`, and content is visible with `javaScriptEnabled: false`.
  - Components `style-*` (C) and `strip` (A+C) at 6 combinations; axe on both fixture pages.
- **Accept:** all of the above green; the Site Editor and post editor show no invalid blocks; `lint:tokens` clean.

#### Task 2.1c: Section patterns, `ishot/marquee`, `ishot/contact-details` contact variant *(M)*

- **Section patterns** (theme `patterns/`, category `ishot-sections` registered in `functions.php`, `Inserter: yes`, outer group `templateLock: "contentOnly"`, prototype copy):
  - `section-heading`: `SectionHeading`, `ui.jsx:46-61`. `div.fo-heading > div.fo-heading__row > (div.ish-stack > p.fo-eyebrow + h2.fo-h2[anchor])` + optional action row `div.ish-actions` + optional lead `p.body-lg.fo-muted.ish-measure`.
  - `feature-list`: `FeatureList`, `ui.jsx:145-157`. Groups `ol.fo-features.ish-plain > li.fo-feature > p.fo-numeral + h3.fo-feature__title + p.fo-feature__text`, using core Group `tagName` `ol`/`li` if the editor validates them, else `div` with a recorded semantics exception (R-2-4). Prototype `process` copy.
  - `stats`: `Stats`, `ui.jsx:159-170`. `fo-stats ish-stats > fo-stat > fo-stat__label + fo-stat__value`, with the same `tagName` rule (`dl`/`div`/`dt`/`dd`). The group filter sets `style="--n:{children}"` on `.ish-stats`. The values are the prototype's "06 Signature services" and "07 Portfolio categories". Per §4.2 the pattern is not placed on a page until the owner confirms the figures (Stage 3 decision, open question Q-3).
  - `why-us`: `Home.jsx:142-150`. `section.fo-section > div.fo-container.ish-split > [group.ish-reveal: section-heading + feature-list (promises)] + core/image style `portrait`, `alt=""``.
  - `cta-band`: `CtaBand`, `ui.jsx:76-98`, **unsynced** (R-2-5). `section.fo-section.fo-section--tall.fo-band > div.fo-container.fo-container--text.fo-cta > ishot/spectrum + p.fo-eyebrow + h2#cta-title.fo-h2 + p.lead + (p > a.fo-btn.fo-btn--primary.fo-btn--lg)`. An empty lead is not printed (extend `inc/templates.php`'s empty-paragraph filter to `p.lead` inside `.fo-cta`).
  - `page-title` already exists.
  - Buttons follow Stage 1's accepted approach: a class-less paragraph holding one `a.fo-btn…` link, inside `div.ish-actions`.
  - Theme group filter (`inc/groups.php`, the single filter, R-1.4-1) also gives a `section` Group whose first heading has an anchor `aria-labelledby="{anchor}"` when none is set (prototype sections are labelled by their h2).
- **`ishot/marquee`** (ishot-blocks; `Marquee`, `ui.jsx:173-201`; `index.css:213-217`; R-2-6):
  - `section.fo-marquee[aria-label]` > `p.fo-sr` (words joined with ", ") + `div.fo-marquee__track[aria-hidden=true]` (the words twice, each followed by `<span aria-hidden="true">●</span>`) + `button.fo-icon-btn.ish-marquee__toggle`.
  - Words: `ishot_category` term names ordered by `term_id` (the prototype's `categories` order). Attribute `label` (default "Categories we shoot").
  - With no terms, it prints nothing.
  - Interactivity module: server-rendered paused (`is-paused`, label "Play scrolling categories", `play` icon), the safe state. On hydration it plays unless reduced motion. The toggle swaps class, label and icon. It stops itself after 5 minutes (WCAG 2.2.2).
  - `inserter: true` (a whole section). SSR editor with an inspector `TextControl` for the label.
- **`ishot/contact-details` contact variant** (`Contact.jsx:200-218`):
  - `variant` enum gains `contact`: `div.ish-contact-list > div (p.h5.fo-display "Studio" + p.small.fo-muted "{address} · {addressNote}") + div (p.h5.fo-display "Phone" + a.small[href=phone_href] + p.small.fo-muted "{phoneNote}") + div (p.h5.fo-display "Email" + a.small[href=mailto])`.
  - New attributes `addressNote` (default "studio & on-location") and `phoneNote` (default "Call or WhatsApp"), each left out when empty (R-2-7).
  - Each group is left out when its Studio value is empty, and the whole list when all three are. A phone without `phone_href` prints as text.
  - Stays `inserter: false` (part of the Contact page section, Stage 3).
  - The footer variant is unchanged byte for byte.
- **Fixtures:** `statement` (eyebrow, `fo-statement` h2, `body-lg`, text link with arrow, the strip gallery, `stats`), `why-us`, `process` (a `fo-mist` section with `section-heading` + `feature-list`), `cta-band`, `cta-band-no-text` (the About variant: `text=""`), `marquee`, `contact-list` (inside the prototype's `ish-split` column, with an empty second column).
- **Tests:**
  - Theme PHPUnit `SectionPatternsTest`: each pattern parses with no invalid blocks, carries `contentOnly`, uses prototype classes and copy, has no `core/html`; the `aria-labelledby` and `--n` filter rules; the empty CTA lead is dropped.
  - `ishot-blocks` `MarqueeBlockTest`: markup parity (normalised, R4) with `ui.jsx`; words doubled and `aria-hidden`; no terms prints ''; paused server state.
  - `ContactDetailsBlockTest` additions: contact parity; each empty field; notes empty; the footer variant unchanged.
  - e2e: marquee toggle (label, class, icon), 5-minute stop under a Playwright clock, starts paused under reduced motion and without JS; patterns insert from the inserter in the post editor and are valid; contact variant renders in the editor.
  - Components `statement`, `why-us`, `process`, `cta-band`, `cta-band-no-text`, `marquee`, `contact-list`: A+C at 6 combinations, axe 0.
- **Accept:** all green; no change to the footer's frame comparison.

#### Task 2.2: `ishot/hero-slideshow` and the minimal front page (E-2) *(M)*

- **`ishot/hero-slideshow`** (ishot-blocks; `HeroSlideshow.jsx:1-96`, `Home.jsx:36-52`, `Creative.jsx:16-42`, `index.css:125-148`):
  - Attributes:
    - `variant` `slideshow`|`static` (R-2-8);
    - `slides` (attachment ids, ordered);
    - `label` (default "Featured work");
    - `eyebrow`;
    - `titleLines` (array of strings);
    - `tagline`;
    - `primary` `{text, url}`;
    - `secondary` `{text, url}` (optional).
  - `slideshow` markup:
    - `section.fo-hero.ish-on-photo[aria-roledescription=carousel][aria-label]` > `div.fo-hero__media` > one `div.ish-slide[role=group][aria-roledescription=slide][aria-label="N of M"]` per slide (`is-active` and no `aria-hidden` on slide 1; `aria-hidden="true"` on the others) holding the image;
    - then `div.fo-hero__body > div.fo-container.ish-hero__inner` > `p.fo-eyebrow`, `h1.fo-hero__title` with one `span.ish-hero__line` per line (joined by a space), `div.fo-hero__row > p.small.ish-hero__tagline + div.ish-actions > a.fo-btn.fo-btn--primary [+ a.fo-btn.fo-btn--outline]`;
    - with 2 or more slides, `div.ish-hero__controls > button.fo-icon-btn` (pause/play) + `div.fo-dots[role=group][aria-label="Choose a slide"] > button[aria-label="Show slide N"]` (`aria-current="true"` on the active one).
  - `static` markup (Creative): no carousel roles, a single `img.ish-cover` directly in `.fo-hero__media`, `h1.ish-display-xl` with the plain title, no controls.
  - Images: new helper `Media::cover( int $attachment_id, bool $priority = false, ?string $alt = null ): string` returns `img.ish-cover` with srcset, `sizes="100vw"`, `object-position` from `focus`, and `loading="eager" fetchpriority="high"` when `$priority` (slide 1 only), otherwise `loading="lazy"`, with `decoding="async"`. An invalid id gives ''.
  - Empty states: no slides → the media box prints empty (no image), no controls, and the h1 still prints. 1 slide → no controls (`slides.length > 1`). Empty eyebrow, tagline or secondary are left out. Empty primary text or url → no primary button.
  - Interactivity (`viewScriptModule`, state per instance):
    - server-rendered paused (label "Play slideshow", `play` icon);
    - on hydration it plays unless `prefers-reduced-motion`;
    - advances every 6000 ms, cross-fading by the `.is-active` class;
    - holds on `mouseenter`/`focusin` and releases on `mouseleave`/`focusout` (leaving the section);
    - the pause/play button toggles its label and icon;
    - a dot sets the slide;
    - stops playing after 5 minutes;
    - nothing is announced on auto-advance (no live region);
    - only the active slide is exposed (`aria-hidden` on the others).
  - Edit UI (real, `index.js`): `MediaUpload` gallery picker for `slides` (reorder and remove); `PlainText`/`RichText` (`allowedFormats: []`) for eyebrow, title lines and tagline; `LinkControl` for both buttons; inspector `variant` and `label`. The canvas shows the server markup for the frame (no autoplay in the editor). `inserter: true`.
- **`templates/front-page.html`** (E-2):
  - `fo-root ish-app` wrapper > header part > `main#main` > pattern `ishot-concept/home-hero` > footer part.
  - `patterns/home-hero.php` (`Inserter: no`) prints the hero with the prototype's copy (`Home.jsx:37-50`) and the slide ids found by `Importer::KEY_META` = `photo:phoneBirthday`, `photo:gownStair`, `photo:graduate`, `photo:denimSmile` behind `class_exists`. With none found, it prints no slides.
  - Stage 3 (Task 3.2) replaces the body with the full home pattern. No other section is added and no spacing is added (E-1).
- **Fixture** `hero-static` (Creative copy and `gownStair`).
- **Tests:**
  - `ishot-blocks` `HeroSlideshowBlockTest`: parity for both variants (normalised, R4); slide 1 eager/high and others lazy; `aria-hidden` and `is-active`; 0/1/2+ slides; empty fields; paused server state; escaping of every attribute.
  - `MediaTest::test_cover_*`.
  - Theme `TemplatesTest`: `front-page` parses, has one h1 inside `main#main`, and the hero sits in `main`; it degrades to header/main/footer without `ishot-blocks`.
  - e2e `hero-slideshow.spec.mjs` (Playwright clock):
    - advances at 6 s;
    - hover and focus hold;
    - pause/play label and icon;
    - dots and `aria-current`;
    - stop at 5 min;
    - starts paused under reduced motion;
    - keyboard: Tab reaches pause then each dot, and Enter/Space operate them;
    - no motion without JS.
  - e2e editor: the block's edit UI picks slides and saves valid; `front-page` opens valid in the Site Editor.
  - Components `hero` (on `/`) and `hero-static`: A+C at 6 combinations.
  - **axe on `home` at 6 combinations, menu closed and open: 0 violations** (E-2 closed).
- **Accept:** all green; frame scope on `home` passes the header 6/6 (footer `y` remains E-1).

#### Task 2.3a: `ishot/project-gallery`: masonry, filters, count, Load more *(M)*

- **Data** (R-2-10): the portfolio is the attachments that carry an `ishot_category` term, ordered `menu_order ASC, ID ASC`. This is what `Importer::importPhotos` writes from the prototype's `portfolio` array. `Portfolio::photos( ?string $category_slug ): list<array{id: int, cat: string, label: string}>` takes `cat` and `label` from the attachment's first term.
- **Attributes:** `mode` `recent`|`portfolio`; `category` (slug, optional; on an `ishot_category` archive the queried term wins); `limit` (`recent`, 0 = all).
- **`recent`** (`Gallery.jsx:1-27`, `Home.jsx:137`):
  - `ul.fo-masonry.ish-plain > li > button.ish-tile[aria-haspopup=dialog]`, holding `Media::photo` with ratio `portrait`, except `square` for every third (`i % 3 === 2`) and `sizes="(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw"`, plus `span.ish-tile__cap.fo-eyebrow` with the category label;
  - no photos → nothing.
- **`portfolio`** (`Portfolio.jsx:29-64`):
  - `h2#gallery-title.fo-sr` ("All work" or the category name);
  - `div.fo-filters.ish-filters[role=group][aria-label="Filter by category"]` with **links** (R-2-11): `a.fo-chip` "All" → `/portfolio/`, and one per term → its term link, with `aria-current="page"` on the active one. `theme.css` aliases `.fo-chip[aria-current="page"]` to the design system's `.fo-chip[aria-pressed="true"]` declarations (tokens only).
  - the masonry with `ish-masonry--page`;
  - the empty state: `div.ish-empty > p.body-lg "No work in this category yet." + a.fo-btn.fo-btn--text "See all work"` → `/portfolio/`;
  - `div.ish-count > p.small.fo-muted[aria-live=polite] "Showing NN / NN"` (two-digit) + Load more.
  - Page size 16. The server renders the first `shown` items (`?shown=` query, default 16, step 16, capped at the total), and the rest `hidden`. "Load more" is `a.fo-btn.fo-btn--secondary[href="?shown=N"]`, so it works without JavaScript. With JS the module reveals the next 16 in place, updates the count, moves focus to the first newly shown tile and replaces the URL.
- The "project" mode of revision 3 is not built (R-2-12).
- **Instagram link of the recent-work heading** (`Home.jsx:130-133`):
  - `ishot-blocks` registers a block-bindings source `ishot/studio` (key `instagram_url` from `Studio::details()`);
  - the fixture binds a `text`-style core button `url` to it;
  - a theme filter drops a bound button whose URL resolves empty (never a dead link);
  - the new-tab marker comes from 2.1b.
- `inserter: false` (part of the recent-work and portfolio sections, placed by fixtures now and by page patterns in Stage 3).
- **Fixtures:** `recent-work` (`fo-mist` section, `section-heading` with the actions, gallery `recent`), `portfolio`, `portfolio-portraits` (`category: portraits`).
- **Tests:**
  - `ishot-blocks` `ProjectGalleryBlockTest`: parity with `Gallery.jsx`/`Portfolio.jsx` (normalised); ratio cycle; captions; order by `menu_order`; category filter; queried-term precedence; chips (`aria-current` on exactly one, real term links, empty terms listed); the empty state; count padding; `?shown` (20 fixture attachments: 16 shown, 4 hidden, Load more `?shown=32`; `?shown=999` capped; invalid values → 16); no Load more at or below 16; nothing in `recent` with no photos.
  - Bindings source tests (empty URL → button dropped).
  - e2e: chips navigate with JavaScript off; with 6 photos there is no Load more (paging is covered by PHPUnit); the count is `aria-live`.
  - Components `recent-work`, `portfolio`, `portfolio-portraits`: A+C at 6 combinations, axe 0.
- **Accept:** all green.

#### Task 2.3b: Gallery lightbox *(M)*

- **Lightbox** (`Lightbox.jsx:1-89`, `hooks.js:11-52`, `index.css:204-210`), part of `ishot/project-gallery`'s `viewScriptModule`:
  - One `div.fo-lightbox[role=dialog][aria-modal=true]` per gallery, server-rendered `hidden`, with the current visible items in the block's Interactivity context (src, srcset, alt, category label).
  - Bar: `span` "NN / NN · {label}" + `button.fo-icon-btn[aria-label=Close][data-autofocus]` (`x`).
  - Stage: previous button (`chevron-left`, "Previous photograph", `disabled` when fewer than 2) + `figure.ish-lb__figure > img.ish-lb__img[sizes=100vw][draggable=false]` + next button.
  - `p.ish-lb__caption.small[aria-hidden=true]` with the alt text.
  - `aria-label` is "{label} photograph, N of M".
- **Behaviour:**
  - a tile (Enter, Space or click) opens it at that index;
  - focus goes to Close;
  - Tab and Shift+Tab wrap;
  - Escape and Close close it, and a click on the backdrop (the dialog itself) closes it;
  - focus returns to the opening tile;
  - `body` scroll is locked while open;
  - ←/→ navigate and wrap;
  - a pointer swipe of 50 px or more navigates;
  - neighbours are preloaded;
  - only the items currently shown (after Load more) take part, as the prototype passes `visible`.
  - The focus-trap code is the module's own (the theme's `site-header` copy can't be shared across packages, because the theme must work without the plugin): an accepted duplication (R-2-13).
  - A `[hidden]` guard rule in `theme.css` is added only if `.fo-lightbox`'s `display: grid` beats `hidden` (as Stage 1's `.fo-menu[hidden]`).
- **Tests:**
  - PHPUnit: the dialog shell markup and the context items match the tiles.
  - e2e `lightbox.spec.mjs`: open by keyboard and by mouse; focus on Close; Tab wrap; arrows with wrap; Escape, Close and backdrop; focus return; scroll lock; swipe (pointer events); counter and `aria-label`; prev/next disabled with a single-item gallery (fixture `portfolio-studio`: the `studio` category has one photo).
  - Component `lightbox` (state `open-first-tile` on both sides): A+C at 6 combinations; axe 0 with the dialog open.
- **Accept:** all green; the B4 keyboard subset passes.

#### Task 2.4a: `ishot/service-tiles`, `ishot/service-list`, `ishot/category-cards` *(M)*

- **Services data:** `Services::published( list<int> $ids = array(), int $limit = 0 ): list<array{id: int, slug: string, title: string, short: string, tag: string, cat: string, icon: string, thumb: int, long: string}>`.
  - Published `ishot_service` posts only: explicit `ids` in the given order, else `menu_order ASC`. `cat` is the first `ishot_category` slug.
  - The placeholder publish guard already keeps placeholder services off production, so there is no second `content_status` filter (R-2-14).
  - **No price is printed** (R-2-15).
- **`ishot/service-tiles`** (`ui.jsx:103-117`):
  - `div.fo-tiles > a.fo-tile[data-cat][href="/services/#{slug}"] > span.fo-numeral "NN" + span.fo-tile__icon (Icons, size 24, unknown or empty → camera) + span.fo-tile__label (short, else title)`;
  - attributes `ids`, `limit` (default 4);
  - none → nothing;
  - `inserter: false`.
- **`ishot/service-list`** (`Services.jsx:21-47`):
  - `article#{slug}.ish-detail.ish-split` (`ish-split--reverse` on odd index) `[aria-labelledby="{slug}-title"]` > `Media::photo(thumb, portrait)` or `Media::placeholder('portrait')` + `div.ish-stack.ish-stack--lg > p.fo-eyebrow.ish-detail__eyebrow (span.fo-numeral NN + tag) + span.fo-catbar[data-cat][aria-hidden] + h3#{slug}-title.ish-h3 + p.body-lg.fo-muted (the content's text, from `wp_strip_all_tags`, prototype `long`) + div.ish-actions > a.fo-btn.fo-btn--secondary "Book this" → /book/?service={ID}`;
  - inclusions are not printed (R-2-15);
  - the "Book this" link always prints (prototype parity); a non-bookable service is handled by the 3e booking flow (recorded for 3e, R-2-16);
  - none → nothing;
  - `inserter: false` (inside the Services details section).
- **`ishot/category-cards`** (`ui.jsx:119-128`, `Home.jsx:24-29, 110-114`):
  - `div.fo-grid.ish-reveal` > `a.fo-card[href=term link] > (Media::photo(cover, landscape, "(min-width: 768px) 50vw, 100vw") | Media::placeholder('landscape')) + span.fo-catbar[data-cat={slug}][aria-hidden] + h3.fo-card__title + p.fo-card__text (description, left out when empty)`;
  - the cover is the category's first portfolio attachment by `menu_order` (R-2-17: this reproduces the prototype's four featured photos exactly);
  - attribute `slugs` (ordered; empty = every term with photos, by `term_id`);
  - a category with no photo shows the placeholder;
  - `data-cat` is the slug (the prototype's CSS keys colours by slug; an owner-added category has no colour, open question Q-1);
  - `inserter: false`.
- **Fixtures:** `services-band` (`fo-band` section, `section-heading` with "Book now", tiles with the ids of the imported s1–s4), `service-details` (`div.fo-container.fo-container--content` with `h2.fo-sr` + list), `explore-work` (`section-heading` with "View all" + cards `portraits, creative, studio, outdoor`).
  - `ComponentFixtures` publishes the six imported placeholder services on dev only (the publish guard allows placeholders on local/development) and records their previous status, so `remove()` restores it.
- **Tests:**
  - `ishot-blocks` `ServiceTilesBlockTest`, `ServiceListBlockTest`, `CategoryCardsBlockTest`: parity (normalised); order; ids; drafts excluded; icon fallback; short-title fallback; no price or inclusions in the output; placeholder image; reverse split; cover derivation; empty description; empty category; escaping.
  - `ComponentFixturesTest`: service status restored by `remove()`.
  - e2e editor: SSR renders in the fixture pages' editor.
  - Components `services-band`, `service-details`, `explore-work`: A+C at 6 combinations, axe 0.
- **Accept:** all green; the booking suite is unchanged (the service posts' booking fields are untouched).

#### Task 2.4b: `ishot/testimonials` *(S)*

- **Data:** `Testimonials::approved( int $limit = 3 ): list<array{quote: string, name: string, session: string}>`. Published `ishot_testimonial` with `consent` true and `content_status` approved, in `menu_order ASC, date DESC` order. An entry with an empty quote or one over 600 characters (the field's `maxLength`) is skipped, never truncated (R-2-18).
- **Markup** (`Testimonials.jsx:13-69`, `index.css:245-256`):
  - `section.fo-section.fo-band[aria-labelledby=testimonials-title]` > `fo-container` > the `SectionHeading` markup (eyebrow "Kind words", h2 "What clients say", action `div.ish-actions.ish-quotes__nav` with two `button.fo-icon-btn[aria-controls=testimonial-track]` "Previous testimonial"/"Next testimonial") + `div#testimonial-track.ish-quotes[role=region][aria-label="Testimonials, scroll sideways on small screens"][tabindex=0] > figure.fo-quote > blockquote + figcaption > span.fo-avatar[aria-hidden] + span.ish-quote__meta (name + small session)`.
  - **Fewer than 3 → the block prints nothing.** No stars, no placeholder state in WordPress (§1).
- **Interactivity:** previous/next scroll the track by one card width (`behavior: auto` under reduced motion).
- `inserter: true`. The editor shows "Testimonials appear here once at least 3 approved testimonials with client consent are published." via `EmptyResponsePlaceholder`.
- **Fixture** (R-2-19): `ComponentFixtures` creates 3 marker-carrying testimonial posts on dev only, using the prototype's own placeholder text ("A client's own words about their session appear here…", "Client name", "Session type"), published, `consent` 1, `content_status` approved, so the block's real path renders. `remove()` deletes them. The component entry lists `exceptions`: the prototype reference shows its placeholder state (`ish-placeholder` muted text and the "Placeholder testimonials." notice), which WordPress never renders. So `color` on the quote landmarks is excluded from C, and the notice and section height are excluded from A, with the owner's agreement in the review pack.
- **Tests:**
  - `ishot-blocks` `TestimonialsBlockTest`: 0, 2 and 3 entries; consent false, draft, placeholder, empty and 601-character quotes excluded; a 600-character quote at 375 doesn't overflow (e2e); order; limit; parity with the non-placeholder markup; escaping.
  - e2e: prev/next at 375 move by one card; the nav is hidden at 768 and above; the track is reachable by keyboard.
  - Component `testimonials`: A+C (with the listed exceptions) at 6 combinations, axe 0.
- **Accept:** all green; with no fixture posts the dev site shows no testimonials anywhere.

#### Task 2.5: `ishot/founder` and `ishot/share-links` *(M)*

- **`ishot/founder`** (D4; `Founder.jsx:10-37`, `index.css:261-264`):
  - Attributes: `status` `placeholder`|`approved` (default `placeholder`), `name`, `title`, `bio`, `photo` (id), `linkText` (default "Read our story"), `linkUrl` (default `/about/`). Defaults for name, title and bio are the prototype's placeholder text (`sampleContent.js` `founderPlaceholder`).
  - Markup: `section.fo-section[aria-labelledby=founder-title] > div.fo-container.ish-split.ish-founder > (Media::photo(photo, portrait, alt "{name}, {title}") | Media::placeholder('portrait')) + div.ish-stack.ish-stack--lg > p.fo-eyebrow "Meet the founder" + div.ish-stack > h2#founder-title.ish-h3 + p.small(.fo-muted | .ish-placeholder) + p.body-lg + a.fo-btn.fo-btn--text (arrow)`.
  - With `status` placeholder, `ish-placeholder` goes on the name, title and bio, plus `Notice::render( 'Placeholder profile.', "The founder's photo, name, title and biography are still to be supplied." )`.
  - Approved with an empty name or bio → the block prints nothing (no half-filled section).
  - Real edit UI: `RichText` (no formats) for name, title and bio; `MediaUpload` for the photo; inspector status toggle and link. `inserter: true`.
- **`ishot/share-links`** (`Article.jsx:26-56`; R-2-20):
  - `usesContext: postId`. Markup: `div.ish-stack > p.fo-eyebrow "Share" + div.ish-social > a.fo-icon-btn[href="https://wa.me/?text={rawurlencode(title + ' ' + permalink)}"][target=_blank][rel="noopener noreferrer"][aria-label="Share on WhatsApp (opens in a new tab)"] (BrandIcon whatsapp) + a.fo-icon-btn[href="mailto:?subject={title}&body={permalink}"][aria-label="Share by email"] (mail) + button.fo-icon-btn[aria-label="Copy link"] (link-2) + p.small.fo-muted[role=status]`.
  - Interactivity: copy writes the permalink with `navigator.clipboard.writeText`. Success → "Link copied."; failure → the status stays empty (prototype).
  - No post → nothing. `inserter: true` (journal posts).
- **Fixtures:** `founder` (placeholder defaults), `share` (in `div.fo-container.fo-container--text.ish-stack.ish-stack--lg` as the Article layout).
- **Tests:**
  - `ishot-blocks` `FounderBlockTest`: placeholder parity with `Founder.jsx`; approved without placeholder classes or notice; photo and alt; approved-but-empty → ''; escaping.
  - `ShareLinksBlockTest`: encoded URLs for a title with `&`, quotes and non-ASCII; no post → ''.
  - e2e: copy with clipboard permission granted → "Link copied." in the status region; denied → empty; founder editing in the post editor saves valid and shows placeholder styling until approved.
  - Components `founder`, `share`: A+C at 6 combinations, axe 0.
- **Accept:** all green.

#### Task 2.6: Stage 2 gate, verification and review pack *(M)*

- **Run, one command at a time** (`--workers=1` on a low-memory host):
  - prototype integrity;
  - `npm run build`, `lint:tokens`, `build:blocks:check`, `qa` (PHPCS, PHPStan level 6);
  - `test:scripts`;
  - `test:php` (booking), `test:blocks`, `test:theme` (the complete PHP regression suite);
  - booking smokes (`content-smoke`, `privacy-smoke`, `me-bookings-probe`), public REST and the `/book/`, `/account/`, `/manage-booking/` routes (as Stage 1 run 4);
  - the component gate for **every** component in the table at 375/768/1440 × light/Night (A and C gates, B reviewed);
  - frame scope on all 16 states (header 0 failures; footer `y` reported per state under E-1, with no artificial spacing);
  - full scope on `404`;
  - alignment 24/24;
  - axe 0 violations on `home`, `about`, `404` (menu closed and open) and on every fixture page and interactive state;
  - `npm run test:e2e` (keyboard and behaviour specs, editor checks).
- **Review pack** `docs/phase-3b-3c-stage-2-review.md`, modelled on the Stage 1 pack:
  - results tables;
  - per component, the prototype, WordPress, overlay and diff images at the 6 combinations, in `docs/phase-3b-3c-evidence/stage-2/` (palette PNGs, cropped to the component);
  - Site Editor and post editor screenshots;
  - every exception and ruling (`R-2-*`), with those needing owner agreement listed separately;
  - the deferred minors;
  - the comparison of WordPress output against the prototype with the differences and unresolved issues;
  - the Stage 1 owner decisions and their status;
  - the B1–B3 and business-content items, still open;
  - what's left for Stage 3.
- Update `docs/phase-3b-3c-progress.md`, with screenshot evidence links.
- Run `component-fixtures-dev.php remove` after capture, and record it.
- Commit, push (owner-authorised for verified changes) and confirm CI is green on the pushed head.
- **Stop for the owner checkpoint.** Stage 3 needs the owner's approval.

**Revision 4 rulings (Stage 2).** Each one is ledgered by the controller. Cost if wrong in brackets.
- **R-2-C** Components are compared section by section on dev-only fixture pages, with geometry relative to the component root (above). *(A re-run per component.)*
- **R-2-1** Component fixture code lives in `wordpress/dev/lib` (wp-env mapping only, never shipped), not in a plugin's autoloaded `src` (E-5 not widened). *(Move one file.)*
- **R-2-2** §4.3 revised:
  - block styles add the design-system class itself (front-end filter plus editor wrapper class), with no duplicated CSS; button `text` is a `theme.css` alias as the existing outline rule;
  - list `features` and quote `fo-quote` are **dropped**: a core list item can't hold FeatureList's numeral, h3 and paragraph, and a core quote can't produce the testimonial card's figure, blockquote and figcaption. The `feature-list` pattern and `ishot/testimonials` cover them, and the article quote is prose (Stage 3).
  - *(Two style registrations.)*
- **R-2-3** Reveal is a progressive-enhancement Interactivity module in `ishot-blocks`, using the hook class `ish-reveal`. Server markup never hides content. *(One module.)*
- **R-2-4** Feature-list and stats use core Group `tagName` `ol`/`li` and `dl`/`div`/`dt`/`dd` if the editor validates them; otherwise `div`s, with a recorded semantics exception. *(Markup only.)*
- **R-2-5** `cta-band` is an **unsynced** section pattern: every prototype page has different CTA copy (`Home`, `About`, `Services`, `Portfolio`, `Creative`, `Contact`, `Project`). *(Revision 3 said synced.)*
- **R-2-6** `ishot/marquee` is interactive: the prototype's pause/play button and 5-minute stop (WCAG 2.2.2) need a module. §4.2 said "CSS only". *(None.)*
- **R-2-7** The contact variant's notes ("studio & on-location", "Call or WhatsApp") are editable attributes with the prototype's defaults. *(Two attributes.)*
- **R-2-8** `ishot/hero-slideshow` has a `static` variant for the Creative hero (`Creative.jsx:16-42`). There is no other block for it, and Stage 3 needs one. *(One attribute.)*
- **R-2-9** Prose spacing for post content (Stage 1 review §11 listed it for Stage 2) and the `blog-card` pattern (§4.4) move to Task 3.5. Both belong to the Journal templates: the Article layout, a posts query, a read-time source and the whole-card link. *(Stage 3 picks them up.)*
- **R-2-10** The portfolio is the category-tagged attachments by `menu_order` (the importer's model of `portfolio`), not the project gallery meta. *(One query.)*
- **R-2-11** The filter chips are links with `aria-current="page"`, plus a token-only `theme.css` alias of the `aria-pressed` style: `aria-pressed` is invalid on a link, and the outline requires filters that work without JS. *(One rule.)*
- **R-2-12** The gallery's "project" mode is not built: the prototype's Project page has no masonry or lightbox. Its photo rhythm is built in Task 3.4. *(One mode later.)*
- **R-2-13** The lightbox has its own focus-trap code (the theme and the plugin can't share a module). *(Accepted duplication.)*
- **R-2-14** No separate `content_status` filter on services: the existing placeholder publish guard already keeps placeholders off production. *(One query clause.)*
- **R-2-15** No price, deposit or inclusions on tiles or the list: the prototype shows none. §4.2's "price with currency / zero price hidden / inclusions" was justification text, not approved design. The booking flow (3e) shows prices. *(Design change if the owner wants them.)*
- **R-2-16** "Book this" always prints (`/book/?service={ID}`); 3e's booking flow must handle a non-bookable service. *(3e requirement.)*
- **R-2-17** A category card's cover is the category's first portfolio photo, so no new term meta is added. *(Add term meta later.)*
- **R-2-18** A testimonial over 600 characters or empty is skipped, never truncated (no misquoting). *(One rule.)*
- **R-2-19** The testimonial fixture posts carry consent and approval on dev only, with the prototype's self-evident placeholder text, and are removed after capture. *(Owner may prefer review-only.)*
- **R-2-20** Share links are WhatsApp, email and copy link, as in `Article.jsx`. §4.2 said WhatsApp and Facebook. *(One link.)*
- **R-2-21** `front-page.html` (E-2) is pulled forward from Stage 3. Its hero slides come from the importer keys, and Task 3.2 replaces the body. *(Stage 3 replaces it.)*

### Stage 3: Page templates and patterns (3b). Not yet authorised.

- **3.1** Activation setup (idempotent pages, front and posts page, `/journal/%postname%/`; plugin rewrites untouched).
- **3.2** Front page (prototype section order). *Revision 4 note:* this replaces the body of the minimal `front-page.html` (header, `ishot/hero-slideshow` with the home h1, footer) that Task 2.2 adds for E-2.
- **3.3** About, Services, Creative direction, Contact (form shell "not switched on yet"; no Web3Forms).
- **3.4** Portfolio, `taxonomy-ishot_category`, `single-ishot_project`.
- **3.5** Journal `home`, `single`, `archive`.
- **3.6** Booking shells `page-book`, `page-account`, `page-manage-booking` (no booking logic; the manage page keeps its CSP and privacy headers with 0 console violations).

### Stage 4: Verification and owner review. Not yet authorised.

Full regression (§5 on 16 states × 6 combinations; axe B5; keyboard B4; overflow and console; alignment 24/24; Lighthouse mobile informational; all PHPUnit; PHPCS/PHPStan; Theme Check) → `docs/phase-3b-3c-verification.md` review pack → content checklist updated with each item's WordPress location → B1–B3 manual sign-off sheet `docs/accessibility-signoff.md`.

---

## 7. Booking integration (3e). Outline only, NOT authorised.

| 3e task | Builds on (existing) | Needs from this plan |
|---|---|---|
| `ishot-booking/booking-flow` (Idempotency-Key, 409 alternatives with focus kept per `033f682`, 422 messages, neutral `create_account` text) | REST `/services`, `/availability`, `/bookings` | 3.6 shell, 0.3 Studio details (WhatsApp on success), 0.4 helpers |
| `ishot-booking/my-bookings` | REST `/me/bookings` | 3.6 shell |
| `ishot-booking/manage-booking` (hash token in memory, `replaceState`, header-only token) | REST `/manage`, `ManageUrl` CSP | 3.6 shell |
| `ishot-booking/contact-form` | REST `/contact` | 3.3 shell |
| Staff and photographer experience | Complete in wp-admin | end-to-end tests only |

---

## 8. Accessibility

### 8.1 Remaining verification (mandatory)

| # | Check | When | How |
|---|---|---|---|
| B4 | Keyboard and focus | Automated in Stages 1–2 (Stage 2: hero, marquee, gallery and lightbox, testimonials, share), full run in Stage 4 | Playwright keyboard specs |
| B5 | axe on every template | Every task; full run in Stage 4 | `@axe-core/playwright`, 3 widths × 2 themes, 0 violations |
| B3 | OS reduced motion | Manual, after Stage 3 and again after 3e | Device setting on: slideshow paused, no reveals or zooms, menu without animation |
| B2 | Screen readers | Manual, after Stage 3 and again after 3e | VoiceOver (iOS/macOS Safari), NVDA (Firefox/Chrome), script in `outstanding-acceptance.md` B2 |
| B1 | Real devices | Manual, after Stage 3 and again after 3e | iOS Safari (current, −1), Android Chrome, iPad Safari |

**B1–B3 are mandatory before production approval.**

### 8.2 Optional refinements A1/A2: exact proposed changes (NOT implemented)

**A1: menu link numbers.** Today the accessible name of each menu link is "01Home", "02About"…

Prototype, `src/components/MenuSheet.jsx` line 30:
```diff
-                <span>{pad2(i + 1)}</span>
+                <span aria-hidden="true">{pad2(i + 1)}</span>
```
WordPress: the same attribute on the numeral span in `ishot/site-header`'s `render.php` (Task 1.2).
- **Benefit:** screen readers announce "Home, link" instead of "zero one Home, link". Faster scanning of the menu with VoiceOver's rotor or NVDA's links list. Speech-control users say "Click Home" as they would expect.
- **Visual change:** none. **Risk:** very low; the visible label still contains the accessible name (WCAG 2.5.3).
- **Test:** `getByRole('link', { name: 'Home', exact: true })` resolves in the open menu.

**A2: mobile booking bar.** Today its text is read with no context ("Birthday & portrait, Mon 23 Nov · 09:00, Continue").

Prototype, `src/pages/Book.jsx` line 400:
```diff
-        <div className="ish-bookbar">
+        <div className="ish-bookbar" role="group" aria-label="Booking summary">
```
WordPress: the same attributes on the bar in the 3e `booking-flow` block.
- **Benefit:** screen readers announce "Booking summary, group" on entering the bar, so the service, date and time are understood as the current selection.
- **Visual change:** none. **Risk:** very low. `group` isn't a landmark, so the nested-landmark issue fixed in `1c85797` can't return.
- **Test:** `getByRole('group', { name: 'Booking summary' })` resolves; the landmark count is unchanged.

If approved, each change would be applied to the prototype and the WordPress version together, to keep parity.

---

## 9. Acceptance criteria for Phase 3b/3c (Stage 4 gate)

1. **Fidelity:** every page state passes §5 checks A (geometry) and C (styles) at 3 widths × 2 themes. Every region flagged by check B has been manually reviewed. Any significant deviation has been fixed. Alignment: 24/24.
2. **Responsiveness:** no horizontal overflow from 320 to 1920 px; fluid type presets; token-only spacing.
3. **Accessibility:** axe 0 violations (B5); keyboard suite passes (B4); reduced-motion paths verified by emulation; the B1–B3 sign-off sheet is ready (and B1–B3 must pass before production).
4. **Performance (wp-env, informational):** Lighthouse mobile ≥ 90/95/95/95 on Home, Portfolio and an article. Front-end JS only as Interactivity API modules, loaded only where needed.
5. **Browser compatibility:** functional specs pass in Chromium, WebKit and Firefox; visual baseline in Chromium.
6. **Content management:** without code, the owner edits hero slides, page copy, services, projects, categories, testimonials, the founder, Studio details, menus and footer links. Locked sections can't be moved by non-administrators. Administrators can unlock in a controlled way (D6). Placeholders are labelled and the publish guard still works.
7. **Booking readiness:** the shells exist; the manage page keeps its CSP and privacy headers; no booking logic is duplicated.
8. **Quality gates:** CI green (build and token lint, generated and built files current, PHPCS, PHPStan L6, all PHPUnit, Playwright); Theme Check has no errors; no front-end jQuery.
9. **Scope:** no design changes; no 3e; A1/A2 not applied; PR #1 still a draft; both Vercel deployments present.

---

## 10. Review focus

1. **Empty or partial owner content:** fewer than 3 testimonials, a project without a gallery, a service without an image or price, an empty category, missing stats, empty Studio details. Sections hide or show the prototype's empty state, never a broken grid, "0" or a dead link. *(Tasks 0.3, 0.4, 2.3a, 2.4a, 2.4b, 2.5.)*
2. **Night theme flash and persistence** with page caching. *(1.1.)*
3. **Page cache leaking per-user state:** the theme prints nothing per user. *(1.2 asserts identical header HTML for a guest and a logged-in user.)*
4. **Reduced motion and no JavaScript:** slideshow, marquee, reveals and menu start safe (paused or visible) and never move under reduced motion. *(1.2, 2.1b, 2.1c, 2.2; B3 manually.)*
5. **Unusual owner input:** long titles, 600-character quotes, missing alt, malformed social URLs or phone numbers. *(0.3, 0.4, 2.4b, 2.5.)*

---

## 11. Technical risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Core wrappers and layout classes shift prototype geometry | High | Medium | Custom blocks print prototype markup; patterns avoid constrained layouts where the prototype has none; the geometry gate catches drift at Stage 1 |
| Root padding vs `.fo-container` padding | Medium | Medium | Full-width groups with zero root padding and `.fo-container` inside (as in `0eff339`); alignment checks |
| Interactivity API differences between WordPress versions | Medium | Medium | "Requires at least: 6.7" for the theme and `ishot-blocks`; Context7 check per task |
| A third package adds maintenance | Medium | Low | Mirrors `ishot-booking`'s structure and shares the same QA tools and CI |
| `@wordpress/scripts` adds dev dependencies and audit findings | High | Low | Dev only, exact pin, committed build; tracked in `docs/dependency-remediation.md` |
| Playwright Chromium download blocked or flaky on this Windows machine | Medium | Medium | Pinned browser via `npx playwright install chromium`. Fallback: `channel: 'chrome'` (system Chrome, used successfully in Phase 1), recorded per run. Geometry and style gates tolerate the channel; pixel signals are re-baselined per channel. |
| Rendering noise between environments | Medium | Low | §5 controls; geometry and style gates aren't pixel-based |
| `contentOnly` too restrictive for the owner | Medium | Low | Sized to the content inventory; administrator unlock path (D6) |
| Cache layers on the future host | Medium | High | Plugin headers already set; exclusions in `phase-3e-integration.md` §5; re-verified in Phase 4 |
| Scope creep into 3e or design tweaks | Medium | Medium | Stage gates with owner checkpoints; §9 item 9 |

## 12. Sequencing

```
Stage 0 (done):       0.1 harness ─┐
                      0.2 scaffold + build + suites ─ 0.4 helpers
                      0.3 Studio details (independent)
   └─ STOP: owner approval
Stage 1 (done):       1.0a build + app CSS → 1.0b WP harness → 1.1 Night → 1.2 header → 1.3 footer → 1.4 templates → 1.5 gate
   └─ STOP: owner checkpoint (accepted 2026-11-22, decisions E-1..E-5)
Stage 2 (authorised): 2.0 E-5 proof + docs → 2.1a component harness + fixtures → 2.1b block styles, strip, reveal
                      → 2.1c section patterns, marquee, contact variant → 2.2 hero + front page (E-2)
                      → 2.3a gallery → 2.3b lightbox → 2.4a services + categories → 2.4b testimonials
                      → 2.5 founder + share → 2.6 gate
   └─ STOP: owner checkpoint
Stage 3 → Stage 4 → owner sign-off
   └─ (separately authorised) 3e → 3f → 3g → Phase 4 (B1–B3 mandatory before production)
```

## 13. Skills and agents

- **Planning:** `superpowers:writing-plans`; a full review of the master plan, phase docs, theme, both plugin areas and the prototype source.
- **Execution:** `superpowers:subagent-driven-development` (owner-approved) with an implementer subagent per task and separate spec-compliance and code-quality reviewer subagents; `superpowers:test-driven-development`; `design-superpowers:ds-consumer`; Context7 (WordPress 6.7, Playwright); `design:accessibility-review` and `design-review` at stage gates; `superpowers:verification-before-completion` before every claim of completion.
