// Central content for the iShot Concept site. Becomes the WordPress import seed in Phase 3.
// Copy rules (design system): write sentence case (CSS uppercases), no exclamation marks,
// two-digit index numerals, no unsourced claims.

export const navItems = [
  { to: "/", label: "Home" },
  { to: "/about", label: "About" },
  { to: "/services", label: "Services" },
  { to: "/portfolio", label: "Portfolio" },
  { to: "/creative-direction", label: "Creative direction" },
  { to: "/journal", label: "Journal" },
  { to: "/contact", label: "Contact" },
  { to: "/book", label: "Book a session" },
];

export const contact = {
  location: "Monrovia, Liberia",
  phone: "0881405259",
  email: "ishotconcept01@gmail.com",
  instagram: "@ishotconcept",
  instagramUrl: "https://www.instagram.com/ishotconcept/",
  whatsappUrl: "https://wa.me/231881405259",
  website: "www.ishotconcept.com",
};

// Categories cycle the logo's aperture colours in order (see index.css [data-cat]).
export const categories = [
  { slug: "portraits", label: "Portraits", blurb: "Personality-rich portraits, beautifully lit." },
  { slug: "weddings", label: "Weddings", blurb: "Engagements, weddings and vow renewals." },
  { slug: "corporate", label: "Corporate", blurb: "Headshots and brand imagery that build trust." },
  { slug: "creative", label: "Creative concepts", blurb: "Art-directed shoots built around one strong idea." },
  { slug: "studio", label: "Studio", blurb: "Controlled light, clean backdrops, full attention." },
  { slug: "outdoor", label: "Outdoor", blurb: "Natural light and real places around Monrovia." },
  { slug: "reels", label: "Reels & films", blurb: "Short films, reels and highlight edits." },
];

export const categoryLabel = (slug) => categories.find((c) => c.slug === slug)?.label ?? slug;

// `photo` is a key in assets.js `photos`, or null where no real photo exists yet (shows a placeholder).
export const services = [
  {
    id: "s1",
    title: "Birthday & portrait photography",
    short: "Birthday & portrait",
    tag: "Portraits",
    cat: "portraits",
    icon: "camera",
    photo: "denimSmile",
    blurb: "Personality-rich portraits and joyful birthday sessions, beautifully lit.",
    long: "From milestone birthdays to signature portraits, we craft frames that capture personality, emotion and light: the kind of images you print, gift and treasure.",
  },
  {
    id: "s2",
    title: "Weddings, renewals & engagements",
    short: "Weddings & engagements",
    tag: "Celebrations",
    cat: "weddings",
    icon: "heart",
    photo: null,
    blurb: "Cinematic coverage of the moments that become forever.",
    long: "Engagements, weddings and vow renewals covered with a cinematic, editorial eye. Every glance, tear and celebration is preserved with intention.",
  },
  {
    id: "s3",
    title: "Corporate & executive shoots",
    short: "Corporate & executive",
    tag: "Brand & business",
    cat: "corporate",
    icon: "briefcase",
    photo: null,
    blurb: "Polished headshots and brand imagery that command trust.",
    long: "Executive headshots, team portraits and brand imagery that make your business look as credible and premium as it truly is.",
  },
  {
    id: "s4",
    title: "Creative direction & concepts",
    short: "Creative direction",
    tag: "Art direction",
    cat: "creative",
    icon: "sparkles",
    photo: "gownStair",
    blurb: "Bold, art-directed shoots built around a strong idea.",
    long: "A fully art-directed experience covering mood, styling, set and story, designed to turn a single idea into an unforgettable visual campaign.",
  },
  {
    id: "s5",
    title: "Videography & short reels",
    short: "Video & reels",
    tag: "Motion",
    cat: "reels",
    icon: "video",
    photo: null,
    blurb: "Motion storytelling: reels, films and highlight edits.",
    long: "Short films, reels and highlight edits that bring movement and sound to your story, made for the way people watch today.",
  },
  {
    id: "s6",
    title: "Studio & outdoor sessions",
    short: "Studio & outdoor",
    tag: "Sessions",
    cat: "studio",
    icon: "sun",
    photo: "graduate",
    blurb: "Controlled studio light or golden-hour outdoors, your call.",
    long: "Studio precision or outdoor atmosphere: we shape the environment around your vision for a look that feels entirely yours.",
  },
];

export const enquiryTypes = [...services.map((s) => s.title), "General question"];

export const process = [
  { title: "Concept", text: "We shape the idea, mood and story you want to tell." },
  { title: "Direction", text: "Styling, set and shot list, with every detail planned." },
  { title: "Shoot", text: "We capture it with precision, energy and creative craft." },
  { title: "Deliver", text: "Signature editing and fast, polished final delivery." },
];

export const promises = [
  { title: "Gallery-grade images", text: "Crisp, cinematic photography every single time." },
  { title: "Signature editing", text: "A retouching style that makes every image unmistakably yours." },
  { title: "Fast delivery", text: "Quick turnaround without compromising on quality." },
  { title: "Creative storytelling", text: "Concepts and direction that turn a shoot into a story worth telling." },
];

export const values = [
  {
    title: "Our vision",
    text: "To become Africa's most trusted premium photography brand, turning everyday moments into legacy and ideas into unforgettable visual stories.",
  },
  {
    title: "Our mission",
    text: "To capture timeless moments with creativity, precision and style: imagery our clients are proud to remember, feel and celebrate.",
  },
];

// Only verifiable numbers (design-system rule: no unsourced claims).
export const stats = [
  { value: "06", label: "Signature services" },
  { value: "07", label: "Portfolio categories" },
];

export const pad2 = (n) => String(n).padStart(2, "0");
