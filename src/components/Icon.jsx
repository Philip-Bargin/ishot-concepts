// Lucide line icons per the design system: 1.5px stroke, square caps, inherit currentColor,
// hidden from assistive tech (the surrounding control carries the label).
export default function Icon({ as: Glyph, size = 16, ...rest }) {
  return <Glyph size={size} strokeWidth={1.5} strokeLinecap="square" aria-hidden="true" focusable="false" {...rest} />;
}
