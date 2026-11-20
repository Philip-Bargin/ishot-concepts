import { useEffect, useRef, useState } from "react";
import { prefersReducedMotion } from "../lib/theme.js";

/**
 * Design-system scroll reveal (.fo-reveal → .is-in): fades and lifts once when 15% visible.
 * Use at most once per section and never above the fold. Under reduced motion the content
 * is shown immediately (the design system CSS also forces it visible).
 */
export default function Reveal({ as: Tag = "div", className = "", children, ...rest }) {
  const ref = useRef(null);
  const [inView, setInView] = useState(prefersReducedMotion);

  useEffect(() => {
    if (inView) return undefined;
    const el = ref.current;
    if (!el || !("IntersectionObserver" in window)) {
      setInView(true);
      return undefined;
    }
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setInView(true);
          io.disconnect();
        }
      },
      { threshold: 0.15 }
    );
    io.observe(el);
    return () => io.disconnect();
  }, [inView]);

  return (
    <Tag ref={ref} className={`fo-reveal${inView ? " is-in" : ""}${className ? ` ${className}` : ""}`} {...rest}>
      {children}
    </Tag>
  );
}
