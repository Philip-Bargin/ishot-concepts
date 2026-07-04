import { useNavigate } from "react-router-dom";
import { services } from "../data.js";
import { images } from "../assets.js";
import { PageHero } from "../components/ui.jsx";
import Reveal from "../components/Reveal.jsx";

export default function Services() {
  const navigate = useNavigate();

  return (
    <div className="page-enter">
      <PageHero
        eyebrow="What We Do"
        title="Our Services"
        subtitle="Crafted experiences for every moment worth remembering."
        bg={images.servicesHero}
      />

      <section
        className="container section-pad"
        style={{ display: "flex", flexDirection: "column", gap: "clamp(40px,7vw,96px)" }}
      >
        {services.map((s, i) => {
          const reversed = i % 2 === 1;
          return (
            <Reveal
              key={s.id}
              style={{
                display: "grid",
                gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))",
                gap: "clamp(28px,4vw,60px)",
                alignItems: "center",
                direction: reversed ? "rtl" : "ltr",
              }}
            >
              <div className="zoom" style={{ direction: "ltr", borderRadius: 8, boxShadow: "var(--sh-2)" }}>
                <img
                  src={s.img}
                  loading="lazy"
                  alt={s.title}
                  style={{ width: "100%", height: "clamp(280px,36vw,420px)", objectFit: "cover" }}
                />
              </div>
              <div style={{ direction: "ltr" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 14, marginBottom: 16 }}>
                  <span
                    className="display"
                    style={{ fontSize: 40, color: "rgba(23,45,96,.16)", lineHeight: 1, fontWeight: 700 }}
                  >
                    {String(i + 1).padStart(2, "0")}
                  </span>
                  <span className="eyebrow" style={{ fontSize: 11 }}>
                    {s.tag}
                  </span>
                </div>
                <h2 className="display" style={{ fontSize: "clamp(26px,3.2vw,40px)", lineHeight: 1.14, color: "var(--ink)", margin: "0 0 16px" }}>
                  {s.title}
                </h2>
                <p style={{ font: "400 16px/1.75 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 24px" }}>
                  {s.long}
                </p>
                <button className="btn btn--primary btn--sm" onClick={() => navigate("/contact")}>
                  Book This Session <span className="arw">→</span>
                </button>
              </div>
            </Reveal>
          );
        })}
      </section>

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
          <h2 className="display" style={{ fontSize: "clamp(28px,4vw,44px)", color: "#fff", margin: "0 0 14px" }}>
            Not sure which fits?
          </h2>
          <p className="lead" style={{ color: "var(--on-dark-2)", margin: "0 0 30px" }}>
            Let's shape a concept around your vision.
          </p>
          <button className="btn btn--primary btn--lg" onClick={() => navigate("/contact")}>
            Start a Concept <span className="arw">→</span>
          </button>
        </Reveal>
      </section>
    </div>
  );
}
