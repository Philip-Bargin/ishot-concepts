// The 16 page states compared between the React prototype and the WordPress site.
// prototypePath: route in the Vite prototype (pinned at 50e60f4). wpPath: URL on the wp-env dev site.
// landmarks: key into landmarks.mjs (common landmarks plus that page's extras).
import { categories } from '../../src/data.js';
import { projects, posts, sampleBookings } from '../../src/prototype/sampleContent.js';

const category = categories.find((c) => c.slug === 'portraits').slug;
const [project, secondProject] = projects.map((p) => p.slug);
const post = posts[0].slug;
const bookingToken = sampleBookings.find((b) => b.status === 'confirmed').token; // mock token, see sampleContent.js

export const ROUTES = [
  { name: 'home', prototypePath: '/', wpPath: '/', landmarks: 'home' },
  { name: 'about', prototypePath: '/about', wpPath: '/about/', landmarks: 'page' },
  { name: 'services', prototypePath: '/services', wpPath: '/services/', landmarks: 'page' },
  { name: 'portfolio', prototypePath: '/portfolio', wpPath: '/portfolio/', landmarks: 'portfolio' },
  { name: 'portfolio-category', prototypePath: `/portfolio/${category}`, wpPath: `/portfolio/${category}/`, landmarks: 'portfolio' },
  { name: 'work', prototypePath: `/work/${project}`, wpPath: `/work/${project}/`, landmarks: 'page' },
  { name: 'creative-direction', prototypePath: '/creative-direction', wpPath: '/creative-direction/', landmarks: 'page' },
  { name: 'journal', prototypePath: '/journal', wpPath: '/journal/', landmarks: 'journal' },
  { name: 'journal-article', prototypePath: `/journal/${post}`, wpPath: `/journal/${post}/`, landmarks: 'page' },
  { name: 'contact', prototypePath: '/contact', wpPath: '/contact/', landmarks: 'form' },
  { name: 'book', prototypePath: '/book', wpPath: '/book/', landmarks: 'form' },
  { name: 'account', prototypePath: '/account', wpPath: '/account/', landmarks: 'page' },
  // Prototype route is /manage/<mock token>; the WordPress page is /manage-booking/ (token supplied by the plugin).
  { name: 'manage-booking', prototypePath: `/manage/${bookingToken}`, wpPath: '/manage-booking/', landmarks: 'page' },
  // Unknown URL: the prototype's catch-all route renders the "Page not found" template.
  { name: '404', prototypePath: '/this-page-does-not-exist', wpPath: '/this-page-does-not-exist/', landmarks: 'page' },
  // The prototype has NO search page. For this state the prototype reference is its 404 (NotFound) route, the
  // closest template, and the WordPress side is a search for "portrait". Search-specific layout cannot be
  // checked against the prototype; it needs the owner's visual review instead.
  { name: 'search', prototypePath: '/search-is-not-in-the-prototype', wpPath: '/?s=portrait', landmarks: 'page' },
  // A second project page so the "Sample content" PrototypeNotice (label default) is covered as its own state.
  { name: 'sample-notice', prototypePath: `/work/${secondProject}`, wpPath: `/work/${secondProject}/`, landmarks: 'notice' },
];
