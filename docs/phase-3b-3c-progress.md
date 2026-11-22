# WordPress Phase 3b/3c: progress and evidence log

Binding evidence log for `docs/superpowers/plans/2026-11-21-wordpress-phase-3b-3c.md` (sections 1 and 6). The per-task working reports are gitignored, so the results are recorded here. Everything below comes from the controller ledger and the task and fix reports; where a result was not recorded, this says so.

Branch: `redesign/wp-phase-3b-3c` (from `d14c387`; plan commit `d004925`). Not pushed.

## Stage 0: scope and status

Stage 0 = Tasks 0.1 to 0.4 (visual harness, `ishot-blocks` scaffold and build pipeline, Studio details, shared render helpers). Owner authorised Stage 0 only (2026-11-21).

**Status: complete, pending owner approval. Stage 1 has not been started.** Every task was independently reviewed (and re-reviewed after fixes), then the whole branch was reviewed (`d14c387..f36ad13`, verdict "With fixes"; see below).

## Task 0.1: visual comparison harness and prototype references

- **Built:** Playwright harness (bundled Chromium 159.0.8154.6, DSF 1, viewports 375/768/1440, fixed clock `2026-11-23T09:00Z`, reduced motion, freeze CSS). 16 route states x 3 widths x 2 themes = 96 reference states. Geometry and computed-style probes per landmark, compare module with +/-2 px geometry gate, region pixel diffs, HTML report, prototype integrity check against commit `50e60f4` (tracked and untracked files, fail-closed).
- **Commits:** `43a2a7e` (harness), `dcc947d` (fix round 1).
- **Files (summary):** `playwright.config.mjs`, `tests/visual/*` (routes, landmarks, masks, geometry, capture, compare, integrity, specs, tests, committed `reference/geometry.json` and `styles.json`), `scripts/visual-reference.mjs`, `package.json` (exact-pinned `@playwright/test 1.65.1`, `@axe-core/playwright 4.14.0`, `pixelmatch 8.0.0`, `pngjs 7.0.0`), `package-lock.json`, `.gitignore`.
- **Tests (round 0):** `node --test tests/visual/*.test.mjs` 10/10; `npm run visual:reference` 97 Playwright tests pass; `npm run test:visual` 97 pass.
- **Tests (after fix round 1):** `node --test tests/visual/*.test.mjs` 20/20; `npm run visual:reference` run twice, 97 passed each, `geometry.json` and `styles.json` byte-identical between runs, 96 of 96 PNGs byte-identical; `npm run test:visual` 97 passed, no warnings; `npm run lint:tokens` and `npm run build` OK.
- **TDD:** RED `ERR_MODULE_NOT_FOUND` for `compare.mjs`, then GREEN 7/7; fix round RED 4 failing, then GREEN.
- **Review:** r0 "Needs fixes" (Important I1 one-way landmark comparison). Re-review r1 clean.
- **Fixed in round 1 (5):** I1 two-way landmark and count comparison with required landmarks; promoted M1 region diffs never silent (`regionStatus`, stale-PNG fingerprint); M3 search vs 404 is review-only (`gate:false`); M4 integrity also checks untracked files; M7 DEP0190 warning removed.
- **Honest notes:** the prototype has no search page (its 404 template is the reference, search is review-only); book coverage is step 1 only; reference PNGs are in the gitignored `.output/`, so region diffs need one local `visual:reference` run first.
- **Deferred minors:** M2 no WordPress capture target (`VISUAL_TARGET=wp`); M5 `styles.json` is 1.5 MB; M6 masks cover only the footer year; region assertion still runs for `gate:false` routes; `unexpected`-landmark failure and fingerprint comparison lack direct unit tests; book landmarks cover step 1 only.

## Task 0.2: ishot-blocks scaffold, build pipeline, QA and CI

