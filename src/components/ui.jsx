// Small presentational helpers shared across pages.
import Reveal from "./Reveal.jsx";

export function Eyebrow({ children, variant, center }) {
  const cls = ["eyebrow", variant === "teal" && "eyebrow--teal", center && "eyebrow--center"]
    .filter(Boolean)
    .join(" ");
  return <span className={cls}>{children}</span>;
}

// Full-width navy header band used at the top of interior pages.
// Pass `bg` (an image path) for a cinematic photo backdrop; a navy
// gradient overlay is layered on top so heading text stays readable.
export function PageHero({ eyebrow, title, subtitle, kicker, bg }) {
  return (
    <section
      className="grain vignette"
      style={{
        position: "relative",
        overflow: "hidden",
        background:
          "radial-gradient(120% 140% at 50% 0%, #1c3573 0%, #172d60 45%, #0b1836 100%)",
        textAlign: "center",
        padding: "clamp(64px,9vw,116px) clamp(20px,5vw,40px)",
      }}
    >
      {bg && (
        <>
          <img
            className="kenburns"
            src={bg}
            alt=""
            aria-hidden="true"
            style={{
              position: "absolute",
              inset: 0,
              width: "100%",
              height: "100%",
              objectFit: "cover",
              opacity: 0.3,
            }}
          />
          <div
            aria-hidden="true"
            style={{
              position: "absolute",
              inset: 0,
              background:
                "linear-gradient(180deg, rgba(11,24,54,.72) 0%, rgba(11,24,54,.82) 100%)",
            }}
          />
        </>
      )}
      <div className="container container--narrow" style={{ position: "relative" }}>
        <Reveal>
          {eyebrow && (
            <div style={{ marginBottom: 20 }}>
              <Eyebrow variant="teal" center>
                {eyebrow}
              </Eyebrow>
            </div>
          )}
          <h1
            className="display display--hero"
            style={{
              fontSize: "clamp(38px,5.4vw,66px)",
              color: "#fff",
              margin: subtitle ? "0 auto 18px" : "0 auto",
              maxWidth: 820,
            }}
          >
            {title}
          </h1>
          {subtitle && (
            <p className="lead" style={{ color: "var(--on-dark-2)", margin: "0 auto", maxWidth: 560 }}>
              {subtitle}
            </p>
          )}
        </Reveal>
      </div>
    </section>
  );
}

// Centered section intro (eyebrow + serif heading + optional lead).
export function SectionHead({ eyebrow, title, lead, maxWidth = 640, align = "center" }) {
  return (
    <Reveal
      style={{
        textAlign: align,
        maxWidth,
        margin: align === "center" ? "0 auto clamp(40px,5vw,56px)" : "0 0 clamp(40px,5vw,56px)",
      }}
    >
      {eyebrow && (
        <div style={{ marginBottom: 16 }}>
          <Eyebrow center={align === "center"}>{eyebrow}</Eyebrow>
        </div>
      )}
      <h2
        className="display"
        style={{ fontSize: "clamp(30px,4vw,50px)", lineHeight: 1.08, color: "var(--ink)", margin: 0 }}
      >
        {title}
      </h2>
      {lead && (
        <p className="lead" style={{ margin: "14px auto 0", maxWidth: 560 }}>
          {lead}
        </p>
      )}
    </Reveal>
  );
}
