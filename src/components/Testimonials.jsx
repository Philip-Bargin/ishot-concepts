import { useRef } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import Icon from "./Icon.jsx";
import { PrototypeNotice, SectionHeading } from "./ui.jsx";

/**
 * Design-system Testimonial section: band ground, heading row, three Quote cards
 * (a swipe row with previous/next on mobile, a 3-column grid from 768px).
 * Renders nothing with fewer than 3 items, matching the WordPress rule.
 * Placeholder items are styled as such and carry a visible notice; no stars are shown
 * because no real ratings exist.
 */
export default function Testimonials({ items }) {
  const track = useRef(null);
  if (!items || items.length < 3) return null;
  const isPlaceholder = items.some((t) => t.placeholder);

  const step = (dir) => {
    const el = track.current;
    if (!el) return;
    const card = el.firstElementChild;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    el.scrollBy({ left: dir * (card ? card.getBoundingClientRect().width : el.clientWidth), behavior: reduce ? "auto" : "smooth" });
  };

  return (
    <section className="fo-section fo-band" aria-labelledby="testimonials-title">
      <div className="fo-container">
        <SectionHeading
          eyebrow="Kind words"
          title="What clients say"
          id="testimonials-title"
          action={
            <div className="ish-actions ish-quotes__nav">
              <button type="button" className="fo-icon-btn" aria-controls="testimonial-track" aria-label="Previous testimonial" onClick={() => step(-1)}>
                <Icon as={ChevronLeft} />
              </button>
              <button type="button" className="fo-icon-btn" aria-controls="testimonial-track" aria-label="Next testimonial" onClick={() => step(1)}>
                <Icon as={ChevronRight} />
              </button>
            </div>
          }
        />

        {/* Scrolls sideways on mobile, so it must be keyboard-focusable (WCAG 2.1.1). */}
        <div id="testimonial-track" ref={track} className="ish-quotes" role="region" aria-label="Testimonials, scroll sideways on small screens" tabIndex={0}>
          {items.map((t) => (
            <figure key={t.id} className={`fo-quote${t.placeholder ? " ish-placeholder" : ""}`}>
              <blockquote>{t.quote}</blockquote>
              <figcaption>
                <span className="fo-avatar" aria-hidden="true" />
                <span className="ish-quote__meta">
                  {t.name}
                  <small>{t.session}</small>
                </span>
              </figcaption>
            </figure>
          ))}
        </div>

        {isPlaceholder && (
          <PrototypeNotice label="Placeholder testimonials." className="ish-notice">
            None of these are real reviews. Approved client quotes, with each client's consent, replace them before launch.
          </PrototypeNotice>
        )}
      </div>
    </section>
  );
}
