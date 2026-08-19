# Occasion — Ceremony & Event Management Platform (Laravel + MySQL)

A production-structured Laravel 11 rebuild of the ceremony/occasion management
prototype: multi-event platform (weddings, funerals, graduations, corporate events,
etc.) with a System Admin, per-event Admins/Viewers, pledges, service providers,
committees, scheduling, guest/announcement messaging, and event-type-aware theming.

## ⚠️ Important — read this first

This project was generated in a sandboxed environment that **blocks Packagist**
(Composer's package registry), so it was **hand-written to real Laravel 11
conventions but could not be `composer install`-ed or booted here to test it live**.
Every PHP file passed `php -l` syntax validation and every Blade view's directive
pairs were checked for balance, but you should still treat this as a thorough
**first implementation pass**, not a battle-tested release — run it locally, click
through the flows, and expect to fix the odd rough edge.

## Requirements

- PHP 8.2+
- MySQL 8+ (or MariaDB 10.6+)
- Composer 2.x

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create a MySQL database named `occasion` (or edit .env to match your own),
# then:
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Sign in at `http://localhost:8000` with:

- **Username:** `Admin`
- **Password:** `1234`

You'll be forced to set a real password immediately on first login.

## Architecture overview

### Roles
| Role | Scope |
|---|---|
| **System Admin** (`is_super_user`) | Creates/deletes accounts, resets passwords, edits emails. Zero visibility into any event's data. |
| **Event Admin** | Full read/write on their one event. |
| **Event Viewer** | Read-only on their one event. |

Every non-super-user account is limited to **exactly one event** — either the one
they created (auto-becomes its admin) or one they were invited into via Team
Management. This is enforced in `EventController::store()` and
`ResolveCurrentEvent` middleware.

### Key services (`app/Services/`)
- **`PhoneNumberService`** — normalizes any phone input to Tanzania's `+255` format
  so `wa.me`/`sms:` links always resolve.
- **`MessageTemplateService`** — fills the `{placeholder}` tokens in each of the six
  editable message templates stored on `events` (reminder, broadcast, invitation,
  meeting, announcement, provider, committee).
- **`EventThemeService`** — a distinct cool-toned color palette per event type,
  injected as CSS custom properties in `layouts/app.blade.php`.
- **`NavLabelService`** — event-type-aware terminology ("Pledges" vs "Contribution"
  vs "Condolences"; "Guest Management" vs "Announcement" for Funeral) and the
  role-filtered nav item list.
- **`PasswordGeneratorService`** — unambiguous-character temp passwords and 6-digit
  verification codes.

### Funeral-specific behavior
Funeral events get a meaningfully different experience, all branched on
`Event::isFuneral()`:
- No countdown ring on Home; Financial Status is Dashboard-only (no Pledge status
  chart), led by "Funeral day" instead of a days-left counter.
- "Pledges" is labeled **Condolences**, shown as a simplified Name + Contribution
  list (no separate Reminder tab).
- **Team Management is hidden entirely** (`BlockTeamManagementForFuneral`
  middleware).
- Guest Management becomes a single **Announcement** screen (no Event/Meeting
  invitation tabs): one editable message + a **Broadcast SMS** button that tries
  the real device **Contact Picker API** first (Android Chrome only, and only over
  HTTPS — see the `isSecureContext` guard in
  `resources/views/event/guests/announcement.blade.php`), falling back to saved
  condolence contacts when unavailable.

### The 3-step forgot-password flow
`ForgotPasswordController` implements a proper verified reset rather than a
single-step lookup:
1. Username **and** email must both match (generic error either way, to avoid
   account enumeration).
2. A 6-digit code is generated and stored in `password_reset_codes` (15-minute
   expiry, 5-attempt lockout). **This prototype has no real mail/SMS sender**, so
   the code is flashed to the session and shown directly on-screen — replace that
   with an actual `Notification`/`Mail` dispatch in production and delete the
   `demo_code` flash.
3. The person chooses their own new password (not a system-generated one), and
   `must_change_password` is left `false` since identity was already verified.

### SMS/WhatsApp "sending"
There's no SMS/WhatsApp gateway integration — every send action is a redirect to
an `sms:` or `https://wa.me/...` URL, which opens the *device's own* Messages/
WhatsApp app with the message pre-filled. This matches the prototype's behavior
exactly and requires zero third-party API keys, but does mean sending only works
when a real person taps the button on their own device (there's no way to fire
these server-side/programmatically).

### Excel/PDF export
`maatwebsite/excel` and `barryvdh/laravel-dompdf` back the two export buttons on
the Pledges page (`PledgeController::exportExcel/exportPdf`), both including a
totals row.

## Security audit (post-build hardening pass)

Since this environment couldn't actually boot the app, a second pass was done
purely through static/logical analysis — tracing every route, cross-checking
every `route()`/controller-method reference, and reviewing every place user
input reaches a query or a redirect. This caught several real issues, now fixed:

1. **IDOR / cross-tenant data access (the big one).** Routes like
   `PATCH /pledges/{pledge}`, `DELETE /providers/{provider}`, the committee
   member and schedule-item routes, etc. used Laravel's automatic route-model
   binding with **zero verification that the model belonged to the requester's
   own event**. An authenticated admin of Event A could edit, delete, or send
   messages using another event's pledge/provider/committee just by guessing or
   incrementing an ID. Fixed via `App\Http\Controllers\Concerns\AuthorizesEventOwnership`,
   applied as the first line of every affected controller method.
2. **Cross-tenant data leak via validation.** `CommitteeController`'s
   `members.*.pledge_id` validation only checked that a pledge existed *anywhere*
   in the database (`exists:pledges,id`), not that it belonged to the current
   event — a malicious admin could link another event's pledger's name into
   their own committee. Fixed by scoping the `exists` rule to the current
   event's pledges.
3. **Fatal error on the homepage for every regular account.** `/` (the
   dashboard) was registered outside the `resolve_event` middleware group, but
   `DashboardController` calls `app('currentEvent')` unconditionally — since
   nothing ever bound that container key for that route, any non-super-user
   visiting the homepage would hit a fatal error instead of the intended
   redirect/dashboard. Moved the route inside the `resolve_event` group.
4. **Same root cause, broader blast radius.** The `layouts.app` view composer
   *also* calls `app('currentEvent')` unconditionally on every page using that
   layout — including the Super Admin's own User Management page and
   `/event/create`, both of which sit outside `resolve_event`. Fixed at the
   root by giving `currentEvent` a safe `null` default binding in
   `AppServiceProvider::register()`, so it's always resolvable regardless of
   which middleware ran.
5. **No rate limiting on login or the forgot-password flow** — unlimited brute-
   force attempts were possible. Added named rate limiters (`login`,
   `password-reset`) in `AppServiceProvider`, keyed by username+IP for login so
   a shared office IP can't lock out other accounts.
6. **Minor hardening:** timing-safe comparison (`hash_equals`) for the 6-digit
   reset code; `max:5000` length caps added to all seven message-template
   fields (previously unbounded `string` validation); `place` validation made
   consistently `required` in both event creation and settings (it was
   `required` at creation but silently droppable via settings); phone numbers
   now `rawurlencode()`'d before being interpolated into `sms:` URIs as an
   extra defensive layer even though `PhoneNumberService::normalize()` already
   strips them to digits/`+` only; the Contact Picker broadcast endpoint now
   validates and normalizes client-supplied phone numbers instead of trusting
   them verbatim.

What was checked and found **already safe**: CSRF (every POST/PATCH/DELETE form
has `@csrf`, and Laravel's default `web` middleware group includes
`VerifyCsrfToken`), XSS (no `{!! !!}` raw output anywhere — everything goes
through Blade's auto-escaping `{{ }}`), SQL injection (all `whereRaw`/`like`
usage is parameter-bound, never string-concatenated into SQL), and mass
assignment (every `User`/`Event`/etc. creation explicitly builds its array from
validated fields rather than `$request->all()`).

## Cyber-attack-focused hardening pass (round 2)

A follow-up pass specifically targeting attack classes not covered by the audit
above — some of this built on protections already in place
(`SecurityHeaders` middleware, forced HTTPS + secure cookies in production, an
opt-in "remember me" checkbox instead of always-persistent sessions) which were
verified rather than re-explained. What's new in this pass:

1. **Username enumeration via login timing.** `Auth::attempt()` returns almost
   instantly when a username doesn't exist (no password hash to check), but
   takes the full bcrypt verification time (~100–250ms) when the username is
   valid and only the password was wrong — an identical error message doesn't
   help if the *response time* still reveals which case occurred. Fixed by
   always running `Hash::check()`, against the real hash when found or a fixed
   dummy hash when not, so both paths cost the same. Verified with a standalone
   timing simulation: the gap dropped from what would otherwise be ~230ms to
   ~1ms — below any practical exploitation threshold.
2. **CSV/Excel formula injection.** Pledge names and phone numbers are
   free-text, admin-entered fields that flow straight into the Excel export. A
   name like `=HYPERLINK("http://evil.example","click")` would be evaluated as
   a live formula by Excel/Sheets/LibreOffice the next time *anyone* opened
   that exported file — not just the person who typed it. Fixed by prefixing
   any cell value starting with `=`, `+`, `-`, `@`, tab, or CR with a leading
   apostrophe, forcing spreadsheet apps to treat it as literal text. Verified
   against 7 test cases including a legitimate name containing a mid-string
   dash (correctly left untouched).
3. **Unbounded array in committee creation** — `members[]` had no max count,
   so a single request could attempt a very large bulk insert. Capped at 200.
4. **Weak password floor.** Every user-*chosen* password (forgot-password
   reset, forced first-login change, self-service change, and the temporary
   password an admin types for a new team member) previously only required
   `min:8` with no complexity floor. Upgraded to Laravel's `Password::min(8)
   ->mixedCase()->numbers()` and updated every affected form's placeholder
   text so the requirement is visible before submission, not just after a
   validation error. Deliberately *not* applied to system-*generated* temp
   passwords (`PasswordGeneratorService`), which are already high-entropy
   random strings.

What was checked in this pass and confirmed already safe: no dangerous PHP
functions anywhere in the codebase (`eval`, `unserialize`, `exec`, etc.); the
PDF export has no image/external-resource embedding so it isn't exposed to the
DomPDF remote-file-inclusion class of issue; the public RSVP page only exposes
the pledger's name and the event's own public details, never financial
figures; invitation tokens are generated via `Str::random()` (backed by
`random_bytes()`, not a predictable PRNG) and are DB-unique.

## What's deliberately out of scope for this pass

- Automated tests (PHPUnit/Pest) — the controllers are straightforward enough to
  test conventionally; none were written here given the environment constraint.
- Real transactional email/SMS delivery (see the forgot-password note above).
- Authorization via Laravel Policies — role checks are currently done via
  middleware + inline `abort_if`/`abort_unless` calls, matching the prototype's
  "defense in depth" approach. Extracting these into `EventPolicy` would be a
  natural next refactor for a larger team.
- Frontend build tooling (Vite/Tailwind compile step) — Tailwind is loaded via
  CDN (`cdn.tailwindcss.com`) to keep `composer install && php artisan serve`
  sufficient to run the app with zero `npm install` step. Swap to a compiled
  build for production (the CDN build includes a console warning about this).
