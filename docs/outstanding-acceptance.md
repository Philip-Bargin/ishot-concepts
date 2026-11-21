# Outstanding acceptance items

This list was recorded on 2026-11-20 after Phase 1 interaction and accessibility verification. None of these items is authorised for implementation yet. Each needs the owner's go-ahead.

## 1. Small accessibility refinements (optional, for a future refinement)

Neither of these is a defect: both pass automated checks and are understandable as they are. Both would make screen-reader output cleaner. Visual design must not change.

| # | Where | Now | Proposed refinement |
|---|---|---|---|
| A1 | Full-screen menu links (`src/components/MenuSheet.jsx`; later the WordPress menu-sheet part) | Accessible names read "01Home", "02About": the decorative index number is part of the link text. | Hide the number from assistive technology (`aria-hidden="true"` on the numeral span), so names read "Home", "About". |
| A2 | Mobile booking bar (`.ish-bookbar` in `src/pages/Book.jsx`; later the booking block) | An unlabelled container with the current selection text and a Continue button, read as "Birthday & portrait, Mon 23 Nov · 09:00, Continue". | Give it a name, for example `role="group"` + `aria-label="Booking summary"`, so screen-reader users know what the text is. Keep it non-landmark, to avoid the nested-landmark issue fixed in `1c85797`. |

## 2. Acceptance checks for the WordPress front end (Phase 3e and later)

The React prototype was verified in headless Chrome only. These checks remain open and must pass on the WordPress front end before launch.

| # | Check | Scope |
|---|---|---|
| B1 | **Real devices** | iOS Safari (current and current − 1) and Android Chrome on a phone; iPad Safari at tablet width. Real touch: menu, lightbox swipe, booking calendar and time slots, the sticky mobile booking bar with the on-screen keyboard open, form auto-fill. |
| B2 | **Screen readers** | VoiceOver (iOS and macOS Safari) and NVDA (Windows, Firefox or Chrome). Check: the menu dialog; carousel announcements and the pause control; lightbox announcements; the booking steps (heading focus on each step, the error summary, the conflict alert and alternatives, the confirmation status); contact form errors and the "not switched on" / sent messages; manage-booking cancel and reschedule notices; the skip link. |
| B3 | **Reduced motion** | With the operating-system "Reduce motion" setting turned on (macOS/iOS/Windows/Android), not just emulated: the hero slideshow starts paused, scroll reveals and image zooms are off, and the menu opens without animation. The prototype was verified through its code path and CSS rules only, because the test browser couldn't emulate the setting. |
| B4 | Keyboard and focus (repeat on WordPress) | The same checks that passed on the prototype: focus trap and return in the menu and lightbox, a visible focus indicator on every stop, the skip link, booking-step heading focus, and focus kept after choosing a conflict alternative (`033f682`). |
| B5 | Automated accessibility (repeat on WordPress) | axe on every template at 375, 768 and 1440, in light and Night: zero violations, as the prototype achieved. |

These belong in the Phase 3e Playwright and axe suite where they can be automated (B4, B5), plus a manual sign-off sheet for B1-B3. See also `docs/phase-3e-integration.md` §6.

## 3. Business content (separate requirement, owner-supplied)

Tracked in `docs/content-inventory.md`, separately from design and engineering work:
- services, prices, deposits and session lengths;
- opening hours and booking or cancellation policies;
- testimonials, with consent;
- founder bio and photo; the about-page photo;
- vector logo;
- real photography for every category, and **consent from the people shown**;
- hosting, domain and the notification email.

The prototype marks every gap: placeholder frames where photos are missing, "Sample content" notices, a "Prototype" notice on booking, and a contact form that isn't switched on. None of these gaps is a defect.
