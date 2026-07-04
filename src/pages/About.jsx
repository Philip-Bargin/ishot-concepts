import { useNavigate } from "react-router-dom";
import { images } from "../assets.js";
import { PageHero } from "../components/ui.jsx";
import Reveal from "../components/Reveal.jsx";

const stats = [
  { value: "6+", color: "#F26A22", label: "Signature services" },
  { value: "7", color: "#0071BF", label: "Portfolio categories" },
  { value: "100%", color: "#47A743", label: "Client focus" },
];

const promises = [
  "High-Quality Images",
  "Unique Editing & Retouch",
  "Fast Delivery",
  "Creative Storytelling",
];

export default function About() {
  const navigate = useNavigate();

  return (
    <div className="page-enter">
      <PageHero
        eyebrow="Our Story"
        title="Premium photography, rooted in Monrovia — with global ambition."
        bg={images.aboutHero}
      />

      <section
        className="container section-pad"
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))",
          gap: "clamp(32px,6vw,68px)",
          alignItems: "center",
        }}
      >
        <Reveal className="zoom" style={{ borderRadius: 8, boxShadow: "var(--sh-2)" }}>
          <img
            src={images.aboutFounder}
            alt="iShot Concept founder"
            style={{ width: "100%", height: "clamp(340px,46vw,520px)", objectFit: "cover" }}
          />
        </Reveal>
        <Reveal delay={120}>
          <h2 className="display" style={{ fontSize: "clamp(26px,3.4vw,42px)", lineHeight: 1.14, color: "var(--ink)", margin: "0 0 20px" }}>
            Capturing emotion, identity &amp; legacy.
          </h2>
          <p style={{ font: "400 17px/1.8 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 16px" }}>
            iShot Concept is a premium photography brand based in Monrovia, dedicated to capturing timeless moments with
            creativity, precision, and style. We believe your moments deserve more than just pictures — they deserve to
            be remembered, felt, and celebrated.
          </p>
          <p style={{ font: "400 17px/1.8 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 28px" }}>
            From birthdays and portraits to weddings, corporate brands, and bold creative concepts, we bring an
            editorial eye and cinematic craft to every frame.
          </p>
          <div style={{ display: "flex", flexWrap: "wrap", gap: 40 }}>
            {stats.map((s) => (
              <div key={s.label}>
                <div className="display" style={{ fontSize: 40, fontWeight: 700, color: s.color, lineHeight: 1 }}>
                  {s.value}
                </div>
                <div style={{ font: "500 13px Montserrat, sans-serif", color: "var(--gray-muted)", marginTop: 6 }}>
                  {s.label}
                </div>
              </div>
            ))}
          </div>
        </Reveal>
      </section>

      <section style={{ background: "#172D60" }}>
        <div
          className="container section-pad"
          style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(300px,1fr))", gap: 24 }}
        >
          {[
            {
              title: "Our Vision",
              body:
                "To become Africa's most trusted premium photography brand, turning everyday moments into legacy and ideas into unforgettable visual stories.",
            },
            {
              title: "Our Mission",
              body:
                "To capture timeless moments with creativity, precision, and style — imagery our clients are proud to remember, feel, and celebrate.",
            },
          ].map((v, i) => (
            <Reveal
              key={v.title}
              delay={i * 120}
              style={{ background: "#101F44", border: "1px solid #223B78", borderRadius: 8, padding: "clamp(28px,4vw,40px)" }}
            >
              <h3 className="display" style={{ fontSize: 27, color: "#fff", margin: "0 0 12px" }}>
                {v.title}
              </h3>
              <p style={{ font: "400 16px/1.75 Montserrat, sans-serif", color: "var(--on-dark-2)", margin: 0 }}>{v.body}</p>
            </Reveal>
          ))}
        </div>
      </section>

      <section className="container section-pad">
        <Reveal
          as="h2"
          className="display"
          style={{ textAlign: "center", fontSize: "clamp(26px,3.4vw,42px)", color: "var(--ink)", margin: "0 0 44px" }}
        >
          Why clients choose iShot
        </Reveal>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(220px,1fr))", gap: 20 }}>
          {promises.map((p, i) => (
            <Reveal
              key={p}
              delay={(i % 4) * 80}
              className="lift"
              style={{ textAlign: "center", padding: "32px 20px", background: "#F7F9FC", borderRadius: 8, border: "1px solid var(--border)" }}
            >
              <div className="display" style={{ fontSize: 19, color: "var(--ink)", fontWeight: 600 }}>{p}</div>
            </Reveal>
          ))}
        </div>
      </section>

      <section style={{ background: "#F7F9FC", textAlign: "center", padding: "clamp(56px,7vw,88px) clamp(20px,5vw,40px)" }}>
        <Reveal>
          <h2 className="display" style={{ fontSize: "clamp(26px,3.6vw,44px)", color: "var(--ink)", margin: "0 0 20px" }}>
            Ready to tell your story?
          </h2>
          <button className="btn btn--primary btn--lg" onClick={() => navigate("/contact")}>
            Book Your Session <span className="arw">→</span>
          </button>
        </Reveal>
      </section>
    </div>
  );
}
