# Session Management Security Audit — Smart Hospital Management System

**Scope:** `Database-final` (the only user-facing, session-authenticated app in this system). `central-service` is a stateless internal API authenticated by a shared API key (`central-service.key` middleware) — it has no user sessions, no roles, `SESSION_DRIVER=array` and `CACHE_STORE=array` (in-process only). Nothing in this audit applies to it, and nothing there needs changing.

**Methodology:** Every finding below is verified against actual config files, actual production deployment compose files, and — where noted — **live evidence** pulled directly from the real local Redis/Postgres instance running in this environment (started earlier in this session), not just static code reading. Where I state something empirically, I mean I actually observed it, not inferred it.

**Per your instruction, no code has been changed in this turn.** Section E lists exact planned changes; nothing executes until you approve.

---

## A. Current Configuration Analysis

### Session
| Setting | Local (`.env`) | Production (`deploy/digitalocean/docker-compose.app.yml`) | Effective value |
|---|---|---|---|
| `SESSION_DRIVER` | `redis` | `redis` | Redis-backed sessions, both environments |
| `SESSION_LIFETIME` | `120` (minutes) | *not set* → config default `120` | 120 min idle timeout |
| `SESSION_EXPIRE_ON_CLOSE` | *not set* | *not set* | `false` (session survives browser close) |
| `SESSION_ENCRYPT` | `false` (explicit) | *not set* → default `false` | **Not encrypted** |
| `SESSION_SECURE_COOKIE` | *not set* | **not set, even in production** | `null` → Laravel does **not** send the `Secure` cookie attribute |
| `SESSION_HTTP_ONLY` | *not set* | *not set* | `true` (Laravel default) — correct |
| `SESSION_SAME_SITE` | *not set* | *not set* | `lax` (Laravel default) |
| `SESSION_CONNECTION` | *not set* | *not set* | Redis `default` connection (**verified**: live session keys found in Redis DB0, not DB1) |
| Serialization | `'serialization' => 'json'` (config/session.php, not env-overridable) | same | **JSON, not PHP serialize()** |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` | Correct per environment |
| `APP_URL` | `http://localhost:8000` | `https://<real domain>` | Production is genuinely HTTPS (via Caddy) |

