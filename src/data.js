// Central content for the iShot Concept site — mirrors the prototype's data model.
// Image paths come from ./assets.js (the one place to swap in real photos).
import { serviceImages, portfolioImages } from "./assets.js";

export const navItems = [
  { to: "/", label: "Home" },
  { to: "/about", label: "About" },
  { to: "/services", label: "Services" },
  { to: "/portfolio", label: "Portfolio" },
  { to: "/creative-direction", label: "Creative Direction" },
  { to: "/contact", label: "Contact" },
];

export const contact = {
  location: "Monrovia, Liberia",
  phone: "0881405259",
  email: "ishotconcept01@gmail.com",
  instagram: "@ishotconcept",
  website: "www.ishotconcept.com",
};

export const services = [
  {
    id: "s1",
    title: "Birthday & Portrait Photography",
    blurb: "Personality-rich portraits and joyful birthday sessions, beautifully lit.",
    img: serviceImages.s1,
    tag: "Portraits",
    long: "From milestone birthdays to signature portraits, we craft frames that capture personality, emotion, and light — the kind of images you print, gift, and treasure.",
  },
  {
    id: "s2",
    title: "Weddings, Renewals & Engagements",
    blurb: "Cinematic coverage of the moments that become forever.",
    img: serviceImages.s2,
    tag: "Celebrations",
    long: "Engagements, weddings, and vow renewals covered with a cinematic, editorial eye — every glance, tear, and celebration preserved with intention.",
  },
  {
    id: "s3",
    title: "Corporate & Executive Shoots",
    blurb: "Polished headshots and brand imagery that command trust.",
    img: serviceImages.s3,
    tag: "Brand & Business",
    long: "Executive headshots, team portraits, and brand imagery that make your business look as credible and premium as it truly is.",
  },
  {
    id: "s4",
    title: "Creative Direction & Concepts",
    blurb: "Bold, art-directed shoots built around a strong idea.",
    img: serviceImages.s4,
    tag: "Art Direction",
    long: "A fully art-directed experience — mood, styling, set, and story — designed to turn a single idea into an unforgettable visual campaign.",
  },
  {
    id: "s5",
    title: "Videography & Short Reels/Films",
    blurb: "Motion storytelling — reels, films, and highlight edits.",
    img: serviceImages.s5,
    tag: "Motion",
    long: "Short films, reels, and highlight edits that bring movement and sound to your story, made for the way people watch today.",
  },
  {
    id: "s6",
    title: "Studio & Outdoor Sessions",
    blurb: "Controlled studio light or golden-hour outdoors, your call.",
    img: serviceImages.s6,
    tag: "Sessions",
    long: "Studio precision or outdoor atmosphere — we shape the environment around your vision for a look that feels entirely yours.",
  },
];

export const serviceOptions = [
  "Birthday & Portrait",
  "Weddings, Renewals & Engagements",
  "Corporate & Executive",
  "Creative Direction & Concepts",
  "Videography & Reels/Films",
  "Studio & Outdoor Sessions",
];

export const process = [
  { n: "1", title: "Concept", blurb: "We shape the idea, mood, and story you want to tell." },
  { n: "2", title: "Direction", blurb: "Styling, set, and shot list — every detail planned." },
  { n: "3", title: "Shoot", blurb: "We capture it with precision, energy, and creative craft." },
  { n: "4", title: "Deliver", blurb: "Signature editing and fast, polished final delivery." },
];

export const filters = [
  "All",
  "Portraits",
  "Weddings",
  "Corporate",
  "Creative Concepts",
  "Studio",
  "Outdoor",
  "Reels/Films",
];

// Category per grid slot; the photo for each comes from portfolioImages (assets.js) in order.
const portfolioCats = [
  "Portraits",
  "Weddings",
  "Creative Concepts",
  "Corporate",
  "Studio",
  "Outdoor",
  "Portraits",
  "Reels/Films",
  "Weddings",
  "Creative Concepts",
  "Corporate",
  "Studio",
];

export const portfolio = portfolioCats.map((cat, i) => ({ img: portfolioImages[i], cat }));
