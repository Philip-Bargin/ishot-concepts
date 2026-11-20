import { useState } from "react";
import { Link } from "react-router-dom";
import { contact, navItems, services } from "../data.js";
import { applyTheme } from "../lib/theme.js";
import Logo from "./Logo.jsx";
import SocialLinks from "./SocialLinks.jsx";
import { Spectrum } from "./ui.jsx";

function ThemeToggle() {
  const [theme, setTheme] = useState(() => document.documentElement.dataset.theme || "light");
  const toggle = () => {
    const next = theme === "dark" ? "light" : "dark";
    applyTheme(next);
    setTheme(next);
  };
  return (
    <button type="button" className="ish-linkbtn" aria-pressed={theme === "dark"} onClick={toggle}>
      Night mode
    </button>
  );
}

export default function Footer() {
  return (
    <footer className="fo-footer">
      <div className="fo-footer__grid">
        <div className="fo-footer__col">
          <Link to="/" className="fo-logo">
            <Logo />
          </Link>
          <Spectrum />
          <p className="small">A creative photography brand turning moments into timeless visual stories.</p>
          <div className="ish-social">
            <SocialLinks />
          </div>
        </div>

        <nav className="fo-footer__col" aria-labelledby="footer-explore">
          <p id="footer-explore" className="fo-eyebrow">
            Explore
          </p>
          {navItems.map((item) => (
            <Link key={item.to} to={item.to}>
              {item.label}
            </Link>
          ))}
        </nav>

        <nav className="fo-footer__col" aria-labelledby="footer-services">
          <p id="footer-services" className="fo-eyebrow">
            Services
          </p>
          {services.map((s) => (
            <Link key={s.id} to={`/services#${s.id}`}>
              {s.short}
            </Link>
          ))}
        </nav>

        <div className="fo-footer__col">
          <p className="fo-eyebrow">Contact</p>
          <span className="small">{contact.location}</span>
          <a href={`tel:${contact.phone}`}>{contact.phone}</a>
          <a href={`mailto:${contact.email}`}>{contact.email}</a>
          <a href={contact.instagramUrl} target="_blank" rel="noopener noreferrer">
            {contact.instagram} <span aria-hidden="true">↗</span>
            <span className="fo-sr"> (opens in a new tab)</span>
          </a>
        </div>
      </div>

      <p className="fo-footer__word" aria-hidden="true">
        iShot
      </p>

      <div className="fo-footer__legal">
        <span>© {new Date().getFullYear()} iShot Concept · Photography, creative direction, visual storytelling</span>
        <ThemeToggle />
      </div>
    </footer>
  );
}
