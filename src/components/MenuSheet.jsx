import { useRef } from "react";
import { Link, NavLink } from "react-router-dom";
import { X } from "lucide-react";
import { contact, navItems, pad2 } from "../data.js";
import { useFocusTrap } from "../lib/hooks.js";
import Logo from "./Logo.jsx";
import Icon from "./Icon.jsx";
import SocialLinks from "./SocialLinks.jsx";

export default function MenuSheet({ onClose }) {
  const ref = useRef(null);
  useFocusTrap(ref, onClose);

  return (
    <div id="site-menu" ref={ref} className="fo-menu fo-band" role="dialog" aria-modal="true" aria-label="Site menu">
      <div className="ish-menu__top">
        <Link to="/" className="fo-logo" onClick={onClose}>
          <Logo variant="reversed" />
        </Link>
        <button type="button" className="fo-icon-btn" onClick={onClose} aria-label="Close menu">
          <Icon as={X} />
        </button>
      </div>

      <nav aria-label="Main">
        <ol className="fo-menu__list">
          {navItems.map((item, i) => (
            <li key={item.to} style={{ "--i": i }}>
              <NavLink to={item.to} end={item.to === "/"} onClick={onClose} data-autofocus={i === 0 ? "" : undefined}>
                <span>{pad2(i + 1)}</span>
                {item.label}
              </NavLink>
            </li>
          ))}
        </ol>
      </nav>

      <div className="ish-menu__foot">
        <Link to="/book" className="fo-btn fo-btn--primary" onClick={onClose}>
          Book a session
        </Link>
        <p className="small">
          {contact.location} · <a href={`tel:${contact.phone}`}>{contact.phone}</a>
        </p>
        <div className="ish-social">
          <SocialLinks />
        </div>
      </div>
    </div>
  );
}
