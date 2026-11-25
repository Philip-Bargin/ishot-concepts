// ============================================================
//  iShot Concept: logo + photo registry (the one place to swap images).
//
//  ADDING OR REPLACING A PHOTO
//  1. Put the original in public/images/ (lowercase, no spaces).
//  2. Run `npm run images` to generate the responsive sizes
//     (public/images/opt/<file>-480|960|1600|2400.webp).
//  3. Add an entry below with:
//       file   the original's name without extension
//       alt    a unique description of what is in the photo
//       cat    a category slug from data.js
//       focus  CSS object-position keeping the subject's eyes in frame
// ============================================================

export const logos = {
  full: "/brand/ishot-concept-full-colour-trimmed.png",
  reversed: "/brand/ishot-concept-reversed-white-trimmed.png",
  alt: "iShot Concept",
};

export const IMAGE_WIDTHS = [480, 960, 1600, 2400];

export const photos = {
  phoneBirthday: {
    file: "hero",
    alt: "Birthday concept: a gloved hand holds up a phone showing a woman with red hair tasting the frosting on a candlelit cake slice",
    cat: "creative",
    focus: "50% 45%",
  },
  burgundyLounge: {
    file: "birthday",
    alt: "Woman in a burgundy cap and sweatshirt holding a coupe glass in a dimly lit lounge",
    cat: "portraits",
    focus: "45% 22%",
  },
  graduate: {
    file: "graduation-1",
    alt: "Graduate in a black blazer dress and orange stole smiling at a phone that reads Graduated",
    cat: "studio",
    focus: "60% 22%",
  },
  denimSmile: {
    file: "birthday-1",
    alt: "Woman in a red cap, printed headscarf and denim jacket smiling over her shoulder outdoors",
    cat: "outdoor",
    focus: "50% 38%",
  },
  redFurSofa: {
    file: "portrait",
    alt: "Woman with long red hair in a red faux-fur coat and lace tights seated on a velvet sofa",
    cat: "portraits",
    focus: "45% 30%",
  },
  gownStair: {
    file: "outdoor-portrait",
    alt: "Woman with copper curls in a black strapless gown posing beside a plant under a sculptural ceiling light",
    cat: "creative",
    focus: "55% 45%",
  },
  // Not in the portfolio grid: used by the Founder block only.
  founder: {
    file: "founder-judah-lindah",
    alt: "Judah Darlington Lindah, founder of iShot Concept, seated with arms folded in a grey check three-piece suit and red tie beside a camera on a tripod",
    cat: "studio",
    focus: "60% 25%",
  },
};

// Portfolio grid order (each photo appears once).
export const portfolio = [
  photos.burgundyLounge,
  photos.phoneBirthday,
  photos.denimSmile,
  photos.graduate,
  photos.gownStair,
  photos.redFurSofa,
];

export const srcFor = (photo, width = 960) => `/images/opt/${photo.file}-${width}.webp`;
export const srcSetFor = (photo) => IMAGE_WIDTHS.map((w) => `${srcFor(photo, w)} ${w}w`).join(", ");