- **Built:** `ishot-blocks` companion plugin (header: WordPress 6.7, PHP 8.1, `Requires Plugins: ishot-booking`; `Plugin::registerBlocks()` registers `build/blocks/*/block.json` from the plugin itself, never from the theme; skipped blocks are logged, announced via `ishot_blocks_block_skipped` and shown as an admin notice). Reproducible block build (`scripts/build-blocks.mjs` with `--check` mode that rebuilds into a temp dir and byte-compares). PHPUnit suites (unit, integration, theme). PHPCS and PHPStan extended to `ishot-blocks` and the whole theme. CI: block build check, `test:blocks`, `test:theme`, a `script-tests` job.
- **Commits:** `6cfe19b` (build pipeline), `ce05ad4` (plugin scaffold), `2792e31` (QA and CI), `148cdb2` (fix round 1).
- **Files (summary):** `.github/workflows/ci.yml`, `.gitignore`, `.gitattributes`, `.wp-env.json`, `package.json`, `package-lock.json`, `scripts/build-blocks.mjs`, `tests/blocks/build-blocks.test.mjs`, `tools/qa/*`, `wordpress/wp-content/plugins/ishot-blocks/**`, theme `README.md` and `tests/ThemeSetupTest.php`.
- **Tests (initial):** `npm run test:scripts` 24 pass; ishot-blocks PHPUnit unit 2, integration 5, theme 2; ishot-booking `npm run test:php` OK 319 tests, 1194 assertions (no regression); `npm run build`, `npm run lint:tokens`, `npm run build:blocks:check` OK (zero blocks); `npm run qa` PHPCS clean (82 files), PHPStan level 6 no errors.
- **Tests (after fix round 1):** unit+integration OK 9 tests, 28 assertions; theme OK 2 tests, 8 assertions; build-blocks node tests 4 pass; `npm run qa` clean.
- **TDD:** RED `Cannot find module build-blocks.mjs` and `Class "IShot\Blocks\Plugin" not found`, then GREEN.
- **Dependency:** `@wordpress/scripts` pinned 35.0.0. Versions 36 and 37 declare an optional peer `vite ^7 || ^8`, which breaks `npm ci` against the prototype's vite 5.
- **Review:** r0 "Approved", 0 Critical/Important, 9 Minor. Re-review r1 clean.
- **Fixed in round 1 (5):** M1 skipped blocks now produce an admin notice; M3 the theme-independence test now really switches to `twentytwentyfive`; M5 `.gitattributes` forces LF for scss/jsx/ts/tsx/svg (build reproducibility); M2 booking notice needs `activate_plugins`; M4 log wording.
- **Evidence:** wp-env dev and tests-cli had `ishot-blocks` and `ishot-booking` active; deactivating booking printed the warning notice, reactivating removed it. No `wp-env destroy` or `clean` was run.
- **Deferred minors:** M6 normal build deletes `build/blocks/.gitkeep`; M7 Babel patch bumps (CLOSED, see schedule); M8 `build:blocks:check` is trivial with zero blocks; M9 `@wordpress/scripts` audit exposure (recorded in `docs/dependency-remediation.md`); orphaned docblock in `Plugin.php` (fixed in the final fix wave); `PluginTest` registers the build twice, which will fail once a real block exists (load-bearing, see schedule).

## Task 0.3: Studio details (ishot-booking, additive)

