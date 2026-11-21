import { useEffect, useState } from "react";
import { Pause, Play } from "lucide-react";
import { srcFor, srcSetFor } from "../assets.js";
import { prefersReducedMotion } from "../lib/theme.js";
import Icon from "./Icon.jsx";

const INTERVAL = 6000;
const MAX_RUNTIME = 5 * 60 * 1000;

/**
 * Hero variant 1 (slideshow): cross-fades every 6s, pauses on hover/focus, has a pause
 * button and dots, stops after 5 minutes, and never auto-advances under reduced motion.
 * Only the active slide is exposed to assistive tech; nothing is announced on auto-advance.
 */
export default function HeroSlideshow({ slides, label = "Featured work", children }) {
  const [index, setIndex] = useState(0);
  const [playing, setPlaying] = useState(() => !prefersReducedMotion());
  const [held, setHeld] = useState(false);

  useEffect(() => {
    if (!playing || held || slides.length < 2) return undefined;
    const t = setInterval(() => setIndex((i) => (i + 1) % slides.length), INTERVAL);
    return () => clearInterval(t);
  }, [playing, held, slides.length]);

  useEffect(() => {
    const t = setTimeout(() => setPlaying(false), MAX_RUNTIME);
    return () => clearTimeout(t);
  }, []);

  return (
    <section
      className="fo-hero ish-on-photo"
      aria-roledescription="carousel"
      aria-label={label}
      onMouseEnter={() => setHeld(true)}
      onMouseLeave={() => setHeld(false)}
      onFocus={() => setHeld(true)}
      onBlur={(e) => !e.currentTarget.contains(e.relatedTarget) && setHeld(false)}
    >
      <div className="fo-hero__media">
        {slides.map((photo, i) => (
          <div
            key={photo.file}
            className={`ish-slide${i === index ? " is-active" : ""}`}
            role="group"
            aria-roledescription="slide"
            aria-label={`${i + 1} of ${slides.length}`}
            aria-hidden={i !== index}
          >
            <img
              className="ish-cover"
              src={srcFor(photo, 1600)}
              srcSet={srcSetFor(photo)}
              sizes="100vw"
              alt={photo.alt}
              loading={i === 0 ? "eager" : "lazy"}
              fetchPriority={i === 0 ? "high" : undefined}
              decoding="async"
              style={{ objectPosition: photo.focus }}
            />
          </div>
        ))}
      </div>

      <div className="fo-hero__body">
        <div className="fo-container ish-hero__inner">
          {children}
          {slides.length > 1 && (
            <div className="ish-hero__controls">
              <button
                type="button"
                className="fo-icon-btn"
                onClick={() => setPlaying((p) => !p)}
                aria-label={playing ? "Pause slideshow" : "Play slideshow"}
              >
                <Icon as={playing ? Pause : Play} />
              </button>
              <div className="fo-dots" role="group" aria-label="Choose a slide">
                {slides.map((photo, i) => (
                  <button
                    key={photo.file}
                    type="button"
                    aria-label={`Show slide ${i + 1}`}
                    aria-current={i === index ? "true" : undefined}
                    onClick={() => setIndex(i)}
                  />
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
