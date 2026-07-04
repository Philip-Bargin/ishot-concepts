import { useEffect } from "react";

export default function Lightbox({ item, index, total, onClose, onPrev, onNext }) {
  // Lock body scroll for the lifetime of the lightbox only.
  // Kept in its own mount-once effect (empty deps) so the captured
  // original overflow is the page's true value and is restored exactly
  // once on unmount — the keyboard effect below re-runs when the nav
  // callbacks change, which must NOT re-capture/restore the overflow
  // (that could leave the page permanently unscrollable).
  useEffect(() => {
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = prevOverflow;
    };
  }, []);

  // Keyboard navigation: Esc closes, arrows cycle.
  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowRight") onNext();
      if (e.key === "ArrowLeft") onPrev();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose, onPrev, onNext]);

  if (!item) return null;

  const counter = typeof index === "number" && total ? `${index + 1} / ${total}` : "";

  return (
    <div
      onClick={onClose}
      role="dialog"
      aria-modal="true"
      aria-label={`${item.cat} photograph${counter ? `, ${counter}` : ""}`}
      style={{
        position: "fixed",
        inset: 0,
        zIndex: 80,
        background: "rgba(11,24,54,.94)",
        backdropFilter: "blur(6px)",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        padding: "clamp(16px,4vw,48px)",
        animation: "ish-fade .2s ease",
      }}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{ position: "relative", maxWidth: 1000, width: "100%", animation: "ish-lb .3s ease" }}
      >
        <img
          src={item.img}
          alt={`${item.cat} photography by iShot Concept`}
          style={{
            width: "100%",
            maxHeight: "82vh",
            objectFit: "contain",
            borderRadius: 8,
            boxShadow: "0 20px 50px rgba(0,0,0,.5)",
          }}
        />
        <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginTop: 16 }}>
          <button className="lb-btn" onClick={onPrev} aria-label="Previous photograph">
            ‹
          </button>
          <span style={{ font: "600 13px Montserrat, sans-serif", letterSpacing: "0.04em", color: "var(--on-dark-2)" }}>
            {item.cat}
            {counter && <span style={{ color: "var(--on-dark-3)" }}> · {counter}</span>}
          </span>
          <button className="lb-btn" onClick={onNext} aria-label="Next photograph">
            ›
          </button>
        </div>
      </div>
      <button className="lb-btn" onClick={onClose} aria-label="Close" style={{ position: "fixed", top: 22, right: 26 }}>
        ✕
      </button>
    </div>
  );
}
