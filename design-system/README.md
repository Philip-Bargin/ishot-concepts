# iShot Concept design system (vendored)

Read-only copy of the official design system. **Do not edit these files by hand.** Change the source design system, then re-vendor.

| | |
|---|---|
| Source | https://claude.ai/artifact/RRJN6B8YYomt5rRkF1DQUx |
| Version | `1795143900-037f` (last change 2026-11-13, "Rebranded to iShot Concept: palette from the official logo, logo files added") |
| Vendored | 2026-11-20 |

## Contents
| Path | What | Use |
|---|---|---|
| `tokens.json` | Token source of truth (colour ×2 themes, type, spacing, radius, layout, motion, z-index) | Input to the tokens → `theme.json` sync script (Phase 3a) |
| `tokens.css` | Generated CSS custom properties + type classes (`.h1`, `.eyebrow`, …) | Load first |
| `components/bundle.css` | `fo-` component classes | Load after `tokens.css`. **Line 1 `@import`s Google Fonts.** Strip it in production builds and self-host Oswald + Space Grotesk (plan §5). |
| `logos/*.png` | Official logo: full-colour, reversed-white, mono-navy (each also trimmed) | Header/footer use the `-trimmed` files. Vector masters are still needed from the owner. |

## Load order
Fonts → `tokens.css` → `components/bundle.css` → page CSS. Set `data-theme="light"` or `"dark"` on `<html>`.

## Logo usage
Full colour on `paper`/`mist`; reversed white on `band` and photography; mono navy for one-colour use. Header 48px tall (40px mobile), footer 56px, minimum width 96px. Never recolour or add effects.