### Authentication (`config/auth.php`, `AuthController.php`)
- Single guard: `web`, session-driven, Eloquent `User` provider.
- `Auth::attempt(['email' => ..., 'status' => 'active', 'password' => ...], $request->boolean('remember'))` — **"Remember Me" is live and wired to a checkbox on the login form.**
- Session is regenerated on login (`$request->session()->regenerate()`) — correct, prevents session fixation.
- Logout calls `invalidate()` + `regenerateToken()` — correct.
- `Auth::attempt()` correctly constrains to `status = 'active'`, so deactivated accounts can't log in fresh.
- **No `throttle` middleware anywhere on `/login`** (verified: zero matches for `throttle`/`RateLimiter` in `routes/web.php`).
- No account lockout after repeated failures.
- No password-confirmation / step-up auth anywhere in the codebase (verified: zero matches for `password.confirm`/`ConfirmPassword`).
- `password_timeout` (Laravel's built-in "confirm password" window) is configured at its 3-hour default but is **unused** — nothing in the app actually requires password confirmation for any action.

### Redis (session store)
- Redis requires a password in production (`REDIS_PASSWORD` is a required compose variable — confirmed from the earlier Redis reliability audit this session). Good, already correct.
- One Redis instance, split by logical DB number: DB0 (`default` — sessions + bus-adjacent default cache), DB1 (`cache`), DB2 (`bus`). A single Redis credential has full read/write access to **all three** — no ACL scoping restricts the app's Redis user to session-only operations.
- Timeouts (connect/read) were already hardened for reliability in an earlier pass this session (250ms), which incidentally also bounds how long a session read/write can hang.
- **Live evidence** (pulled directly from the real Redis instance, `redis-cli -n 0 keys "*"` + `get`):
  ```
  s:220:"{"_token":"81WA0VeQ...","url":[],"_previous":{"url":"http://localhost:8000/bills","route":null},
  "_flash":{"old":[],"new":[]},"login_web_59ba36addc...":"USR0001"}"
  ```
  Plaintext, human-readable, 141–223 bytes observed per session. TTLs observed: 6601–7149 seconds, consistent with the configured 120-minute (7200s) lifetime minus elapsed time — **TTL enforcement is confirmed working correctly.**

---

## B. Security Issues Found

### Critical

**B1 — Deactivating a staff account does not revoke their active session.**
`AdminController::deactivateStaff()` flips `staff.status`/`users.status` to `inactive`, but nothing re-validates that status on subsequent requests from an already-logged-in session — `EnsurePermission` only checks `Gate::denies($permission)` against the role, never re-checks account status. A terminated or compromised staff member's existing session stays fully valid (with all its original permissions) for up to the remaining session lifetime, or effectively indefinitely if "Remember Me" was used. For a healthcare system, "how fast can access be cut off" is a core control — this is the single most important finding in this audit.

**B2 — No rate limiting on login.**
Zero throttling on `/login`. An attacker can attempt unlimited password guesses against any known email (e.g., `admin@hospital.test`-style addresses, which — per the demo seed pattern — are guessable). Combined with no account lockout, this is a straightforward brute-force path to any account, including admin.

**B3 — No re-authentication for sensitive/destructive actions.**
Permission changes (`RolePermissionController::update`), data exports (`PatientController::exportPatients`, `AdminController::exportStaff`), and any deletion-equivalent action are gated only by the role-level `Gate::denies()` check — the same check that gates viewing a patient list. An attacker with a hijacked or unattended session (e.g., admin walks away from an unlocked screen) can silently change permissions or exfiltrate the entire patient database without ever re-proving identity.

### High

**B4 — `SESSION_SECURE_COOKIE` is unset, even in production.**
Production genuinely serves over HTTPS (confirmed: `APP_URL=https://...`, Caddy handles TLS termination), but because this setting is never explicitly set, Laravel never sends the `Secure` attribute on the session cookie. In the normal case Caddy's HTTPS redirect prevents plain-HTTP access — but this is a defense-in-depth gap, not a redundant one: it's the difference between "this cookie is structurally incapable of being sent over plaintext HTTP" and "this cookie currently isn't sent over plaintext HTTP because the redirect happens to work correctly." Zero-risk, zero-cost to fix.

**B5 — "Remember Me" has no role differentiation and no visible expiry policy.**
Laravel's remember-me cookie is a long-lived (effectively multi-year), signed token independent of `SESSION_LIFETIME`. It's offered identically to every role — an admin checking that box on a shared or public terminal remains persistently authenticated far beyond any reasonable idle-timeout policy, with no session-lifetime-based mitigation possible (remember-me bypasses the normal session expiry entirely by silently re-establishing a fresh session).

**B6 — Uniform 120-minute timeout regardless of role or privilege.**
An admin session (able to change permissions, export all data, deactivate accounts) and a receptionist session (booking appointments) currently carry identical idle-timeout risk exposure. Privilege level isn't factored into session policy at all.

### Medium

**B7 — Session data is unencrypted at rest, confirmed readable in plaintext.**
Demonstrated directly above: anyone with Redis read access (an ops engineer, a misconfigured backup, a compromised adjacent container sharing the Redis instance, a Redis RDB/AOF file that leaks) can read exactly who is logged in (`login_web_* → USR0001`) and their CSRF token, in cleartext, without needing `APP_KEY`. This is meaningfully less severe than it could be — see the "already correct" note below — but it is a real confidentiality gap.

**B8 — `_previous.url` incidentally carries patient/record identifiers.**
Because this app's routes are RESTful with the resource ID in the path (`/patients/PAT0001`, `/medical-records/MR0001`, etc.), Laravel's automatic "previous URL" session tracking means the session store passively accumulates a trail of *which specific patient or record a user was viewing*. This is PHI-adjacent metadata living in a store that isn't encrypted (B7) and isn't access-audited the way the app's own `AuditLogger` is.

**B9 — No concurrent-session visibility or revocation.**
A user (or an admin on a user's behalf) has no way to see "you are logged in on 3 devices" or to revoke a specific session — e.g., if a laptop is lost or stolen while still authenticated, there is no self-service or admin-driven way to kill that specific session short of a full password reset (which Laravel's default reset flow does invalidate remember tokens for, but doesn't touch already-live Redis sessions either — same root cause as B1).

**B10 — Single Redis credential spans sessions + cache + bus, no ACL scoping.**
Already flagged in the earlier Redis reliability audit from a different angle; repeating here because it's directly relevant to session confidentiality: if the app's Redis credential ever leaks, the blast radius includes full read/write on session data (impersonation-adjacent), not just cache.

### Low / Notable but not urgent

**B11 — `SESSION_SAME_SITE` relies on an implicit default rather than an explicit, intentional setting.** `lax` is a reasonable value for this architecture (a single-origin server-rendered app, no cross-site embedding), but for a compliance-sensitive system, an auditor generally wants to see the value stated deliberately in config, not inherited silently from a framework default that could change between Laravel versions.

**B12 — Session key naming has a cosmetic double-prefix** (`...-database-...-cache-<random>`), inherited from how Laravel's cache-backed redis session handler composes the connection-level and cache-store-level prefixes. Not a security issue — purely a debuggability/hygiene note, surfaced because I directly observed it while pulling the live evidence above.

---

## Already Correct — Worth Confirming Explicitly

Not everything is a gap. Specifically because you asked about `SESSION_ENCRYPT`:

- **Session serialization is JSON, not PHP** (`config/session.php`'s hardcoded `'serialization' => 'json'`, a non-negotiable Laravel default, not env-overridable). This matters directly: the *reason* to encrypt session data isn't only confidentiality — historically, unencrypted PHP-serialized session data combined with a leaked `APP_KEY` (or in older Laravel, unauthenticated session cookies) opened the door to PHP Object Injection / gadget-chain remote code execution. Because this app serializes sessions as JSON, that entire attack class is structurally unavailable regardless of `SESSION_ENCRYPT`. This **does not eliminate** the confidentiality problem in B7, but it correctly reframes the risk: encrypting session data here is about **information disclosure**, not remote code execution.
- Session regeneration on login, proper invalidation on logout, `HttpOnly` correctly defaulted true, `remember_token` correctly hidden from serialization, `Auth::attempt()` correctly scoped to `status = 'active'` for fresh logins, Redis password-protected in production, and the earlier session's Redis timeout/fail-open work all directly benefit session reliability too (a Redis outage now degrades gracefully with an honest 503 page rather than crashing every logged-in user, per the earlier work in this same session).

---

## C. Recommended Configuration

### Session/cookie settings
| Setting | Recommended | Why |
|---|---|---|
| `SESSION_SECURE_COOKIE` | `true` in production, unset (or `false`) locally | Production is HTTPS-only already; this makes that structural, not incidental (B4) |
| `SESSION_SAME_SITE` | `lax` (explicit, not implicit) | Matches current behavior, but stated deliberately (B11) |
| `SESSION_ENCRYPT` | `true` | Closes B7/B8 directly — encrypts the JSON payload with `APP_KEY` before it ever reaches Redis. Given JSON serialization already removes the RCE risk, this is a pure confidentiality upgrade with no gadget-chain trade-off to weigh |
| `SESSION_HTTP_ONLY` | Keep `true` (already correct) | No change needed |
| `SESSION_LIFETIME` | Role-differentiated — see D below, not a single global value | See multi-role strategy |

### Multi-role session strategy (your section 5, answered directly)

There is **no "Patient" role or patient-facing login in this system** — worth stating plainly since your prompt says "Patient (if applicable)." Confirmed against `config/permissions.php`'s role list and the `users.role` enum: the only roles are `super_admin`, `admin`, `doctor`, `nurse`, `receptionist`, `pharmacist`, `lab_technician`. Patients exist only as *data*, managed by staff — there is no patient portal to design a timeout policy for. If a future patient portal is planned, it should get its own guard entirely (separate from `web`), not a longer timeout bolted onto the current one.

| Role | Idle timeout | Remember Me | Step-up re-auth required for |
|---|---|---|---|
| `super_admin` / `admin` | **15 minutes** | Disallowed | Permission changes, bulk exports, account deactivation/reactivation, hospital settings changes |
| `doctor` | 60 minutes | Allowed, but capped (see below) | None beyond normal login — clinical workflows need continuity, but see B1 fix (deactivation must still cut access immediately) |
| `nurse` | 60 minutes | Allowed, capped | None additional |
| `receptionist` / `pharmacist` / `lab_technician` | 60 minutes | Allowed, capped | None additional |

Rationale for the asymmetry: admin is the only role that can change *other users'* access (permissions, deactivation) or exfiltrate bulk data — that's qualitatively different risk from clinical/operational roles that primarily act on individual records they're already authorized to see. A 15-minute idle timeout for admin is standard practice for the "can grant/revoke access to everyone else" role in security-conscious systems generally, not healthcare-specific overkill.

**On "Remember Me, capped":** rather than disabling it outright for clinical roles (which would be a real UX regression for staff on trusted, department-owned workstations), cap its actual lifetime explicitly (e.g., 14 days) instead of Laravel's uncapped default, and — critically — fix B1 first, since a capped remember-me is only meaningfully safer if a deactivated account's *existing* session/remember-token is also invalidated immediately rather than surviving until natural expiry.

**Step-up re-authentication mechanism:** Laravel ships this — `password.confirm` middleware + the `password_timeout` config already present (currently unused at its 3-hour default). Recommend: apply `->middleware('password.confirm')` to the specific sensitive routes (`RolePermissionController::update`, the two export routes, `deactivateStaff`/`reactivateStaff`), and lower `password_timeout` to something short (e.g., 5 minutes) so confirmation itself doesn't become a rubber stamp valid for hours.

---

## D. Risk Level Per Change

| # | Change | Risk level | Why |
|---|---|---|---|
| 1 | `SESSION_SECURE_COOKIE=true` (production only) | **None** | Production is already HTTPS-only; this can't break anything that isn't already broken |
| 2 | `SESSION_SAME_SITE=lax` (explicit) | **None** | No behavior change, just makes the existing default explicit |
| 3 | `SESSION_ENCRYPT=true` | **Low** | Existing sessions at deploy time won't decrypt (users get logged out once) — cosmetic inconvenience, not a break. No code changes needed, Laravel handles this transparently |
| 4 | Login throttling (`throttle:5,1`-style on `/login`) | **Low** | Standard Laravel middleware, well-tested pattern; only affects abuse behavior, not legitimate use |
| 5 | Role-based session lifetime (admin 15min / others 60min) | **Medium** | Requires either a dynamic per-request lifetime mechanism (Laravel's session lifetime is normally static/global) or an idle-timeout check implemented as middleware — genuine code, needs care and testing, and will change UX (admins will get logged out more often — should be communicated) |
| 6 | Invalidate sessions on deactivation (B1 fix) | **Medium** | Requires tracking active session keys per user (or switching to database-driven session invalidation, or a "security stamp" check per request) — real architectural addition, needs careful design to avoid a performance regression on every request |
| 7 | Step-up re-auth (`password.confirm`) on sensitive routes | **Low-Medium** | Well-supported Laravel feature, but changes UX for admin actions (extra password prompt) — needs a quick design check on exactly which routes qualify as "sensitive" |
| 8 | Cap Remember Me lifetime | **Low** | Requires a small custom cookie-lifetime override; Laravel doesn't expose this via config by default, needs a small code change in the login flow |
| 9 | Redis ACL scoping (session-only credential) | **Medium-High** | Infrastructure change (new Redis ACL user, updated deployment secrets) — explicitly the same category of change you told me to hold on in the last request (infra/deployment changes), flagging for your awareness but treating as out of scope unless you say otherwise |

---

## E. Migration/Change Steps (once approved)

**Zero-risk, do first:** items 1–2 (env-only, no code, no behavior change) can go in the same change as item 3.

1. Set `SESSION_SECURE_COOKIE=true` in the production compose file (not `.env`, matching how other production-only values are handled there) and `SESSION_SAME_SITE=lax` explicitly in both `.env.example` and the production compose file.
2. Set `SESSION_ENCRYPT=true` in both `.env`/`.env.example` and production compose. No code changes — Laravel handles this transparently via existing `APP_KEY`. Deploy note: this invalidates all existing sessions at deploy time (everyone re-logs in once) — worth doing during a low-traffic window, not because it's risky, just to avoid a wave of simultaneous re-logins.
3. Add `throttle:login` (Laravel's built-in rate limiter, configured via a `RateLimiter::for('login', ...)` in a service provider, keyed by email+IP) to the `POST /login` route.
4. Design and implement the role-based idle-timeout mechanism (item 5) — this needs a short design decision from you: middleware-based idle-check (compare `session('last_activity')` against a per-role threshold, independent of Laravel's global `SESSION_LIFETIME`) is the standard approach and doesn't require switching session drivers.
5. Fix B1 (deactivation doesn't revoke sessions) — recommend a "security stamp" pattern: add a `session_version` (or reuse `updated_at`) column check in `EnsurePermission` (or a new lightweight middleware) that compares a value cached in the session against the current DB value, forcing re-authentication when a staff record is deactivated/reactivated or its role changes. This is the one change here I'd want to prototype and test carefully before considering it done, since it runs on every request.
6. Add `password.confirm` middleware to the specific sensitive routes, and lower `password_timeout`.
7. Cap Remember Me's cookie lifetime explicitly.

Steps 1–3 have no meaningful risk and could reasonably be batched into one implementation pass. Steps 4–7 involve real design decisions and should each get their own focused implementation + test pass.

---

## Performance Considerations (your section 6)

**Concrete numbers from this environment, not estimates:** a live session currently observed at 141–223 bytes as stored JSON. Accounting for Redis's own per-key overhead (roughly 50-100 bytes for a small string value), call it **~300-400 bytes per active session** in Redis memory, realistically. Encrypting (item 3 above) adds a small amount of ciphertext/base64 overhead — call it ~1.3x, so **~400-550 bytes/session** post-encryption. This is a trivial planning number:

| Concurrent sessions | Redis memory (session data only) |
|---|---|
| 1,000 | ~400-550 KB |
| 10,000 | ~4-5.5 MB |

**Session storage memory is a non-issue at either scale** — this is nothing like the earlier finding about accidentally caching full Eloquent object graphs; a session is tiny by design. The actual scaling consideration is **Redis command throughput**, not memory: every authenticated request does at minimum one session `GET` and one session `SET` (session data touches on every request due to CSRF token/flash handling), so at 10,000 concurrent users with, say, 1 request/second/user average, that's ~20,000 Redis ops/sec just for session I/O — well within a single Redis instance's normal capacity (Redis routinely handles 100,000+ ops/sec on modest hardware), but worth knowing as the real bottleneck to watch, not memory. This ties directly into the connection-timeout hardening already done earlier this session (250ms connect/read timeouts) — that work is exactly what protects session reads/writes from hanging under load.

**Cleanup:** TTL-based expiry (already confirmed working) means Redis never accumulates stale sessions indefinitely — no separate cleanup job is needed, unlike the database/file session drivers which need a garbage-collection lottery (`'lottery' => [2, 100]` in config, irrelevant for the redis driver since Redis's own TTL does this natively).

---

## Testing Plan (your section 8 — design now, implement alongside the approved changes)

| Test | What it proves |
|---|---|
| Session expires after configured idle lifetime | Fast-forward session `last_activity` (or manipulate the Redis TTL directly in a test) past the threshold, assert the next request redirects to `/login` |
| Admin session expires faster than other roles | Once role-based timeout ships: two sessions created at the same instant, admin's expires at 15min mark while a doctor's is still valid |
| `SESSION_SECURE_COOKIE`/`HttpOnly`/`SameSite` flags are actually sent | Feature test asserting the `Set-Cookie` response header on login contains `Secure; HttpOnly; SameSite=Lax` (test env can force `APP_ENV=production`-equivalent config for this one assertion) |
| Login is rate-limited | N+1 failed login attempts from the same IP/email get a 429, not another "invalid credentials" 200/302 |
| Logout invalidates the session server-side, not just the cookie | Log in, capture the session ID, log out, replay the old session cookie on a new request — must be treated as unauthenticated, not silently accepted |
| Deactivating a staff account kills their live session (B1 fix) | Log in as user X, deactivate X's staff record via an admin session, replay X's original session cookie — must now be rejected, not just blocked on next *login* attempt |
| Multi-role access boundaries | For each role, assert both a route it should reach (200) and one it shouldn't (403) — extending the existing `test_every_role_dashboard_renders`-style pattern already in the test suite |
| Redis session storage round-trip | Log in, assert a real key exists in Redis DB0 with a TTL close to the configured lifetime, log out, assert the key is gone (not just expired-eventually) |
| Concurrent sessions (same user, two browsers/devices) | Two independent logins for the same user must get two independent session IDs, and invalidating one (once B9's session-listing exists, if you choose to build it) must not affect the other |
| Sensitive-action re-authentication | Once `password.confirm` is applied: hitting a step-up-protected route without recent password confirmation redirects to the confirm-password screen instead of executing the action |

---

## Summary — what's actually broken vs. what's already fine

**Genuinely concerning, worth prioritizing:** B1 (deactivation doesn't revoke sessions) and B2/B3 (no login throttling, no re-auth for sensitive actions) are the three findings I'd call load-bearing for a healthcare system specifically — they're about **how fast a bad actor's access can be cut off**, which is the crux of session security in this domain, more than the cookie-flag items.

**Cheap and should just be done regardless of anything else:** B4 (`SESSION_SECURE_COOKIE`) and encryption (B7) — zero-to-low risk, no design decisions required.

**Genuinely reassuring, not a gap:** JSON session serialization already rules out the worst-case (RCE via deserialization) that `SESSION_ENCRYPT` discussions often center on — the real remaining exposure from unencrypted sessions here is information disclosure (who's logged in, what they were viewing), not code execution.

Tell me which of the items in Section E you want implemented — I'd suggest starting with the zero-risk batch (1–3) plus login throttling, since those have no open design questions, and treating the session-lifetime/deactivation-revocation work (4–5) as its own follow-up once you've confirmed the role-timeout values and the security-stamp approach make sense to you.
