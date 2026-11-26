# Phase 3e: front-end integration requirements

Phase 3e is the booking flow, My bookings, the manage-booking page and the contact form in the WordPress theme. It starts after Phase 3b/3c, which are on hold until the owner approves the Phase 1 visuals.

The backend for all of it is built and tested; this is the contract the front end must follow. API base: `/wp-json/ishot/v1`. Full details are in `wordpress/wp-content/plugins/ishot-booking/README.md`.

## 1. Booking flow (`/book`)

| Step | Call | Notes |
|---|---|---|
| Services | `GET /services` | Published, bookable, approved services only. |
| Times | `GET /availability?service=&date=YYYY-MM-DD` | Advisory only; the server re-checks on submit. |
| Submit | `POST /bookings` | Send an `Idempotency-Key` header (8-64 `[A-Za-z0-9_-]`) so a double click can't book twice. Include the hidden honeypot field `website`, left empty. `consent: true` is required. |
| Account option | `create_account: true` | Offered to guests only. The response's `account` is `check_email` or `not_created`; show the same neutral text for both (for example "If you don't have an account yet, check your email to set a password"). Never say "an account already exists". |
| Clash | 409 `ishot_slot_taken` | Show `data.alternatives` (ISO times) as one-tap choices, as the prototype does. |
| Policy | 422 `ishot_*` | Show `message` next to the date/time field. |
| Busy | 503 `ishot_busy` (header `Retry-After: 2`, `data.retry_after` = 2) | Rare: the database was too contended to save. Nothing was stored. Keep the form filled in, show "The calendar is busy right now. Nothing was changed. Please try again in a moment." with a "Try again" button, wait at least `retry_after` seconds before resending, and resend with the **same** `Idempotency-Key` (so a retry can never create a second booking). Do not auto-retry more than once. |
| Success | 201 | Has `token`. Build the manage link with the fragment convention (section 3) and **never put the token in a URL path or query**. |

## 2. My bookings (`/account`)

- Signed-in clients only. Call `GET /me/bookings?page=&per_page=&when=upcoming|past|all` with the `X-WP-Nonce` header (from `wp_create_nonce('wp_rest')` printed into the page).
- **Pagination:** read `X-WP-Total` and `X-WP-TotalPages`; `per_page` is at most 50.
- **401:** show sign-in. **403:** the account isn't a client account.
- **Explain the guest-booking rule:** guest bookings made under the same email appear once the client has set a password from an email link. That's how ownership of the address is proved, so the page should say so.
- **No change actions in this list yet.** Changes go through the manage link. If wanted, adding cancel/reschedule for signed-in clients is a backend change: a `PATCH /me/bookings/{id}` reusing `ClientBookings`.

## 3. Manage-booking page (`/manage-booking/`)

**Link format:** `https://<site>/manage-booking/#token=<64 hex>`. Build it with `IShot\Booking\Frontend\ManageUrl::for()` in PHP, or the same format in JS.

**The page must:**
1. Read the token from `location.hash` on load, then **immediately** remove it with `history.replaceState(null, '', location.pathname)`. That keeps it out of browser history, bookmarks and screen shares.
2. Keep the token in memory only: no `localStorage`, `sessionStorage` or cookies.
3. Call `GET /manage` and `PATCH /manage` (`{action:"cancel"}` or `{action:"reschedule", start:"YYYY-MM-DDTHH:MM"}`) with the token in the **`X-Ishot-Manage-Token` header**. Never use the deprecated `/bookings/{token}` route, and never use `?token=` (the server ignores it).
4. Load **no third-party scripts, fonts, images or iframes**. The server sends a Content-Security-Policy allowing only same-origin `script/connect/img`, so any third-party tag will be blocked. Extend it only deliberately, through `ishot_booking_manage_csp`.
5. Handle 404 as "this link has expired or is not valid" (the same text for unknown and expired links), 422 (past the cut-off, or the booking can't be changed), 409 (clash: show alternatives), 429 (wait and retry) and 503 `ishot_busy` (cancel or reschedule: "The calendar is busy right now. Nothing was changed." with a "Try again" button, after at least `Retry-After` seconds; nothing was changed, so resending the same request is safe).
6. Treat **any** 503, even one whose body is not JSON (some hosts and proxies replace 5xx bodies with an HTML page, or answer 503 themselves), as "this may not have been saved, try again", never as success. Only a JSON `ishot_busy` guarantees that nothing changed. Retrying is still safe: the booking form resends the same `Idempotency-Key` (a booking that did save comes back as 200 `replayed`), and a cancel that did go through answers the retry with 422 `ishot_not_changeable`, so reload the booking and show its current state. This applies to the booking form (section 1) too.

**Already enforced by the server:**
- `Referrer-Policy: no-referrer`, `Cache-Control: no-store, private` and `X-Robots-Tag: noindex` on the page.
- Old links (`/manage/<token>` and `/manage-booking/?token=`) redirect to the fragment form.
- Rate limiting, and links expire 30 days after the session.

**Legacy removal:** delete the `/bookings/{token}` route and the legacy redirects once no pre-standard link is still valid. On the dev site the last one expires 2027-01-07. Production has issued none yet, so they can be removed before launch.

## 4. Contact form (`/contact`)

- `POST /contact` with `name`, `email`, `phone` (optional), `enquiry` (optional), `message` (10-2000), `consent: true`, and the hidden honeypot `website`.
- **202:** show "Thanks, we'll reply by email". **400:** show the field errors. **429:** "Please wait a little and try again, or email us".
- Remove the Web3Forms code and `VITE_WEB3FORMS_ACCESS_KEY` from the theme port; WordPress handles delivery and retries.

## 5. Page cache and security plugins (from plan §2.6)

- **Exclude from page cache:** `/book/*`, `/account/*`, `/manage-booking/*` and `/wp-json/ishot/*`.
- **REST API allowlist:** security plugins must allow the `ishot/v1` namespace for guests.

## 6. Acceptance tests (Playwright, as in plan §7)

- **Guest booking:** guest books, staff confirm the deposit in wp-admin, the client reschedules through the emailed link, then cancels.
- **Account:** a guest asks for an account, sets the password from Mailpit, and sees the earlier guest booking in My bookings.
- **Manage page:** the token is gone from `location.href` after load, no request carries it in a URL (check the network log), and a third-party script injected for testing is blocked by CSP.
- **Contact form:** an enquiry reaches Mailpit with `Reply-To` set to the sender.

Outstanding manual acceptance checks (real devices, screen readers, OS-level reduced motion) are listed in `docs/outstanding-acceptance.md` §2.
