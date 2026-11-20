import { Link } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { photos, portfolio } from "../assets.js";
import { categories, contact, promises, services, stats } from "../data.js";
import { usePageTitle } from "../lib/hooks.js";
import HeroSlideshow from "../components/HeroSlideshow.jsx";
import Gallery from "../components/Gallery.jsx";
import Reveal from "../components/Reveal.jsx";
import Icon from "../components/Icon.jsx";
import { CategoryCard, CtaBand, FeatureList, Marquee, Photo, SectionHeading, ServiceTiles, Stats } from "../components/ui.jsx";

const slides = [photos.phoneBirthday, photos.gownStair, photos.graduate, photos.denimSmile];
// About strip: 1:2 · 1:2 · 2:3 (centre) · 1:2 · 1:2. Decorative here (each photo is described in Recent work).
const strip = [
  [photos.denimSmile, "strip"],
  [photos.burgundyLounge, "strip"],
  [photos.graduate, "tall"],
  [photos.redFurSofa, "strip"],
  [photos.gownStair, "strip"],
];
const featuredCategories = [
  { slug: "portraits", photo: photos.burgundyLounge },
  { slug: "creative", photo: photos.phoneBirthday },
  { slug: "studio", photo: photos.graduate },
  { slug: "outdoor", photo: photos.denimSmile },
].map((c) => ({ ...c, ...categories.find((k) => k.slug === c.slug) }));

export default function Home() {
  usePageTitle(null);

  return (
    <>
      <HeroSlideshow slides={slides}>
        <p className="fo-eyebrow">Monrovia · Liberia</p>
        <h1 className="fo-hero__title">
          <span className="ish-hero__line">iShot</span> <span className="ish-hero__line">Concept</span>
        </h1>
        <div className="fo-hero__row">
          <p className="small ish-hero__tagline">Premium photography that turns your moments into timeless visual stories.</p>
          <div className="ish-actions">
            <Link to="/book" className="fo-btn fo-btn--primary">
              Book a session
            </Link>
            <Link to="/portfolio" className="fo-btn fo-btn--outline">
              View gallery
            </Link>
          </div>
        </div>
      </HeroSlideshow>

      {/* Statement / about */}
      <section className="fo-section fo-section--tall" aria-labelledby="statement-title">
        <div className="fo-container">
          <div className="ish-stack ish-center ish-measure">
            <p className="fo-eyebrow">Who we are</p>
            <h2 id="statement-title" className="fo-statement">
              Your moments, made timeless
            </h2>
            <p className="body-lg fo-muted">
              iShot Concept captures emotion, identity and legacy, from intimate portraits to bold creative concepts. Every
              frame is crafted with intention, precision and a distinctly African creative excellence.
            </p>
            <Link to="/about" className="fo-btn fo-btn--text">
              Read our story <Icon as={ArrowRight} />
            </Link>
          </div>
          <div className="ish-strip">
            {strip.map(([photo, ratio]) => (
              <Photo key={photo.file} photo={photo} ratio={ratio} alt="" sizes="(min-width: 768px) 20vw, 42vw" />
            ))}
          </div>
          <Stats items={stats} />
        </div>
      </section>

      {/* Services */}
      <section className="fo-section fo-band" aria-labelledby="services-title">
        <div className="fo-container">
          <SectionHeading
            eyebrow="What we do"
            title="Our services"
            id="services-title"
            action={
              <Link to="/book" className="fo-btn fo-btn--primary">
                Book now
              </Link>
            }
          />
          <ServiceTiles items={services.slice(0, 4)} />
        </div>
      </section>

      {/* Categories */}
      <section className="fo-section" aria-labelledby="categories-title">
        <div className="fo-container">
          <SectionHeading
            eyebrow="Portfolio"
            title="Explore the work"
            id="categories-title"
            action={
              <Link to="/portfolio" className="fo-btn fo-btn--text">
                View all <Icon as={ArrowRight} />
              </Link>
            }
          />
          <Reveal className="fo-grid">
            {featuredCategories.map((c) => (
              <CategoryCard key={c.slug} to={`/portfolio/${c.slug}`} photo={c.photo} cat={c.slug} title={c.label} text={c.blurb} />
            ))}
          </Reveal>
        </div>
      </section>

      {/* Recent work */}
      <section className="fo-section fo-mist" aria-labelledby="recent-title">
        <div className="fo-container">
          <SectionHeading
            eyebrow="Recent work"
            title="Selected frames"
            id="recent-title"
            action={
              <div className="ish-actions">
                <Link to="/portfolio" className="fo-btn fo-btn--text">
                  Full portfolio <Icon as={ArrowRight} />
                </Link>
                <a href={contact.instagramUrl} target="_blank" rel="noopener noreferrer" className="fo-btn fo-btn--text">
                  Instagram <span aria-hidden="true">↗</span>
                  <span className="fo-sr"> (opens in a new tab)</span>
                </a>
              </div>
            }
          />
          <Gallery items={portfolio} />
        </div>
      </section>

      {/* Why us */}
      <section className="fo-section" aria-labelledby="why-title">
        <div className="fo-container ish-split">
          <Reveal>
            <SectionHeading eyebrow="The iShot promise" title="Why clients choose us" id="why-title" />
            <FeatureList items={promises} />
          </Reveal>
          <Photo photo={photos.redFurSofa} ratio="portrait" alt="" />
        </div>
      </section>

      <CtaBand />
      <Marquee words={categories.map((c) => c.label)} />
    </>
  );
}
