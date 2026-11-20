import { useEffect, useRef, useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { CalendarPlus, CircleAlert, CircleCheck, Info, MessageCircle } from "lucide-react";
import { contact, pad2 } from "../data.js";
import { MAX, validate } from "../lib/validation.js";
import { usePageTitle } from "../lib/hooks.js";
import Icon from "../components/Icon.jsx";
import { ErrorSummary, Field } from "../components/ui.jsx";
import SlotPicker from "../components/SlotPicker.jsx";
import {
  attemptBooking,
  bookingServices,
  buildIcs,
  endTime,
  formatDate,
  formatDateLong,
  formatDuration,
} from "../prototype/bookingMock.js";

const STEPS = ["Service", "Date & time", "Your details", "Confirm"];
const EMPTY = { name: "", email: "", phone: "", location: "", notes: "", consent: false };
const REQUIRED = ["name", "email", "consent"];
const LABELS = { name: "Full name", email: "Email address", consent: "Consent" };
const HINTS = ["Choose a service to continue.", "Choose a date and a start time to continue.", "", ""];

function Summary({ service, date, time }) {
  return (
    <aside className="fo-summary ish-book__aside" aria-labelledby="summary-title">
      <h2 id="summary-title" className="h5 fo-display">
        Your booking
      </h2>
      <dl>
        <dt>Service</dt>
        <dd>{service ? service.short : "Not chosen"}</dd>
        <dt>Date</dt>
        <dd>{date ? formatDate(date) : "Not chosen"}</dd>
        <dt>Time</dt>
        <dd>{time && service ? `${time}–${endTime(time, service.durationMin)}` : "Not chosen"}</dd>
        <dt>Duration</dt>
        <dd>{service ? formatDuration(service.durationMin) : "–"}</dd>
        <dt>Deposit</dt>
        <dd>Instructions by email</dd>
      </dl>
      {time && <span className="fo-badge fo-badge--success">Available</span>}
    </aside>
  );
}

export default function Book() {
  usePageTitle("Book a session");
  const [params] = useSearchParams();
  const preset = bookingServices.find((s) => s.id === params.get("service"));

  const [step, setStep] = useState(preset ? 1 : 0);
  const [serviceId, setServiceId] = useState(preset?.id ?? null);
  const [date, setDate] = useState(null);
  const [time, setTime] = useState(null);
  const [details, setDetails] = useState(EMPTY);
  const [touched, setTouched] = useState({});
  const [detailsSubmitted, setDetailsSubmitted] = useState(false);
  const [status, setStatus] = useState("idle"); // idle | sending | conflict | done
  const [alternatives, setAlternatives] = useState([]);
  const [taken, setTaken] = useState([]);
  const [reference, setReference] = useState(null);

  const headingRef = useRef(null);
  const summaryRef = useRef(null);
  const conflictRef = useRef(null);
  const mounted = useRef(false);

  const service = bookingServices.find((s) => s.id === serviceId);
  const errors = validate(details, REQUIRED);
  const errorFor = (k) => ((detailsSubmitted || touched[k]) && errors[k]) || undefined;
  const stepValid = [Boolean(service), Boolean(date && time), Object.keys(errors).length === 0, true][step];

  // Move focus to the new step's heading (design system: booking steps keyboard rule).
  useEffect(() => {
    if (!mounted.current) {
      mounted.current = true;
      return;
    }
    headingRef.current?.focus();
  }, [step, status === "done"]);

  useEffect(() => {
    if (status === "conflict") conflictRef.current?.focus();
  }, [status]);

  const setDetail = (k) => (e) => setDetails((d) => ({ ...d, [k]: e.target.type === "checkbox" ? e.target.checked : e.target.value }));
  const blur = (k) => () => setTouched((t) => ({ ...t, [k]: true }));

  const chooseService = (id) => {
    if (id === serviceId) return;
    setServiceId(id);
    setTime(null); // a different duration changes which start times fit
  };

  const next = () => {
    if (step === 2 && !stepValid) {
      setDetailsSubmitted(true);
      requestAnimationFrame(() => summaryRef.current?.focus());
      return;
    }
    if (!stepValid) return;
    setStep((s) => Math.min(s + 1, STEPS.length - 1));
  };
  const back = () => setStep((s) => Math.max(s - 1, 0));

  const submit = () => {
    if (status === "sending" || !time) return;
    setStatus("sending");
    setTimeout(() => {
      const result = attemptBooking({ date, time, durationMin: service.durationMin, taken });
      if (result.ok) {
        setReference(result.reference);
        setStatus("done");
      } else {
        setTaken((t) => [...t, `${date}|${time}`]);
        setAlternatives(result.alternatives);
        setTime(null);
        setStatus("conflict");
      }
    }, 900);
  };

  const pickAlternative = (alt) => {
    setDate(alt.date);
    setTime(alt.time);
    setStatus("idle");
  };

  const downloadIcs = () => {
    const ics = buildIcs({ reference, title: service.title, date, time, durationMin: service.durationMin });
    const url = URL.createObjectURL(new Blob([ics], { type: "text/calendar;charset=utf-8" }));
    const a = Object.assign(document.createElement("a"), { href: url, download: `ishot-${reference}.ics` });
    a.click();
    URL.revokeObjectURL(url);
  };

  if (status === "done") {
    const whatsapp = `${contact.whatsappUrl}?text=${encodeURIComponent(
      `Hi iShot Concept, I just requested ${service.title} on ${formatDate(date)} at ${time}. Reference ${reference}.`
    )}`;
    return (
      <section className="fo-section ish-title" aria-labelledby="done-title">
        <div className="fo-container fo-container--text ish-stack ish-stack--lg">
          <p className="fo-eyebrow">Reference {reference}</p>
          <h1 id="done-title" ref={headingRef} tabIndex={-1} className="fo-h1 ish-step-title">
            Request received
          </h1>
          <div className="fo-alert fo-alert--success" role="status">
            <Icon as={CircleCheck} />
            <div className="ish-stack">
              <strong>
                {service.title}, {formatDateLong(date)}, {time}–{endTime(time, service.durationMin)}
              </strong>
              <p>
                We've held this time for you. We'll email {details.email} to confirm, with deposit instructions (bank
                transfer, mobile money or cash at the studio). The booking is confirmed once the deposit is received.
              </p>
            </div>
          </div>
          <div className="ish-actions">
            <button type="button" className="fo-btn fo-btn--secondary" onClick={downloadIcs}>
              <Icon as={CalendarPlus} /> Add to calendar
            </button>
            <a className="fo-btn fo-btn--outline" href={whatsapp} target="_blank" rel="noopener noreferrer">
              <Icon as={MessageCircle} /> Message on WhatsApp
            </a>
          </div>
          <Link to="/" className="fo-btn fo-btn--text">
            Back to home
          </Link>
        </div>
      </section>
    );
  }

  const continueButton = (className = "") => (
    <button
      type="button"
      className={`fo-btn fo-btn--primary${className}`}
      aria-disabled={step < 2 && !stepValid ? true : undefined}
      aria-describedby={step < 2 && !stepValid ? "step-hint" : undefined}
      onClick={next}
    >
      Continue
    </button>
  );

  return (
    <>
      <section className="fo-section ish-title ish-title--compact" aria-labelledby="book-title">
        <div className="fo-container ish-stack">
          <p className="fo-eyebrow">Booking</p>
          <h1 id="book-title" className="fo-h1">
            Book a session
          </h1>
          <p className="lead fo-muted ish-measure">Choose a service, a date and a time. No account needed.</p>
          <div className="fo-alert ish-alert--info ish-measure">
            <Icon as={Info} />
            <p>
              <strong>Prototype.</strong> Availability, session lengths and the double-booking check are simulated in your
              browser. Nothing is sent or saved.
            </p>
          </div>
        </div>
      </section>

      <section className="fo-section" aria-label="Booking steps">
        <div className="fo-container fo-container--content ish-book">
          <div>
            <ol className="fo-steps">
              {STEPS.map((label, i) => (
                <li key={label} aria-current={i === step ? "step" : undefined} className={i < step ? "is-done" : undefined}>
                  <span className="fo-sr">Step {i + 1}: </span>
                  {label}
                  {i < step && <span className="fo-sr"> (done)</span>}
                </li>
              ))}
            </ol>

            <h2 ref={headingRef} tabIndex={-1} className="h4 fo-display ish-step-title">
              {pad2(step + 1)} · {STEPS[step]}
            </h2>

            <div className="ish-stack ish-stack--lg">
              {step === 0 && (
                <div className="ish-options" role="group" aria-label="Services">
                  {bookingServices.map((s) => (
                    <button key={s.id} type="button" className="ish-option" aria-pressed={serviceId === s.id} onClick={() => chooseService(s.id)}>
                      <span className="fo-card__title">{s.title}</span>
                      <span className="small fo-muted">
                        {formatDuration(s.durationMin)} · {s.blurb}
                      </span>
                    </button>
                  ))}
                </div>
              )}

              {step === 1 && (
                <SlotPicker
                  service={service}
                  date={date}
                  time={time}
                  taken={taken}
                  onDate={(iso) => {
                    setDate(iso);
                    setTime(null);
                  }}
                  onTime={setTime}
                />
              )}

              {step === 2 && (
                <div className="ish-form">
                  {detailsSubmitted && <ErrorSummary errors={errors} labels={LABELS} innerRef={summaryRef} prefix="b" />}
                  <p className="small fo-muted">Booked with us before? Sign-in arrives with client accounts; for now, just enter your details.</p>
                  <div className="ish-form__row">
                    <Field id="b-name" label="Full name" required error={errorFor("name")}>
                      {(p) => <input {...p} className="fo-input" value={details.name} onChange={setDetail("name")} onBlur={blur("name")} autoComplete="name" maxLength={MAX.name} />}
                    </Field>
                    <Field id="b-email" label="Email address" required error={errorFor("email")} help="We send your confirmation here.">
                      {(p) => (
                        <input {...p} className="fo-input" type="email" inputMode="email" value={details.email} onChange={setDetail("email")} onBlur={blur("email")} autoComplete="email" maxLength={MAX.email} />
                      )}
                    </Field>
                  </div>
                  <div className="ish-form__row">
                    <Field id="b-phone" label="Phone number" help="Optional. For WhatsApp updates.">
                      {(p) => <input {...p} className="fo-input" type="tel" inputMode="tel" value={details.phone} onChange={setDetail("phone")} autoComplete="tel" maxLength={MAX.phone} />}
                    </Field>
                    <Field id="b-location" label="Shoot location" help="Optional. Studio, outdoors, or an address.">
                      {(p) => <input {...p} className="fo-input" value={details.location} onChange={setDetail("location")} maxLength={MAX.location} />}
                    </Field>
                  </div>
                  <Field id="b-notes" label="Ideas & notes">
                    {(p) => <textarea {...p} className="fo-textarea" value={details.notes} onChange={setDetail("notes")} maxLength={MAX.message} />}
                  </Field>
                  <div className="fo-field">
                    <label className="fo-check" htmlFor="b-consent">
                      <input
                        id="b-consent"
                        type="checkbox"
                        checked={details.consent}
                        onChange={setDetail("consent")}
                        onBlur={blur("consent")}
                        aria-required="true"
                        aria-invalid={errorFor("consent") ? true : undefined}
                        aria-describedby={errorFor("consent") ? "b-consent-error" : undefined}
                      />
                      <span>
                        I agree to iShot Concept using these details to manage my booking. <span className="fo-req" aria-hidden="true">*</span>
                      </span>
                    </label>
                    {errorFor("consent") && (
                      <p id="b-consent-error" className="fo-error">
                        <Icon as={CircleAlert} />
                        {errors.consent}
                      </p>
                    )}
                  </div>
                </div>
              )}

              {step === 3 && (
                <>
                  {status === "conflict" && (
                    <div ref={conflictRef} tabIndex={-1} className="fo-alert fo-alert--danger" role="alert">
                      <Icon as={CircleAlert} />
                      <div className="ish-stack">
                        <strong>That time was just booked by someone else.</strong>
                        <p>Your details are kept. Choose one of the nearest free times:</p>
                        <div className="ish-actions">
                          {alternatives.map((alt) => (
                            <button key={`${alt.date}|${alt.time}`} type="button" className="fo-btn fo-btn--outline fo-btn--sm" onClick={() => pickAlternative(alt)}>
                              {formatDate(alt.date)}, {alt.time}
                            </button>
                          ))}
                          <button type="button" className="fo-btn fo-btn--text" onClick={() => setStep(1)}>
                            Pick another date
                          </button>
                        </div>
                      </div>
                    </div>
                  )}
                  <div className="fo-summary">
                  <dl>
                    <dt>Service</dt>
                    <dd>{service.title}</dd>
                    <dt>When</dt>
                    <dd>{date && time ? `${formatDateLong(date)}, ${time}–${endTime(time, service.durationMin)}` : "Choose a new time"}</dd>
                    <dt>Name</dt>
                    <dd>{details.name}</dd>
                    <dt>Email</dt>
                    <dd>{details.email}</dd>
                    {details.phone && (
                      <>
                        <dt>Phone</dt>
                        <dd>{details.phone}</dd>
                      </>
                    )}
                    {details.location && (
                      <>
                        <dt>Location</dt>
                        <dd>{details.location}</dd>
                      </>
                    )}
                  </dl>
                  </div>
                  <p className="small fo-muted">
                    Sending a request holds this time. We confirm by email with deposit instructions; your booking is
                    confirmed once the deposit is received.
                  </p>
                  <div className="ish-actions">
                    <button
                      type="button"
                      className="fo-btn fo-btn--primary"
                      onClick={submit}
                      aria-busy={status === "sending" || undefined}
                      aria-disabled={!time ? true : undefined}
                    >
                      Send request
                    </button>
                  </div>
                </>
              )}
            </div>

            <div className="ish-stepnav">
              {step > 0 ? (
                <button type="button" className="fo-btn fo-btn--text" onClick={back}>
                  Back
                </button>
              ) : (
                <span />
              )}
              {step < 3 && (
                <div className="ish-actions">
                  {!stepValid && HINTS[step] && (
                    <p id="step-hint" className="fo-help">
                      {HINTS[step]}
                    </p>
                  )}
                  {continueButton(" ish-stepnav__continue")}
                </div>
              )}
            </div>
          </div>

          <Summary service={service} date={date} time={time} />
        </div>
      </section>

      {step < 3 && (
        <div className="ish-bookbar">
          <p className="small">
            <strong>{service ? service.short : "No service yet"}</strong>
            <br />
            {date ? formatDate(date) : "Pick a date"}
            {time ? ` · ${time}` : ""}
          </p>
          {continueButton()}
        </div>
      )}
    </>
  );
}

