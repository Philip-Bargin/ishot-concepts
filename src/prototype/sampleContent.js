// ============================================================
//  PROTOTYPE SAMPLE CONTENT: for design review only.
//  Journal posts, project write-ups and client bookings below are
//  placeholders that show how the templates behave. In Phase 3 they
//  are replaced by real WordPress posts, ishot_project entries and
//  bookings from the ishot-booking plugin. Pages using this data show
//  a visible "Sample content" notice.
// ============================================================
import { photos } from "../assets.js";
import { bookingServices, firstBookableDay, toISO } from "./bookingMock.js";

export const journalCategories = ["Guides", "Behind the scenes"];

export const posts = [
  {
    slug: "what-to-wear-for-your-portrait-session",
    title: "What to wear for your portrait session",
    category: "Guides",
    readMins: 4,
    date: "2026-11-02",
    photo: photos.burgundyLounge,
    excerpt: "Colours, textures and fit that photograph well, and what to leave at home.",
    body: [
      { p: "The right outfit does half the work before the camera comes out. It sets the mood, flatters your shape and keeps the attention on you rather than on a busy print." },
      { h: "Start with one strong colour" },
      { p: "A single confident colour, like a deep burgundy or a clean white, reads beautifully on camera and works with almost any backdrop. If you love pattern, keep it to one piece and let everything else stay quiet." },
      { quote: "Wear what makes you feel like yourself." },
      { h: "Texture beats logos" },
      { p: "Knits, satin, denim and faux fur catch light in interesting ways. Large logos and slogans pull the eye away from your face, so save them for another day." },
      { h: "Bring options" },
      { list: ["Two or three outfits that work together", "Comfortable shoes for between shots", "Any accessories that carry meaning for you"] },
      { p: "Not sure? Send us photos of what you're considering when you book, and we'll help you choose." },
    ],
  },
  {
    slug: "how-to-prepare-for-a-studio-session",
    title: "How to prepare for a studio session",
    category: "Guides",
    readMins: 3,
    date: "2026-10-21",
    photo: photos.graduate,
    excerpt: "A simple checklist for the week before, the night before and the day itself.",
    body: [
      { p: "Studio sessions move quickly once the lights are set. A little preparation means you spend that time creating, not adjusting." },
      { h: "The week before" },
      { p: "Confirm your outfits, book hair and make-up if you want it, and tell us about any props or ideas you'd like to bring." },
      { h: "The night before" },
      { list: ["Steam or iron everything you plan to wear", "Charge your phone if it's part of the concept", "Get some sleep: it shows in the eyes"] },
      { h: "On the day" },
      { p: "Arrive ten minutes early so you can settle in. We'll walk you through the set, the poses and the plan before the first frame." },
    ],
  },
  {
    slug: "behind-the-birthday-call-concept",
    title: "Behind the birthday call concept",
    category: "Behind the scenes",
    readMins: 3,
    date: "2026-10-08",
    photo: photos.phoneBirthday,
    excerpt: "How one idea, a birthday wish arriving as a phone call, became a set of images.",
    body: [
      { p: "Concept shoots start with a single sentence. For this one it was simple: what if a birthday wish arrived as an incoming call?" },
      { h: "Building the frame" },
      { p: "The phone becomes the frame within the frame. The celebration lives on the screen, while the world around it falls back into soft black and white." },
      { quote: "One idea, carried all the way through, beats ten good-looking ones." },
      { h: "Your concept next" },
      { p: "Every creative direction session starts the same way: a conversation about the story you want to tell. Bring a sentence; we'll bring the rest." },
    ],
  },
  {
    slug: "planning-your-graduation-photos",
    title: "Planning your graduation photos",
    category: "Guides",
    readMins: 3,
    date: "2026-09-27",
    photo: photos.denimSmile,
    excerpt: "When to book, what to bring and how to make the stole and gown work for you.",
    body: [
      { p: "Graduation photos capture years of work in a single afternoon. Booking early gives you the best choice of dates around the ceremony." },
      { h: "Bring the details" },
      { list: ["Your stole, cap and any medals", "A second outfit for relaxed shots", "Family members you'd like in a few frames"] },
      { p: "Studio or outdoors both work well. Tell us the feeling you want and we'll suggest the setting." },
    ],
  },
];

export const projects = [
  {
    slug: "birthday-call-concept",
    title: "Birthday call concept",
    cat: "creative",
    location: "Monrovia",
    cover: photos.phoneBirthday,
    lead: "A creative-direction shoot built around one idea: a birthday wish that arrives as an incoming call.",
    full: photos.gownStair,
    pair: [photos.redFurSofa, photos.burgundyLounge],
    text: "The concept carried through styling, set and edit: a dark, gloved frame around a bright, joyful moment on screen.",
    strip: [photos.graduate, photos.denimSmile, photos.phoneBirthday],
  },
  {
    slug: "studio-portrait-series",
    title: "Studio portrait series",
    cat: "portraits",
    location: "Monrovia",
    cover: photos.graduate,
    lead: "Clean studio light and confident styling: portraits that let personality lead.",
    full: photos.redFurSofa,
    pair: [photos.burgundyLounge, photos.denimSmile],
    text: "Each sitting started with a conversation about how the client wanted to feel, then built the light around it.",
    strip: [photos.gownStair, photos.phoneBirthday, photos.graduate],
  },
];

// Sample client bookings (the "Test Client" account). Dates are relative to today so
// upcoming bookings stay upcoming. Tokens stand in for the hashed manage-booking links.
const day = (offset) => {
  const d = firstBookableDay();
  return toISO(new Date(d.getFullYear(), d.getMonth(), d.getDate() + offset));
};
const svc = (id) => bookingServices.find((s) => s.id === id);

export const sampleClient = { name: "Test Client", email: "test@example.com" };

export const sampleBookings = [
  { token: "demo-awaiting", reference: "ISC-7K2Q", service: svc("s1"), date: day(5), time: "10:00", status: "awaiting_deposit", holdHours: 48 },
  { token: "demo-confirmed", reference: "ISC-4M9T", service: svc("s6"), date: day(19), time: "14:00", status: "confirmed" },
  { token: "demo-soon", reference: "ISC-2B6H", service: svc("s3"), date: day(0), time: "09:00", status: "confirmed" },
  { token: "demo-completed", reference: "ISC-9P1D", service: svc("s4"), date: "2026-09-12", time: "11:00", status: "completed" },
  { token: "demo-cancelled", reference: "ISC-5X8R", service: svc("s1"), date: "2026-08-03", time: "15:00", status: "cancelled" },
];

export const CANCEL_CUTOFF_HOURS = 72; // placeholder until the owner sets the policy
