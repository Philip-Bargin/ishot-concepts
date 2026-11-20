import { useState } from "react";
import { Link, useParams } from "react-router-dom";
import { Link2, Mail, MessageCircle } from "lucide-react";
import { posts } from "../prototype/sampleContent.js";
import { usePageTitle } from "../lib/hooks.js";
import Icon from "../components/Icon.jsx";
import { BlogCard, CtaBand, PageTitle, Photo, PrototypeNotice, formatPostDate } from "../components/ui.jsx";

function Body({ blocks }) {
  return blocks.map((b, i) => {
    if (b.h) return <h2 key={i}>{b.h}</h2>;
    if (b.quote) return <blockquote key={i}>{b.quote}</blockquote>;
    if (b.list)
      return (
        <ul key={i} className="ish-prose-list">
          {b.list.map((item) => (
            <li key={item}>{item}</li>
          ))}
        </ul>
      );
    return <p key={i}>{b.p}</p>;
  });
}

function Share({ title }) {
  const [copied, setCopied] = useState(false);
  const url = typeof window !== "undefined" ? window.location.href : "";
  const copy = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
    } catch {
      setCopied(false);
    }
  };
  return (
    <div className="ish-stack">
      <p className="fo-eyebrow">Share</p>
      <div className="ish-social">
        <a className="fo-icon-btn" href={`https://wa.me/?text=${encodeURIComponent(`${title} ${url}`)}`} target="_blank" rel="noopener noreferrer" aria-label="Share on WhatsApp (opens in a new tab)">
          <Icon as={MessageCircle} />
        </a>
        <a className="fo-icon-btn" href={`mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}`} aria-label="Share by email">
          <Icon as={Mail} />
        </a>
        <button type="button" className="fo-icon-btn" onClick={copy} aria-label="Copy link">
          <Icon as={Link2} />
        </button>
      </div>
      <p className="small fo-muted" role="status">
        {copied ? "Link copied." : ""}
      </p>
    </div>
  );
}

export default function Article() {
  const { slug } = useParams();
  const post = posts.find((p) => p.slug === slug);
  usePageTitle(post ? post.title : "Post not found");

  if (!post) {
    return (
      <>
        <PageTitle eyebrow="Journal" title="Post not found" lead="This post may have moved." />
        <section className="fo-section">
          <div className="fo-container">
            <Link to="/journal" className="fo-btn fo-btn--primary">
              All posts
            </Link>
          </div>
        </section>
      </>
    );
  }

  const related = posts.filter((p) => p.slug !== post.slug).slice(0, 3);

  return (
    <>
      <article aria-labelledby="article-title">
        <header className="fo-section ish-title">
          <div className="fo-container fo-container--text ish-stack ish-stack--lg">
            <PrototypeNotice>This post demonstrates the article template.</PrototypeNotice>
            <p className="fo-eyebrow">
              {post.category} · {post.readMins} min read
            </p>
            <h1 id="article-title" className="fo-h1">
              {post.title}
            </h1>
            <div className="ish-byline">
              <span className="ish-avatar-initials" aria-hidden="true">
                iS
              </span>
              <span className="small">
                <strong>iShot Concept studio</strong>
                <br />
                <time dateTime={post.date}>{formatPostDate(post.date)}</time>
              </span>
            </div>
          </div>
        </header>

        <div className="fo-section ish-media-band">
          <div className="fo-container fo-container--content">
            <Photo photo={post.photo} ratio="wide" sizes="(min-width: 1152px) 1152px, 100vw" priority />
          </div>
        </div>

        <div className="fo-section">
          <div className="fo-container fo-container--text ish-stack ish-stack--lg">
            <p className="lead">{post.excerpt}</p>
            <div className="fo-prose">
              <Body blocks={post.body} />
            </div>
            <Share title={post.title} />
          </div>
        </div>
      </article>

      <section className="fo-section fo-mist" aria-labelledby="related-title">
        <div className="fo-container">
          <div className="fo-heading">
            <p className="fo-eyebrow">Keep reading</p>
            <h2 id="related-title" className="fo-h2">
              Related posts
            </h2>
          </div>
          <div className="fo-grid ish-grid-3">
            {related.map((p) => (
              <BlogCard key={p.slug} post={p} />
            ))}
          </div>
        </div>
      </section>

      <CtaBand />
    </>
  );
}
