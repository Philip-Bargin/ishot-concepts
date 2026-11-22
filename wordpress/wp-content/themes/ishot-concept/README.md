# iShot Concept (WordPress block theme)

Presentation only: bookings, services and client data live in the `ishot-booking` plugin, so switching themes never loses data.

## One token source

`npm run sync:ds` (also run before `dev` and `build`) generates everything below from `design-system/`. **Never edit the generated files by hand.**

| File | Source |
|---|---|
| `theme.json` | `theme.base.json` (hand-kept: settings switches and element styles) + presets from `design-system/tokens.json` |
| `assets/css/tokens.css`, `assets/css/bundle.css` | The design system's CSS, unchanged except that the Google Fonts `@import` is removed, so `fo-` markup renders exactly as in the React prototype |
| `assets/fonts/*.woff2` | Latin subsets of Oswald (500/600/700) and Space Grotesk (400/500/700) from `@fontsource`, self-hosted |

Generated presets:
- **Palette:** semantic colours only (`paper`, `ink`, `band`, `accent`, `highlight`, …). Their values are `var(--token)`, so the Night theme (`data-theme="dark"`) re-colours anything the editor applies.
- **Font sizes:** one per type style. Each style's usage note gives its sizes, such as "(60 / 48 / 36)". Styles with more than one size become fluid between 375px and 1280px.
- **Spacing:** the `space-*` scale. **Layout:** content 720px, wide 1280px.

`assets/css/theme.css` is hand-written. It adapts core blocks: square buttons and images, the outline button, form controls, hairline separators, and gutters that grow with the viewport. It may use only tokens; `npm run lint:tokens` rejects raw colours and durations.

The owner cannot pick custom colours, sizes, gradients, duotones or radii. Every editor choice is a design-system token.

## Requires plugins

The theme is presentation only and needs two companion plugins from this repository:

- **`ishot-booking`**: bookings, services, content types and client data.
- **`ishot-blocks`**: the reusable content blocks (hero, founder, services and so on). They live in the plugin, not the theme, so page content keeps rendering after a theme change. `ishot-blocks` itself requires `ishot-booking` (`Requires Plugins` header, WordPress 6.5+).

Planned for Stage 1 (not built yet): without the companion plugins the theme will show only its frame (header, menu sheet, footer) and an admin notice, and its frame blocks will call `IShot\Blocks\...` helpers behind a `class_exists` guard. Today the theme has no templates beyond a placeholder, so none of this behaviour exists yet.

Theme tests (`tests/`) run from the `ishot-blocks` PHPUnit config, which loads both plugins and activates this theme: `npm run test:theme`.

## Status

- **3a done:** scaffold, generated `theme.json`, self-hosted and preloaded fonts, core overrides, editor styles. `templates/index.html` is a placeholder.
- **3b–3f (after Phase 1 sign-off):** header, menu-sheet and footer parts; all templates; `ishot/*` blocks ported from the approved prototype; patterns and template locking.

## Development

The theme is mapped into wp-env (`.wp-env.json`) and active on the dev site. `wordpress/dev/style-check.php` creates a "Style check" page of core blocks for eyeballing the overrides.
