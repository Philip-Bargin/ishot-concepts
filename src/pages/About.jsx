import { photos } from "../assets.js";
import { stats, values } from "../data.js";
import { usePageTitle } from "../lib/hooks.js";
import Reveal from "../components/Reveal.jsx";
import { CtaBand, FeatureList, PageTitle, Photo, SectionHeading, Stats } from "../components/ui.jsx";

export default function About() {
  usePageTitle("About");

  return (
    <>
      <PageTitle
        eyebrow="Our story"
        title="Rooted in Monrovia, made to last"
        lead="A premium photography brand capturing timeless moments with creativity, precision and style."
      />

      <div className="fo-section ish-media-band">
        <div className="fo-container">
          <Photo photo={photos.redFurSofa} ratio="wide" sizes="100vw" priority />
        </div>
      </div>

      <section className="fo-section fo-section--tall" aria-labelledby="story-title">
        <div className="fo-container fo-container--content ish-split">
          <div className="ish-stack ish-stack--lg">
            <p className="fo-eyebrow">Who we are</p>
            <h2 id="story-title" className="fo-h2">
              Capturing emotion, identity & legacy
            </h2>
            <p className="body-lg">
              iShot Concept is a premium photography brand based in Monrovia. We believe your moments deserve more than just
              pictures: they deserve to be remembered, felt and celebrated.
            </p>
            <p className="body-lg fo-muted">
              From birthdays and portraits to weddings, corporate brands and bold creative concepts, we bring an editorial eye
              and cinematic craft to every frame.
            </p>
          </div>
          <Photo photo={photos.burgundyLounge} ratio="tall" />
        </div>
        <div className="fo-container fo-container--content">
          <Stats items={stats} />
        </div>
      </section>

      <section className="fo-section fo-mist" aria-labelledby="values-title">
        <div className="fo-container fo-container--content">
          <Reveal>
            <SectionHeading eyebrow="What guides us" title="Vision & mission" id="values-title" />
            <FeatureList items={values} />
          </Reveal>
        </div>
      </section>

      <CtaBand title="Ready to tell your story?" text="" />
    </>
  );
}
