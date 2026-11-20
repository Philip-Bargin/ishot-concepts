// Light / Night theme (design system: data-theme on <html>). The choice is a per-viewer
// convenience kept in localStorage; storage can be unavailable, so every access is guarded.
const KEY = "ishot-theme";

export function getInitialTheme() {
  try {
    const saved = localStorage.getItem(KEY);
    if (saved === "light" || saved === "dark") return saved;
  } catch {
    /* storage blocked: fall back to light */
  }
  return "light";
}

export function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  try {
    localStorage.setItem(KEY, theme);
  } catch {
    /* not persisted; still applied for this visit */
  }
}

export const prefersReducedMotion = () =>
  typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
