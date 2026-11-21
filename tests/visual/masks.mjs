// Dynamic content hidden (visibility: hidden, so layout is untouched) before the screenshot only.
// Geometry and style probes still read these elements. Every mask needs a reason (plan §5.1).
export const MASKS = [
  {
    selector: '.fo-footer__legal',
    reason: 'Footer year: the prototype reads the browser clock (fixed to 2026-11-23), WordPress renders the server year.',
  },
];

// Clock used on both sides for every date the page computes ("today", booking lead days, sample bookings).
export const FIXED_TIME = '2026-11-23T09:00:00Z';
