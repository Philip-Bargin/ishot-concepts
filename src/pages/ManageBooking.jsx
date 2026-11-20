import { useEffect, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { Circle, CircleAlert, CircleCheck, Info } from "lucide-react";
import { contact } from "../data.js";
import { attemptBooking, endTime, formatDate, formatDateLong, formatDuration, fromISO } from "../prototype/bookingMock.js";
import { CANCEL_CUTOFF_HOURS, sampleBookings } from "../prototype/sampleContent.js";
import { usePageTitle } from "../lib/hooks.js";
import Icon from "../components/Icon.jsx";
import SlotPicker from "../components/SlotPicker.jsx";
import { PageTitle, PrototypeNotice, StatusBadge } from "../components/ui.jsx";

const startOf = (b) => {
  const d = fromISO(b.date);
  const [h, m] = b.time.split(":").map(Number);
  d.setHours(h, m, 0, 0);
  return d;
};
const hoursUntil = (b) => (startOf(b) - new Date()) / 36e5;

function Timeline({ status }) {
  const order = ["requested", "deposit", "confirmed", "session"];
  const reached = { requested: 0, awaiting_deposit: 0, confirmed: 2, completed: 3 }[status] ?? 0;
  const labels = { requested: "Request received", deposit: "Deposit received", confirmed: "Booking confirmed", session: "Session day" };
  return (
    <ol className="ish-timeline">
      {order.map((key, i) => {
        const done = i <= reached;
        return (
          <li key={key} className={done ? "is-done" : undefined}>
            <Icon as={done ? CircleCheck : Circle} size={20} />
            <span>
              {labels[key]}
              <span className="fo-sr">{done ? " (done)" : " (to come)"}</span>
            </span>
          </li>
        );
      })}
    </ol>
  );
}

export default function ManageBooking() {
  const { token } = useParams();
  const initial = sampleBookings.find((b) => b.token === token);
  usePageTitle(initial ? "Manage booking" : "Link not valid");

  const [booking, setBooking] = useState(initial);
  const [mode, setMode] = useState("view"); // view | reschedule | cancel
  const [date, setDate] = useState(null);
  const [time, setTime] = useState(null);
  const [taken, setTaken] = useState([]);
  const [notice, setNotice] = useState(null); // { kind: "success" | "danger", text }
  const panelRef = useRef(null);
  const noticeRef = useRef(null);

  useEffect(() => {
    if (mode !== "view") panelRef.current?.focus();
  }, [mode]);
  useEffect(() => {
    if (notice) noticeRef.current?.focus();
  }, [notice]);

  if (!booking) {
    return (
      <>
        <PageTitle eyebrow="Manage booking" title="This link isn't valid" lead="It may have expired, or the booking was changed. Use the newest email from us, or get in touch." />
        <section className="fo-section">
          <div className="fo-container ish-actions">
            <Link to="/contact" className="fo-btn fo-btn--primary">
              Contact us
            </Link>
            <Link to="/account" className="fo-btn fo-btn--outline">
              My bookings
            </Link>
          </div>
        </section>
      </>
    );
  }

  const { service, reference, status } = booking;
  const upcoming = (status === "awaiting_deposit" || status === "confirmed") && hoursUntil(booking) > 0;
  const canChange = upcoming && hoursUntil(booking) >= CANCEL_CUTOFF_HOURS;

  const confirmReschedule = () => {
    if (!date || !time) return;
    const result = attemptBooking({ date, time, durationMin: service.durationMin, taken });
    if (!result.ok) {
      setTaken((t) => [...t, `${date}|${time}`]);
      setTime(null);
      setNotice({ kind: "danger", text: "That time was just booked by someone else. Your original booking is unchanged; choose another time." });
      return;
    }
    setBooking((b) => ({ ...b, date, time }));
    setMode("view");
    setNotice({ kind: "success", text: `Rescheduled to ${formatDateLong(date)}, ${time}. We've emailed you the new details.` });
  };

  const confirmCancel = () => {
    setBooking((b) => ({ ...b, status: "cancelled" }));
    setMode("view");
    setNotice({ kind: "success", text: "Your booking is cancelled. We've emailed you a confirmation." });
  };

  return (
    <>
      <section className="fo-section ish-title ish-title--compact" aria-labelledby="manage-title">
        <div className="fo-container fo-container--content ish-stack ish-stack--lg">
          <PrototypeNotice>This is a sample booking. Changes here are simulated and reset when you reload.</PrototypeNotice>
          <p className="fo-eyebrow">Reference {reference}</p>
          <h1 id="manage-title" className="fo-h1">
            Your booking
          </h1>
          <StatusBadge status={status} />
        </div>
      </section>

      <section className="fo-section" aria-label="Booking details">
        <div className="fo-container fo-container--content ish-book">
          <div className="ish-stack ish-stack--lg">
            {notice && (
              <div ref={noticeRef} tabIndex={-1} className={`fo-alert fo-alert--${notice.kind}`} role={notice.kind === "danger" ? "alert" : "status"}>
                <Icon as={notice.kind === "danger" ? CircleAlert : CircleCheck} />
                <p>{notice.text}</p>
              </div>
            )}

            {status === "awaiting_deposit" && (
              <div className="fo-alert ish-alert--info">
                <Icon as={Info} />
                <div className="ish-stack">
                  <strong>Deposit needed to confirm</strong>
                  <p>
                    We're holding this time for {booking.holdHours} hours from your request. Pay the deposit by bank
                    transfer, mobile money or cash at the studio, quoting reference {reference}. Payment details are in your
                    email.
                  </p>
                </div>
              </div>
            )}

            {mode === "view" && (
              <>
                <h2 className="h4 fo-display">Progress</h2>
                <Timeline status={status} />
                {upcoming && (
                  <div className="ish-stack">
                    <div className="ish-actions">
                      <button type="button" className="fo-btn fo-btn--secondary" aria-disabled={!canChange || undefined} onClick={() => canChange && setMode("reschedule")}>
                        Reschedule
                      </button>
                      <button type="button" className="fo-btn fo-btn--outline" aria-disabled={!canChange || undefined} onClick={() => canChange && setMode("cancel")}>
                        Cancel booking
                      </button>
                    </div>
                    <p className="fo-help">
                      {canChange
                        ? `You can change or cancel online up to ${CANCEL_CUTOFF_HOURS} hours before your session.`
                        : `Your session is less than ${CANCEL_CUTOFF_HOURS} hours away, so changes are handled by phone or WhatsApp: ${contact.phone}.`}
                    </p>
                  </div>
                )}
              </>
            )}

            {mode === "reschedule" && (
              <div className="ish-stack ish-stack--lg">
                <h2 ref={panelRef} tabIndex={-1} className="h4 fo-display ish-step-title">
                  Choose a new time
                </h2>
                <p className="small fo-muted">Your current time stays booked until you confirm a new one.</p>
                <SlotPicker
                  service={service}
                  date={date}
                  time={time}
                  taken={taken}
                  exclude={{ date: booking.date, time: booking.time }}
                  idPrefix="resched"
                  onDate={(iso) => {
                    setDate(iso);
                    setTime(null);
                  }}
                  onTime={setTime}
                />
                <div className="ish-actions">
                  <button type="button" className="fo-btn fo-btn--primary" aria-disabled={!time || undefined} onClick={confirmReschedule}>
                    Confirm new time
                  </button>
                  <button type="button" className="fo-btn fo-btn--text" onClick={() => setMode("view")}>
                    Keep current time
                  </button>
                </div>
              </div>
            )}

            {mode === "cancel" && (
              <div className="ish-stack ish-stack--lg">
                <h2 ref={panelRef} tabIndex={-1} className="h4 fo-display ish-step-title">
                  Cancel this booking?
                </h2>
                <p className="body">
                  {service.title} on {formatDateLong(booking.date)} at {booking.time} will be released for other clients. How
                  any deposit is handled follows the studio's cancellation policy, which is confirmed in your email.
                </p>
                <div className="ish-actions">
                  <button type="button" className="fo-btn fo-btn--primary" onClick={confirmCancel}>
                    Yes, cancel booking
                  </button>
                  <button type="button" className="fo-btn fo-btn--text" onClick={() => setMode("view")}>
                    Keep my booking
                  </button>
                </div>
              </div>
            )}
          </div>

          <aside className="fo-summary" aria-labelledby="details-title">
            <h2 id="details-title" className="h5 fo-display">
              Details
            </h2>
            <dl>
              <dt>Service</dt>
              <dd>{service.short}</dd>
              <dt>Date</dt>
              <dd>{formatDate(booking.date)}</dd>
              <dt>Time</dt>
              <dd>
                {booking.time}–{endTime(booking.time, service.durationMin)}
              </dd>
              <dt>Duration</dt>
              <dd>{formatDuration(service.durationMin)}</dd>
              <dt>Location</dt>
              <dd>{contact.location}</dd>
            </dl>
          </aside>
        </div>
      </section>
    </>
  );
}
