import { logo } from "../assets.js";

// Renders the real logo image when one is set in assets.js (logo.src),
// otherwise falls back to the text wordmark: bold "iSHOT" + script "Concept".
export default function Logo({ scriptSize = 30 }) {
  if (logo.src) {
    return (
      <img
        src={logo.src}
        alt={logo.alt}
        style={{ height: logo.height, width: "auto", display: "block" }}
      />
    );
  }

  return (
    <>
      <span className="mark">iSHOT</span>
      <span className="script" style={{ fontSize: scriptSize }}>
        Concept
      </span>
    </>
  );
}
