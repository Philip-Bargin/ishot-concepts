import { Link } from "react-router-dom";
import { ArrowRight } from "lucide-react";
import Icon from "./Icon.jsx";
import { Photo, Placeholder, PrototypeNotice } from "./ui.jsx";

/**
 * Design-system Founder block (Profile card, split): 4:5 portrait left;
 * eyebrow, name (h3 in the system, h2 here because it heads its own section), title, bio, text link.
 */
export default function Founder({ person }) {
  const ph = person.placeholder;
  return (
    <section className="fo-section" aria-labelledby="founder-title">
      <div className="fo-container ish-split ish-founder">
        {person.photo ? <Photo photo={person.photo} ratio="portrait" alt={`${person.name}, ${person.title}`} /> : <Placeholder ratio="portrait" />}
        <div className="ish-stack ish-stack--lg">
          <p className="fo-eyebrow">Meet the founder</p>
          <div className="ish-stack">
            <h2 id="founder-title" className={`ish-h3${ph ? " ish-placeholder" : ""}`}>
              {person.name}
            </h2>
            <p className={`small${ph ? " ish-placeholder" : " fo-muted"}`}>{person.title}</p>
          </div>
          <p className={`body-lg${ph ? " ish-placeholder" : ""}`}>{person.bio}</p>
          <Link to="/about" className="fo-btn fo-btn--text">
            Read our story <Icon as={ArrowRight} />
          </Link>
          {ph && (
            <PrototypeNotice label="Placeholder profile.">
              The founder's photo, name, title and biography are still to be supplied.
            </PrototypeNotice>
          )}
        </div>
      </div>
    </section>
  );
}
