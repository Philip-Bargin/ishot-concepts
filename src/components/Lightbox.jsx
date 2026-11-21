import { useCallback, useEffect, useRef } from "react";
import { ChevronLeft, ChevronRight, X } from "lucide-react";
import { srcFor, srcSetFor } from "../assets.js";
import { categoryLabel, pad2 } from "../data.js";
import { useFocusTrap } from "../lib/hooks.js";
import Icon from "./Icon.jsx";

const SWIPE_MIN = 50;

/**
 * Design-system Lightbox: counter + close bar, prev/next stage, caption.
 * Keyboard: ←/→ navigate, Esc closes; focus is trapped and returns to the opening tile.
 * Touch: horizontal swipe. Neighbouring images are preloaded. Navigation wraps.
 */
export default function Lightbox({ items, index, onIndex, onClose }) {
  const ref = useRef(null);
  const swipeStart = useRef(null);
  useFocusTrap(ref, onClose);

  const total = items.length;
  const item = items[index];
  const label = categoryLabel(item.cat);
  const go = useCallback((step) => onIndex((index + step + total) % total), [index, total, onIndex]);

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "ArrowRight") go(1);
      if (e.key === "ArrowLeft") go(-1);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [go]);

  useEffect(() => {
    if (total < 2) return;
    for (const p of [items[(index + 1) % total], items[(index - 1 + total) % total]]) {
      const img = new Image();
      img.sizes = "100vw";
      img.srcset = srcSetFor(p);
    }
  }, [index, items, total]);

  return (
    <div
      ref={ref}
      className="fo-lightbox"
      role="dialog"
      aria-modal="true"
      aria-label={`${label} photograph, ${index + 1} of ${total}`}
      onClick={(e) => e.target === e.currentTarget && onClose()}
    >
      <div className="fo-lightbox__bar">
        <span>
          {pad2(index + 1)} / {pad2(total)} · {label}
        </span>
        <button type="button" className="fo-icon-btn" onClick={onClose} aria-label="Close" data-autofocus="">
          <Icon as={X} />
        </button>
      </div>

      <div
        className="fo-lightbox__stage"
        onPointerDown={(e) => {
          swipeStart.current = e.clientX;
        }}
        onPointerUp={(e) => {
          if (swipeStart.current == null) return;
          const dx = e.clientX - swipeStart.current;
          swipeStart.current = null;
          if (Math.abs(dx) >= SWIPE_MIN) go(dx < 0 ? 1 : -1);
        }}
      >
        <button type="button" className="fo-icon-btn" onClick={() => go(-1)} aria-label="Previous photograph" disabled={total < 2}>
          <Icon as={ChevronLeft} />
        </button>
        <figure className="ish-lb__figure">
          <img key={item.file} className="ish-lb__img" src={srcFor(item, 1600)} srcSet={srcSetFor(item)} sizes="100vw" alt={item.alt} draggable="false" />
        </figure>
        <button type="button" className="fo-icon-btn" onClick={() => go(1)} aria-label="Next photograph" disabled={total < 2}>
          <Icon as={ChevronRight} />
        </button>
      </div>

      <p className="ish-lb__caption small" aria-hidden="true">
        {item.alt}
      </p>
    </div>
  );
}
