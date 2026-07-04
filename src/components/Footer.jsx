import { useNavigate } from "react-router-dom";
import { navItems, contact } from "../data.js";
import Logo from "./Logo.jsx";

export default function Footer() {
  const navigate = useNavigate();

  return (
    <footer style={{ background: "#0b1836", borderTop: "1px solid #223B78" }}>
      {/* editorial closing line — set apart with a soft-blue tint + accent rule */}
      {/* <div className="container" style={{ paddingTop: "clamp(48px,7vw,80px)", textAlign: "center" }}>
        <span
          aria-hidden="true"
          style={{
            display: "block",
            width: 54,
            height: 3,
            margin: "0 auto 26px",
            borderRadius: 3,
            background: "linear-gradient(90deg, var(--teal-light), var(--orange))",
          }}
        />
        <p
          className="display serif-italic"
          style={{
            fontSize: "clamp(24px,3.4vw,40px)",
            color: "#8FB4E8",
            margin: "0 auto",
            maxWidth: 760,
            lineHeight: 1.2,
          }}
        >
          Your moments deserve to be{" "}
          <em style={{ color: "#F26A22", fontStyle: "italic" }}>remembered, felt, and celebrated.</em>
        </p>
      </div> */}

      <div
        className="container"
        style={{
          paddingTop: "clamp(40px,5vw,56px)",
          paddingBottom: "clamp(40px,6vw,64px)",
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit,minmax(200px,1fr))",
          gap: 40,
        }}
      >
        <div>
          <div style={{ display: "flex", alignItems: "baseline", gap: 8, marginBottom: 13 }}>
            <Logo />
          </div>
          <p
            style={{
              font: "400 15px/1.7 Montserrat, sans-serif",
              color: "var(--on-dark-2)",
              maxWidth: 380,
              margin: "0 0 16px",
            }}
          >
            A creative photography brand turning moments into timeless visual experiences.
          </p>
          <p
            style={{
              font: "600 11px/1.6 Montserrat, sans-serif",
              letterSpacing: "0.14em",
              textTransform: "uppercase",
              color: "#8FA0C4",
              margin: 0,
            }}
          >
            Photography · Creative Direction · Visual Storytelling
          </p>
        </div>

        <div>
          <h4 className="field-label" style={{ color: "#fff", marginBottom: 18 }}>
            Explore
          </h4>
          <div style={{ display: "flex", flexDirection: "column", gap: 12, alignItems: "flex-start" }}>
            {navItems.map((item) => (
              <button key={item.to} className="footer-link" onClick={() => navigate(item.to)}>
                {item.label}
              </button>
            ))}
          </div>
        </div>

        <div>
          <h4 className="field-label" style={{ color: "#fff", marginBottom: 18 }}>
            Contact
          </h4>
          <div
            style={{
              display: "flex",
              flexDirection: "column",
              gap: 12,
              font: "400 14px Montserrat, sans-serif",
              color: "var(--on-dark-2)",
            }}
          >
            <span>{contact.location}</span>
            <a href={`tel:${contact.phone}`} className="footer-link" style={{ color: "var(--on-dark-2)" }}>
              {contact.phone}
            </a>
            <a href={`mailto:${contact.email}`} className="footer-link" style={{ color: "var(--on-dark-2)" }}>
              {contact.email}
            </a>
            <span style={{ color: "var(--teal-light)" }}>{contact.instagram}</span>
            <span style={{ color: "var(--teal-light)" }}>{contact.website}</span>
          </div>
        </div>
      </div>

      <div style={{ borderTop: "1px solid #223B78" }}>
        <div
          className="container"
          style={{
            paddingTop: 22,
            paddingBottom: 22,
            display: "flex",
            flexWrap: "wrap",
            gap: 12,
            justifyContent: "center",
            alignItems: "center",
          }}
        >
          <span style={{ font: "400 13px Montserrat, sans-serif", color: "#8FA0C4" }}>
            © 2026 iShot Concept. All rights reserved.
          </span>
        </div>
      </div>
    </footer>
  );
}
