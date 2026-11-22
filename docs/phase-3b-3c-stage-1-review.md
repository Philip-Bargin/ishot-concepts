# Stage 1 review pack: WordPress frame (header, footer, base templates)

Owner checkpoint for Stage 1 of `docs/superpowers/plans/2026-11-21-wordpress-phase-3b-3c.md` (Task 1.5). Branch `redesign/wp-phase-3b-3c`, gates run at `43a6349` (CI run 45058877266 green), then the whole-stage review findings were fixed in the "Final review fix wave" (§13), which re-ran the suites listed there. Every result below comes from a command run for this gate on 2026-11-22; the commands and raw counts are also in `docs/phase-3b-3c-progress.md` ("Task 1.5").

**Stage 2 needs the owner's approval.** Nothing has been deployed to Vercel or anywhere else.

## 1. Summary

- **Gates met except the exceptions in §9.2:** prototype integrity; build, token lint, block-build check, PHPCS and PHPStan; all PHPUnit suites (current counts in §3 and §13); script tests; all end-to-end tests (keyboard, focus trap, skip link, sticky header, Night toggle, Site Editor); visual geometry (A) and styles (C) on the header and footer, apart from the two exceptions below; 404 full scope with 0 differences; alignment 24/24; axe 0 violations on `about` and `404` (menu closed and open), and on `home` with the menu open.
- **Exceptions that need owner agreement (§9.2, E-1 and E-2). These are unmet acceptance criteria, not expected passes:**
  - **E-1, footer `y` in 84 of 96 frame states.** This misses Task 1.3 "frame geometry for every state passes" and Task 1.4 "about/home/journal-article pass in frame scope". Cause: the WordPress page bodies are shorter or longer than the prototype's until Stages 2–3, so the whole footer sits at a different `y`; measured from the footer's own top it is offset by 0.00 px (`tests/visual/summarise-frame.mjs`, §12). It clears when the Stage 2–3 bodies exist.
  - **E-2, `home` axe `page-has-heading-one` (menu closed, 6 runs).** This misses "0 violations on home". Cause: `home` is the posts index and has no h1 until the Stage 2 hero.
- **Deviation from the plan (§9.2, E-3):** `ishot/spectrum` is a native theme block, where plan §4.2 specifies a core block. It needs owner agreement.
- **Booking regression:** the booking PHPUnit suite, three dev smokes, the public REST endpoints, and `/book/`, `/account/` and `/manage-booking/` (each with `main#main` and the skip link) all behave as expected. One smoke was skipped by design (see §5).
- **Found in this gate, fixed in the final fix wave (§13):** the frame-only blocks (`ishot/logo`, `ishot/site-header`, `ishot/contact-details`, `ishot/social-links`, `ishot/theme-toggle`) were in the inserter, and inserted alone they broke the page (§9.1, D1). They are now hidden from the inserter; template parts still render and edit them.

## 2. What was built

| Part | Files |
|---|---|
| Night bootstrap and toggle (`ishot/theme-toggle`) | theme `blocks/theme-toggle/`, `functions.php` (bootstrap inline script) |
| Logo (`ishot/logo`) | theme `blocks/logo/`, `inc/logo.php` |
| Site header (`ishot/site-header`: header, menu sheet, focus trap, scroll lock) | theme `blocks/site-header/` (`render.php`, `view.js` Interactivity module), `inc/navigation.php` |
| Skip link | theme `inc/skip-link.php` (`a.ish-skip[href=#main]`, first in `<body>`) |
| Footer spectrum (`ishot/spectrum`). **Deviation from plan §4.2 (spectrum = native), see §9.2 E-3** | theme `blocks/spectrum/` |
| Social links (`ishot/social-links`) | plugin `ishot-blocks/blocks/social-links/`, `src/SocialLinks.php` |
| Contact details, footer variant (`ishot/contact-details`) | plugin `ishot-blocks/blocks/contact-details/`, `src/ContactDetails.php` |
| Notice (`ishot/notice`) | plugin `ishot-blocks/blocks/notice/`, `src/Notice.php` |
| Template parts | theme `parts/header.html`, `parts/footer.html` (core blocks, the `ishot-concept/site` bindings source for the legal line, `inc/footer.php`) |
| Templates | theme `templates/index.html`, `page.html`, `single.html`, `404.html`, `search.html`; `inc/groups.php` (`main#main[tabindex=-1]`), `inc/templates.php` |
| Pattern | theme `patterns/page-title.php` (PageTitle markup, `Inserter: no`) |
| Styles | theme `assets/css/theme.css` (wrapper neutralisers, tokens only), `editor.css` and `editor-root.css` (editor canvas only), generated `app.css`, `bundle.css`, `tokens.css`; `theme.base.json` / `theme.json` |
| Navigation seed | plugin `ishot-booking` `Importer` (core `wp_navigation` "Primary") |
| Dev fixtures | `wordpress/dev/studio-details-dev.php`, `wordpress/dev/page-shells-dev.php` (`IShot\Booking\Dev\DevGuard`) |
| Tests | theme `tests/*Test.php` (7 classes), `ishot-blocks/tests/Integration/*`, `tests/e2e/*.spec.mjs` (5 specs), `tests/visual/*` |

