import { Link } from "react-router-dom";
import { endTime, formatDateLong, fromISO } from "../prototype/bookingMock.js";
import { sampleBookings, sampleClient } from "../prototype/sampleContent.js";
import { usePageTitle } from "../lib/hooks.js";
import { PageTitle, PrototypeNotice, StatusBadge } from "../components/ui.jsx";

const isUpcoming = (b) => b.status !== "cancelled" && b.status !== "completed" && fromISO(b.date) >= new Date(new Date().toDateString());

function BookingRow({ booking }) {
  const { service, date, time, reference, status, token } = booking;
  return (
    <li className="ish-booking">
      <div className="ish-stack">
        <StatusBadge status={status} />
        <h3 className="h5 fo-display">{service.title}</h3>
        <p className="small fo-muted">
          {formatDateLong(date)}, {time}–{endTime(time, service.durationMin)} · Ref {reference}
        </p>
      </div>
      <Link to={`/manage/${token}`} className="fo-btn fo-btn--outline fo-btn--sm">
        {isUpcoming(booking) ? "Manage" : "View"}
        <span className="fo-sr">
          {" "}
          {service.title} on {formatDateLong(date)}
        </span>
      </Link>
    </li>
  );
}

export default function Account() {
  usePageTitle("My bookings");
  const upcoming = sampleBookings.filter(isUpcoming).sort((a, b) => a.date.localeCompare(b.date));
  const past = sampleBookings.filter((b) => !isUpcoming(b));

  return (
    <>
      <PageTitle compact eyebrow="Your account" title="My bookings" lead={`Signed in as ${sampleClient.name} (${sampleClient.email}).`} />

      <section className="fo-section" aria-labelledby="upcoming-title">
        <div className="fo-container fo-container--content ish-stack ish-stack--lg">
          <PrototypeNotice>
            You're seeing a sample client account. Sign-in by emailed link and real bookings arrive with the booking plugin
            in Phase 2.
          </PrototypeNotice>
          <div className="fo-heading__row">
            <h2 id="upcoming-title" className="fo-h2">
              Upcoming
            </h2>
            <Link to="/book" className="fo-btn fo-btn--primary">
              Book a session
            </Link>
          </div>
          {upcoming.length ? (
            <ul className="ish-plain">
              {upcoming.map((b) => (
                <BookingRow key={b.token} booking={b} />
              ))}
            </ul>
          ) : (
            <p className="body fo-muted">No upcoming sessions yet.</p>
          )}
        </div>
      </section>

      <section className="fo-section fo-mist" aria-labelledby="past-title">
        <div className="fo-container fo-container--content ish-stack ish-stack--lg">
          <h2 id="past-title" className="fo-h2">
            Past & cancelled
          </h2>
          <ul className="ish-plain">
            {past.map((b) => (
              <BookingRow key={b.token} booking={b} />
            ))}
          </ul>
          <p className="small fo-muted">
            Booked as a guest? Every confirmation email has a private link to manage that booking, no password needed.
          </p>
        </div>
      </section>
    </>
  );
}
