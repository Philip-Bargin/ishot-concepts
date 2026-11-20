import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import { Menu } from "lucide-react";
import Logo from "./Logo.jsx";
import Icon from "./Icon.jsx";
import SocialLinks from "./SocialLinks.jsx";
import MenuSheet from "./MenuSheet.jsx";

// Design system Header: logo left · socials centre (≥768) · BOOK NOW + MENU right.
// Navigation lives in the full-screen MenuSheet at every breakpoint.
export default function Header() {
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();

  useEffect(() => setOpen(false), [pathname]);

  return (
    <>
      <header className="fo-header">
        <Link to="/" className="fo-logo">
          <Logo />
        </Link>
        <nav className="fo-header__social" aria-label="Social">
          <SocialLinks />
        </nav>
        <div className="fo-header__actions">
          <Link to="/account" className="fo-navlink ish-signin">
            My bookings
          </Link>
          <Link to="/book" className="fo-btn fo-btn--primary fo-btn--sm">
            Book now
          </Link>
          <button
            type="button"
            className="fo-menu-toggle"
            aria-expanded={open}
            aria-controls="site-menu"
            onClick={() => setOpen(true)}
          >
            Menu <Icon as={Menu} size={20} />
          </button>
        </div>
      </header>
      {open && <MenuSheet onClose={() => setOpen(false)} />}
    </>
  );
}
