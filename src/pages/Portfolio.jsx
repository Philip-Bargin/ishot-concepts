import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { portfolio } from "../assets.js";
import { categories, categoryLabel, pad2 } from "../data.js";
import { usePageTitle } from "../lib/hooks.js";
import Gallery from "../components/Gallery.jsx";
import { CtaBand, PageTitle } from "../components/ui.jsx";

const PAGE_SIZE = 16;
const chips = [{ slug: "all", label: "All" }, ...categories];

export default function Portfolio() {
  const { category } = useParams();
  const navigate = useNavigate();
  const active = categories.some((c) => c.slug === category) ? category : "all";
  const [shown, setShown] = useState(PAGE_SIZE);
  usePageTitle(active === "all" ? "Portfolio" : `${categoryLabel(active)} · Portfolio`);

  useEffect(() => setShown(PAGE_SIZE), [active]);

  const filtered = active === "all" ? portfolio : portfolio.filter((p) => p.cat === active);
  const visible = filtered.slice(0, shown);
  const choose = (slug) => navigate(slug === "all" ? "/portfolio" : `/portfolio/${slug}`, { replace: true });

  return (
    <>
      <PageTitle compact eyebrow="Our work" title="Portfolio" />

      <section className="fo-section" aria-labelledby="gallery-title">
        <div className="fo-container">
          <h2 id="gallery-title" className="fo-sr">
            {active === "all" ? "All work" : categoryLabel(active)}
          </h2>
          <div className="fo-filters ish-filters" role="group" aria-label="Filter by category">
            {chips.map((c) => (
              <button key={c.slug} type="button" className="fo-chip" aria-pressed={active === c.slug} onClick={() => choose(c.slug)}>
                {c.label}
              </button>
            ))}
          </div>

          {visible.length ? (
            <Gallery key={active} items={visible} className="ish-masonry--page" />
          ) : (
            <div className="ish-empty">
              <p className="body-lg">No work in this category yet.</p>
              <button type="button" className="fo-btn fo-btn--text" onClick={() => choose("all")}>
                See all work
              </button>
            </div>
          )}

          <div className="ish-count">
            <p className="small fo-muted" aria-live="polite">
              Showing {pad2(visible.length)} / {pad2(filtered.length)}
            </p>
            {shown < filtered.length && (
              <button type="button" className="fo-btn fo-btn--secondary" onClick={() => setShown((n) => n + PAGE_SIZE)}>
                Load more
              </button>
            )}
          </div>
        </div>
      </section>

      <CtaBand title="Picture yourself here" text="Every portfolio frame started with a booking." />
    </>
  );
}
