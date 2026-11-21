// Shared form validation (contact + booking). Messages say how to fix the problem
// (design system FormField rule). Validate on blur and on submit, never per keystroke.
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export const rules = {
  name: (v) => (v.trim() ? "" : "Enter your name."),
  email: (v) =>
    !v.trim() ? "Enter your email address." : EMAIL.test(v.trim()) ? "" : "Enter an email address like name@example.com.",
  enquiry: (v) => (v ? "" : "Choose what your enquiry is about."),
  consent: (v) => (v ? "" : "Tick the box so we can reply to you."),
};

export function validate(values, fields) {
  const errors = {};
  for (const key of fields) {
    const msg = rules[key](values[key]);
    if (msg) errors[key] = msg;
  }
  return errors;
}

// Input length caps, mirrored server-side in Phase 2.
export const MAX = { name: 100, email: 254, phone: 30, location: 120, message: 2000 };
