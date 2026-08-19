# Travel Booking Platform — Upgrade Tracker

## Phase A — Advanced Database Transformations (Completed)
- [x] Verified `schema.sql` contains required tables/columns: `users` additions, `wishlists` join table, `packages` enum + new fields.
- [x] Executed `schema.sql` against local MySQL (`travel_db`) and confirmed table structure matches expectations.
- [x] Confirmed exactly 6 seed inventory items across `flight`, `hotel`, `package`.
- [x] Confirmed seed data includes valid non-empty `map_coordinates` and non-null competitor price columns.

## Phase B — Security Reinforcement (Next)
- [ ] Add robust regex validators to all mutating endpoints (booking-action, toggle-wishlist, admin package create/update/delete).
- [ ] Enforce CSRF verification + JSON-only error responses (no HTML echoes) for every state-changing backend endpoint.
- [ ] Tighten server-side input validation in `api/get-packages.php` and any other API endpoints returning data.

## Phase C — Gamification Badge Correctness
- [ ] Update badge logic to match spec precisely (Cascade requires flight + hotel booking).
- [ ] Ensure badges stored/merged consistently in comma-separated `badge_flags`.

## Phase D — Marketplace UI Consistency
- [ ] Ensure compare-prices module highlights the true best rate among our internal price + competitor feeds.
- [ ] Implement wishlist/profile wishlisted items panel as required.

## Phase E — Package Detail Feature Alignment
- [ ] Replace/ensure A–F grading system display mapping across UI.
- [ ] Enforce verified maps + right-side media carousel layout.
- [ ] Ensure split-payment slider bounds strictly by booked seats.

## Phase F — Admin Dashboard Enhancements
- [ ] Add platform metrics cards required by spec (inventory distribution by vertical_type; total seating fulfillment).
- [ ] Add admin table sort-by-letter-grade for active rows; ensure grade column exists.
- [ ] Verify manage/edit/delete links work with the real inventory schema and CSRF protection.

## Phase G — Smoke Tests
- [ ] Run syntax checks for modified PHP/JS.
- [ ] Run curl tests for `api/toggle-wishlist.php` and `booking-action.php` (success + sold-out 409).

