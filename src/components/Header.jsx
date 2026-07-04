import { useState, useEffect } from "react";
import { useNavigate, useLocation, NavLink } from "react-router-dom";
import { navItems } from "../data.js";
import Logo from "./Logo.jsx";

export default function Header() {
  const [menuOpen, setMenuOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const navigate = useNavigate();
  const { pathname } = useLocation();

  // Close the mobile drawer whenever the route changes.
  useEffect(() => {
    setMenuOpen(false);
  }, [pathname]);

  // Condense / blur the header once the page is scrolled.
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  // Prevent body scroll while the mobile drawer is open.
  useEffect(() => {
    document.body.style.overflow = menuOpen ? "hidden" : "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [menuOpen]);

  return (
    <header className={"site-header" + (scrolled ? " scrolled" : "")}>
      <div
        className="container"
        style={{
          height: 74,
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          gap: 20,
        }}
      >
        <button className="brand" onClick={() => navigate("/")} aria-label="iShot Concept — home">
          <Logo />
        </button>

        <nav className="desktop-nav" aria-label="Primary">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === "/"}
              className={({ isActive }) => "nav-link" + (isActive ? " is-active" : "")}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
          <button
            className="btn btn--primary"
            style={{ display: "inline-flex" }}
            onClick={() => navigate("/contact")}
          >
            Book Your Session
          </button>
          <button
            className={"hamburger" + (menuOpen ? " open" : "")}
            aria-label={menuOpen ? "Close menu" : "Open menu"}
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((v) => !v)}
          >
            <span />
            <span />
            <span />
          </button>
        </div>
      </div>

      {menuOpen && (
        <div
          style={{
            position: "fixed",
            inset: "74px 0 0 0",
            zIndex: 49,
            background: "rgba(11,24,54,.97)",
            backdropFilter: "blur(8px)",
            animation: "ish-fade .25s ease",
            padding: "28px clamp(20px,6vw,40px)",
          }}
        >
          <nav style={{ display: "flex", flexDirection: "column", gap: 4 }} aria-label="Mobile">
            {navItems.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === "/"}
                className={({ isActive }) => "nav-link nav-link--drawer" + (isActive ? " is-active" : "")}
              >
                {item.label}
              </NavLink>
            ))}
            <button
              className="btn btn--primary btn--lg"
              style={{ marginTop: 20, width: "100%" }}
              onClick={() => navigate("/contact")}
            >
              Book Your Session
            </button>
          </nav>
        </div>
      )}
    </header>
  );
}
