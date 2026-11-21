// ============================================================
//  PROTOTYPE ONLY: simulated availability for the Phase 1 booking flow.
//  Replaced in Phase 2 by the ishot-booking REST API, where the server
//  enforces availability with database slot locks. Nothing here is authoritative.
//
//  Durations below are placeholders until the studio confirms them
//  (docs/content-inventory.md, section A).
// ============================================================
import { services } from "../data.js";

const DURATION_MIN = { s1: 60, s2: 480, s3: 90, s4: 180, s5: 240, s6: 120 };
export const bookingServices = services.map((s) => ({ ...s, durationMin: DURATION_MIN[s.id] }));

export const LEAD_DAYS = 2; // earliest bookable day = today + 2
export const HORIZON_DAYS = 180;
const OPEN_HOUR = 9;
const CLOSE_HOUR = 18;
const DAY_MS = 86400000;

const pad = (n) => String(n).padStart(2, "0");
export const toISO = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const fromISO = (iso) => {
  const [y, m, d] = iso.split("-").map(Number);
  return new Date(y, m - 1, d);
};
const startOfToday = () => {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
};
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

// Deterministic pseudo-random so the prototype looks the same on every visit (FNV-1a).
function hash(str) {
  let h = 2166136261;
  for (let i = 0; i < str.length; i += 1) {
    h ^= str.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return h >>> 0;
}

export const firstBookableDay = () => addDays(startOfToday(), LEAD_DAYS);
export const lastBookableDay = () => addDays(startOfToday(), HORIZON_DAYS);

export function isDayOpen(iso) {
  const d = fromISO(iso);
  if (d < firstBookableDay() || d > lastBookableDay()) return false;
  if (d.getDay() === 0) return false; // closed on Sundays
  return hash(iso) % 9 !== 0; // simulated closures
}

/**
 * Start times for a service on a day. `taken` lists "YYYY-MM-DD|HH:MM" keys lost to
 * conflicts in this session. One free slot per day is "contested": it looks free but is
 * taken by someone else at submit time, to demonstrate the double-booking guard.
 */
export function slotsFor(iso, durationMin, taken = []) {
  if (!isDayOpen(iso)) return [];
  const slots = [];
  for (let h = OPEN_HOUR; h * 60 + durationMin <= CLOSE_HOUR * 60; h += 1) {
    const time = `${pad(h)}:00`;
    slots.push({ time, booked: hash(`${iso}|${time}`) % 4 === 0, contested: false });
  }
  const free = slots.filter((s) => !s.booked);
  if (free.length > 1) free[1].contested = true;
  for (const s of slots) {
    if (taken.includes(`${iso}|${s.time}`)) {
      s.booked = true;
      s.contested = false;
    }
  }
  return slots;
}

/** Calendar cells for the month containing `monthStart`, Monday first. */
export function monthCells(monthStart) {
  const y = monthStart.getFullYear();
  const m = monthStart.getMonth();
  const offset = (new Date(y, m, 1).getDay() + 6) % 7;
  const days = new Date(y, m + 1, 0).getDate();
  const cells = Array.from({ length: offset }, () => null);
  for (let d = 1; d <= days; d += 1) {
    const iso = toISO(new Date(y, m, d));
    cells.push({ iso, day: d, open: isDayOpen(iso) });
  }
  return cells;
}

/**
 * Simulates the server's atomic slot claim. Returns { ok, reference } or
 * { ok: false, alternatives: [{ date, time }] } (the 409 path in Phase 2).
 */
export function attemptBooking({ date, time, durationMin, taken }) {
  const slot = slotsFor(date, durationMin, taken).find((s) => s.time === time);
  if (slot && !slot.booked && !slot.contested) {
    return { ok: true, reference: `ISC-${Math.random().toString(36).slice(2, 6).toUpperCase()}` };
  }
  const alternatives = [];
  const nextTaken = [...taken, `${date}|${time}`];
  for (let i = 0; alternatives.length < 3 && i < 30; i += 1) {
    const iso = toISO(addDays(fromISO(date), i));
    for (const s of slotsFor(iso, durationMin, nextTaken)) {
      if (!s.booked && !s.contested && alternatives.length < 3) alternatives.push({ date: iso, time: s.time });
    }
  }
  return { ok: false, alternatives };
}

const fmtShort = new Intl.DateTimeFormat("en-GB", { weekday: "short", day: "numeric", month: "short" });
const fmtLong = new Intl.DateTimeFormat("en-GB", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
const fmtMonth = new Intl.DateTimeFormat("en-GB", { month: "long", year: "numeric" });
export const formatDate = (iso) => fmtShort.format(fromISO(iso)); // "Sat 14 Dec"
export const formatDateLong = (iso) => fmtLong.format(fromISO(iso));
export const formatMonth = (d) => fmtMonth.format(d);

export function formatDuration(min) {
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (!h) return `${m} min`;
  return `${h} hr${h > 1 ? "s" : ""}${m ? ` ${m} min` : ""}`;
}

export function endTime(time, durationMin) {
  const [h, m] = time.split(":").map(Number);
  const total = h * 60 + m + durationMin;
  return `${pad(Math.floor(total / 60))}:${pad(total % 60)}`;
}

/** iCalendar file for "add to calendar". Monrovia is UTC+0 with no DST, so local time = UTC. */
export function buildIcs({ reference, title, date, time, durationMin }) {
  const stamp = new Date().toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, "");
  const ymd = date.replace(/-/g, "");
  const t = (hhmm) => `${hhmm.replace(":", "")}00Z`;
  return [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//iShot Concept//Booking prototype//EN",
    "BEGIN:VEVENT",
    `UID:${reference}@ishotconcept.com`,
    `DTSTAMP:${stamp}`,
    `DTSTART:${ymd}T${t(time)}`,
    `DTEND:${ymd}T${t(endTime(time, durationMin))}`,
    `SUMMARY:iShot Concept: ${title} (requested)`,
    "LOCATION:Monrovia\\, Liberia",
    `DESCRIPTION:Booking request ${reference}. Awaiting confirmation and deposit.`,
    "END:VEVENT",
    "END:VCALENDAR",
    "",
  ].join("\r\n");
}
