import { useNavigate } from "react-router-dom";
import { services } from "../data.js";
import { images } from "../assets.js";
import { Eyebrow, SectionHead } from "../components/ui.jsx";
import Reveal from "../components/Reveal.jsx";

const whyCards = [
  {
    bg: "#FBE4D5",
    stroke: "#F26A22",
    title: "High-Quality Images",
    blurb: "Crisp, cinematic, gallery-grade photography every single time.",
    icon: (
      <>
        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
        <circle cx="12" cy="13" r="4" />
      </>
    ),
  },
  {
    bg: "#DCECF7",
    stroke: "#0071BF",
    title: "Unique Editing & Retouch",
    blurb: "A signature style that makes every image unmistakably yours.",
    icon: <path d="M12 2l2.4 7.4H22l-6 4.5 2.3 7.1L12 16.6 5.7 21l2.3-7.1-6-4.5h7.6z" />,
  },
  {
    bg: "#E9F5E8",
    stroke: "#47A743",
    title: "Fast Delivery",
    blurb: "Quick turnaround without ever compromising on quality.",
    icon: <path d="M13 2L3 14h7l-1 8 10-12h-7z" />,
  },
  {
    bg: "#DFF0F9",
    stroke: "#329AD3",
    title: "Creative Storytelling",
    blurb: "Concepts and direction that turn a shoot into a story worth telling.",
    icon: (
      <>
        <rect x="2" y="2" width="20" height="20" rx="2.18" />
        <path d="M7 2v20M17 2v20M2 12h20M2 7h5M2 17h5M17 17h5M17 7h5" />
      </>
    ),
  },
];

