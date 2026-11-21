import { useState } from "react";
import { Link } from "react-router-dom";
import { contact } from "../data.js";
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

// Short, balanced columns (3 links each). Everything dropped from here is still one tap away:
// Home via the logo; Creative direction and Journal in the menu sheet (every page);
// individual services on the Services page; Instagram in the social icons above.
const studioLinks = [
  { to: "/about", label: "About" },
  { to: "/services", label: "Services" },
  { to: "/portfolio", label: "Portfolio" },
];
const bookingLinks = [
  { to: "/book", label: "Book a session" },
  { to: "/account", label: "My bookings" },
  { to: "/contact", label: "Contact" },
];

export default function Footer() {
  return (
    <footer className="fo-footer">
      {/* Outer rows stay full width (gutters, legal band); inner content uses the page container. */}
      <div className="fo-footer__grid ish-footer__outer">
        <div className="fo-container ish-footer__grid">
          <div className="fo-footer__col ish-footer__brand">
            <Link to="/" className="fo-logo">
              <Logo />
            </Link>
            <Spectrum />
            <p className="small">A creative photography brand turning moments into timeless visual stories.</p>
            <div className="ish-social">
              <SocialLinks />
            </div>
          </div>

          <nav className="fo-footer__col" aria-labelledby="footer-studio">
            <p id="footer-studio" className="fo-eyebrow">
              Studio
            </p>
            {studioLinks.map((item) => (
              <Link key={item.to} to={item.to}>
                {item.label}
              </Link>
            ))}
          </nav>

          <nav className="fo-footer__col" aria-labelledby="footer-book">
            <p id="footer-book" className="fo-eyebrow">
              Book
            </p>
            {bookingLinks.map((item) => (
              <Link key={item.to} to={item.to}>
                {item.label}
              </Link>
            ))}
          </nav>

          <div className="fo-footer__col">
            <p className="fo-eyebrow">Visit</p>
            <span className="small">{contact.location}</span>
            <a href={`tel:${contact.phone}`}>{contact.phone}</a>
            <a href={`mailto:${contact.email}`}>{contact.email}</a>
          </div>
        </div>
      </div>

      <div className="fo-footer__legal ish-footer__outer">
        <div className="fo-container ish-footer__legal">
          <span>© {new Date().getFullYear()} iShot Concept · Photography, creative direction, visual storytelling</span>
          <ThemeToggle />
        </div>
      </div>
    </footer>
  );
}
