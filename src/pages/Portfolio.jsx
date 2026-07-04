import { useState, useMemo, useCallback } from "react";
import { filters, portfolio } from "../data.js";
import { images } from "../assets.js";
import { PageHero } from "../components/ui.jsx";
import Reveal from "../components/Reveal.jsx";
import Lightbox from "../components/Lightbox.jsx";

export default function Portfolio() {
  const [filter, setFilter] = useState("All");
  const [lightbox, setLightbox] = useState(null); // index into `filtered`

  const filtered = useMemo(
    () => (filter === "All" ? portfolio : portfolio.filter((p) => p.cat === filter)),
    [filter]
  );

  const changeFilter = (f) => {
    setFilter(f);
    setLightbox(null);
  };

  const next = useCallback(
    () => setLightbox((i) => (i == null ? i : (i + 1) % filtered.length)),
    [filtered.length]
  );
  const prev = useCallback(
    () => setLightbox((i) => (i == null ? i : (i - 1 + filtered.length) % filtered.length)),
    [filtered.length]
  );

  return (
    <div className="page-enter">
      <PageHero
        eyebrow="Our Work"
        title="Visual Stories"
        subtitle="A curated look at moments we've captured and concepts we've created."
        bg={images.portfolioHero}
      />

      <section
        className="container"
        style={{ paddingTop: "clamp(44px,6vw,80px)", paddingBottom: "clamp(56px,8vw,104px)" }}
      >
        {/* filter pills */}
        <Reveal
          role="group"
          aria-label="Filter portfolio by category"
          style={{ display: "flex", flexWrap: "wrap", gap: 10, justifyContent: "center", marginBottom: 40 }}
        >
          {filters.map((f) => {
            const active = filter === f;
            return (
              <button
                key={f}
                className="filter-pill"
                aria-pressed={active}
                onClick={() => changeFilter(f)}
                style={{
                  background: active ? "#F26A22" : "#FFFFFF",
                  color: active ? "#FFFFFF" : "var(--ink)",
                  border: `1.5px solid ${active ? "#F26A22" : "var(--border)"}`,
                }}
              >
                {f}
              </button>
            );
          })}
        </Reveal>

        {/* masonry grid */}
        <div className="masonry">
          {filtered.map((p, i) => (
            <Reveal
              key={`${p.img}-${i}`}
              as="button"
              delay={Math.min(i, 6) * 60}
              className="masonry-item"
              aria-label={`View ${p.cat} photograph`}
              onClick={() => setLightbox(i)}
            >
              <img src={p.img} loading="lazy" alt={`${p.cat} photography by iShot Concept`} />
              <span className="plus" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                  <path d="M12 5v14M5 12h14" />
                </svg>
              </span>
              <span className="overlay">
                <span className="cap">{p.cat}</span>
              </span>
            </Reveal>
          ))}
        </div>
      </section>

      {lightbox != null && (
        <Lightbox
          item={filtered[lightbox]}
          index={lightbox}
          total={filtered.length}
          onClose={() => setLightbox(null)}
          onNext={next}
          onPrev={prev}
        />
      )}
    </div>
  );
}