## 3. Results: build, static checks and unit tests (runs 1–3)

| Command | Result |
|---|---|
| Prototype integrity (`assertPrototypeIntegrity`) | "Prototype matches 50e60f4." |
| `git diff --quiet 50e60f4 HEAD -- src public index.html vite.config.js design-system` | exit 0 (identical) |
| `npm run build` | exit 0; 1626 modules transformed |
| `npm run lint:tokens` | OK, no raw colours or durations outside tokens |
| `npm run build:blocks:check` | up to date: theme 4 blocks, ishot-blocks 3 blocks |
| `npm run qa` | PHPCS 107/107 files clean; PHPStan level 6 "No errors" |
| `npm run test:scripts` | 51 tests: 50 pass, 0 fail, 1 skipped (POSIX signal test, Windows) at `43a6349`; 53 tests: 52 pass, 0 fail, 1 skipped after the final fix wave (§13) |
| `npm run test:php` (booking) | OK (406 tests, 1588 assertions) |
| `npm run test:blocks` (ishot-blocks unit + integration) | OK (76 tests, 367 assertions) |
| `npm run test:theme` | OK (100 tests, 667 assertions) at `43a6349`; OK (110 tests, 692 assertions) after the final fix wave (§13) |

## 4. Results: responsive visual comparison (run 5)

All 16 states × 375/768/1440 × light/Night = 96 states, frame scope (header and footer landmarks), compared with the committed prototype reference. Playwright ran with `--workers=1`. Geometry (A) and styles (C) are gates. Regions (B) were compared in all 96 states and are reviewed, not gated.

| State | WP URL (status) | States | A+C pass | Footer `y` only | Other failures | Style fails | Alignment fails | Max footer Δy (px) | Max footer-relative Δ (px) | Regions > 1 % |
|---|---|---|---|---|---|---|---|---|---|---|
| 404 | `/this-page-does-not-exist/` (404) | 6 | **6** | 0 | 0 | 0 | 0 | 0.00 | 0.00 | 0 |
| about | `/about/` (200) | 6 | 0 | 6 | 0 | 0 | 0 | 2641.56 | 0.00 | 0 |
| account | `/account/` (200) | 6 | 0 | 6 | 0 | 0 | — | 1690.00 | 0.00 | 0 |
| book | `/book/` (200) | 6 | 0 | 6 | 0 | 0 | — | 1168.00 | 0.00 | 0 |
| contact | `/contact/` (200) | 6 | 0 | 6 | 0 | 0 | — | 2144.14 | 0.00 | 0 |
| creative-direction | `/creative-direction/` (200) | 6 | 0 | 6 | 0 | 0 | 0 | 3079.25 | 0.00 | 0 |
| home | `/` (200) | 6 | 0 | 6 | 0 | 0 | 0 | 8572.41 | 0.00 | 0 |
| journal | `/journal/` (200) | 6 | 0 | 6 | 0 | 0 | — | 2565.00 | 0.00 | 0 |
| journal-article | `/journal/what-to-wear-for-your-portrait-session/` (200) | 6 | 0 | 6 | 0 | 0 | — | 3637.14 | 0.00 | 0 |
| manage-booking | `/manage-booking/` (404, page in Stage 3) | 6 | 0 | 6 | 0 | 0 | — | 664.00 | 0.00 | 0 |
| portfolio | `/portfolio/` (200) | 6 | 0 | 6 | 0 | 0 | — | 1275.50 | 0.00 | 0 |
| portfolio-category | `/portfolio/portraits/` (200) | 6 | 0 | 6 | 0 | 0 | — | 1097.38 | 0.00 | 0 |
| sample-notice | `/work/studio-portrait-series/` (404: projects are drafts until Stage 3) | 6 | 0 | 6 | 0 | 0 | — | 4342.33 | 0.00 | 0 |
| search (review-only) | `/?s=portrait` (200) | 6 | 0 | 6 | 0 | 0 | — | 24.00 | 0.00 | 0 |
| services | `/services/` (200) | 6 | 0 | 6 | 0 | 0 | — | 7591.50 | 0.00 | 0 |
| work | `/work/birthday-call-concept/` (404: projects are drafts until Stage 3) | 6 | 0 | 6 | 0 | 0 | — | 4342.33 | 0.00 | 0 |

- **Totals:** 96 states, Playwright 12 passed and 84 failed (plus the prototype-only determinism test skipped). There were 0 header failures and 0 style failures in 96/96. Every one of the 84 failures is the footer `y` class: 8 footer landmarks per state (48 per row) are shifted by the same amount as the `footer` box, and the footer-relative difference is 0.00 px. The `search` row is review-only (`gate: false`), so it fails nothing.
- **Classification:** every non-pass is the footer-`y` class (exception E-1, §9.2). The cause is that WordPress page bodies (page shells, the posts index, the 404 template on unbuilt routes) are shorter or longer than the prototype's pages until Stages 2–3. No other failure was found.
- **Alignment (R-S1-1):** 24/24. `home`, `about`, `creative-direction` and `404` × 3 widths × 2 themes, with 0 alignment failures.
- **404, full scope** (`VISUAL_SCOPE=full VISUAL_STATES=404`): 6/6 pass, with 0 geometry, 0 style and 0 alignment failures. All 130 region diffs are 0 pixels.
- **Side-by-side report** (local, gitignored): `tests/visual/.output/compare-report.html`.

