// Thin React wrappers that emit the design system's exact `fo-` markup.
import { Fragment, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Briefcase, Camera, CircleAlert, CircleCheck, CircleX, Clock, Heart, Info, Pause, Play, Sparkles, Sun, Video } from "lucide-react";
import { srcFor, srcSetFor } from "../assets.js";
import { pad2 } from "../data.js";
import Icon from "./Icon.jsx";

const RATIO_LABEL = { portrait: "4:5", landscape: "4:3", square: "1:1", tall: "2:3", strip: "1:2", wide: "21:9" };

/** Responsive photo in a fixed-ratio slot (no layout shift). Pass alt="" for decorative repeats. */
export function Photo({ photo, ratio = "portrait", alt, sizes = "(min-width: 768px) 50vw, 100vw", priority = false, className = "" }) {
  return (
    <span className={`fo-img ish-r-${ratio}${className ? ` ${className}` : ""}`}>
      <img
        src={srcFor(photo)}
        srcSet={srcSetFor(photo)}
        sizes={sizes}
        alt={alt ?? photo.alt}
        loading={priority ? "eager" : "lazy"}
        fetchPriority={priority ? "high" : undefined}
        decoding="async"
        style={{ objectPosition: photo.focus }}
      />
    </span>
  );
}

/** Design-system placeholder slot: shown where real photography is still to come. */
export function Placeholder({ ratio = "portrait" }) {
  return <span className={`fo-img fo-img--ph ish-r-${ratio}`} data-ratio={RATIO_LABEL[ratio]} aria-hidden="true" />;
}

export function Spectrum({ full = false }) {
  return (
    <span className={`fo-spectrum${full ? " fo-spectrum--full" : ""}`} aria-hidden="true">
      <i />
      <i />
      <i />
      <i />
    </span>
  );
}

/** Eyebrow + uppercase title, with an optional action aligned right (e.g. VIEW ALL →). */
export function SectionHeading({ eyebrow, title, id, action, lead, center = false }) {
  return (
    <div className={`fo-heading${center ? " ish-center" : ""}`}>
      <div className="fo-heading__row">
        <div className="ish-stack">
          <p className="fo-eyebrow">{eyebrow}</p>
          <h2 id={id} className="fo-h2">
            {title}
          </h2>
        </div>
        {action}
      </div>
      {lead && <p className="body-lg fo-muted ish-measure">{lead}</p>}
    </div>
  );
}

/** Type-only page title (Hero variant 4): eyebrow + h1 + lead on paper. */
export function PageTitle({ eyebrow, title, lead, compact = false }) {
  return (
    <section className={`fo-section ish-title${compact ? " ish-title--compact" : ""}`}>
      <div className="fo-container ish-stack">
        <p className="fo-eyebrow">{eyebrow}</p>
        <h1 className="fo-h1">{title}</h1>
        {lead && <p className="lead fo-muted ish-measure">{lead}</p>}
      </div>
    </section>
  );
}

export function CtaBand({
  eyebrow = "Ready when you are",
  title = "Let's create that magic",
  text = "Your story is waiting to be told. Let's make it unforgettable.",
  to = "/book",
  label = "Book a session",
}) {
  return (
    <section className="fo-section fo-section--tall fo-band" aria-labelledby="cta-title">
      <div className="fo-container fo-container--text fo-cta">
        <Spectrum />
        <p className="fo-eyebrow">{eyebrow}</p>
        <h2 id="cta-title" className="fo-h2">
          {title}
        </h2>
        {text && <p className="lead">{text}</p>}
        <Link to={to} className="fo-btn fo-btn--primary fo-btn--lg">
          {label}
        </Link>
      </div>
    </section>
  );
}

const SERVICE_ICONS = { camera: Camera, heart: Heart, briefcase: Briefcase, sparkles: Sparkles, video: Video, sun: Sun };

/** Numbered service entry points on a band, each with its aperture-colour top bar. */
export function ServiceTiles({ items }) {
  return (
    <div className="fo-tiles">
      {items.map((s, i) => (
        <Link key={s.id} className="fo-tile" data-cat={s.cat} to={`/services#${s.id}`}>
          <span className="fo-numeral">{pad2(i + 1)}</span>
          <span className="fo-tile__icon">
            <Icon as={SERVICE_ICONS[s.icon] ?? Camera} size={24} />
          </span>
          <span className="fo-tile__label">{s.short}</span>
        </Link>
      ))}
    </div>
  );
}

export function CategoryCard({ to, photo, cat, title, text, ratio = "landscape", sizes = "(min-width: 768px) 50vw, 100vw" }) {
  return (
    <Link className="fo-card" to={to}>
      {photo ? <Photo photo={photo} ratio={ratio} sizes={sizes} /> : <Placeholder ratio={ratio} />}
      <span className="fo-catbar" data-cat={cat} aria-hidden="true" />
      <h3 className="fo-card__title">{title}</h3>
      {text && <p className="fo-card__text">{text}</p>}
    </Link>
  );
}

/** Overlay card variant: title over the image on a scrim. */
export function OverlayCard({ to, photo, cat, title, sizes = "(min-width: 768px) 50vw, 100vw" }) {
  return (
    <Link className="fo-card" to={to}>
      <span className="fo-img ish-r-portrait">
        <img src={srcFor(photo)} srcSet={srcSetFor(photo)} sizes={sizes} alt={photo.alt} loading="lazy" decoding="async" style={{ objectPosition: photo.focus }} />
        <span className="fo-card__overlay ish-stack">
          <span className="fo-catbar" data-cat={cat} aria-hidden="true" />
          <span className="fo-card__title">{title}</span>
        </span>
      </span>
    </Link>
  );
}