export default function Home() {
  const navigate = useNavigate();

  return (
    <div className="page-enter">
      {/* HERO */}
      <section
        className="grain vignette"
        style={{
          position: "relative",
          minHeight: "min(88vh,760px)",
          display: "flex",
          alignItems: "center",
          overflow: "hidden",
          background: "#0b1836",
        }}
      >
        <img
          className="kenburns"
          src={images.homeHero}
          alt=""
          aria-hidden="true"
          style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", opacity: 0.5 }}
        />
        <div
          style={{
            position: "absolute",
            inset: 0,
            background:
              "linear-gradient(100deg,rgba(11,24,54,.94) 0%,rgba(23,45,96,.7) 52%,rgba(23,45,96,.28) 100%)",
          }}
        />
        <div className="container" style={{ position: "relative", width: "100%" }}>
          <div style={{ maxWidth: 760 }}>
            <Reveal>
              <div style={{ marginBottom: 22 }}>
                <Eyebrow variant="teal">Monrovia · Liberia</Eyebrow>
              </div>
            </Reveal>
            <Reveal delay={90}>
              <h1
                className="display display--hero"
                style={{ fontSize: "clamp(40px,7vw,82px)", color: "#fff", margin: "0 0 24px", maxWidth: 780 }}
              >
                Your moments deserve more than just pictures — they deserve to be{" "}
                <em style={{ color: "#F26A22", fontStyle: "italic" }}>celebrated.</em>
              </h1>
            </Reveal>
            <Reveal delay={180}>
              <p className="lead" style={{ color: "var(--on-dark-2)", margin: "0 0 36px", maxWidth: 540 }}>
                A premium photography brand capturing timeless moments with creativity, precision, and style.
              </p>
            </Reveal>
            <Reveal delay={260}>
              <div style={{ display: "flex", flexWrap: "wrap", gap: 14 }}>
                <button className="btn btn--primary btn--lg" onClick={() => navigate("/contact")}>
                  Book Your Session <span className="arw">→</span>
                </button>
                <button className="btn btn--ghost-teal btn--lg" onClick={() => navigate("/portfolio")}>
                  View Portfolio
                </button>
              </div>
            </Reveal>
          </div>
        </div>

        {/* scroll cue */}
        <div
          aria-hidden="true"
          style={{
            position: "absolute",
            left: "50%",
            bottom: 26,
            transform: "translateX(-50%)",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 8,
            color: "var(--on-dark-3)",
          }}
        >
          <span style={{ font: "600 11px Montserrat, sans-serif", letterSpacing: "0.24em", textTransform: "uppercase" }}>
            Scroll
          </span>
          <span style={{ width: 1, height: 34, background: "linear-gradient(var(--teal-light), transparent)" }} />
        </div>
      </section>

      {/* WHO WE ARE */}
      <section
        className="container section-pad"
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))",
          gap: "clamp(32px,6vw,72px)",
          alignItems: "center",
        }}
      >
        <Reveal>
          <div style={{ marginBottom: 18 }}>
            <Eyebrow>Who We Are</Eyebrow>
          </div>
          <h2 className="display" style={{ fontSize: "clamp(28px,3.6vw,46px)", lineHeight: 1.1, margin: "0 0 22px" }}>
            A creative brand turning moments into timeless visual experiences.
          </h2>
          <p style={{ font: "400 17px/1.75 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 16px" }}>
            iShot Concept is more than a studio. We capture emotion, identity, legacy, and culturally rich stories —
            from intimate portraits to grand celebrations, executive brands to bold creative concepts.
          </p>
          <p style={{ font: "400 17px/1.75 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 30px" }}>
            Every frame is crafted with intention, precision, and a distinctly African creative excellence.
          </p>
          <button className="btn btn--secondary btn--sm" onClick={() => navigate("/about")}>
            Read Our Story <span className="arw">→</span>
          </button>
        </Reveal>
        <Reveal delay={120} style={{ position: "relative" }}>
          <div className="zoom" style={{ borderRadius: 8, boxShadow: "var(--sh-2)" }}>
            <img
              src={images.homeStudio}
              alt="iShot Concept studio session"
              style={{ width: "100%", height: "clamp(320px,44vw,500px)", objectFit: "cover" }}
            />
          </div>
          <div
            style={{
              position: "absolute",
              left: -16,
              bottom: -18,
              background: "#172D60",
              color: "#fff",
              padding: "20px 24px",
              borderRadius: 8,
              boxShadow: "var(--sh-3)",
            }}
          >
            <div className="display" style={{ fontSize: 34, color: "#F26A22", lineHeight: 1 }}>
              100%
            </div>
            <div style={{ font: "500 12px Montserrat, sans-serif", letterSpacing: "0.04em", color: "var(--on-dark-2)", marginTop: 4 }}>
              Moments, remembered
            </div>
          </div>
        </Reveal>
      </section>

      {/* VISION & MISSION */}
      <section style={{ background: "#172D60" }}>
        <div
          className="container section-pad"
          style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))", gap: 24 }}
        >
          {[
            {
              ring: "#329AD3",
              inner: <div style={{ width: 20, height: 20, border: "2px solid #329AD3", borderRadius: "50%" }} />,
              title: "Our Vision",
              body:
                "To become Africa's most trusted premium photography brand — turning everyday moments into legacy, and ideas into unforgettable visual stories with global reach.",
            },
            {
              ring: "#F26A22",
              inner: <div style={{ width: 16, height: 16, background: "#F26A22", borderRadius: "50%" }} />,
              title: "Our Mission",
              body:
                "To capture timeless moments with creativity, precision, and style — delivering imagery that our clients are proud to remember, feel, and celebrate for a lifetime.",
            },
          ].map((v, i) => (
            <Reveal
              key={v.title}
              delay={i * 120}
              style={{ background: "#101F44", border: "1px solid #223B78", borderRadius: 8, padding: "clamp(28px,4vw,40px)" }}
            >
              <div
                style={{
                  width: 52,
                  height: 52,
                  border: `2px solid ${v.ring}`,
                  borderRadius: "50%",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  marginBottom: 22,
                }}
              >
                {v.inner}
              </div>
              <h3 className="display" style={{ fontSize: 27, color: "#fff", margin: "0 0 12px" }}>
                {v.title}
              </h3>
              <p style={{ font: "400 16px/1.75 Montserrat, sans-serif", color: "var(--on-dark-2)", margin: 0 }}>
                {v.body}
              </p>
            </Reveal>
          ))}
        </div>
      </section>

      {/* SERVICES */}
      <section className="container section-pad">
        <SectionHead
          eyebrow="What We Do"
          title="Our Services"
          lead="Six core offerings, each crafted to tell your story with intention and style."
        />
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))", gap: 24 }}>
          {services.map((s, i) => (
            <Reveal key={s.id} delay={(i % 3) * 90}>
              <button
                className="card zoom"
                onClick={() => navigate("/services")}
                style={{ display: "block", width: "100%", textAlign: "left", padding: 0, cursor: "pointer", overflow: "hidden" }}
              >
                <div style={{ position: "relative", height: 210, overflow: "hidden" }}>
                  <img src={s.img} loading="lazy" alt={s.title} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
                  <div style={{ position: "absolute", top: 0, left: 0, width: "100%", height: 4, background: "#F26A22" }} />
                </div>
                <div style={{ padding: 26 }}>
                  <span className="eyebrow" style={{ fontSize: 11, marginBottom: 12 }}>
                    {s.tag}
                  </span>
                  <h3 className="display" style={{ fontSize: 24, lineHeight: 1.2, color: "var(--ink)", margin: "10px 0 10px" }}>
                    {s.title}
                  </h3>
                  <p style={{ font: "400 15px/1.65 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 16px" }}>
                    {s.blurb}
                  </p>
                  <span style={{ font: "600 14px Montserrat, sans-serif", color: "#0071BF" }}>
                    Explore <span className="arw">→</span>
                  </span>
                </div>
              </button>
            </Reveal>
          ))}
        </div>
      </section>

      {/* PORTFOLIO STRIP */}
      <section style={{ background: "#F7F9FC" }}>
        <div className="container section-pad">
          <div
            style={{
              display: "flex",
              flexWrap: "wrap",
              gap: 16,
              alignItems: "flex-end",
              justifyContent: "space-between",
              marginBottom: 40,
            }}
          >
            <Reveal>
              <div style={{ marginBottom: 12 }}>
                <Eyebrow>Visual Stories</Eyebrow>
              </div>
              <h2 className="display" style={{ fontSize: "clamp(28px,3.6vw,48px)", margin: 0 }}>
                Selected Work
              </h2>
            </Reveal>
            <Reveal delay={100}>
              <button className="btn btn--outline-blue btn--sm" onClick={() => navigate("/portfolio")}>
                See Our Work <span className="arw">→</span>
              </button>
            </Reveal>
          </div>
          <Reveal className="work-strip">
            <div className="feature zoom" style={{ borderRadius: 8 }}>
              <img src={images.work1} alt="Featured work" style={{ width: "100%", height: "100%", objectFit: "cover" }} />
            </div>
            {[images.work2, images.work3, images.work4, images.work5].map((src, i) => (
              <div key={i} className="zoom" style={{ borderRadius: 8 }}>
                <img
                  src={src}
                  alt="Portfolio sample"
                  loading="lazy"
                  style={{ width: "100%", height: "100%", objectFit: "cover" }}
                />
              </div>
            ))}
          </Reveal>
        </div>
      </section>

      {/* WHY CHOOSE US */}
      <section className="container section-pad">
        <SectionHead eyebrow="The iShot Promise" title="Why Choose Us" maxWidth={600} />
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(220px,1fr))", gap: 24 }}>
          {whyCards.map((c, i) => (
            <Reveal
              key={c.title}
              delay={(i % 4) * 80}
              className="lift"
              style={{ textAlign: "center", padding: "36px 22px", border: "1px solid var(--border)", borderRadius: 8, background: "#fff" }}
            >
              <div
                style={{
                  width: 58,
                  height: 58,
                  margin: "0 auto 20px",
                  borderRadius: "50%",
                  background: c.bg,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke={c.stroke} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  {c.icon}
                </svg>
              </div>
              <h3 className="display" style={{ fontSize: 20, color: "var(--ink)", margin: "0 0 8px" }}>
                {c.title}
              </h3>
              <p style={{ font: "400 14px/1.65 Montserrat, sans-serif", color: "var(--gray)", margin: 0 }}>{c.blurb}</p>
            </Reveal>
          ))}
        </div>
      </section>

      {/* CTA BAND */}
      <section className="grain vignette" style={{ position: "relative", overflow: "hidden", background: "#0b1836" }}>
        <img
          src={images.homeCta}
          alt=""
          aria-hidden="true"
          style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", opacity: 0.24 }}
        />
        <div
          style={{
            position: "relative",
            maxWidth: 900,
            margin: "0 auto",
            padding: "clamp(64px,9vw,112px) clamp(20px,5vw,40px)",
            textAlign: "center",
          }}
        >
          <Reveal>
            <h2 className="display" style={{ fontSize: "clamp(32px,5vw,58px)", color: "#fff", margin: "0 0 20px", lineHeight: 1.08 }}>
              Let's Create That <em style={{ color: "#F26A22", fontStyle: "italic" }}>Magic</em>
            </h2>
            <p className="lead" style={{ color: "var(--on-dark-2)", margin: "0 0 34px" }}>
              Your story is waiting to be told. Let's make it unforgettable.
            </p>
            <button className="btn btn--primary btn--xl" onClick={() => navigate("/contact")}>
              Book Your Session <span className="arw">→</span>
            </button>
          </Reveal>
        </div>
      </section>
    </div>
  );
}
