# iShot Booking (WordPress plugin)

Booking management for the iShot Concept studio: availability, conflict-safe bookings, deposits and notifications. This is Phase 2 of the implementation plan.

## How double bookings are prevented

1. A booking's session is widened by its buffers (`NewBooking::blockedRange()`).
2. That range is mapped onto fixed slot units, 30 minutes by default (`Domain\SlotUnits::cover()`). Any unit the range touches counts.
3. `Persistence\BookingRepository::create()` inserts the booking row and one `ishot_slot_locks` row per unit in a single InnoDB transaction.
4. The lock table's primary key `(resource_id, unit_start_utc)` makes MySQL reject a unit that is already held. The transaction rolls back and the caller gets a `SlotConflict` (HTTP 409).
5. Statuses that release a booking (cancelled, expired, completed, no-show) delete its lock rows in the same transaction as the status change.

The UI's availability (`Domain\Availability`) is advisory; the insert is the only authority. `tests/Integration/ConcurrencyTest.php` releases 50 separate processes at the same instant against overlapping times and asserts exactly one booking.

## Layout

| Path | What |
|---|---|
| `src/Domain/` | Pure PHP, no WordPress: time ranges, slot units, lifecycle, policy, opening hours, availability |
| `src/Persistence/` | Schema (dbDelta, InnoDB), repository, conflict and stale-version exceptions |
| `src/Catalog/` | `ishot_service` post type and its booking meta (duration, buffers, price, deposit, bookable) |
| `src/Application/` | Booking use cases: free start times, submitting a request, nearest alternatives |
| `src/Rest/` | `ishot/v1` routes and the per-address rate limiter |
| `src/Settings.php`, `src/Installer.php`, `src/Plugin.php` | Studio policy settings, activation (InnoDB check) and upgrades, hooks |
| `tests/Unit/` | Domain tests (no database) |
| `tests/Integration/` | Real MySQL in the wp-env test container, including the concurrency race |

## Development

Requires Docker. From the repository root:

```sh
npm run wp:start      # WordPress dev site on :8888, test site on :8889
npm run wp:setup      # composer install inside the test container (first time)
npm run test:php      # all PHP tests
npm run test:php:unit # domain tests only (fast)
```

Dev site login: `admin` / `password`.

**If `wp-env start` says the network is unavailable:** wp-env checks connectivity with a raw DNS query. On machines where Node's resolver points at a local address with no DNS server behind it, preload a shim that routes `dns.resolve` through the OS resolver:

```sh
NODE_OPTIONS="--require /path/to/dns-lookup-shim.cjs" npm run wp:start
```

## Public API (`/wp-json/ishot/v1`)

| Route | Notes |
|---|---|
| `GET /services` | Bookable services: id, title, duration, from-price, deposit |
| `GET /availability?service=&date=YYYY-MM-DD` | Free studio-local start times (`H:i`) for that day; `Cache-Control: no-store` |
| `POST /bookings` | Body: `service`, `start` (`YYYY-MM-DDTHH:MM`, studio time), `name`, `email`, `phone`, `location`, `notes`, `consent: true`, `website` (honeypot, leave empty). Optional `Idempotency-Key` header. |

`POST /bookings` responses:
- **201**: `{status, start, end, token}`. The token is the client's manage-booking secret. Only its SHA-256 hash is stored.
- **200** `{replayed: true}`: a retry with the same Idempotency-Key. No new booking is created.
- **202**: the honeypot was filled. Nothing is stored.
- **400**: invalid fields.
- **409** `ishot_slot_taken`: `data.alternatives` lists up to 3 of the nearest free starts.
- **422** `ishot_too_soon | too_far | misaligned | outside_hours | unknown_service`
- **429**: rate limited. The default is 10 requests per 10 minutes per address; change it with the `ishot_booking_rate_limit` filter.

## Status

- Done:
  - domain layer
  - schema
  - atomic create with conflict detection
  - idempotent retries
  - status transitions with an optimistic version check
  - audit log
  - concurrency proof
  - activation (InnoDB check) and upgrades
  - settings
  - services post type
  - public REST routes (services, availability, booking requests) with honeypot, rate limit and hashed tokens
- Next:
  - roles and capabilities
  - staff endpoints and admin screens
  - client manage-booking routes (view, reschedule, cancel by token)
  - hold expiry via Action Scheduler
  - notifications and ICS
  - privacy exporter and eraser
  - manual-deposit payment gateway