- **Built:** `Studio::details()`, `socials()`, `unconfirmed()` and a "Studio details" form on Booking Settings (phone, WhatsApp, Instagram, Facebook, email, address) with validation (`StudioForm`), `manage_ishot_settings` capability check and nonce. A separate dev-only fixture (`wordpress/dev/studio-details-dev.php`, `StudioDevFixtures`) fills the local dev site from the prototype values, marked unconfirmed. Docs updated with where each contact item is entered; no content-checklist status changed.
- **Commits:** `63de825`, `ace6813` (fix round 1).
- **Files (summary):** plugin `src/Studio.php`, `src/Dev/StudioDevFixtures.php`, `src/Admin/StudioForm.php`, `src/Admin/SettingsScreen.php`, `src/Settings.php`, `src/Plugin.php`, `assets/css/admin.css`; `.wp-env.json`; `wordpress/dev/studio-details-dev.php`; `docs/phase-1-verification.md`, `docs/content-inventory.md`; `StudioDetailsTest.php`, `StudioDevFixturesTest.php`. `Importer.php`, the seed export, `src/`, `public/` and `design-system/` untouched.
- **Tests (initial):** `npm run test:php` OK 367 tests, 1380 assertions (319 original + 48 new); `test:blocks` 9/9; `npm run qa` clean. RED `Tests: 80, Errors: 46, Failures: 1` then GREEN `OK (80 tests, 254 assertions)` for `--filter Studio`.
- **Tests (after fix round 1):** `npm run test:php` OK 376 tests, 1421 assertions; `npm run test:blocks` OK 9 tests, 28 assertions; `npm run qa` clean. No separate RED capture in the fix round (noted); the re-reviewer confirmed each new test fails on the pre-fix code.
- **Dev fixture evidence:** on the tests environment `wp eval-file` printed "Refused, nothing changed: ISHOT_DEV_FIXTURES is not defined as true", exit 1, option checksum unchanged. On the dev environment run 1 filled five fields as unconfirmed (Facebook left empty), run 2 changed nothing.
- **Review:** r0 "Needs fixes" (Important I1: unconfirmed values were published in every environment). Re-review r1 clean.
- **Fixed in round 1 (4):** I1 `details()`/`socials()` withhold unconfirmed values unless the environment is local or development (an environment filter can only make the check stricter); M1 `StudioDevFixtures::run()` takes no parameters and reads the guards itself; M2 the confirm flag compares normalised stored vs input, so an unchanged re-save keeps the flag; M3 the unconfirmed note is part of each input's `aria-describedby`.
- **Deferred minors:** M4 uppercase `HTTPS://` scheme rejected (fails safe); M5 array POST values cast to "Array"; `showsUnconfirmed()` hardcodes the filter name instead of the constant. Note: the fixture code ships inside the plugin package (see final-review minors).

## Task 0.4: shared render helpers (ishot-blocks)

