import { Link, useParams } from "react-router-dom";
import { categoryLabel } from "../data.js";
import { projects } from "../prototype/sampleContent.js";
import { usePageTitle } from "../lib/hooks.js";
import { CategoryCard, CtaBand, PageTitle, Photo, PrototypeNotice } from "../components/ui.jsx";

// Project detail template: 16:9 image (no scrim) → title below → meta badges → lead →
// rhythm of full-width, 2-up pair, text block, 3-up strip → prev/next → CTA.
export default function Project() {
  const { slug } = useParams();
  const index = projects.findIndex((p) => p.slug === slug);
  const project = projects[index];
  usePageTitle(project ? project.title : "Project not found");

  if (!project) {
    return (
      <>
        <PageTitle eyebrow="Projects" title="Project not found" lead="This project may have moved." />
        <section className="fo-section">
          <div className="fo-container">
            <Link to="/portfolio" className="fo-btn fo-btn--primary">
              View gallery
            </Link>
          </div>
        </section>
      </>
    );
  }

  const prev = projects[(index - 1 + projects.length) % projects.length];
  const next = projects[(index + 1) % projects.length];

  return (
    <>
      <article aria-labelledby="project-title">
        <div className="fo-section ish-media-band ish-media-band--top">
          <div className="fo-container">
            <Photo photo={project.cover} ratio="video" sizes="100vw" priority />
          </div>
        </div>

        <header className="fo-section ish-title ish-title--compact">
          <div className="fo-container ish-stack ish-stack--lg">
            <PrototypeNotice>This project shows the project-detail layout using existing portfolio photos.</PrototypeNotice>
            <p className="fo-eyebrow">Project</p>
            <h1 id="project-title" className="fo-h1">
              {project.title}
            </h1>
            <ul className="ish-plain ish-badges" aria-label="Project details">
              <li className="fo-badge">{categoryLabel(project.cat)}</li>
              <li className="fo-badge">{project.location}</li>
            </ul>
          </div>
        </header>

        <div className="fo-section">
          <div className="fo-container fo-container--text">
            <p className="lead">{project.lead}</p>
          </div>
        </div>

        <div className="fo-section ish-stack ish-stack--lg">
          <div className="fo-container">
            <Photo photo={project.full} ratio="wide" sizes="100vw" />
          </div>
          <div className="fo-container ish-pair">
            {project.pair.map((p) => (
              <Photo key={p.file} photo={p} ratio="portrait" />
            ))}
          </div>
          <div className="fo-container fo-container--text">
            <p className="body-lg">{project.text}</p>
          </div>
          <div className="fo-container ish-trio">
            {project.strip.map((p) => (
              <Photo key={p.file} photo={p} ratio="portrait" sizes="(min-width: 768px) 33vw, 100vw" />
            ))}
          </div>
        </div>
      </article>

      {projects.length > 1 && (
        <nav className="fo-section fo-mist" aria-labelledby="more-projects">
          <div className="fo-container">
            <div className="fo-heading">
              <p className="fo-eyebrow">More work</p>
              <h2 id="more-projects" className="fo-h2">
                Previous & next
              </h2>
            </div>
            <div className="fo-grid">
              <CategoryCard to={`/work/${prev.slug}`} photo={prev.cover} cat={prev.cat} title={prev.title} text="Previous project" />
              <CategoryCard to={`/work/${next.slug}`} photo={next.cover} cat={next.cat} title={next.title} text="Next project" />
            </div>
          </div>
        </nav>
      )}

      <CtaBand eyebrow="Your story next" title="Let's create yours" />
    </>
  );
}
