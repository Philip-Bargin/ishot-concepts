import { useState } from "react";
import { categoryLabel } from "../data.js";
import Lightbox from "./Lightbox.jsx";
import { Photo } from "./ui.jsx";

// Masonry tiles alternate 4:5 and 1:1 (never two squares adjacent); each tile opens the Lightbox.
const ratioFor = (i) => (i % 3 === 2 ? "square" : "portrait");

export default function Gallery({ items, className = "" }) {
  const [open, setOpen] = useState(null);

  return (
    <>
      <ul className={`fo-masonry ish-plain${className ? ` ${className}` : ""}`}>
        {items.map((photo, i) => (
          <li key={photo.file}>
            <button type="button" className="ish-tile" aria-haspopup="dialog" onClick={() => setOpen(i)}>
              <Photo photo={photo} ratio={ratioFor(i)} sizes="(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw" />
              <span className="ish-tile__cap fo-eyebrow">{categoryLabel(photo.cat)}</span>
            </button>
          </li>
        ))}
      </ul>
      {open != null && <Lightbox items={items} index={open} onIndex={setOpen} onClose={() => setOpen(null)} />}
    </>
  );
}
