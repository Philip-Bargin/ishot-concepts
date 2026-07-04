# iShot Concept

Marketing site for **iShot Concept** — a premium photography brand based in Monrovia, Liberia.
Photography, creative direction, and visual storytelling.

Built with **React + Vite**, an editorial-cinematic design system (Cormorant + Montserrat +
Great Vibes), scroll-reveal motion that respects `prefers-reduced-motion`, and a fully
accessible booking flow.

## Getting started

```bash
npm install      # install dependencies
npm run dev      # start the dev server → http://localhost:5173
npm run build    # production build into dist/
npm run preview  # preview the production build locally
```

## Pages

| Route | Page |
|-------|------|
| `/` | Home — hero, who we are, vision/mission, services, selected work, why choose us, CTA |
| `/about` | About — story, stats, vision/mission, promises |
| `/services` | Services — six alternating service articles |
| `/portfolio` | Portfolio — filterable masonry grid + lightbox |
| `/creative-direction` | Creative Direction — hero, process, featured concepts |
| `/contact` | Contact — booking form with validation, contact details, map |

## Booking form email

The contact page's booking form emails submissions through
[Web3Forms](https://web3forms.com) — no server to run. To turn it on:

1. Create a (free) access key at <https://web3forms.com>, using the address that should
   **receive** inquiries (e.g. `ishotconcept01@gmail.com`).
2. Copy `.env.example` to `.env.local` and set `VITE_WEB3FORMS_ACCESS_KEY`.
3. Restart the dev server / rebuild.

Until a key is configured, the form validates input and prompts visitors to email directly,
so nothing breaks. The key is a public *submit* key (safe in client code); `.env.local` is
git-ignored. The form also includes a hidden honeypot field for basic spam protection.

## Swapping in real logo & photos

All logo and image paths live in **one file: `src/assets.js`**.

- **Logo:** put your file at `public/logo.svg` (a white/transparent version reads best on the
  navy header/footer), then set `logo.src` in `src/assets.js`. Until then, the text wordmark
  (iSHOT + "Concept") is used.
- **Photos:** drop images into `public/images/`, then replace the placeholder URL for that key
  in `src/assets.js` (e.g. `homeHero: "/images/hero.jpg"`). See
  `public/images/HOW-TO-ADD-PHOTOS.txt`.
- **Favicon:** replace `public/favicon.svg`.

The site currently ships with `picsum.photos` placeholder imagery so it runs out of the box.

## Project structure

```
public/            static assets (favicon, images/)
src/
  main.jsx         app entry
  App.jsx          routes + layout
  assets.js        ← all logo + image paths (edit here)
  data.js          nav, services, portfolio, process, contact content
  index.css        design tokens + component styles
  components/      Header, Footer, Logo, Lightbox, Reveal, ui
  pages/           Home, About, Services, Portfolio, Creative, Contact
```

## Tech

- Vite 5, React 18, React Router 6
- Google Fonts: Cormorant, Montserrat, Great Vibes
- No CSS framework — a small hand-authored design system in `src/index.css`

## Brand

Monrovia, Liberia · 0881405259 · ishotconcept01@gmail.com · [@ishotconcept](https://instagram.com/ishotconcept)