## 5. Results: booking regression (run 4)

| Check | Result |
|---|---|
| `npm run test:php` | OK (406 tests, 1588 assertions) |
| `wp eval-file wp-content/ishot-dev/content-smoke.php` | exit 0. REST testimonial rating 7 → **400**, as expected because the field allows 1–5 and the temporary testimonial is deleted. Project `birthday-call-concept`: draft, placeholder, 3 gallery images, cover alt present, 2048×2560 with 6 sizes. Category URL `/portfolio/portraits/`. 7 services, of which `portrait-session` is published and bookable at price 150; the other 6 are drafts and not bookable. |
| `wp eval-file wp-content/ishot-dev/privacy-smoke.php` | exit 0: schema 5; columns `deposit_gateway, deposit_reference, anonymised_at`; 1 export item for the seeded client; retention scheduled yes |
| `wp eval-file wp-content/ishot-dev/me-bookings-probe.php efua@client.test` | verified=efua@client.test, status 200, 2 bookings |
| `… me-bookings-probe.php verify-flow@client.test` | verified=no, status 200, 1 booking (the user's own, by owner id) |
| `notification-smoke.php` | **Skipped, not repeatable.** It overwrites the dev site's email and deposit settings (`Settings::update`), creates 5 new smoke bookings on every run, never deletes them, and sends mail. It isn't meant for repeat runs on the shared dev site, and the booking PHPUnit suite covers notifications. |
| `GET /wp-json/ishot/v1/services` | 200: `[{"id":5,"title":"Portrait session","duration_min":60,"price_from":150,"deposit_amount":50}]` |
| `GET /wp-json/ishot/v1/availability?service=5&date=…` | 200 for 9 dates. 2026-11-23 and 11-24 have no times (lead time). 11-25 has 17 slots (09:00–17:00 every 30 min). 11-27 and 12-03 have 12 slots from 11:30 (existing bookings). |
| `GET …/availability?service=999&date=2026-11-25` | 422 `ishot_unknown_service` |
| `GET …/availability?service=5&date=bad` | 400 `rest_invalid_param` |
| `GET /wp-json/ishot/v1/me/bookings` (guest) | 401 `rest_not_logged_in` |
| `/book/` | 200, "Book a session · …", one `<main tabindex="-1" id="main">`, skip link `a.ish-skip[href=#main]` |
| `/account/` | 200, "My bookings · …", `main#main`, skip link |
| `/manage-booking/` (also with `#token=…`) | 404 through the 404 template, as expected because the page arrives in Task 3.6. Has `main#main` and the skip link. |

## 6. Results: accessibility and keyboard (run 6)

**axe** (`VISUAL_TARGET=wp VISUAL_STATES=home,about,404 npx playwright test tests/visual/a11y.spec.mjs --workers=1`; WCAG 2.0/2.1 A and AA plus best practice; this run at `43a6349` predates the WCAG 2.2 tags, see §13 for the re-run with them): 36 tests, 30 passed and 6 failed, with no browser crash.

| State | Menu closed (6 combos) | Menu open (6 combos) |
|---|---|---|
| home | 6 × `page-has-heading-one` (moderate, 1 node: `html`). Unmet "0 violations on home"; owner exception E-2 (§9.2). | 0 violations |
| about | 0 violations | 0 violations |
| 404 | 0 violations | 0 violations |

**Keyboard and behaviour** (`npm run test:e2e -- --workers=1`): **29 passed**, 0 failed.
- The skip link is the only one, is the first Tab stop and shows on focus. Activating it moves focus to `main#main` on every base template.
- The sheet is closed on load (`aria-expanded=false`, `aria-controls`). Opening it focuses the first link and locks scroll.
- Tab and Shift+Tab wrap inside the sheet. An early open before hydration still moves focus into the sheet.
- Escape, the close button and a menu link each close the sheet; Escape and the close button return focus to the toggle.
- `aria-current` is set on the current page only.
- The header stays stuck at top 0 after a 1500 px scroll at 375, 768 and 1440.
- Two `templates.spec.mjs` tests check that core adds no block gap.
- Night toggle: a page starts in light and unpressed; a stored dark choice applies at DOMContentLoaded with no flash; the choice survives a reload; with storage blocked, the toggle still works for the visit.
- Footer: normal and degraded layouts.

## 7. Results: WordPress editor (run 7)

The tests ran logged in, inside the same e2e run, with `ISHOT_EVIDENCE_DIR=docs/phase-3b-3c-evidence/stage-1/editor`.

| Check | Result |
|---|---|
| Header template part in the Site Editor | pass: site header shown, no invalid or unsupported block; "Book now" is 36 px in the editor, the same as on the front end |
| Footer template part | pass: valid, editable core link columns. No `core/html`, textarea or code view. 4 spectrum bars. The Visit lines are stacked rows. |
| Templates `index`, `page`, `single`, `404`, `search` | 5/5 pass: valid, supported blocks; design-system styles in the canvas |
| Post editor (`logo`, `social-links`, `theme-toggle`) | pass (e2e); separate evidence shot with `ishot/notice` added: 0 block warnings |

Observation from the post-editor evidence shot: the standalone `ishot/logo` shows at full image size (§9, D1). The test checks that the block is visible, not how big it is.

## 8. Evidence images

All images are in `docs/phase-3b-3c-evidence/stage-1/`: 67 PNGs, 1.05 MB in total, the largest 69 KB. They are palette PNGs.

**Header, open menu sheet and footer** (`landmarks/`). Each was captured on the `404` state on both sides (it's the one state whose page body is complete in Stage 1) at 3 widths × 2 themes. Three images per combination:
- `*-side-by-side.png`: the prototype on the left and WordPress on the right.
- `*-overlay.png`: a 50 % blend of the two.
- `*-diff.png`: a pixelmatch diff at threshold 0.1, the same as the harness. Red pixels differ.

Unlike the harness, the legal band is not masked here, so the owner sees the real line.

| Landmark | 375 light | 375 Night | 768 light | 768 Night | 1440 light | 1440 Night |
|---|---|---|---|---|---|---|
| header | 0 px | 0 px | 0 px | 0 px | 0 px | 0 px |
| menu sheet | 6 px | 10 px | 0 px | 0 px | 0 px | 0 px |
| footer | 72 px | 60 px | 76 px | 60 px | 76 px | 60 px |

The sizes are identical on both sides for every pair: header 375×72, 768×80, 1440×80; menu 375×812, 768×1024, 1440×900; footer 375×737, 768×529, 1440×369.
- **Footer:** the 60–76 differing pixels (at most 0.03 %) are glyph anti-aliasing in the legal line. The text is identical on both sides; WordPress prints it in a `<p>` where the prototype uses a `<span>` (R-1.3-1). Geometry and styles pass.
- **Menu sheet at 375:** the 6–10 differing pixels are anti-aliasing.

Files, for example:
- `landmarks/header-375-light-side-by-side.png`
- `landmarks/menu-sheet-768-dark-overlay.png`
- `landmarks/footer-1440-dark-diff.png`

The full list is in `docs/phase-3b-3c-progress.md`.

**Site Editor** (`editor/`): `site-editor-part-header.png`, `site-editor-part-footer.png`, `site-editor-template-404.png`, plus `site-editor-template-{index,page,single,search}.png`.

**Front end and post editor** (`frontend/`):
- `frontend-404-1440-light.png`
- `frontend-404-375-dark.png`
- `frontend-about-768-light.png`
- `frontend-book-1440-dark.png`
- `frontend-menu-open-375-light.png`
- `post-editor-theme-blocks.png`

The front-end page shots are full-page captures; the 1440 ones are scaled to 1440 wide.

## 9. Exceptions, rulings and deferred items

Sources:
- the controller ledger lines for Tasks 1.0a–1.4 that contain `Ruling:` or `minor (deferred)`;
- the `R-S1-*` and `R-1.x-*` rulings in the Task 1.2–1.4 briefs;
- the Task 1.4 review items the controller asked to show here.

### 9.1 Defect found in this gate

| # | Item | Evidence | Suggested route |
|---|---|---|---|
| D1 (minor, **fixed in §13**) | `ishot/logo` is in the inserter (`supports.inserter: true`, `isLink` defaults to `false`). On its own it prints only the two `<img>`s, with no `.fo-logo` parent, and every logo sizing and swapping rule (`.fo-logo img { height }`, `.fo-logo .ish-logo__rev { display:none }`) is scoped to `.fo-logo`. So an owner who inserts it gets the logo at natural size, with the full and reversed files both shown. The header and footer uses are unaffected: they pass A and C. | `frontend/post-editor-theme-blocks.png`; `blocks/logo/block.json`; `blocks/logo/render.php` line 15; `assets/css/app.css` lines 82–88 | Fixed: `supports.inserter: false` on the five frame-only blocks. The same class of problem existed for `ishot/site-header` (duplicate banner, `#site-menu` and focus traps), `ishot/contact-details` footer variant (text runs together) and `ishot/theme-toggle` (a second toggle out of sync). |

### 9.2 Rulings and accepted exceptions (decisions for the owner)

| # | Item | Reason | Status / when resolved |
|---|---|---|---|
| R-S1-1 | Alignment compares the WordPress header container, footer grid and legal container edges with the **prototype reference** `container-edge` (±1 px), and with WordPress's own `main .fo-container` where it exists. The 4 states are `home`, `about`, `creative-direction` and `404` (24 checks). | WordPress pages have no body content until Stages 2–3, so the prototype edge is the stable target. | In force; 24/24 in this gate. |
| R-S1-2 | The template-part wrappers get `display: contents` in `theme.css`. | A wrapper around the sticky `.fo-header` would become its containing block, and the header would scroll away. | In force; the sticky-header e2e passes. |
| R-S1-3 | `ishot/site-header` prints `ishot/social-links` only when that block is registered, and reads Studio details behind `class_exists`. | The theme must degrade without the plugins and never fatal. | In force; covered by the theme PHPUnit tests. |
| Ruling (1.2) | Task 1.2's uncommitted partial work was audited and completed by a fresh implementer, not discarded. | The work was plausibly correct and reviewable as one diff. | Done; reviewed like any other code. |
| Ruling (1.2) | The `fo-root ish-app` wrapper was added to `templates/index.html` ahead of Task 1.4. | Needed for box-sizing, so the header geometry could be verified. | Done; Task 1.4 extended it to every template. |
| Ruling (1.2) | The logo `alt` is the literal "iShot Concept" (from `Logo.jsx`), not the editable site title. | A logo's alt names the brand, and the site title may carry a tagline. | In force. |
| Ruling (1.2) | Menu lookup order: block attribute `ref`, then the published `wp_navigation` titled "Primary", then the first published `wp_navigation`, then the 8 prototype nav items. | Deterministic when several menus exist, and consistent with the importer seed. | In force. |
| Ruling (1.2 review) | Promoted to Important and fixed: POSIX watch orphans; an empty `nav[aria-label=Social]` when social links are unavailable; the skip link must be first in `<body>` for everyone; the importer must not create an empty "Primary"; the Site Editor must not show "unsupported block" for server-only blocks. | Owner requirements: WCAG 2.2 AA, clean degradation, editor verification. | Fixed in `cac6d1c`. |
| M5 (1.2) | The skip link sits outside `.fo-root`. | It has to be first in `<body>`; it renders the same. | Accepted structural exception. |
| M8 (1.2) | Core adds `decoding="async"` to the logo images. | Core behaviour, with no visual effect. | Accepted markup exception. |
| R-1.3-1 | The legal line is a `core/paragraph` (editable and bindable) instead of a `<span>`. `theme.css` neutralises the paragraph so its geometry and computed styles equal the span. Core bindings replace the whole `content`, so the `ishot-concept/site` source provides `year` and `copyright`, and the paragraph binds `copyright`. | Keeps the line editable and the year current. | Accepted (`p` vs `span`); the footer styles pass. |
| Ruling (1.3 review) | Promoted and fixed: the Site Editor legal paragraph showed the source label instead of the copyright line (the source is now registered client-side); dev `/about/` returned 404 because the prototype content wasn't imported. | The owner requires editor verification. | Fixed in `1d144a0`. |
| Ruling I5 (1.3) | The importer seeds the custom post types only (projects and services are drafts by design). Pages and journal posts don't exist until Stage 3, so Task 1.4 added a guarded, dev-only page-shell fixture to exercise the page and single templates. | Header and footer are identical on every template, and no business copy is invented. | Done (`page-shells-dev.php`); the real pages come in Stage 3. |
| R-1.4-1 | `main#main[tabindex=-1]` is a core Group (`tagName: main`, anchor `main`) and a direct child of `.fo-root.ish-app`. `tabindex` comes from the theme's single `render_block_core/group` filter. | Gives every template a skip-link target. | In force; the skip-link e2e passes on 5 templates, and `/book/`, `/account/` and `/manage-booking/` have the target. |
| R-1.4-2 | The page and single H1 is `core/post-title`, with the extra class `wp-block-post-title`. | Binds the title to the post, with no visual effect. | Accepted markup exception. |
| R-1.4-3 | `document_title_separator` is `·`, so titles read "{Title} · {Site Title}". | Matches the prototype's titles. | In force. **Preview note:** the dev site's Site Title was the wp-env default "ishot-concepts"; the final fix wave set it to "iShot Concept" on the dev site only (`wp option update blogname`, a dev database change, see §12). |
| R-1.4-4 | The editor canvas root `.editor-styles-wrapper` gets the `.fo-root` base rules from a generated `editor-root.css`, which loads in the editor only. | The Site Editor part view has no `.fo-root` wrapper ("Book now" was about 54 px tall instead of 36 px). | In force; "Book now" is 36 px in the editor and on the front end. |
| R-1.4-5 | A guarded dev-only fixture creates title-only page shells and one journal post shell (`content_status` = placeholder), and switches the wp-env permalink structure to `/journal/%postname%/`. | Exercises the page and single templates without inventing content. | Dev only. Stage 3 builds the real pages; Task 3.1 must handle the services `with_front`. |
| Exceptions (1.4) | Each 404 button sits in a class-less `<p>` (an editable core Paragraph with `display: contents`). The gallery link is `/portfolio/` with a trailing slash. The 404 lead's apostrophe is written `&#39;`. | Keeps the 404 copy editable with core blocks. | Accepted; the 404 full scope shows 0 differences. |
| Ruling (1.4 review) | Promoted and fixed: the 404 test asserts the real markup; e2e evidence goes to `ISHOT_EVIDENCE_DIR`; axe waits for the menu animations. | Gives stable, deterministic gate evidence. | Fixed in `43a6349`. |
| Ruling (1.4) | **`settings.spacing.blockGap: null` theme-wide.** | Core's `(0,2,0)` first-child and last-child flow rules otherwise zero the prototype classes' margins, and the prototype geometry is the authority. | In force. **Cost to the owner:** no per-block "Block spacing" control in the editor. Row, Stack and Buttons get core's fixed 0.5em fallback gap. Stage 2–3 patterns must space content with prototype classes. |
| Accepted (1.4 review) | `scripts/sync-design-system.mjs` gained 26 lines (one extra generated file; the other outputs are byte-identical). The `site-header` `view.js` early-open race fix (WCAG 2.4.3, RED then GREEN) is recorded as a Task 1.2 change. | A required generated file, and a real defect fix. | Done. |
| **E-1** (needs owner agreement) | **Footer `y` fails in 84 of 96 frame states** (every state except `404`; 12 states pass A and C). Unmet acceptance: Task 1.3 "frame geometry for every state passes" and Task 1.4 "about/home/journal-article pass in frame scope". | Cause: WordPress `main` is shorter or longer than the prototype's page body until Stages 2–3, so every footer landmark is shifted by the same amount. Evidence: §4 table; `node tests/visual/summarise-frame.mjs` reports a footer-relative offset of 0.00 px in all 16 states; footer x, width, height and styles pass in 96/96. | Open. Clears when the Stage 2–3 bodies exist; not hidden or relaxed in the harness. |
| **E-2** (needs owner agreement) | **`home` fails axe `page-has-heading-one`** with the menu closed (6 runs). Unmet acceptance: "0 violations on home". | Cause: `home` is the posts index; it has no h1 until the Stage 2 hero. Evidence: §6 table. | Open. Clears in Stage 2. |
| **E-3** (needs owner agreement) | **`ishot/spectrum`: deviation from plan §4.2 (spectrum = native).** | Reason: `core/html` shows raw source in the Site Editor, and a Group can't print `<span aria-hidden><i>×4`. Front-end markup is exactly `<span class="fo-spectrum" aria-hidden="true"><i></i><i></i><i></i><i></i></span>`. | Open. Frame-only: hidden from the inserter. |
| **E-4** (needs owner agreement) | **Degraded-footer override** in `theme.css`: `.ish-footer__grid > nav.fo-footer__col:last-child { grid-column: auto }`. It changes a prototype rule (the prototype's last-child rule makes the Visit column full width). | Without the Visit column (no Studio details or no plugin) the Studio and Book columns must stay side by side below 768 px. Evidence: `tests/e2e/footer.spec.mjs` (RED 572/732 px, then GREEN). | Accepted by the controller in Task 1.3 fix 1; listed here for owner agreement. |
| **E-5** (needs owner agreement) | **ishot-booking gained production-autoloaded dev-only classes** `IShot\Booking\Dev\DevGuard` and `IShot\Booking\Dev\PageShellFixtures`, and `StudioDevFixtures` was refactored to use `DevGuard`. All are guarded and dev-only (`wp eval-file` fixtures; nothing runs on a normal request). This goes beyond plan §1, which names only the Importer seed as a change to ishot-booking. | The page-shell fixture needed a shared guard so the Studio and page-shell fixtures cannot run on production. Covered by `PageShellFixturesTest` (18 tests, 9 guard data sets). | Open. Alternative: move the fixtures out of the plugin's autoloaded `src/`. |
| Ruling (controller) | **Task 1.5 had no separate task review.** It is covered by the Stage 1 whole-stage review, which produced the findings fixed in §13. | Task 1.5 only gathers gate evidence and writes this pack; the whole-stage review reads the same diff. | Done. |

### 9.3 Deferred minor items

| Task | Item | Reason | When |
|---|---|---|---|
| 1.0a | Watch mode didn't forward SIGINT or kill siblings; `--watch --check` silently ignores `--check`. | Tooling. | Orphans fixed in `cac6d1c`; `--watch --check` was fixed in Task 1.2 (`scripts/build-blocks.mjs` errors and exits 2). |
| 1.0a | Theme block registration repeats the plugin's glob/decode logic; `$ours` isn't cleared by `forgetSkipped()`; no direct test of the brand PNG copy or the `app.css` banner (the CI diff covers them). | The plan sanctions separate folders. | Backlog. |
| 1.0b | The a11y spec has no prototype integrity check; a worker-count claim; (the missing-toggle skip and the WCAG 2.2 tags were fixed in §13); `SCOPE` also applies in prototype mode; no HTTP status check on WordPress navigation; alignment uses box edges, not content edges. | Harness hardening. | Backlog. |
| 1.0b | Prototype `gate:false` states also get a "regions over 1 %" annotation. | Harmless. | Backlog. |
| 1.1 | The e2e throwaway page isn't cleaned if `beforeAll`/`afterAll` fail; NightModeTest matches on the key, not the script id; `type="text/javascript"` (needs html5 script/style support); the `aria-pressed` default is set in two places. | Minor. | Backlog. |
| 1.2 | M2: SIGTERM/SIGHUP exit codes are 130 instead of 143/129. | Tooling. | Backlog. |
| 1.2 | M6: fragment-only or query-only menu URLs map to path `/`, so Home is marked current; plain-permalinks edge case. | Edge case. | Stage 3, with the real menus. |
| 1.2 | M9: the normalised parity test ignores `id`, `href`, `hidden`, `style` and `data-autofocus`. | Covered by separate tests. | Backlog. |
| 1.2 | A SIGKILL of the parent orphans detached watchers; a self-exiting Windows watcher isn't tree-killed; the site-editor spec persists welcome-guide prefs for the dev admin; logo `index.js` name-inlining variance. | Tooling and dev only. | Backlog. |
| 1.3 | M2: `.fo-footer__col > p:not([class])` stops matching if the owner adds a class to a link paragraph. | Owner note. | Revisit if it's needed. |
| 1.3 | M3: footer links are root-relative (`/about/`), so they break on a subdirectory install. | Production runs at a domain root. | Before any subdirectory install. |
| 1.3 | M5: an unlinked phone number is a bare `<span>`, and its styling is unverified. | Only happens when the phone has no link. | Stage 3. |
| 1.3 | M6: `FooterPartTest::expectedSave` re-implements core's save; the e2e `isValid` check is the real proof. | Test design. | Backlog. |
| 1.3 | The degraded-footer e2e removes Visit in the browser (the server-side drop is proven in PHPUnit); the `capture.test` fake doesn't exercise the mask-paint wait; `site-bindings.js` calls `__()` without `wp_set_script_translations`; the client year comes from the browser clock, not `wp_date` in the site's timezone; the degraded footer isn't checked at ≥768 px; standalone editor entries need build-blocks support. | Minor. | Backlog; translations in Stage 3. |
| 1.4 | `single.html` lacks the Article's `<article aria-labelledby>` and has an empty padded content div; the 404 links and copy are hard-coded (subdirectory installs, i18n); the dev permalink `/journal/%postname%/` moves the service singles (ServiceCatalog has no `with_front: false`); the CDP-only early-open regression test is slow. | The Article page and services arrive later. | Stage 3 (Task 3.1 for permalinks). |
| 1.4 | The `templates.spec` header comment is stale, and its probe group isn't asserted to be `is-layout-flow`; (the spectrum `aria-hidden` wrapper and className supports items were fixed in §13, M-a); `editor.css` `display: contents` removes the contact-details selection outline; the evidence names lost their `task-1.4-` prefix. | Minor, editor only. | Backlog. |
| 1.4 (noted) | **Post content isn't spaced as prose:** `post-content` isn't inside `.fo-prose`. | Page bodies arrive in Stages 2–3. | Stages 2–3. |
| 1.4 (noted) | **The Site Editor's page template shows an empty, padded notice section.** This is editor only: the front end drops the section when the notice prints nothing. | The editor preview has no post context. | Stages 2–3. |
| 1.4 (exception) | `home` fails axe `page-has-heading-one` because the posts index has no h1. Unmet "0 violations on home"; see E-2. | The home hero (with its h1) arrives in Stage 2. | Stage 2, owner agreement. |

## 10. Outstanding content checklist

The checklist is unchanged from `docs/phase-1-verification.md`; Stage 1 changed no item. Every item is still waiting on the owner:
- Facebook page URL: not supplied. The icon stays inactive.
- WhatsApp number: 0881405259 (+231) is **to be confirmed**.
- Founder/CEO name, title, biography and portrait: not supplied.
- Testimonials: none supplied. At least 3 consented entries are needed.

The full inventory is in `docs/content-inventory.md`.

Studio details are entered under Booking → Settings → Studio details. On the dev site they are filled from the prototype and marked "Not confirmed by the studio yet".

## 11. What's left for Stages 2–3 (not yet authorised)

- **Stage 2, content blocks, styles and patterns:**
  - block styles and section patterns, `ishot/marquee`;
  - the hero slideshow;
  - the project gallery and lightbox;
  - service tiles and list, category cards, testimonials (hidden below 3 entries);
  - founder and share links;
  - the home h1 (clears the axe item);
  - prose spacing for post content.
- **Stage 3, page templates:**
  - activation setup: idempotent pages, front and posts page, `/journal/%postname%/`, services `with_front`;
  - the front page;
  - About, Services, Creative direction and Contact;
  - Portfolio, its category taxonomy and project singles (clears the `work` and `sample-notice` 404s);
  - Journal home, single (with `<article aria-labelledby>`) and archive;
  - booking shells `page-book`, `page-account` and `page-manage-booking`.

  Footer `y` should then match on each route as its body arrives.
- **Before production approval:** real-device, screen-reader and OS reduced-motion checks (B1–B3) are mandatory.

## 12. How to preview locally

From the repository root, with Docker running (in Git Bash, prefix wp-cli calls that take a `/` path with `MSYS_NO_PATHCONV=1`):

```sh
export NODE_OPTIONS="--require ./wordpress/dev/dns-lookup-shim.cjs"
npm run wp:start                      # wp-env: dev http://localhost:8888, tests http://localhost:8889
npm run wp:setup                      # first time: composer install in both plugins
npm run wp:import                     # prototype content (projects, services, images, Primary menu)
npx wp-env run cli wp eval-file wp-content/ishot-dev/studio-details-dev.php   # Studio details (dev only, marked unconfirmed)
npx wp-env run cli wp eval-file wp-content/ishot-dev/page-shells-dev.php      # title-only page shells (dev only)
npx wp-env run cli wp option update blogname "iShot Concept"                   # dev site title (dev cli container only, not tests-cli)
```

Open http://localhost:8888 (admin: `admin` / `password`). The Site Editor is at Appearance → Editor.

Re-run this gate with:
- `npm run test:visual:wp -- --scope frame`, then `node tests/visual/summarise-frame.mjs` from the repository root (it reads `tests/visual/.output/current`, or pass another folder) for the per-state table including the footer-relative offset;
- `npm run test:a11y:wp -- --states home,about,404`;
- `npm run test:e2e`.

On a host short of memory, run the Playwright command directly with `--workers=1`, as in `docs/phase-3b-3c-progress.md`.

## 13. Final review fix wave

The Stage 1 whole-stage review (it also covers Task 1.5, which had no separate task review, see §9.2) returned findings. They were fixed on top of `396094e`, each test first (RED seen, then GREEN). Commands ran one at a time on a low-memory host, Playwright with `--workers=1`.

| Finding | Change | Covering test (RED then GREEN) |
|---|---|---|
| I1/D1 inserter | `supports.inserter: false` on `ishot/logo`, `ishot/site-header`, `ishot/theme-toggle` (theme) and `ishot/contact-details`, `ishot/social-links` (plugin); builds regenerated and committed. | New `InserterTest` (theme suite): six frame blocks are not in the inserter, `ishot/notice` still is, and the header and footer parts still render the blocks. RED: 5 inserter failures (plus the spectrum case and a wrong needle in my first draft of the parts test); GREEN 110 tests. `site-editor.spec.mjs` stays green (8/8; the post-editor test still renders the blocks from post content). |
| M-a spectrum | `block.json` `className: false`, `customClassName: false`; the editor markup puts `aria-hidden` on an inner span only; `editor.css` (tokens only) keeps the four bars; front-end markup unchanged (still asserted exactly by `FooterPartTest`). | `InserterTest::test_spectrum_has_no_class_field_...`: RED 1 failure, GREEN. `site-editor.spec.mjs` now counts `.fo-spectrum > span[aria-hidden=true] > i` = 4 and asserts the wrapper has no `aria-hidden` (RED first: the old selector failed once the markup changed, then GREEN after the `editor.css` rules). |
| M-b | `site-header/render.php`: `class_exists` plus `is_callable` guard on `Studio::details`, address and phone trimmed before the empty checks. | `SiteHeaderBlockTest::test_a_whitespace_only_address_prints_no_separator`: RED (`<p class="small">  \t  · <a …`), GREEN. |
| M-c | `tests/visual/axe-config.mjs` adds `wcag22a` and `wcag22aa`; `a11y.spec.mjs` uses it. | `tests/visual/axe-config.test.mjs`: RED (2 failures with the old tag list), GREEN 2/2. |
| M-d | In WP mode a missing `.fo-menu-toggle` fails (`menuToggleOutcome`); the prototype still skips. | Same test file. |
| Footer-relative figure | `tests/visual/summarise-frame.mjs` committed (no machine paths; usage in §12). | Run against the last 404 output: 0.00 px, 6/6. |
| Dev preview | `wp option update blogname "iShot Concept"` on the dev `cli` container (not `tests-cli`). Dev database only, not code. | `blogname` was "ishot-concepts"; the command returned "Success: Updated 'blogname' option." |

**Verification after the fixes (one command at a time):**

| Command | Result |
|---|---|
| `npm run test:theme` | OK (110 tests, 692 assertions) |
| `npm run test:blocks` | OK (76 tests, 367 assertions) |
| `npm run test:php` (booking) | OK (406 tests, 1588 assertions) |
| `npx playwright test --config playwright.e2e.config.mjs --workers=1` | 29 passed (first run before the `editor.css` rules: 28 passed, 1 failed, the spectrum selector above) |
| axe with WCAG 2.2 tags (`VISUAL_TARGET=wp VISUAL_STATES=home,about,404 npx playwright test tests/visual/a11y.spec.mjs --workers=1`) | 36 tests: 30 passed, 6 failed. The 6 are the existing home `page-has-heading-one` (menu closed, exception E-2). **No new violations from `wcag22a` or `wcag22aa`**; no browser crash. |
| `npm run build:blocks:check`, `npm run qa` (PHPCS clean, PHPStan 0 errors), `npm run lint:tokens`, `npm run build` | OK |
| `npm run test:scripts` | 53 tests: 52 pass, 0 fail, 1 skipped (Windows) |
| Prototype integrity | `assertPrototypeIntegrity()` passes; `git diff 50e60f4 HEAD` of `src public index.html vite.config.js design-system` is empty |

No new axe findings, so there is no extra open item for the owner from the WCAG 2.2 run.
