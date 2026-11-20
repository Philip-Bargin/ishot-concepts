import { useState } from "react";
import { Link } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import { journalCategories, posts } from "../prototype/sampleContent.js";
import { usePageTitle } from "../lib/hooks.js";
import Icon from "../components/Icon.jsx";
import { BlogCard, PageTitle, Photo, PrototypeNotice, formatPostDate } from "../components/ui.jsx";

function Newsletter() {
  const [email, setEmail] = useState("");
  const [done, setDone] = useState(false);
  return (
    <section className="fo-section fo-mist" aria-labelledby="newsletter-title">
      <div className="fo-container fo-container--text ish-stack ish-stack--lg">
        <p className="fo-eyebrow">Newsletter</p>
        <h2 id="newsletter-title" className="fo-h2">
          New stories, now and then
        </h2>
        {done ? (
          <p className="body" role="status">
            Prototype: nothing was sent. Newsletter sign-up arrives in a later phase.
          </p>
        ) : (
          <form
            className="ish-stack"
            onSubmit={(e) => {
              e.preventDefault();
              if (e.currentTarget.checkValidity()) setDone(true);
            }}
          >
            <label className="fo-label" htmlFor="nl-email">
              Email address
            </label>
            <div className="fo-newsletter">
              <input id="nl-email" className="fo-input" type="email" required autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} />
              <button type="submit" className="fo-btn fo-btn--primary">
                Subscribe
              </button>
            </div>
            <p className="fo-help">One email when there's something worth reading. Unsubscribe any time.</p>
          </form>
        )}
      </div>
    </section>
  );
}

export default function Journal() {
  usePageTitle("Journal");
  const [category, setCategory] = useState("All");
  const [featured, ...rest] = posts;
  const list = category === "All" ? rest : posts.filter((p) => p.category === category);

  return (
    <>
      <PageTitle eyebrow="Journal" title="Stories & guides" lead="Planning tips, behind-the-scenes notes and the stories behind our favourite frames." />

      <section className="fo-section" aria-labelledby="featured-title">
        <div className="fo-container ish-stack ish-stack--lg">
          <PrototypeNotice>These posts show how the journal looks. Real posts are written in WordPress in Phase 3.</PrototypeNotice>
          <article className="ish-featured">
            <Photo photo={featured.photo} ratio="blog" sizes="(min-width: 1024px) 58vw, 100vw" alt={featured.photo.alt} />
            <div className="ish-stack ish-stack--lg">
              <p className="fo-card__meta">
                <span className="fo-eyebrow">{featured.category}</span>
                <span aria-hidden="true">·</span>
                <span>{featured.readMins} min read</span>
              </p>
              <h2 id="featured-title" className="fo-h2">
                {featured.title}
              </h2>
              <p className="body-lg fo-muted">{featured.excerpt}</p>
              <time className="small fo-muted" dateTime={featured.date}>
                {formatPostDate(featured.date)}
              </time>
              <Link to={`/journal/${featured.slug}`} className="fo-btn fo-btn--text">
                Read the guide <Icon as={ArrowRight} />
                <span className="fo-sr">: {featured.title}</span>
              </Link>
            </div>
          </article>
        </div>
      </section>

      <section className="fo-section" aria-labelledby="posts-title">
        <div className="fo-container">
          <h2 id="posts-title" className="fo-sr">
            All posts
          </h2>
          <div className="fo-filters" role="group" aria-label="Filter posts by category">
            {["All", ...journalCategories].map((c) => (
              <button key={c} type="button" className="fo-chip" aria-pressed={category === c} onClick={() => setCategory(c)}>
                {c}
              </button>
            ))}
          </div>
          <div className="fo-grid ish-grid-3">
            {list.map((p) => (
              <BlogCard key={p.slug} post={p} />
            ))}
          </div>
        </div>
      </section>

      <Newsletter />
    </>
  );
}
