import { useNavigate } from "react-router-dom";
import { process } from "../data.js";
import { images } from "../assets.js";
import { Eyebrow, SectionHead } from "../components/ui.jsx";
import Reveal from "../components/Reveal.jsx";

export default function Creative() {
  const navigate = useNavigate();

  return (
    <div className="page-enter">
      {/* HERO */}
      <section
        className="grain vignette"
        style={{ position: "relative", overflow: "hidden", background: "#0b1836" }}
      >
        <img
          className="kenburns"
          src={images.creativeHero}
          alt=""
          aria-hidden="true"
          style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover", opacity: 0.4 }}
        />
        <div
          style={{ position: "absolute", inset: 0, background: "linear-gradient(100deg,rgba(11,24,54,.92),rgba(23,45,96,.5))" }}
        />
        <div
          className="container"
          style={{ position: "relative", paddingTop: "clamp(72px,10vw,140px)", paddingBottom: "clamp(72px,10vw,140px)" }}
        >
          <div style={{ maxWidth: 700 }}>
            <Reveal>
              <div style={{ marginBottom: 20 }}>
                <Eyebrow variant="teal">Creative Direction</Eyebrow>
              </div>
            </Reveal>
            <Reveal delay={100}>
              <h1 className="display display--hero" style={{ fontSize: "clamp(38px,6vw,72px)", color: "#fff", margin: "0 0 20px", maxWidth: 640 }}>
                Concepts that turn ideas into <em style={{ color: "#F26A22", fontStyle: "italic" }}>unforgettable</em> visuals.
              </h1>
            </Reveal>
            <Reveal delay={180}>
              <p className="lead" style={{ color: "var(--on-dark-2)", margin: "0 0 32px", maxWidth: 540 }}>
                From mood and styling to set and story, we direct the whole creative — so every frame is intentional.
              </p>
            </Reveal>
            <Reveal delay={260}>
              <button className="btn btn--primary btn--lg" onClick={() => navigate("/contact")}>
                Start a Concept <span className="arw">→</span>
              </button>
            </Reveal>
          </div>
        </div>
      </section>

      {/* PROCESS */}
      <section className="container section-pad">
        <SectionHead eyebrow="How We Work" title="Our Process" />
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(220px,1fr))", gap: 24 }}>
          {process.map((p, i) => (
            <Reveal
              key={p.n}
              delay={(i % 4) * 90}
              className="lift"
              style={{ border: "1px solid var(--border)", borderRadius: 8, padding: 30, background: "#fff" }}
            >
              <div
                style={{
                  width: 48,
                  height: 48,
                  borderRadius: "50%",
                  background: "#F26A22",
                  color: "#fff",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  boxShadow: "0 6px 16px rgba(242,106,34,.3)",
                  marginBottom: 20,
                }}
              >
                <span className="display" style={{ fontSize: 22, fontWeight: 700 }}>{p.n}</span>
              </div>
              <h3 className="display" style={{ fontSize: 21, color: "var(--ink)", margin: "0 0 8px" }}>
                {p.title}
              </h3>
              <p style={{ font: "400 14px/1.65 Montserrat, sans-serif", color: "var(--gray)", margin: 0 }}>{p.blurb}</p>
            </Reveal>
          ))}
        </div>
      </section>

      {/* FEATURED CONCEPTS */}
      <section style={{ background: "#F7F9FC" }}>
        <div className="container section-pad">
          <SectionHead eyebrow="Selected" title="Featured Concepts" />
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))", gap: 24 }}>
            <Reveal
              className="zoom"
              style={{ position: "relative", borderRadius: 10, height: "clamp(300px,40vw,440px)", cursor: "pointer", boxShadow: "var(--sh-2)" }}
            >
              <img src={images.conceptReel} alt="Editorial short reel concept" style={{ width: "100%", height: "100%", objectFit: "cover" }} />
              <div style={{ position: "absolute", inset: 0, background: "rgba(11,24,54,.34)", display: "flex", alignItems: "center", justifyContent: "center" }}>
                <div
                  style={{
                    width: 68,
                    height: 68,
                    borderRadius: "50%",
                    background: "rgba(255,255,255,.92)",
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "center",
                    boxShadow: "0 8px 24px rgba(0,0,0,.3)",
                  }}
                >
                  <div style={{ width: 0, height: 0, borderLeft: "19px solid #172D60", borderTop: "12px solid transparent", borderBottom: "12px solid transparent", marginLeft: 5 }} />
                </div>
              </div>
              <span style={{ position: "absolute", left: 22, bottom: 20, font: "600 15px Montserrat, sans-serif", color: "#fff" }}>
                Short Reel · Editorial
              </span>
            </Reveal>
            <Reveal
              delay={120}
              className="zoom"
              style={{ position: "relative", borderRadius: 10, height: "clamp(300px,40vw,440px)", boxShadow: "var(--sh-2)" }}
            >
              <img src={images.conceptStill} alt="Studio fashion concept" style={{ width: "100%", height: "100%", objectFit: "cover" }} />
              <span style={{ position: "absolute", left: 22, bottom: 20, font: "600 15px Montserrat, sans-serif", color: "#fff" }}>
                Studio Concept · Fashion
              </span>
            </Reveal>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section
        className="grain"
        style={{
          position: "relative",
          overflow: "hidden",
          background: "radial-gradient(120% 140% at 50% 0%, #1c3573 0%, #172d60 50%, #0b1836 100%)",
          textAlign: "center",
          padding: "clamp(56px,8vw,96px) clamp(20px,5vw,40px)",
        }}
      >
        <Reveal className="container container--narrow" style={{ position: "relative" }}>
          <h2 className="display" style={{ fontSize: "clamp(28px,4vw,46px)", color: "#fff", margin: "0 0 24px" }}>
            Have a concept in mind?
          </h2>
          <button className="btn btn--primary btn--lg" onClick={() => navigate("/contact")}>
            Start a Concept <span className="arw">→</span>
          </button>
        </Reveal>
      </section>
    </div>
  );
}
