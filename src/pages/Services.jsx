import { Link } from "react-router-dom";
import { photos } from "../assets.js";
import { pad2, process, services } from "../data.js";
import { usePageTitle } from "../lib/hooks.js";
import Reveal from "../components/Reveal.jsx";
import { CtaBand, FeatureList, PageTitle, Photo, Placeholder, SectionHeading, ServiceTiles } from "../components/ui.jsx";

export default function Services() {
  usePageTitle("Services");

  return (
    <>
      <PageTitle eyebrow="What we do" title="Our services" lead="Six ways we tell your story, each one planned around you." />

      <section className="fo-section fo-band" aria-label="Featured services">
        <div className="fo-container">
          <ServiceTiles items={services.slice(0, 4)} />
        </div>
      </section>

      <section className="fo-section" aria-labelledby="details-title">
        <div className="fo-container fo-container--content">
          <h2 id="details-title" className="fo-sr">
            Service details
          </h2>
          {services.map((s, i) => (
            <article key={s.id} id={s.id} className={`ish-detail ish-split${i % 2 ? " ish-split--reverse" : ""}`} aria-labelledby={`${s.id}-title`}>
              {s.photo ? <Photo photo={photos[s.photo]} ratio="portrait" /> : <Placeholder ratio="portrait" />}
              <div className="ish-stack ish-stack--lg">
                <p className="fo-eyebrow ish-detail__eyebrow">
                  <span className="fo-numeral">{pad2(i + 1)}</span> {s.tag}
                </p>
                <span className="fo-catbar" data-cat={s.cat} aria-hidden="true" />
                <h3 id={`${s.id}-title`} className="ish-h3">
                  {s.title}
                </h3>
                <p className="body-lg fo-muted">{s.long}</p>
                <div className="ish-actions">
                  <Link to={`/book?service=${s.id}`} className="fo-btn fo-btn--secondary">
                    Book this
                  </Link>
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>

      <section className="fo-section fo-mist" aria-labelledby="process-title">
        <div className="fo-container fo-container--content">
          <Reveal>
            <SectionHeading eyebrow="How we work" title="Our process" id="process-title" />
            <FeatureList items={process} />
          </Reveal>
        </div>
      </section>

      <CtaBand
        eyebrow="Not sure which fits?"
        title="Let's shape your concept"
        text="Tell us your idea and we'll plan a session around it."
        to="/contact"
        label="Start a concept"
      />
    </>
  );
}