export function FeatureList({ items }) {
  return (
    <ol className="fo-features ish-plain">
      {items.map((f, i) => (
        <li key={f.title} className="fo-feature">
          <span className="fo-numeral">{pad2(i + 1)}</span>
          <h3 className="fo-feature__title">{f.title}</h3>
          <p className="fo-feature__text">{f.text}</p>
        </li>
      ))}
    </ol>
  );
}

export function Stats({ items }) {
  return (
    <dl className="fo-stats ish-stats" style={{ "--n": items.length }}>
      {items.map((s) => (
        <div key={s.label} className="fo-stat">
          <dt className="fo-stat__label">{s.label}</dt>
          <dd className="fo-stat__value">{s.value}</dd>
        </div>
      ))}
    </dl>
  );
}

/** Kinetic word row. Has a pause control and stops itself after 5 minutes (WCAG 2.2.2). */
export function Marquee({ words, label = "Categories we shoot" }) {
  const [paused, setPaused] = useState(false);
  useEffect(() => {
    const t = setTimeout(() => setPaused(true), 5 * 60 * 1000);
    return () => clearTimeout(t);
  }, []);
  const run = [...words, ...words];
  return (
    <section className={`fo-marquee${paused ? " is-paused" : ""}`} aria-label={label}>
      <p className="fo-sr">{words.join(", ")}</p>
      <div className="fo-marquee__track" aria-hidden="true">
        {run.map((w, i) => (
          <Fragment key={i}>
            <span>{w}</span>
            <span aria-hidden="true">●</span>
          </Fragment>
        ))}
      </div>
      <button
        type="button"
        className="fo-icon-btn ish-marquee__toggle"
        onClick={() => setPaused((p) => !p)}
        aria-label={paused ? "Play scrolling categories" : "Pause scrolling categories"}
      >
        <Icon as={paused ? Play : Pause} />
      </button>
    </section>
  );
}

/**
 * FormField: visible label, required marker, help and error linked via aria-describedby.
 * `children` is a render function receiving the props to spread on the control.
 */
export function Field({ id, label, required = false, help, error, children }) {
  const describedBy = [help && `${id}-help`, error && `${id}-error`].filter(Boolean).join(" ") || undefined;
  return (
    <div className="fo-field">
      <label className="fo-label" htmlFor={id}>
        {label}
        {required && (
          <span className="fo-req" aria-hidden="true">
            {" "}
            *
          </span>
        )}
      </label>
      {children({
        id,
        "aria-describedby": describedBy,
        "aria-invalid": error ? true : undefined,
        "aria-required": required || undefined,
      })}
      {help && (
        <p id={`${id}-help`} className="fo-help">
          {help}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="fo-error">
          <Icon as={CircleAlert} />
          {error}
        </p>
      )}
    </div>
  );
}

/** Error summary shown on submit; focus moves here and each item links to its field. */
export function ErrorSummary({ errors, labels, innerRef, prefix = "f" }) {
  const keys = Object.keys(errors);
  if (!keys.length) return null;
  return (
    <div ref={innerRef} tabIndex={-1} className="fo-alert fo-alert--danger" role="alert">
      <Icon as={CircleAlert} />
      <div>
        <strong>
          {keys.length === 1 ? "There is 1 problem to fix." : `There are ${keys.length} problems to fix.`}
        </strong>
        <ul>
          {keys.map((k) => (
            <li key={k}>
              <a
                href={`#${prefix}-${k}`}
                onClick={(e) => {
                  e.preventDefault();
                  document.getElementById(`${prefix}-${k}`)?.focus();
                }}
              >
                {labels[k]}: {errors[k]}
              </a>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

/** Visible label for prototype-only data (sample posts, projects, bookings). */
export function PrototypeNotice({ children }) {
  return (
    <div className="fo-alert ish-alert--info ish-measure">
      <Icon as={Info} />
      <p>
        <strong>Sample content.</strong> {children}
      </p>
    </div>
  );
}

const dateFmt = new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric" });
export const formatPostDate = (iso) => dateFmt.format(new Date(`${iso}T00:00:00`));

/** Journal teaser: 3:2 image · category · read time · title · 2-line excerpt · date. */
export function BlogCard({ post, headingLevel: H = "h3" }) {
  return (
    <Link className="fo-card" to={`/journal/${post.slug}`}>
      <Photo photo={post.photo} ratio="blog" sizes="(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw" alt="" />
      <span className="fo-card__meta">
        <span className="fo-eyebrow">{post.category}</span>
        <span aria-hidden="true">·</span>
        <span>{post.readMins} min read</span>
      </span>
      <H className="fo-card__title">{post.title}</H>
      <p className="fo-card__text ish-clamp-2">{post.excerpt}</p>
      <time className="fo-card__meta" dateTime={post.date}>
        {formatPostDate(post.date)}
      </time>
    </Link>
  );
}

const STATUS = {
  requested: { label: "Requested", icon: Clock, cls: "fo-badge--accent" },
  awaiting_deposit: { label: "Awaiting deposit", icon: Clock, cls: "fo-badge--warning" },
  confirmed: { label: "Confirmed", icon: CircleCheck, cls: "fo-badge--success" },
  completed: { label: "Completed", icon: CircleCheck, cls: "" },
  cancelled: { label: "Cancelled", icon: CircleX, cls: "" },
};

/** Booking status: always an icon and a word, never colour alone. */
export function StatusBadge({ status }) {
  const s = STATUS[status] ?? STATUS.requested;
  return (
    <span className={`fo-badge ${s.cls}`}>
      <Icon as={s.icon} size={12} />
      {s.label}
    </span>
  );
}
