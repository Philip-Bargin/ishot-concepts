// ============================================================
//  iShot Concept — ALL logo + photo paths live in this one file.
//
//  HOW TO USE YOUR OWN ASSETS
//  1. Put image files in the  public/images/  folder
//     (put the logo in  public/  , e.g.  public/logo.svg ).
//  2. Files in public/ are served from the site root, so
//        public/images/hero.jpg   ->   "/images/hero.jpg"
//        public/logo.svg          ->   "/logo.svg"
//  3. Replace the placeholder URL on the right with that path.
//     Nothing else to touch — every page reads from here.
// ============================================================

// -------------------- LOGO --------------------
// Put your logo at  public/logo.svg  (or .png), then set `src` below.
// A WHITE / TRANSPARENT version reads best on the navy header & footer.
// Leave `src` as "" to keep the current text wordmark (iSHOT + "Concept" script).
export const logo = {
  src: "/logo.svg", // e.g. "/logo.svg"
  alt: "iShot Concept",
  height: 94, // px — tweak to size the logo in the header/footer
};

// -------------------- HERO / SECTION PHOTOS --------------------
export const images = {
  // Home page
  homeHero: "/images/hero.webp", // big hero background
  homeStudio: "/images/birthday-1.webp", // "Who We Are" photo
  homeCta: "/images/birthday.webp", // "Let's Create That Magic" band

  // Home "Selected Work" strip (1 large + 4 small)
  work1: "/images/birthday.webp", // large feature tile
  work2: "/images/hero.webp",
  work3: "/images/outdoor-portrait.webp",
  work4: "/images/portrait.webp",
  work5: "/images/graduation-1.webp",

  // About page
  aboutFounder: "https://picsum.photos/seed/ishotcreativehero/1800/900", // founder / studio portrait

  // Creative Direction page
  creativeHero: "/images/birthday.webp",
  conceptReel: "/images/hero.webp", // has a play icon overlay
  conceptStill: "/images/outdoor-portrait.webp",

  // Interior page hero backdrops (About / Services / Portfolio / Contact).
  // Shown behind the navy gradient header band on each page.
  aboutHero: "/images/portrait.webp",
  servicesHero: "/images/hero.webp",
  portfolioHero: "/images/outdoor-portrait.webp",
  contactHero: "/images/graduation-1.webp",
};

// -------------------- SERVICE PHOTOS --------------------
// Shown on the Home service cards and the Services page (in this order).
export const serviceImages = {
  s1: "/images/birthday.webp", // Birthday & Portrait
  s2: "/images/hero.webp", // Weddings, Renewals & Engagements
  s3: "/images/outdoor-portrait.webp", // Corporate & Executive
  s4: "/images/portrait.webp", // Creative Direction & Concepts
  s5: "/images/graduation-1.webp", // Videography & Short Reels/Films
  s6: "/images/birthday.webp", // Studio & Outdoor Sessions
};

// -------------------- PORTFOLIO PHOTOS --------------------
// The Portfolio page grid + lightbox. Order matches the categories in data.js:
// 1 Portraits · 2 Weddings · 3 Creative Concepts · 4 Corporate · 5 Studio · 6 Outdoor
// 7 Portraits · 8 Reels/Films · 9 Weddings · 10 Creative Concepts · 11 Corporate · 12 Studio
export const portfolioImages = [
  "/images/hero.webp",
  "/images/outdoor-portrait.webp",
  "/images/birthday.webp",
  "/images/portrait.webp",
  "/images/graduation-1.webp",
  "/images/birthday.webp",
  "/images/birthday.webp",
  "/images/outdoor-portrait.webp",
  "/images/portrait.webp",
  "/images/birthday-1.webp",
  "/images/hero.webp",
  "/images/outdoor-portrait.webp",
];