- **Built:** `Media::photo()` and `placeholder()` (core `wp_get_attachment_image` with a scoped filter), `Icons` (26 Lucide icons generated from the prototype's lucide-react 0.468.0 by `scripts/export-icons.mjs`, plus the three brand glyphs), `Notice::render()` and `forPost()`, and a test markup normaliser (ruling R4).
- **Commits:** `96f9544`, `f36ad13` (fix round 1).
- **Files (summary):** `scripts/export-icons.mjs`, `package.json` (`build:icons`), `tests/blocks/export-icons.test.mjs`, plugin `src/{Media,Icons,Notice}.php`, `src/Icons/lucide-data.php`, `tests/{Unit/IconsTest,Integration/MediaTest,Integration/NoticeTest,Support/MarkupNormaliser}.php`.
- **Tests (initial):** ishot-blocks suites 37 tests, 117 assertions; theme 2/8; ishot-booking 376 OK; node 26 pass; `npm run qa` clean; `build:blocks:check` OK. RED `Tests: 37, Errors: 28` then GREEN.
- **Tests (after fix round 1):** `npm run test:blocks` OK 46 tests, 310 assertions; `npm run test:php` OK 376 tests; `npm run test:theme` OK 2 tests; `node --test tests/blocks/export-icons.test.mjs` 2 pass; `npm run qa` clean.
- **Review:** r0 "Approved", 0 Critical/Important, 6 Minor. Re-review r1 clean.
- **Fixed in round 1 (4):** M1 the earlier claim that `sizes` is restored was wrong (core re-adds `auto, `), so the override was removed and the docs corrected, with a test through `wp_filter_content_tags`; M2 the image filter is scoped to the attachment and removed in `finally`; M3 `Notice::render` and `forPost` accept a message and extra class; M4 `Icons::svg` accepts a size and relabels via an attribute-safe regex (size 20 menu icon matches lucide-react byte for byte).
- **Markup differences vs the prototype (recorded):** core adds `width`/`height` to `img` (no geometry change; `.fo-img` sizes by ratio frame), core's `auto, ` prefix stays in `sizes`, `src` uses the `large` size.
- **Deferred minors:** M5 focus regex `$` accepts a trailing newline (use `\z`); M6 test tidy-ups; `Icons::svg` size <= 0 silently becomes 16; Notice `@param` alignment; Notice message is plain text only.

## Controller rulings

- **Branch (work in main checkout, not a worktree):** the owner asked for a dedicated branch, and wp-env maps plugin and theme paths relative to the repo root (a worktree would need a second wp-env instance and ports). Cost if wrong: none to the code.
- **R1 (Task 0.3 does not modify `Importer.php` or the seed export):** the plan said the importer seeds Studio details as placeholders, but `scripts/export-wp-seed.mjs` deliberately exports no contact details (owner-confirmed values only). Instead a dev-only script fills the local dev site so visual comparison has identical content. Cost if wrong: the owner types six fields once.
- **R2 (manage-booking reference):** the prototype reference uses the mock route `/manage/<mock token>`; the WordPress counterpart is `/manage-booking/` (shell only until 3e). Cost if wrong: one route entry.
- **R3 (ishot-blocks setup):** `wp:setup` composer-installs `ishot-blocks` too; its `vendor/` is ignored and `build/` is committed. Cost if wrong: trivial.
- **R4 (prototype parity tests):** compare normalised markup (tags, class lists, aria/role/loading/decoding/fetchpriority, object-position) and ignore src/srcset/width/height, with fixtures hand-derived from `src/components/ui.jsx`. Cost if wrong: weaker parity proof, caught by the Stage 1 geometry gate.
- **R5 (owner, dev fixture guards):** the dev script must fail safe with layered guards, all required before any write: (1) environment type local or development, (2) `ISHOT_DEV_FIXTURES === true` defined only in `.wp-env.json` `env.development.config`, (3) `home_url()` host is localhost, 127.0.0.1, `*.local` or `*.test`, (4) never overwrite a non-empty owner value; plus a test proving each guard alone blocks the write; values marked unconfirmed in the Settings UI.
- **Ruling (Task 0.1 promotions):** reviewer Minors M1, M3, M4 (and M7) promoted to Important because they violated the owner's requirement that comparisons must not give misleading results. Cost if wrong: small extra fix round.
- **Ruling (Task 0.2 promotions):** M1 (skipped blocks invisible), M3 (theme-independence test proved nothing), M5 (LF rules for build reproducibility) promoted to Important because they contradict the owner's requirements (reliable registration, content independent of theme, reproducible builds). M2 and M4 bundled as trivial. Cost if wrong: one small fix round.
- **Ruling (Task 0.3 promotions):** M1 (guard inputs caller-supplied; owner said not to rely on convention), M2 (confirm check could silently clear a flag; owner-confirmed-only rule), M3 (warning not in `aria-describedby`; WCAG 2.2 AA) promoted to Important. Cost if wrong: small fix round.
- **Ruling (Task 0.4 promotions):** M1 (false `sizes` claim would mislead later gates), M2 (filter leaks on throw), M3 (Notice cannot carry a message or class, so blocks would duplicate markup; owner said avoid duplication), M4 (Icons need a size; the header needs 20) promoted to Important. Cost if wrong: one small fix round.
- **Ruling (final review routing):** FI3, FI4, FI5 are not Stage 0 code defects and are reported to the owner as outstanding items or decisions; FI1, FI2, the orphaned docblock and the M7 closure note go to one fix wave. Cost if wrong: the owner asks for a further doc tweak.
- **Ruling (`PluginTest` duplicate registration):** re-scheduled from Stage 2 to Stage 1.3 (first `ishot-blocks` block, social-links), per the final reviewer.
- **Owner instructions (2026-11-21):** geometry compared at +/-2 px only in a controlled, reproducible browser; `ishot-blocks` follows WordPress standards, registers reliably and keeps content theme-independent; Studio dev values isolated from production data and deployments; helpers must not duplicate theme or booking functionality; each task reviewed before the next; the final report includes a Stage 0 acceptance checklist.

## Final whole-branch review (`d14c387..f36ad13`)

Verdict: **With fixes.** Important items and their handling:

| # | Finding | Handling |
|---|---|---|
| FI1 | `ishot-blocks/blocks/` was linted by neither PHPCS nor PHPStan, so future `render.php` files (where escaping happens) would be unchecked | **Fixed in the final fix wave.** `blocks/` added to `tools/qa/phpcs.xml.dist` and `tools/qa/phpstan.neon.dist`; `build/` stays excluded. `npm run qa` is clean with the empty folder, and a temporary bad `render.php` was flagged by both tools (then removed). Also fixed the orphaned docblock in `Plugin.php` (`maybeShowBookingNotice` has its own docblock again). |
| FI2 | This evidence log was missing; the `@wordpress/scripts` audit note (ledger M9) was missing | **Fixed in the final fix wave.** This file created; dated 2026-11-22 section added to `docs/dependency-remediation.md` with the audit totals; theme README "Requires plugins" reworded as planned for Stage 1. |
| FI3 | CI green on the branch is unverified | **OUTSTANDING.** The branch is not pushed. Pushing needs owner authorisation. The Vercel project was disconnected from Git on 2026-11-20, so a push should not create a deployment; confirm this before pushing. |
| FI4 | Phone link conflict: the prototype prints `<a href="tel:0881405259">` (`Footer.jsx:79`, `MenuSheet.jsx:43`, `Contact.jsx:207`), while `Studio::details()` returns `phone_href` null for local numbers without a country code (plan rule: `tel:+E164`). `BookingScreen.php:206` already builds `tel:` from local numbers. | **RESOLVED 2026-11-22 (owner): option (a).** `Studio::details()` now emits `tel:<digits as typed>` for local numbers (no country code added, no leading 0 stripped, stored value never rewritten); international input still gives `tel:+<E.164>`; only owner-confirmed numbers appear publicly. WhatsApp stays international-only. |
| FI5 | `build-blocks` lacks `--experimental-modules`, needed for `viewScriptModule` | **First step of Stage 1.1**, together with proving that a real block's Windows build equals the CI rebuild. |

## Deferred-minor schedule (final reviewer's triage)

| Item | When |
|---|---|
| 0.1 M2 WordPress capture target (`VISUAL_TARGET=wp`) | Stage 1, before 1.2 |
| 0.1 M5 `styles.json` size | Next regeneration (optional) |
| 0.1 M6 masks | Stage 1.4 (404, search); Stage 3e (book, account, manage) |
| `gate:false` region assertion | Stage 1.4 |
| `unexpected` and fingerprint unit tests | Stage 1 |
| Book step-1-only landmarks | Stage 3e |
| 0.2 M6 `.gitkeep` churn | Stage 1.1 |
| 0.2 M7 Babel patch bumps | **CLOSED.** Prototype-relevant packages (vite, react, react-dom, lucide-react, `@vitejs/plugin-react`, react-router-dom, `@babel/core`, esbuild, rollup, postcss) resolve to identical versions; protected files identical to `50e60f4`. |
| 0.2 M8 trivial build check | Stage 1.1 |
| `PluginTest` duplicate registration (load-bearing) | Stage 1.3 (first block, social-links) |
| 0.3 M4 uppercase HTTPS | Stage 1.3 |
| 0.3 M5 array POST | Before contact details are first published (Stage 1.2/1.3) |
| Hardcoded filter name in `showsUnconfirmed()` | Stage 1 |
| 0.4 M5 focus regex `\z` | Stage 2.2 |
| 0.4 test tidy-ups | Stage 2 |
| Icons size <= 0, Notice `@param` alignment, plain-text message | Stage 1.4 / 2 |
| Theme README describes not-yet-built behaviour | Reworded in the final fix wave |
| Reference metadata lacks platform | Stage 1 |
| Notice default label not translatable | Stage 1.4 |
| Dev fixture code ships in the plugin package | Exclude at packaging (3f) |
| `script-tests` CI job installs Chromium unnecessarily | Optional |
| Confusable keys `email_studio_address` vs `studio_address` | No action |

## Stage 0 acceptance checklist

| Requirement | Status | Evidence |
|---|---|---|
| Prototype byte-identical to `50e60f4` | Verified | Integrity check (tracked and untracked, fail-closed) passes in every harness run; protected files confirmed identical at final review |
| Visual harness deterministic | Verified | Two identical `visual:reference` runs: JSON byte-identical, 96/96 PNGs identical; 16 states x 3 widths x 2 themes; bundled Chromium 159.0.8154.6 |
| Geometry and style gates two-way with count and required checks | Verified | Task 0.1 fix round 1; 20 node tests |
| Integrity check fails closed, including untracked files | Verified | Task 0.1: appended line to `src/main.jsx` and an untracked `public/zz-untracked.txt` both aborted the run |
| `ishot-blocks` registers independent of the theme | Verified | `PluginTest` switches to `twentytwentyfive` and still registers the fixture block (Task 0.2 fix round 1) |
| Skipped-block admin notice | Verified | `PluginTest` administrator sees the notice, subscriber does not |
| Reproducible build check | Verified on fixture only | Real-block proof (Windows build equals CI rebuild) outstanding until Stage 1.1 |
| Exact-pinned devDependencies | Verified | Task 0.1 and 0.2 package changes |
| PHPCS and PHPStan level 6 clean | Verified | `npm run qa` clean after FI1 (89 files, no errors); `blocks/` coverage proven with a temporary bad file |
| PHPUnit and node test counts | Verified | ishot-booking 376 tests (original 319, 1421 assertions); ishot-blocks 46 tests, 310 assertions; theme 2 tests, 8 assertions; node tests passing (26 at Task 0.4; count after the final wave not re-recorded here) |
| Studio details validation, permissions, nonce | Verified | `StudioDetailsTest` (403 for photographer, bad nonce, validation, escaping) |
| Unconfirmed values withheld outside local or development | Verified | Task 0.3 fix round 1 tests |
| Dev fixture: four layered guards each tested alone; refuses on tests env | Verified | `StudioDevFixturesTest`; real refusal on tests-cli, option checksum unchanged |
| Content checklist statuses unchanged | Verified | Task 0.3 docs only say where to enter values |
| Booking backend unchanged except additive Studio files | Verified | Task 0.3 file list; original 319 tests still pass |
| CI green on the branch | **Outstanding** | Branch not pushed; needs owner authorisation (FI3) |
| Phone link (`tel:` for local numbers) | **Decided 2026-11-22: local numbers link as typed** | FI4, needed before Stage 1.2 |
| B1 to B3 real-device, screen-reader, reduced-motion checks | Not applicable yet | No WordPress pages exist; mandatory before production |
| A1, A2 | Not implemented | Out of Stage 0 scope |

## Stage 1

### Task 1.0a: block build modules, theme block registration, app stylesheet, duplicate-registration fix

- **Built:** `scripts/build-blocks.mjs` passes `--experimental-modules` to every `wp-scripts` call (build, `--check`, and the new `--watch`, which runs `wp-scripts start` per package with blocks; `npm run start:blocks`). `ISHOT_WP_SCRIPTS` overrides the wp-scripts binary (test seam). Theme `functions.php` registers `build/blocks/*/block.json` on `init` (`ishot_theme_register_blocks()`; no `block.json` = skipped silently; already-registered names left alone). `sync:ds` writes `assets/css/app.css` (generated banner + `src/index.css`, CRLF normalised to LF) and copies `public/brand/*.png` to `assets/brand/`. Enqueue and editor-style order is tokens, bundle, app, theme. `lint:tokens` scans `app.css` (already covered by the theme CSS folder walk). Stale `app.css` is caught by the existing CI step `git diff --exit-code -- wordpress/wp-content/themes/ishot-concept` after `npm run build` (which runs `sync:ds`).
- **Duplicate registration:** a regression test proved a real false positive: a second `registerBlocks()` of the same build reported the folder as missing. `Plugin` now remembers names it registered and skips them while still registered. `PluginTest::tear_down` unregisters every `ishot-fixture/*` block and clears the skipped list.
- **TDD RED:** `node --test tests/blocks/build-blocks.test.mjs`: recorder test `actual: 0, expected: 3` (no `wp-scripts` call received the flag path) and the `viewScriptModule` test failed (`view.js` not built). `npm run test:theme`: 2 failures + 3 errors (`ishot-app` missing from the order; `ishot_theme_register_blocks` undefined). `npm run test:blocks`: `test_registering_the_same_build_twice_in_one_request_reports_nothing` failed, `missing` contained `/tmp/ishot-blocks-twice-.../blocks/sample`.
- **GREEN:** `npm run test:scripts` 29 pass, 0 fail; `npm run test:blocks` OK (47 tests, 315 assertions); `npm run test:theme` OK (6 tests, 21 assertions); `npm run build:blocks:check` OK (no blocks); `npm run qa` clean (PHPCS, PHPStan level 6); `npm run build` and `lint:tokens` OK; prototype integrity check "Prototype matches 50e60f4."

### Task 1.0b: WordPress capture target for the visual and accessibility harness

- **Built:** `VISUAL_TARGET=wp` (`tests/visual/target.mjs`) captures `wpPath` on `VISUAL_BASE_URL` (default `http://localhost:8888`) with the same controlled rendering and compares it with the committed prototype reference. No Vite server is started in this mode (`playwright.config.mjs`), the prototype build is skipped, the integrity check still runs, and a missing landmark is a count failure, not a crash. `VISUAL_SCOPE=frame` filters reference and actual before comparing (`scope.mjs`); `VISUAL_STATES=404,about` selects states. `alignment.mjs` compares `header-container`, `footer-grid` and `footer-legal-container` left/right edges with the prototype reference `container-edge` (and the page's own, when present), within 1 px, for `home`, `about`, `creative-direction`, `404`; counted as `alignment` failures. `gate: false` states are exempt from the region assertion (`flaggedRegions`). The report has an overlay (50 % blend) column. Regions wholly above the page (the off-screen skip link) are not pixel-compared. `tests/visual/a11y.spec.mjs` runs axe (wcag2a/aa, wcag21a/aa, best-practice) at 3 widths x 2 themes, menu closed and open (open is skipped with an annotation when there is no `.fo-menu-toggle`).
- **New landmarks (prototype reference regenerated, insertions only):** `skip-link` (`.ish-skip`), `footer-grid` (`.fo-footer .ish-footer__grid`), `footer-legal-container` (`.fo-footer__legal .fo-container`). No existing landmark changed.
- **Commands:** `npm run test:visual:wp [-- --scope frame --states 404,about]`, `npm run test:a11y:wp [-- --states ...]`, `npm run test:a11y` (prototype, builds first).
- **Evidence:** `npm run test:scripts` 43 pass; `npm run test:visual` 97 passed; prototype axe `npm run test:a11y` 192 passed (16 states x 3 widths x 2 themes x menu closed/open), 0 violations; `test:visual:wp` against the Stage 0 placeholder theme ran end to end (96 states, report written): 1972 geometry, 216 style, 72 alignment (24/24 states failing, expected until 1.1 to 1.4), 102 flagged regions. Details in `.superpowers/sdd/2026-11-21-wordpress-phase-3b-3c/task-1.0b-report.md`.

### Task 1.1: Night bootstrap and `ishot/theme-toggle`

- **Built:** theme `functions.php`: `ishot_theme_storage_key()` (the one definition of `ishot-theme`), `language_attributes` filter adding `data-theme="light"` (front end only, never duplicated), and a tiny inline script (`#ishot-night-bootstrap`, `wp_print_inline_script_tag`, `wp_head` priority 0, before the stylesheets at priority 8) that reads the guarded `localStorage` choice (`dark`, else `light`) into `document.documentElement.dataset.theme`. Block `ishot/theme-toggle` (`themes/ishot-concept/blocks/theme-toggle/`, built to `build/blocks/theme-toggle/`): `render.php` prints `<button type="button" class="ish-linkbtn" aria-pressed="false">Night mode</button>` plus Interactivity directives and a `data-wp-context` carrying the storage key from PHP; `view.js` (store `ishot/theme`, `viewScriptModule`) syncs `aria-pressed` from the document on init and toggles, persists (guarded) and updates `aria-pressed` on click. Not placed in any template yet (Task 1.3). No jQuery.
- **`.gitkeep` churn (0.2 M6):** `build/blocks/.gitkeep` is removed from the theme, since the build now has content and `wp-scripts` deleted it on every build. The plugin's placeholder stays (no blocks yet).
- **Tests:** `NightModeTest` (PHPUnit `theme` suite): key, head script priority and position before `ishot-tokens-css`, guarded script content and size, `data-theme="light"` on `<html>` and not duplicated, block registered with a view script module, exact button markup, shared key through context, identical markup for guests and logged-in users, manage-page CSP still allows `'unsafe-inline'` scripts. Playwright `tests/e2e/theme-toggle.spec.mjs` (`npm run test:e2e`, `playwright.e2e.config.mjs`, dev site on :8888, no web server): starts light; stored `dark` seen at `DOMContentLoaded`; click toggles, persists across reload; storage blocked still loads light and toggles. The spec creates a throwaway page with wp-cli in `beforeAll` and force-deletes it in `afterAll` (simpler than a tests-environment page; the dev site is left unchanged).
- **Evidence:** see `.superpowers/sdd/2026-11-21-wordpress-phase-3b-3c/task-1.1-report.md` for RED/GREEN output and the build check.
