# WordPress Phase 3b/3c Implementation Plan (templates, template parts, blocks)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status (revision 2, 2026-11-21):** The owner approved the strategy and decisions D1–D7 with refinements (recorded in §3). **Only Stage 0 is authorised.** Stop after Stage 0 and ask for approval before Stage 1. Phase 3e, A1/A2, merging PR #1 and deleting either Vercel deployment all need separate explicit authorisation.

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
- **Preserve completed work:** the booking backend, REST API, content model, wp-admin screens and their 319 tests are reused and not rebuilt. Plugin changes are additive (Task 0.3 only, in Stage 0).
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
| Custom blocks | 15 (§4.2) | 1, 2 |
| Block styles and patterns | §4.3, §4.4 | 2, 3 |
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

### 4.3 Core blocks with design-system block styles (theme `inc/BlockStyles.php`)

paragraph `eyebrow` · heading `statement` · button `primary` / `outline` / `text` · list `features` · image `portrait` / `landscape` / `square` · gallery `strip` · quote `fo-quote` · group `band` / `mist` · post-terms `eyebrow`.

### 4.4 Patterns (theme `patterns/`)

Section patterns: `page-title`, `section-heading`, `stats`, `feature-list`, `why-us`, `cta-band` (synced), `blog-card` (post template).
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
  - phone and WhatsApp accept digits, spaces, `+`, `-` and parentheses (max 30) and are normalised to E.164 digits for links (`https://wa.me/<digits>`, `tel:+<digits>`);
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

### Stage 1: Global frame (3b template parts). Not yet authorised.

- **1.1 Night bootstrap + `ishot/theme-toggle`:** inline head script with no flash; the same storage key as `src/lib/theme.js`; works under the manage-page CSP.
- **1.2 Header part + `ishot/logo` + `ishot/site-header`:** Primary Navigation entity seeded from `navItems`; menu-sheet dialog parity; header identical for guests and logged-in users (cache-safe); A1 **not** applied.
- **1.3 Footer part + `ishot/social-links`:** 3 × 3 columns, legal band, Night toggle, inner-container rule from `0eff339`; 24/24 alignment checks.
- **1.4 Base templates:** `index`, `page`, `404`, `search`; `page-title` pattern; `ishot/notice`.
- **Gate:** header, footer, 404 and a generic page pass §5 in all 6 combinations → owner checkpoint.

### Stage 2: Content blocks, styles and patterns (3c). Not yet authorised.

- **2.1** Block styles (§4.3) + section patterns (§4.4) + gallery `strip` filter + `ishot/marquee`, `ishot/contact-details`.
- **2.2** `ishot/hero-slideshow` (6000 ms interval, pause on hover/focus, pause/play, dots `aria-current`, 5-minute stop, reduced-motion start paused, carousel roles, first slide `fetchpriority=high`).
- **2.3** `ishot/project-gallery` + lightbox (recent/project/portfolio modes; link-based filters that work without JS; Load more; dialog with focus trap, arrows, swipe, Esc, focus return).
- **2.4** `ishot/service-tiles`, `ishot/service-list`, `ishot/category-cards`, `ishot/testimonials` (testimonials hidden below 3 consented and approved entries; zero price hidden; 600-character quote clamps).
- **2.5** `ishot/founder`, `ishot/share-links`.
- **Gate:** every component passes §5 checks A and C; region diffs reviewed.

### Stage 3: Page templates and patterns (3b). Not yet authorised.

- **3.1** Activation setup (idempotent pages, front and posts page, `/journal/%postname%/`; plugin rewrites untouched).
- **3.2** Front page (prototype section order).
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
| B4 | Keyboard and focus | Automated in Stages 1–2, full run in Stage 4 | Playwright keyboard specs |
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

1. **Empty or partial owner content:** fewer than 3 testimonials, a project without a gallery, a service without an image or price, an empty category, missing stats, empty Studio details. Sections hide or show the prototype's empty state, never a broken grid, "0" or a dead link. *(Tasks 0.3, 0.4, 2.3, 2.4.)*
2. **Night theme flash and persistence** with page caching. *(1.1.)*
3. **Page cache leaking per-user state:** the theme prints nothing per user. *(1.2 asserts identical header HTML for a guest and a logged-in user.)*
4. **Reduced motion:** slideshow, marquee, reveals and menu. *(1.2, 2.1, 2.2; B3 manually.)*
5. **Unusual owner input:** long titles, 600-character quotes, missing alt, malformed social URLs or phone numbers. *(0.3, 0.4, 2.4.)*

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
Stage 0 (authorised): 0.1 harness ─┐
                      0.2 scaffold + build + suites ─ 0.4 helpers
                      0.3 Studio details (independent)
   └─ STOP: owner approval
Stage 1 → owner checkpoint → Stage 2 → Stage 3 → Stage 4 → owner sign-off
   └─ (separately authorised) 3e → 3f → 3g → Phase 4 (B1–B3 mandatory before production)
```

## 13. Skills and agents

- **Planning:** `superpowers:writing-plans`; a full review of the master plan, phase docs, theme, both plugin areas and the prototype source.
- **Execution:** `superpowers:subagent-driven-development` (owner-approved) with an implementer subagent per task and separate spec-compliance and code-quality reviewer subagents; `superpowers:test-driven-development`; `design-superpowers:ds-consumer`; Context7 (WordPress 6.7, Playwright); `design:accessibility-review` and `design-review` at stage gates; `superpowers:verification-before-completion` before every claim of completion.
