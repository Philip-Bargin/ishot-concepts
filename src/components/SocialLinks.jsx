import { socials } from "../data.js";

// Brand glyphs drawn as line icons to match the design system's Lucide set
// (24 viewBox, 1.5px stroke, currentColor). Instagram and Facebook follow the
// design system's own Footer preview; WhatsApp is the handset-in-bubble mark.
const GLYPHS = {
  instagram: (
    <>
      <rect x="3" y="3" width="18" height="18" rx="5" />
      <circle cx="12" cy="12" r="4" />
      <circle cx="17.5" cy="6.5" r="0.6" fill="currentColor" />
    </>
  ),
  facebook: <path d="M14 8h3V4h-3a4 4 0 0 0-4 4v3H7v4h3v7h4v-7h3l1-4h-4V8z" />,
  whatsapp: (
    <>
      <path d="M3.5 20.5l1.3-4.3A8.5 8.5 0 1 1 7.9 19.3z" />
      <path d="M9 8.2c-.2 3.3 3.4 6.9 6.8 6.8l.9-1.7-2.1-1-1 .9a5 5 0 0 1-2.6-2.6l.9-1-1-2.1z" />
    </>
  ),
};

/** Brand line icon (instagram | facebook | whatsapp); decorative, the control carries the label. */
export function BrandIcon({ id }) {
  return (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true" focusable="false">
      {GLYPHS[id]}
    </svg>
  );
}

/** Instagram, Facebook, WhatsApp. A platform without a supplied URL renders inactive and labelled. */
export default function SocialLinks() {
  return (
    <>
      {socials.map((s) =>
        s.url ? (
          <a key={s.id} className="fo-icon-btn" href={s.url} target="_blank" rel="noopener noreferrer" aria-label={`${s.label} (opens in a new tab)`}>
            <BrandIcon id={s.id} />
          </a>
        ) : (
          <span key={s.id} className="fo-icon-btn ish-social--pending" role="img" aria-label={`${s.label}: link not added yet`} title={`${s.label}: link not added yet`}>
            <BrandIcon id={s.id} />
          </span>
        ),
      )}
    </>
  );
}
