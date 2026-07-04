import { useState } from "react";
import { contact, serviceOptions } from "../data.js";
import { images } from "../assets.js";
import { PageHero } from "../components/ui.jsx";
import Reveal from "../components/Reveal.jsx";

const EMPTY = { name: "", phone: "", email: "", service: "", date: "", location: "", message: "" };

const validators = {
  name: (v) => (v.trim() ? "" : "Please enter your name."),
  email: (v) =>
    !v.trim() ? "Please enter your email." : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? "" : "Enter a valid email address.",
  service: (v) => (v ? "" : "Please choose a service."),
};

function validate(form) {
  const errors = {};
  for (const key of Object.keys(validators)) {
    const msg = validators[key](form[key]);
    if (msg) errors[key] = msg;
  }
  return errors;
}

const iconWrap = {
  width: 46,
  height: 46,
  borderRadius: "50%",
  background: "#F7F9FC",
  border: "1px solid var(--border)",
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  flex: "none",
};

export default function Contact() {
  const [form, setForm] = useState(EMPTY);
  const [touched, setTouched] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState("");
  const [hp, setHp] = useState(""); // honeypot — real users leave this blank

  const errors = validate(form);
  const showError = (k) => touched[k] && errors[k];

  const setField = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
  const onBlur = (k) => () => setTouched((t) => ({ ...t, [k]: true }));

  const submit = async () => {
    if (submitting) return;
    const errs = validate(form);
    if (Object.keys(errs).length) {
      setTouched({ name: true, email: true, service: true });
      const first = ["name", "email", "service"].find((k) => errs[k]);
      if (first) document.getElementById(`f-${first}`)?.focus();
      return;
    }

    // Bots that fill the hidden honeypot get a silent "success" — no email sent.
    if (hp) {
      setSubmitted(true);
      return;
    }

    const accessKey = import.meta.env.VITE_WEB3FORMS_ACCESS_KEY;
    if (!accessKey) {
      setError(
        `Booking submissions aren't configured yet. Please email us directly at ${contact.email}.`
      );
      return;
    }

    setError("");
    setSubmitting(true);
    try {
      const res = await fetch("https://api.web3forms.com/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          access_key: accessKey,
          subject: `New booking inquiry — ${form.name}`,
          from_name: "iShot Concept Website",
          name: form.name,
          email: form.email,
          phone: form.phone || "—",
          service: form.service,
          preferred_date: form.date || "—",
          shoot_location: form.location || "—",
          message: form.message || "—",
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        throw new Error(data.message || "Request failed");
      }
      setSubmitted(true);
      setForm(EMPTY);
      setTouched({});
    } catch (err) {
      setError(
        `Sorry — we couldn't send your inquiry just now. Please try again, or email us at ${contact.email}.`
      );
    } finally {
      setSubmitting(false);
    }
  };

  const reset = () => {
    setSubmitted(false);
    setForm(EMPTY);
    setTouched({});
    setError("");
  };

  // Labelled field with inline error messaging.
  // NOTE: a plain function (not a nested component) so React keeps the
  // inputs mounted across renders and doesn't drop focus on each keystroke.
  const field = (k, label, required, control) => (
    <div>
      <label className="field-label" htmlFor={`f-${k}`}>
        {label} {required && <span style={{ color: "#F26A22" }}>*</span>}
      </label>
      {control}
      {showError(k) && (
        <p role="alert" style={{ margin: "7px 0 0", font: "500 12.5px Montserrat, sans-serif", color: "#c2410c" }}>
          {errors[k]}
        </p>
      )}
    </div>
  );

  const fieldStyle = (k) => (showError(k) ? { borderColor: "#c2410c", boxShadow: "0 0 0 3px rgba(194,65,12,.12)" } : undefined);

  return (
    <div className="page-enter">
      <PageHero
        eyebrow="Get In Touch"
        title="Let's Create That Magic"
        subtitle="Tell us about your moment and we'll craft the perfect session."
        bg={images.contactHero}
      />

      <section
        className="container section-pad"
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit,minmax(320px,1fr))",
          gap: "clamp(28px,4vw,52px)",
          alignItems: "start",
        }}
      >
        {/* FORM / SUCCESS */}
        <Reveal
          style={{
            background: "#fff",
            border: "1px solid var(--border)",
            borderRadius: 10,
            padding: "clamp(26px,4vw,44px)",
            boxShadow: "var(--sh-2)",
          }}
        >
          {submitted ? (
            <div style={{ textAlign: "center", padding: "28px 8px", animation: "ish-fade .3s ease" }} aria-live="polite">
              <div
                style={{
                  width: 66,
                  height: 66,
                  margin: "0 auto 22px",
                  borderRadius: "50%",
                  background: "#47A743",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                  boxShadow: "0 8px 22px rgba(71,167,67,.35)",
                }}
              >
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </div>
              <h2 className="display" style={{ fontSize: 28, color: "var(--ink)", margin: "0 0 10px" }}>
                Inquiry sent!
              </h2>
              <p style={{ font: "400 16px/1.6 Montserrat, sans-serif", color: "var(--gray)", margin: "0 0 26px" }}>
                Thank you — we'll be in touch shortly to confirm your session details.
              </p>
              <button className="btn btn--outline-blue btn--sm" onClick={reset}>
                Send another inquiry
              </button>
            </div>
          ) : (
            <div>
              <h2 className="display" style={{ fontSize: 26, color: "var(--ink)", margin: "0 0 26px" }}>
                Booking Inquiry
              </h2>
              <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(200px,1fr))", gap: 20 }}>
                {field(
                  "name",
                  "Full Name",
                  true,
                  <input
                    id="f-name"
                    className="field"
                    style={fieldStyle("name")}
                    value={form.name}
                    onChange={setField("name")}
                    onBlur={onBlur("name")}
                    placeholder="Your name"
                    autoComplete="name"
                    aria-required="true"
                    aria-invalid={!!showError("name")}
                  />
                )}
                {field(
                  "phone",
                  "Phone Number",
                  false,
                  <input
                    id="f-phone"
                    className="field"
                    value={form.phone}
                    onChange={setField("phone")}
                    placeholder="0881405259"
                    type="tel"
                    autoComplete="tel"
                    inputMode="tel"
                  />
                )}
                {field(
                  "email",
                  "Email Address",
                  true,
                  <input
                    id="f-email"
                    className="field"
                    style={fieldStyle("email")}
                    value={form.email}
                    onChange={setField("email")}
                    onBlur={onBlur("email")}
                    placeholder="you@email.com"
                    type="email"
                    autoComplete="email"
                    inputMode="email"
                    aria-required="true"
                    aria-invalid={!!showError("email")}
                  />
                )}
                {field(
                  "service",
                  "Service Type",
                  true,
                  <select
                    id="f-service"
                    className="field"
                    style={{ cursor: "pointer", ...fieldStyle("service") }}
                    value={form.service}
                    onChange={setField("service")}
                    onBlur={onBlur("service")}
                    aria-required="true"
                    aria-invalid={!!showError("service")}
                  >
                    <option value="">Select a service</option>
                    {serviceOptions.map((o) => (
                      <option key={o}>{o}</option>
                    ))}
                  </select>
                )}
                {field(
                  "date",
                  "Preferred Date",
                  false,
                  <input id="f-date" className="field" type="date" value={form.date} onChange={setField("date")} />
                )}
                {field(
                  "location",
                  "Shoot Location",
                  false,
                  <input
                    id="f-location"
                    className="field"
                    value={form.location}
                    onChange={setField("location")}
                    placeholder="Studio / Outdoor / City"
                  />
                )}
              </div>
              <div style={{ marginTop: 20 }}>
                <label className="field-label" htmlFor="f-message">
                  Message / Concept Details
                </label>
                <textarea
                  id="f-message"
                  className="field"
                  value={form.message}
                  onChange={setField("message")}
                  placeholder="Tell us about your vision…"
                />
              </div>

              {/* Honeypot: hidden from real users, catches bots. */}
              <input
                type="text"
                name="botcheck"
                tabIndex={-1}
                autoComplete="off"
                value={hp}
                onChange={(e) => setHp(e.target.value)}
                aria-hidden="true"
                style={{ position: "absolute", left: "-9999px", width: 1, height: 1, opacity: 0 }}
              />

              {error && (
                <p
                  role="alert"
                  style={{
                    margin: "20px 0 0",
                    padding: "12px 14px",
                    borderRadius: 6,
                    background: "#FEF2F2",
                    border: "1px solid #FCA5A5",
                    font: "500 13.5px/1.5 Montserrat, sans-serif",
                    color: "#b91c1c",
                  }}
                >
                  {error}
                </p>
              )}

              <button
                className="btn btn--primary"
                onClick={submit}
                disabled={submitting}
                style={{ marginTop: 24, width: "100%", height: 50, fontSize: 15 }}
              >
                {submitting && (
                  <span
                    aria-hidden="true"
                    style={{
                      width: 18,
                      height: 18,
                      border: "2px solid rgba(255,255,255,.4)",
                      borderTopColor: "#fff",
                      borderRadius: "50%",
                      animation: "ish-spin .7s linear infinite",
                    }}
                  />
                )}
                {submitting ? "Sending…" : "Send Booking Inquiry"}
                {!submitting && <span className="arw">→</span>}
              </button>
              <p style={{ font: "400 12px Montserrat, sans-serif", color: "var(--gray-muted)", margin: "14px 0 0", textAlign: "center" }}>
                <span style={{ color: "#F26A22" }}>*</span> Required. We'll never share your details.
              </p>
            </div>
          )}
        </Reveal>

        {/* DETAILS */}
        <Reveal delay={120}>
          <h2 className="display" style={{ fontSize: 26, color: "var(--ink)", margin: "0 0 24px" }}>
            Contact Details
          </h2>
          <div style={{ display: "flex", flexDirection: "column", gap: 16, marginBottom: 28 }}>
            <div style={{ display: "flex", gap: 14, alignItems: "center" }}>
              <span style={iconWrap} aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#329AD3" strokeWidth="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                  <circle cx="12" cy="10" r="3" />
                </svg>
              </span>
              <div>
                <div style={{ font: "600 15px Montserrat, sans-serif", color: "var(--ink)" }}>{contact.location}</div>
                <div style={{ font: "400 13px Montserrat, sans-serif", color: "var(--gray-muted)" }}>Studio &amp; on-location</div>
              </div>
            </div>

            <div style={{ display: "flex", gap: 14, alignItems: "center" }}>
              <span style={iconWrap} aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#329AD3" strokeWidth="2">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
              </span>
              <div>
                <a href={`tel:${contact.phone}`} style={{ font: "600 15px Montserrat, sans-serif", color: "var(--ink)", textDecoration: "none" }}>
                  {contact.phone}
                </a>
                <div style={{ font: "400 13px Montserrat, sans-serif", color: "var(--gray-muted)" }}>Call or WhatsApp</div>
              </div>
            </div>

            <div style={{ display: "flex", gap: 14, alignItems: "center" }}>
              <span style={iconWrap} aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#329AD3" strokeWidth="2">
                  <rect x="2" y="4" width="20" height="16" rx="2" />
                  <path d="M22 7l-10 6L2 7" />
                </svg>
              </span>
              <div>
                <a href={`mailto:${contact.email}`} style={{ font: "600 15px Montserrat, sans-serif", color: "var(--ink)", textDecoration: "none" }}>
                  {contact.email}
                </a>
                <div style={{ font: "400 13px Montserrat, sans-serif", color: "var(--gray-muted)" }}>Email us anytime</div>
              </div>
            </div>

            <div style={{ display: "flex", gap: 14, alignItems: "center" }}>
              <span style={iconWrap} aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#329AD3" strokeWidth="2">
                  <rect x="2" y="2" width="20" height="20" rx="5" />
                  <circle cx="12" cy="12" r="4" />
                  <circle cx="17.5" cy="6.5" r="1" fill="#329AD3" stroke="none" />
                </svg>
              </span>
              <div>
                <div style={{ font: "600 15px Montserrat, sans-serif", color: "var(--ink)" }}>{contact.instagram}</div>
                <div style={{ font: "400 13px Montserrat, sans-serif", color: "var(--gray-muted)" }}>Follow the work</div>
              </div>
            </div>
          </div>

          <div style={{ borderRadius: 8, overflow: "hidden", border: "1px solid var(--border)", boxShadow: "var(--sh-1)" }}>
            <iframe
              title={`Google Map showing ${contact.location}`}
              src="https://www.google.com/maps?q=Monrovia,+Liberia&z=12&output=embed"
              width="100%"
              height="240"
              style={{ border: 0, display: "block" }}
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
              allowFullScreen
            />
          </div>
        </Reveal>
      </section>
    </div>
  );
}
