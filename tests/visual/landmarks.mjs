// A spec that matches nothing is recorded with count 0 and compared like any other count, so an element that
// appears on one side only is a failure. Common specs (card, image, ...) legitimately match nothing on pages
// without that component; that is the explicit choice. Specs marked required must match: capture fails if not.
// Named landmark lists (plan §5.2). Selectors rely on the shared design-system classes (.fo-*), which the
// WordPress theme emits too, so one list is read on both sides.
//   all:  every match is probed and named name[0], name[1], ...
//   text: a text block; its height may differ by 4 px (wrapping / hinting) instead of 2 px.
const L = (name, selector, opts = {}) => ({ name, selector, all: false, text: false, required: false, ...opts });

export const COMMON = [
  L('header', '.fo-header', { required: true }),
  L('header-container', '.fo-header .fo-container'),
  L('logo', '.fo-header .fo-logo', { required: true }),
  L('header-nav', '.fo-header nav', { all: true }),
  L('header-actions', '.fo-header__actions'),
  L('main', 'main', { required: true }),
  L('container-edge', 'main .fo-container'),
  L('section', 'main section', { all: true }),
  L('heading', 'main h1, main h2', { all: true, text: true }),
  L('button', 'main .fo-btn', { all: true }),
  L('card', 'main .fo-card', { all: true }),
  L('image', 'main .fo-img', { all: true }),
  L('footer', '.fo-footer', { required: true }),
  L('footer-column', '.fo-footer__col', { all: true }),
  L('footer-legal', '.fo-footer__legal'),
];

export const EXTRAS = {
  home: [
    L('hero', '.fo-hero', { required: true }),
    L('hero-heading', '.fo-hero h1', { text: true, required: true }),
    L('marquee', '.fo-marquee'),
    L('tile', 'main .fo-tile', { all: true }),
    L('stat', 'main .fo-stat', { all: true }),
    L('feature', 'main .fo-feature', { all: true }),
  ],
  page: [L('title-block', 'main .ish-title', { required: true }), L('lead', 'main .lead', { all: true, text: true })],
  portfolio: [
    L('title-block', 'main .ish-title', { required: true }),
    L('filter-chip', 'main .fo-chip', { all: true }),
    L('gallery-item', 'main .ish-tile', { all: true }),
  ],
  journal: [
    L('title-block', 'main .ish-title', { required: true }),
    L('notice', 'main .ish-alert--info', { all: true, text: true, required: true }),
  ],
  form: [
    L('title-block', 'main .ish-title', { required: true }),
    L('form', 'main form', { required: true }),
    L('field', 'main .fo-field', { all: true }),
    L('label', 'main .fo-label', { all: true, text: true }),
  ],
  notice: [
    L('title-block', 'main .ish-title', { required: true }),
    L('notice', 'main .ish-alert--info', { all: true, text: true, required: true }),
  ],
  // Manage booking has a compact title block and a sample notice but no lead paragraph.
  manage: [
    L('title-block', 'main .ish-title', { required: true }),
    L('notice', 'main .ish-alert--info', { all: true, text: true, required: true }),
  ],
  // Creative direction opens with a photo hero instead of the shared title block.
  'hero-page': [L('hero', '.fo-hero', { required: true }), L('hero-heading', '.fo-hero h1', { text: true, required: true })],
  // Booking: only step 1 (service choice) is rendered on load. The slot picker, details form and review
  // steps appear after interaction and are NOT covered by this static reference (see task report).
  book: [
    L('title-block', 'main .ish-title', { required: true }),
    L('steps', 'main .fo-steps', { required: true }),
    L('step-options', 'main .ish-options', { required: true }),
    L('step-option', 'main .ish-option', { all: true, required: true }),
    L('step-nav', 'main .ish-stepnav'),
    L('summary', 'main .fo-summary'),
  ],
};

/** Names of required specs that matched nothing. Capture fails when this is non-empty. */
export const findMissingRequired = (specs, counts) => specs.filter((sp) => sp.required && !counts[sp.name]).map((sp) => sp.name);

export const landmarksFor = (key) => [...COMMON, ...(EXTRAS[key] ?? [])];

// Properties read for check C (plan §5.2). Border and padding are read per side because the shorthand
// computed value is empty when the sides differ.
export const STYLE_PROPS = [
  'font-family',
  'font-size',
  'font-weight',
  'line-height',
  'letter-spacing',
  'color',
  'background-color',
  'border',
  'padding',
];
