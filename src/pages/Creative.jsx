import { Link } from "react-router-dom";
import { photos, srcFor, srcSetFor } from "../assets.js";
import { process } from "../data.js";
import { usePageTitle } from "../lib/hooks.js";
import Reveal from "../components/Reveal.jsx";
import { CtaBand, FeatureList, OverlayCard, Photo, SectionHeading } from "../components/ui.jsx";

const hero = photos.gownStair;

export default function Creative() {
  usePageTitle("Creative direction");

  return (
    <>
      {/* Hero variant 2: static image, display-xl title */}
      <section className="fo-hero ish-on-photo">
        <div className="fo-hero__media">
          <img
            className="ish-cover"
            src={srcFor(hero, 1600)}
            srcSet={srcSetFor(hero)}
            sizes="100vw"
            alt={hero.alt}
            fetchPriority="high"
            style={{ objectPosition: hero.focus }}
          />
        </div>
        <div className="fo-hero__body">
          <p className="fo-eyebrow">Creative direction</p>
          <h1 className="ish-display-xl">Ideas, directed into images</h1>
          <div className="fo-hero__row">
            <p className="small ish-hero__tagline">
              From mood and styling to set and story, we direct the whole creative so every frame is intentional.
            </p>
            <Link to="/book?service=s4" className="fo-btn fo-btn--primary">
              Start a concept
            </Link>
          </div>
        </div>
      </section>

      <section className="fo-section" aria-labelledby="process-title">
        <div className="fo-container ish-split">
          <Reveal>
            <SectionHeading eyebrow="How we work" title="Our process" id="process-title" />
            <FeatureList items={process} />
          </Reveal>
          <Photo photo={photos.phoneBirthday} ratio="portrait" alt="" />
        </div>
      </section>

      <section className="fo-section fo-mist" aria-labelledby="concepts-title">
        <div className="fo-container">
          <SectionHeading eyebrow="Selected" title="Featured concepts" id="concepts-title" />
          <div className="fo-grid">
            <OverlayCard to="/portfolio/creative" photo={photos.phoneBirthday} cat="creative" title="Birthday call concept" />
            <OverlayCard to="/portfolio/creative" photo={photos.gownStair} cat="creative" title="Evening gown editorial" />
          </div>
        </div>
      </section>

      <CtaBand eyebrow="Your idea, our direction" title="Have a concept in mind?" text="" to="/book?service=s4" label="Start a concept" />
    </>
  );
}
