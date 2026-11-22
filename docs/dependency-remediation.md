# npm dependency remediation plan

Reviewed 2026-11-20. Baseline: `npm audit` reported **20** advisories (2 critical, 9 high, 7 moderate, 2 low).

**Exposure:** none of these ship in the production website, which is WordPress plus PHP. All of them are in:
- local dev tools: `@wordpress/env`, `vite`'s dev server, `sharp` in `scripts/optimize-images.mjs`;
- or the React prototype bundle (`react-router-dom`), which is used only for design review behind a Vercel login.

## Stage 1: patch and minor updates within the current major ✅ applied

Done with a targeted command:

```
npm update react-router-dom react-router postcss nanoid source-map-js browserslist baseline-browser-mapping update-browserslist-db
```

The lockfile changed only:
- `react-router-dom`/`react-router` 6.30.4 → 6.30.6
- `postcss` 8.5.16 → 8.5.33
- `nanoid` 3.3.15 → 3.3.20
- `source-map-js` 1.2.1 → 1.2.4
- `browserslist` → 4.29.6
- `baseline-browser-mapping`, `update-browserslist-db`

**Verified:**
- `npm run build`, which includes `sync:ds` and `lint:tokens`, passes.
- A browser check of `/`, `/work/birthday-call-concept`, `/book` and `/manage/demo-awaiting` rendered with no new console errors.
- PHP suite: 316 tests green. The PHP code doesn't depend on npm, so this run confirms the WordPress side is unaffected.

**Result:** 20 → **15** advisories.

**Why not plain `npm audit fix`:** it also moves `@wordpress/env` from 10.14 to 10.39. That's in range, but it adds 263 packages (the Playground/php-wasm toolchain) that need Node ≥ 24.18 and npm ≥ 11.16; this machine has Node 24.13 and npm 11.8. That belongs in Stage 2, not a patch stage.

## Stage 2: `@wordpress/env` 10 → 12 (major) ⏳ needs testing

**Clears:** both criticals (`simple-git`, `@simple-git/argv-parser`) and `extract-zip`, `tmp`, `glob`, `minimatch`, `rimraf`, `inquirer`, `external-editor`. 9 of the 15.

**Exposure:** a local Docker orchestration CLI, run by developers only.

**Steps, on a branch:**
1. Upgrade Node to ≥ 24.18 LTS and npm to ≥ 11.16.
2. `npm i -D @wordpress/env@12`
3. Read the changelog for `.wp-env.json` changes (the mappings, the `tests` env, port settings).
4. Restart and run the suite:
   ```
   NODE_OPTIONS="--require ./wordpress/dev/dns-lookup-shim.cjs" npx wp-env destroy && npx wp-env start
   npm run wp:setup && npm run test:php
   ```
5. Re-run the smoke scripts: `wordpress/dev/*-smoke.php`.
6. Check the DNS shim still works with the new CLI.

**Accept when:** all PHP tests are green, the dev site serves, Mailpit receives mail, and the import command works.

## Stage 3: `vite` 5 → 8 (major) ⏳ needs testing

**Clears:** the `vite` and `esbuild` dev-server advisories (path traversal in `.map` handling, cross-origin requests to the dev server, NTLM hash leak via UNC paths on Windows).

**Exposure:** dev server only. It already listens on `127.0.0.1` only, so risk stays low until this is done. Production builds are static.

**Steps:**
1. `npm i -D vite@8 @vitejs/plugin-react@latest`
2. Check the `vite.config` API changes.
3. Run `npm run build` and `npm run preview:build`, then browser-check every prototype page against the review checklist.

## Stage 4: `sharp` 0.33 → 0.35 (major) ⏳ needs testing

**Clears:** libvips/libheif CVEs.

**Exposure:** only `scripts/optimize-images.mjs`, run by hand on the studio's own photos.

**Steps:**
1. `npm i -D sharp@0.35`
2. Run `npm run images` and compare the output sizes and quality in `public/images/opt`.

## Stage 5: React Router 6 → 7 (major) ⏳ optional

**Clears:** the remaining moderate open-redirect and constructor advisories in `react-router-dom`. These are fixed only in 7.18+.

**Exposure:** only the prototype preview. The production site has no React Router.

**Steps:** recommended only if the prototype stays in use after launch. Otherwise archive the prototype at launch (as planned) and drop it.

## Update 2026-11-22: `@wordpress/scripts` added (Phase 3b/3c, Stage 0)

`@wordpress/scripts` **35.0.0** was added as a **dev-only** dependency (exact-pinned) to build the `ishot-blocks` block bundles.
- **Exposure:** build tooling only. The build output is committed to the repository and nothing from this package ships to production.
- **Why 35.0.0:** 36.x and 37.x declare an optional peer `vite ^7 || ^8`, which makes `npm ci` fail (ERESOLVE) against the prototype's vite 5. Revisit when vite is upgraded.
- **Audit after the addition** (`npm audit`, run 2026-11-22, no fixes applied): **42** advisories (2 low, 27 moderate, 11 high, 2 critical), against the 20 recorded as the 2026-11-20 baseline. `npm audit --omit=optional` reports the same set. The increase comes from the `@wordpress/scripts` dev toolchain (for example `webpack-dev-server`, which is not used: only `wp-scripts build` runs). `npm audit fix` was not run. Re-measure after each remediation stage.

## Compatibility requirements (verified with `npm view`, 2026-11-20)

| Upgrade | Version | Needs | This machine | CI (`node-version: 24`) |
|---|---|---|---|---|
| `@wordpress/env` | 12.2.0 | Node ≥ 22.13 itself, but it pulls in `@wp-playground/cli` → `@php-wasm/node`, which needs **Node ≥ 24.18 and npm ≥ 11.16** | Node 24.13 / npm 11.8: **upgrade Node first** | latest 24.x: OK |
| `vite` | 8.3.8 | Node ^20.19 or ≥ 22.12; needs `@vitejs/plugin-react` 6 (peer `vite ^8`), so upgrade both together | OK | OK |
| `@vitejs/plugin-react` | 6.3.0 | Node ^20.19 or ≥ 22.12; peer `vite ^8` | OK | OK |
| `sharp` | 0.35.5 | Node ≥ 20.9 (prebuilt libvips) | OK | not used in CI |
| `react-router-dom` | 7.18.6 | Node ≥ 20; peers `react`/`react-dom` ≥ 18 (have 18.3); code changes (v7 API and future flags) | OK | OK |

CI already runs on the latest Node 24 and reports `npm audit` without failing, so each stage is validated by CI before merge.

## Rule for every stage

One major upgrade per branch. Run the full PHP suite, `npm run build` and a browser check before merging. Never use `npm audit fix --force`.
