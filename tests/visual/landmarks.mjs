// Named landmark lists (plan §5.2). Selectors rely on the shared design-system classes (.fo-*), which the
// WordPress theme emits too, so one list is read on both sides.
//   all:  every match is probed and named name[0], name[1], ...
//   text: a text block; its height may differ by 4 px (wrapping / hinting) instead of 2 px.
const L = (name, selector, opts = {}) => ({ name, selector, all: false, text: false, ...opts });

export const COMMON = [
  L('header', '.fo-header'),
  L('header-container', '.fo-header .fo-container'),
  L('logo', '.fo-header .fo-logo'),
  L('header-nav', '.fo-header nav', { all: true }),
  L('header-actions', '.fo-header__actions'),
  L('main', 'main'),
  L('container-edge', 'main .fo-container'),
  L('section', 'main section', { all: true }),
  L('heading', 'main h1, main h2', { all: true, text: true }),
  L('button', 'main .fo-btn', { all: true }),
  L('card', 'main .fo-card', { all: true }),
  L('image', 'main .fo-img', { all: true }),
  L('footer', '.fo-footer'),
  L('footer-column', '.fo-footer__col', { all: true }),
  L('footer-legal', '.fo-footer__legal'),
];

export const EXTRAS = {
  home: [
    L('hero', '.fo-hero'),
    L('hero-heading', '.fo-hero h1', { text: true }),
    L('marquee', '.fo-marquee'),
    L('tile', 'main .fo-tile', { all: true }),
    L('stat', 'main .fo-stat', { all: true }),
    L('feature', 'main .fo-feature', { all: true }),
  ],
  page: [L('title-block', 'main .ish-title'), L('lead', 'main .lead', { all: true, text: true })],
  portfolio: [
    L('title-block', 'main .ish-title'),
    L('filter-chip', 'main .fo-chip', { all: true }),
    L('gallery-item', 'main .ish-tile', { all: true }),
  ],
  journal: [L('title-block', 'main .ish-title'), L('notice', 'main .ish-alert--info', { all: true, text: true })],
  form: [
    L('title-block', 'main .ish-title'),
    L('form', 'main form'),
    L('field', 'main .fo-field', { all: true }),
    L('label', 'main .fo-label', { all: true, text: true }),
  ],
  notice: [L('title-block', 'main .ish-title'), L('notice', 'main .ish-alert--info', { all: true, text: true })],
};

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
