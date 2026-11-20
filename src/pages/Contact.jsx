import { useRef, useState } from "react";
import { CircleAlert, CircleCheck } from "lucide-react";
import { contact, enquiryTypes } from "../data.js";
import { MAX, validate } from "../lib/validation.js";
import { usePageTitle } from "../lib/hooks.js";
import Icon from "../components/Icon.jsx";
import SocialLinks from "../components/SocialLinks.jsx";
import { CtaBand, ErrorSummary, Field, SectionHeading } from "../components/ui.jsx";

const EMPTY = { name: "", email: "", phone: "", enquiry: "", date: "", message: "", consent: false };
const REQUIRED = ["name", "email", "enquiry", "consent"];
const LABELS = { name: "Full name", email: "Email address", enquiry: "Enquiry type", consent: "Consent" };

function ContactForm() {
  const [form, setForm] = useState(EMPTY);
  const [touched, setTouched] = useState({});
  const [submitted, setSubmitted] = useState(false); // a submit was attempted: show all errors + summary
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState(false);
  const [failure, setFailure] = useState("");
  const [botcheck, setBotcheck] = useState(""); // honeypot: real people leave it empty
  const summaryRef = useRef(null);

  const errors = validate(form, REQUIRED);
  const errorFor = (k) => ((submitted || touched[k]) && errors[k]) || undefined;
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.value }));
  const blur = (k) => () => setTouched((t) => ({ ...t, [k]: true }));

  const onSubmit = async (e) => {
    e.preventDefault();
    if (sending) return;
    setSubmitted(true);
    if (Object.keys(errors).length) {
      requestAnimationFrame(() => summaryRef.current?.focus());
      return;
    }
    if (botcheck) {
      setSent(true); // silent success for bots; nothing is sent
      return;
    }
    const accessKey = import.meta.env.VITE_WEB3FORMS_ACCESS_KEY;
    if (!accessKey) {
      setFailure(`Online enquiries aren't switched on yet. Please email us at ${contact.email}.`);
      return;
    }
    setFailure("");
    setSending(true);
    try {
      const res = await fetch("https://api.web3forms.com/submit", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          access_key: accessKey,
          botcheck, // Web3Forms rejects the submission server-side if this is filled
          subject: `New enquiry: ${form.enquiry}`,
          from_name: "iShot Concept website",
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim() || "Not given",
          enquiry: form.enquiry,
          preferred_date: form.date || "Not given",
          message: form.message.trim() || "Not given",
        }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) throw new Error(data.message || "Request failed");
      setSent(true);
    } catch {
      setFailure(`We couldn't send your message just now. Please try again, or email us at ${contact.email}.`);
    } finally {
      setSending(false);
    }
  };

  const reset = () => {
    setForm(EMPTY);
    setTouched({});
    setSubmitted(false);
    setSent(false);
    setFailure("");
  };

  if (sent) {
    return (
      <div className="fo-alert fo-alert--success" role="status">
        <Icon as={CircleCheck} />
        <div className="ish-stack">
          <strong>Message sent.</strong>
          <p>Thank you. We'll reply by email shortly to talk through your session.</p>
          <button type="button" className="fo-btn fo-btn--text" onClick={reset}>
            Send another message
          </button>
        </div>
      </div>
    );
  }

  return (
    <form className="ish-form" onSubmit={onSubmit} noValidate aria-labelledby="form-title">
      <h2 id="form-title" className="fo-h2">
        Send an enquiry
      </h2>
      {submitted && <ErrorSummary errors={errors} labels={LABELS} innerRef={summaryRef} />}

      <div className="ish-form__row">
        <Field id="f-name" label="Full name" required error={errorFor("name")}>
          {(p) => <input {...p} className="fo-input" value={form.name} onChange={set("name")} onBlur={blur("name")} autoComplete="name" maxLength={MAX.name} />}
        </Field>
        <Field id="f-email" label="Email address" required error={errorFor("email")}>
          {(p) => (
            <input {...p} className="fo-input" type="email" inputMode="email" value={form.email} onChange={set("email")} onBlur={blur("email")} autoComplete="email" maxLength={MAX.email} />
          )}
        </Field>
      </div>
      <div className="ish-form__row">
        <Field id="f-phone" label="Phone number" help="Optional. We may call or WhatsApp you.">
          {(p) => <input {...p} className="fo-input" type="tel" inputMode="tel" value={form.phone} onChange={set("phone")} autoComplete="tel" maxLength={MAX.phone} />}
        </Field>
        <Field id="f-enquiry" label="Enquiry type" required error={errorFor("enquiry")}>
          {(p) => (
            <select {...p} className="fo-select" value={form.enquiry} onChange={set("enquiry")} onBlur={blur("enquiry")}>
              <option value="">Choose one</option>
              {enquiryTypes.map((t) => (
                <option key={t}>{t}</option>
              ))}
            </select>
          )}
        </Field>
      </div>
      <Field id="f-date" label="Preferred date" help="Optional. We'll confirm availability.">
        {(p) => <input {...p} className="fo-input" type="date" value={form.date} onChange={set("date")} />}
      </Field>
      <Field id="f-message" label="Message">
        {(p) => (
          <textarea {...p} className="fo-textarea" value={form.message} onChange={set("message")} placeholder="Tell us about your moment or idea" maxLength={MAX.message} />
        )}
      </Field>

      <div className="fo-field">
        <label className="fo-check" htmlFor="f-consent">
          <input
            id="f-consent"
            type="checkbox"
            checked={form.consent}
            onChange={set("consent")}
            onBlur={blur("consent")}
            aria-required="true"
            aria-invalid={errorFor("consent") ? true : undefined}
            aria-describedby={errorFor("consent") ? "f-consent-error" : undefined}
          />
          <span>
            I agree to iShot Concept using these details to reply to my enquiry. <span className="fo-req" aria-hidden="true">*</span>
          </span>
        </label>
        {errorFor("consent") && (
          <p id="f-consent-error" className="fo-error">
            <Icon as={CircleAlert} />
            {errors.consent}
          </p>
        )}
      </div>

      <div className="fo-sr" aria-hidden="true">
        <label htmlFor="f-botcheck">Leave this field empty</label>
        <input id="f-botcheck" type="text" tabIndex={-1} autoComplete="off" value={botcheck} onChange={(e) => setBotcheck(e.target.value)} />
      </div>

      {failure && (
        <div className="fo-alert fo-alert--danger" role="alert">
          <Icon as={CircleAlert} />
          <p>{failure}</p>
        </div>
      )}

      <div className="ish-actions">
        <button type="submit" className="fo-btn fo-btn--primary" aria-busy={sending || undefined}>
          Send message
        </button>
        <p className="fo-help">
          <span aria-hidden="true">*</span> Required. We never share your details.
        </p>
      </div>
    </form>
  );
}

