import { logos } from "../assets.js";

// Official logo files only (never retyped). "auto" shows full colour on light grounds and
// swaps to the reversed white file in the Night theme; "reversed" is for navy bands and photos.
export default function Logo({ variant = "auto" }) {
  if (variant === "reversed") return <img src={logos.reversed} alt={logos.alt} />;
  return (
    <>
      <img className="ish-logo__full" src={logos.full} alt={logos.alt} />
      <img className="ish-logo__rev" src={logos.reversed} alt={logos.alt} />
    </>
  );
}
