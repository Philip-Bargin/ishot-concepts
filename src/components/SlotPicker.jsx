import { useMemo, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import Icon from "./Icon.jsx";
import {
  firstBookableDay,
  formatDate,
  formatDateLong,
  formatMonth,
  fromISO,
  lastBookableDay,
  monthCells,
  slotsFor,
} from "../prototype/bookingMock.js";

const DOW = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const monthStart = (d) => new Date(d.getFullYear(), d.getMonth(), 1);

function Calendar({ month, onMonth, selected, onSelect }) {
  const canPrev = month > monthStart(firstBookableDay());
  const canNext = month < monthStart(lastBookableDay());
  const shift = (n) => onMonth(new Date(month.getFullYear(), month.getMonth() + n, 1));

  return (
    <div className="ish-cal">
      <div className="ish-cal__head">
        <button type="button" className="fo-icon-btn" onClick={() => shift(-1)} disabled={!canPrev} aria-label="Previous month">
          <Icon as={ChevronLeft} />
        </button>
        <p className="h5 fo-display" aria-live="polite">
          {formatMonth(month)}
        </p>
        <button type="button" className="fo-icon-btn" onClick={() => shift(1)} disabled={!canNext} aria-label="Next month">
          <Icon as={ChevronRight} />
        </button>
      </div>
      <div className="ish-cal__grid" role="group" aria-label={`Dates in ${formatMonth(month)}`}>
        {DOW.map((d) => (
          <span key={d} className="ish-cal__dow fo-eyebrow" aria-hidden="true">
            {d}
          </span>
        ))}
        {monthCells(month).map((cell, i) =>
          cell ? (
            <button
              key={cell.iso}
              type="button"
              className="ish-cal__day"
              disabled={!cell.open}
              aria-pressed={selected === cell.iso}
              aria-label={formatDateLong(cell.iso)}
              onClick={() => onSelect(cell.iso)}
            >
              {cell.day}
            </button>
          ) : (
            <span key={`blank-${i}`} aria-hidden="true" />
          )
        )}
      </div>
    </div>
  );
}

/**
 * Month calendar + start-time slots (design system BookingForm step 2).
 * Selected slot = highlight fill + aria-pressed; booked = disabled, struck through, labelled "booked".
 * `exclude` hides one date/time (the client's current slot when rescheduling).
 */
export default function SlotPicker({ service, date, time, onDate, onTime, taken = [], exclude, idPrefix = "slots" }) {
  const [month, setMonth] = useState(() => monthStart(date ? fromISO(date) : firstBookableDay()));
  const slots = useMemo(() => {
    if (!date || !service) return [];
    return slotsFor(date, service.durationMin, taken).map((s) =>
      exclude && exclude.date === date && exclude.time === s.time ? { ...s, booked: true, current: true } : s
    );
  }, [date, service, taken, exclude]);

  return (
    <div className="ish-stack ish-stack--lg">
      <Calendar month={month} onMonth={setMonth} selected={date} onSelect={onDate} />
      {date &&
        (slots.some((s) => !s.booked) ? (
          <div className="ish-stack">
            <p className="fo-label" id={`${idPrefix}-label`}>
              Start times on {formatDate(date)}
            </p>
            <div className="fo-slots" role="group" aria-labelledby={`${idPrefix}-label`}>
              {slots.map((s) => (
                <button key={s.time} type="button" className="fo-slot" disabled={s.booked} aria-pressed={time === s.time} onClick={() => onTime(s.time)}>
                  {s.time}
                  {s.booked && <span className="ish-slot__note">{s.current ? "your time" : "booked"}</span>}
                </button>
              ))}
            </div>
          </div>
        ) : (
          <p className="body fo-muted">No times left on this day. Try another date.</p>
        ))}
    </div>
  );
}