export default function Contact() {
  usePageTitle("Contact");

  return (
    <>
      <section className="fo-section ish-title" aria-labelledby="contact-title">
        <div className="fo-container fo-container--content ish-split ish-split--top">
          <div className="ish-stack ish-stack--lg">
            <p className="fo-eyebrow">Get in touch</p>
            <h1 id="contact-title" className="fo-h1">
              Let's talk
            </h1>
            <p className="lead fo-muted">Tell us about your moment and we'll shape the right session.</p>
            <div className="ish-contact-list">
              <div>
                <p className="h5 fo-display">Studio</p>
                <p className="small fo-muted">{contact.location} · studio & on-location</p>
              </div>
              <div>
                <p className="h5 fo-display">Phone</p>
                <a className="small" href={`tel:${contact.phone}`}>
                  {contact.phone}
                </a>
                <p className="small fo-muted">Call or WhatsApp</p>
              </div>
              <div>
                <p className="h5 fo-display">Email</p>
                <a className="small" href={`mailto:${contact.email}`}>
                  {contact.email}
                </a>
              </div>
            </div>
            <div className="ish-social">
              <SocialLinks />
            </div>
          </div>
          <ContactForm />
        </div>
      </section>

      <section className="fo-section" aria-labelledby="map-title">
        <div className="fo-container">
          <SectionHeading eyebrow="Find us" title={contact.location} id="map-title" />
          <div className="fo-img ish-r-wide">
            <iframe
              title={`Map of ${contact.location}`}
              src="https://www.google.com/maps?q=Monrovia,+Liberia&z=12&output=embed"
              loading="lazy"
              referrerPolicy="strict-origin-when-cross-origin"
            />
          </div>
        </div>
      </section>

      <CtaBand eyebrow="Know your date?" title="Book a session" text="Pick a service, a date and a time in a few steps." label="Start booking" />
    </>
  );
}
