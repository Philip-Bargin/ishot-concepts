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

## Status

- Done:
  - domain layer
  - schema
  - atomic create with conflict detection
  - idempotent retries
  - status transitions with an optimistic version check
  - audit log
  - concurrency proof
- Next:
  - activation hook (InnoDB check, schema install)
  - studio settings
  - REST API (`ishot/v1`)
  - roles and capabilities
  - hold expiry via Action Scheduler
  - reschedule
  - client tokens
  - notifications
  - admin screens
  - privacy exporter and eraser
  - manual-deposit payment gateway
