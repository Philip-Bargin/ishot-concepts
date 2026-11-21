import { useEffect, useRef } from "react";

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Modal behaviour for dialogs (menu sheet, lightbox):
 * moves focus inside (to [data-autofocus] or the first focusable element), traps Tab,
 * closes on Escape, locks body scroll, and returns focus to the opener on close.
 */
export function useFocusTrap(ref, onEscape) {
  const escapeRef = useRef(onEscape);
  escapeRef.current = onEscape;

  useEffect(() => {
    const root = ref.current;
    if (!root) return undefined;
    const opener = document.activeElement;
    const items = () => [...root.querySelectorAll(FOCUSABLE)].filter((el) => el.getClientRects().length > 0);

    (root.querySelector("[data-autofocus]") ?? items()[0])?.focus();

    const onKey = (e) => {
      if (e.key === "Escape") {
        e.preventDefault();
        escapeRef.current?.();
        return;
      }
      if (e.key !== "Tab") return;
      const list = items();
      if (!list.length) return;
      const first = list[0];
      const last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    };

    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = prevOverflow;
      if (opener instanceof HTMLElement && opener.isConnected) opener.focus();
    };
  }, [ref]);
}

/** Sets the document title for the current page. */
export function usePageTitle(title) {
  useEffect(() => {
    document.title = title ? `${title} · iShot Concept` : "iShot Concept · Premium photography, Monrovia";
  }, [title]);
}
