# Scalability Plan: Scaling to 1M+ Patients / Millions of Records

**Status of prior work:** `analysis.md` (this repo) is the full 9-part audit. A first optimization pass has already shipped: ~65 missing FK indexes + 6 composite indexes (migrations `2026_07_30_000002`/`000003`), `User::permissions()` memoization, `COUNT`-query consolidation via `FILTER` in `AppointmentsApiController`/`BillingApiController`(stats)/`PharmacyApiController`/`LabApiController`/`StaffShiftsApiController`/`AdminController::reports`, `DashboardController`'s admin summary reduced from ~54 to ~19 queries, caching extended to all 6 dashboard role variants (fail-open), and `AdminController::exportStaff` switched to `cursor()`. That work is **not** repeated below except where it changes the ranking or a new dependency was found. Every item below is either genuinely new or explicitly still open.

**Per your instruction, no further code changes are made in this turn.** This is the report; implementation starts after you confirm which items to proceed with, per section 9.

---

## 1. Top 20 Bottleneck Ranking (post first-pass optimization)

**Important honesty note:** there is no reachable Postgres instance in this environment (confirmed: `pg_isready` refuses connection to `127.0.0.1:5432`), and neither repo has Telescope, Debugbar, or query logging installed. Every ranking below is therefore an *analytical* estimate from reading the actual query shapes, not a measured `EXPLAIN ANALYZE` result. Getting real numbers is P0 item #1 in section 4 — treat this list as where to point that tooling first, not as final proof.

| # | Endpoint / Query | Status | Why it's still a concern |
|---|---|---|---|
| 1 | `AdminController::staffSearch` (`LOWER(...) LIKE`) | **NEW FINDING — broken index usage** | Wraps the search predicate in `LOWER()`, but the trigram GIN index on `staff` was built on the raw (non-lowered) expression. Postgres cannot use a GIN trgm index built on `expr` to satisfy a filter on `LOWER(expr)` — this endpoint silently sequential-scans `staff` on every keystroke of the Assign-Shift staff picker, at any scale. Not caught in the original 4-agent audit because it required checking the trigram migration's exact indexed expression against this specific call site's exact predicate. |
| 2 | `BillingApiController::index` main list query | Not yet fixed | The 9-column `GROUP BY` + `LEFT JOIN payment` + `SUM` to compute `paid_amount` per bill is still there — only its *stats* block was consolidated last pass. At 1M+ bills this is the widest single query in the app. |
| 3 | Dashboard cache-miss / cache-stampede | **NEW FINDING** | Every dashboard variant now caches for 60s (good), but nothing prevents a *thundering herd*: when a popular key (`dashboard:summary`, or a busy doctor's `dashboard:doctor:{id}`) expires under concurrent traffic, every simultaneous request in that window independently recomputes the full uncached query set. At "thousands of concurrent users" (your stated target), this can spike load exactly at the moment the cache is supposed to be protecting you. |
| 4 | `LabApiController::index` orders sub-query | Partially improved (stats consolidated) | Still a 5-join query — the highest join count in the codebase — on every page load, uncached. |
| 5 | `PatientsApiController::index`/`search` | Unchanged | Free-text `ilike` across `patient_id`, name, phone, email on the largest table in the system. Trigram coverage looks correct here (unlike #1), but worth confirming under real `EXPLAIN`. |
| 6 | `RolePermissionController`/`EnsurePermission` middleware | Improved, one gap remains | Per-request memoization (last pass) cut 2+ queries/request to 1. That 1 query still fires on nearly every authenticated request across possibly thousands of concurrent users — a cross-request cache (not just per-instance) is the natural next step now that the fail-open Redis pattern is proven safe (see P1 §5.3). |
| 7 | `AppointmentsApiController::index` | Improved (stats consolidated) | Still a 4-join main query, uncached, on the single most-visited screen in the app. |
| 8 | `PatientController::show` | Unchanged | 8 sequential, non-parallelized round trips per page view (5 HTTP + 3 local). Latency, not query cost. |
| 9 | `AdminController::reports` | Improved (1 round trip) | Still does 7 full-table `COUNT(*)`/`SUM()` scans. **Important correction to a common assumption**: Postgres `COUNT(*)` is not O(1) even with an index present — MVCC visibility means every matching row's visibility must be checked at read time. On a 5–10M row table this is a real, non-trivial scan cost every time the page loads. This is the textbook case for a summary table (§5.5). |
| 10 | `PharmacyApiController::index` | Improved (stats consolidated) | Still 4 independent paginated list queries per load. |
| 11 | `MedicalRecordsApiController::show` | Unchanged | 7-branch eager load, 3 hops deep on one branch — fixed cost per request, not multiplied by table size, so lower priority than it looks. |
| 12 | `PharmacyApiController::listBatches` | Unchanged | Two unpaginated `->get()` queries with duplicate joins; `medicine_batch` is the one "currently small" table most likely to actually grow into a real problem. |
| 13 | `Doctor::with('staff')->get()` × 8 call sites | Unchanged | Now index-backed (FK indexes added), but still unpaginated, uncached, duplicated across 4 controllers. |
| 14 | `AdminController::staffQuery` (list/export) | Unchanged | 6-table join (5 subtype `LEFT JOIN`s + department); now FK-indexed and export is `cursor()`'d, but the *list* page's query itself is unchanged. |
| 15 | CSV export wall-clock risk (`exportPatients`, `exportStaff`) | **NEW FINDING** | `cursor()` fixed the *memory* risk last pass, but both exports are still fully synchronous HTTP requests. At 1M+ rows, generating and streaming the CSV can exceed typical reverse-proxy/gateway timeouts (Caddy default, PHP `max_execution_time`) even though memory stays flat — a timeout here fails the whole export with no partial-progress recovery. |
| 16 | `StaffShiftsApiController::index` | Unchanged | Unpaginated `limit(50)` dropdown source. |
| 17 | `PharmacyApiController::listAllMedicines`/`dispensingPage` medicines list | Unchanged | Unpaginated `Medicine::get()`. |
| 18 | Audit log growth (`audit_log_documents`, MongoDB) | **NEW FINDING — no retention/archiving** | Every mutating action writes one document, forever. No TTL index, no partition/rollover, no archival job exists anywhere in the codebase. At healthcare-system scale this becomes the single fastest-growing, never-pruned dataset in the system. |
| 19 | `RoomAssignmentsApiController` (`forPatient`, `bedsForRoom`) | Unchanged | Naturally bounded (per-patient/per-room), low risk, listed for completeness. |
| 20 | `laboratory_equipment`/`laboratory` list endpoints | Unchanged | Small reference tables; only a real risk under the "assume every table hits 1M+" hypothesis from `analysis.md`, not under realistic growth for these specific tables. |

---

## 2. New Findings Not in the Original Audit

1. **`AdminController::staffSearch` bypasses its own trigram index** (see #1 above) — the highest-value single fix in this report: cheap, isolated, and it's a correctness-of-optimization bug (an index exists and isn't being used), not just a missing index.
2. **Cache stampede risk** on every `Cache::remember` call added/extended last pass — no jitter, no lock, no stale-while-revalidate.
3. **Database-final has no queue worker and no scheduler at all.** `QUEUE_CONNECTION=redis` is set, but the Dockerfile's `CMD` only runs `php artisan serve` — nothing ever drains that queue. There is also no `Schedule::` registration and no cron/supervisord entry anywhere in the repo. This matters directly for two of your asks: "move heavy operations into queues" and "background aggregation jobs" both require this infrastructure to exist first — it doesn't today, in either repo except central-service's own bus-relay/queue-work pair.
4. **`COUNT(*)`/`SUM()` on multi-million-row tables is not free even with perfect indexing** — worth stating plainly since it's the direct justification for the materialized-view/summary-table section you asked about.
5. **Partitioning has a real architectural conflict with this schema's design**, not just an implementation cost — see §5.4. This is a "don't make speculative changes" case: I'm flagging the conflict rather than either silently skipping partitioning or blindly proposing it.

---

## 3. Confirmed Already Correct (no change needed)

- No classic read-side N+1 anywhere (DTOs built from pre-joined data, not live lazy relationships).
- `PatientsApiController` search and most trigram-indexed columns are correctly targeted.
- `exists()` used correctly over `count() > 0` throughout.
- `NOT EXISTS` used instead of `whereNotIn(pluck())` in `LabApiController` (avoids the 65,535 bound-parameter ceiling).
- Vitals have zero caching — always read fresh, which is correct for clinical data.
- DTO/`present()` layer avoids over-fetching full entities in API responses.

---

## 4. P0 — Production-breaking / security / will-fail at 1M+ rows

### P0-1: Stand up a real benchmark environment and get actual `EXPLAIN ANALYZE` numbers
- **Problem:** Every ranking in this document (and in `analysis.md`) is analytical, not measured. At this point further tuning without real numbers risks optimizing the wrong thing.
- **Current behavior:** No reachable Postgres in this environment; no Telescope/query-log tooling in either repo.
- **Expected impact:** Turns every subsequent P0/P1 decision from "probably" to "confirmed," and is a prerequisite for meaningfully validating everything else in this report.
- **Risk level:** None (read-only measurement).
- **Change:** (a) Provision a Postgres instance reachable from this environment (or run this from a machine that can reach the droplet's DB via an SSH tunnel), (b) run `php artisan seed:large --patients=1000000 --appointments=5000000 --medical-records=... --bills=1000000` (existing tool, `SeedLargeDataset.php` — flags need raising past its current defaults, see §7), (c) install Laravel Telescope in **local/dev only** (never production — it stores its own data and adds overhead) or use `DB::listen()` + a temporary query-log middleware for a lower-footprint alternative, (d) run `EXPLAIN (ANALYZE, BUFFERS)` on the top 10 queries from §1.
- **Verify:** A written-down `EXPLAIN ANALYZE` plan + timing for each of the top 10 queries, committed alongside this file.

### P0-2: Fix `AdminController::staffSearch`'s broken trigram index usage
- **Problem:** `LOWER(s.staff_id) LIKE ?` / `LOWER(s.first_name||' '||s.last_name) LIKE ?` cannot use the GIN trigram index built on the raw (non-lowered) expression.
- **Current behavior:** Full sequential scan of `staff` on every keystroke of the Assign-Shift staff picker.
- **Expected impact:** At 1M+ staff rows (or even realistic hospital-staff scale, tens of thousands), this goes from a scan-every-keystroke UX freeze to an index-backed sub-millisecond lookup.
- **Risk level:** Low — query-shape change only, no schema change, behavior-identical (case-insensitive substring match either way, since `ilike` is already case-insensitive).
- **Change:** Replace `whereRaw('LOWER(s.staff_id) LIKE ?', [$like])` / `whereRaw("LOWER(...) LIKE ?", [$like])` with `where('s.staff_id', 'ilike', $like)` / `whereRaw("(s.first_name||' '||s.last_name) ilike ?", [$like])`, matching the pattern used correctly everywhere else in the codebase (`AppointmentsApiController::search`, `PatientsApiController`, etc.). The `$like` value itself no longer needs `strtolower()` either, since `ilike` is already case-insensitive — drop that too.
- **Verify:** `EXPLAIN` the query before/after; before shows `Seq Scan on staff`, after shows `Bitmap Index Scan` on `staff_id_trgm_idx`/`staff_full_name_trgm_idx`.

### P0-3: Cache stampede protection on dashboard keys
- **Problem:** No lock/jitter around any `Cache::remember` call — concurrent cache-miss requests all recompute independently.
- **Current behavior:** A 60s TTL expiring under load means N concurrent requests all run the full uncached query set simultaneously.
- **Expected impact:** Prevents exactly the load spike a cache is supposed to prevent, at the moment it matters most (peak concurrent traffic).
- **Risk level:** Low — this is an additive locking wrapper around existing cache calls, not a behavior change to what gets cached.
- **Change:** Use Laravel's built-in `Cache::lock()` around the recompute path in `cachedRoleData()`, or switch to `Cache::flexible([$fresh, $stale], $compute)` (Laravel 11+) for stale-while-revalidate semantics — serve the stale value immediately while one request refreshes in the background, instead of blocking/duplicating work.
- **Verify:** Load-test one dashboard endpoint with concurrent requests timed exactly at TTL expiry (e.g., `hey`/`k6` with a burst of 50 requests at second 60); confirm the underlying query set runs once, not 50 times (check via query log/Telescope from P0-1).

### P0-4: Audit log retention policy (security + scalability)
- **Problem:** `audit_log_documents` grows forever with no TTL, no archival, no partition/rollover strategy anywhere in the codebase.
- **Current behavior:** Every mutating action (patient/record/lab/billing/staff changes) writes one document indefinitely.
- **Expected impact:** Without a policy, this becomes the single fastest-growing collection with no bound — at healthcare-system audit-log volumes (potentially every field change on millions of patients over years), this affects Mongo storage cost, backup size/time, and eventually query performance on the collection even with its existing `entity`/`entity_id`/`at` index.
- **Risk level:** **This is a policy decision, not a code decision** — I am not choosing a retention period for you. Many healthcare regulatory regimes (HIPAA and similar) mandate *minimum* audit-trail retention periods (commonly measured in years), so "delete old logs" must be driven by your actual compliance requirement, not a performance guess.
- **Change (once you specify a retention period):** A scheduled job (requires adding the scheduler infrastructure noted in Finding #3) that either (a) archives documents older than the retention cutoff to cold storage (e.g., exported to R2/S3 as compressed batches, matching the existing `documents` disk pattern used for PDFs) then removes them from the hot collection, or (b) if compliance requires *indefinite* retention, instead moves old documents to a separate, less-frequently-queried Mongo collection so the hot `audit_log_documents` collection stays small and fast for recent-activity queries (which is almost always what the UI actually needs).
- **Verify:** Collection document count and average query latency on `audit_log_documents` before/after, plus a written confirmation that the chosen policy meets your compliance obligation (outside my ability to certify — that needs your compliance/legal sign-off).

---

## 5. P1 — High-value performance improvements

### 5.1: Fix `BillingApiController::index`'s wide `GROUP BY` (analysis.md §4.10, still open)
- **Problem:** 9-column `GROUP BY` + `LEFT JOIN payment` + `SUM` to compute `paid_amount` per bill.
- **Current behavior:** Every bill list page load re-aggregates payments per bill via a join+group, rather than a targeted per-row computation.
- **Expected impact:** Meaningful reduction in the heaviest single query in the app at 1M+ bills.
- **Risk level:** Medium — requires care to preserve identical output shape/values, worth validating against real data (P0-1) before/after.
- **Change:** Replace the join+`GROUP BY` with a correlated scalar subquery: `(SELECT COALESCE(SUM(amount_paid),0) FROM payment WHERE payment.bill_id = b.bill_id) as paid_amount`, dropping the `GROUP BY` and the `LEFT JOIN payment` entirely from the main query (the `item_count` correlated subquery already in this query is the existing precedent for this exact pattern).
- **Verify:** `EXPLAIN ANALYZE` before/after; confirm identical `paid_amount` values against a sample of bills.

### 5.2: `daily_statistics` summary table + scheduled aggregation (your own example, directly addressed)
- **Problem:** `AdminController::reports`, dashboard KPIs, and any future analytics all pay the real cost of `COUNT(*)`/`SUM()` over multi-million-row tables on every request (§1 finding #9).
- **Current behavior:** Live aggregation, every request.
- **Expected impact:** Turns O(rows) scans into O(1) lookups for anything that doesn't need up-to-the-second freshness (reports, trend charts, KPI badges) — this is the single highest-leverage change for the "<2 second dashboard" target at scale.
- **Risk level:** Medium — new table + new scheduled job + new infrastructure dependency (Finding #3: no scheduler exists yet).
- **Change:**
  1. New migration: `daily_statistics` table — `date` (PK or unique), plus precomputed columns matching what dashboards/reports actually need (`new_patients`, `appointments_total`, `appointments_completed`, `medical_records_created`, `lab_orders_total`, `lab_orders_completed`, `revenue_collected`, `bills_outstanding_amount`, etc.).
  2. New Artisan command (e.g. `stats:rollup-daily`) that computes yesterday's row via the same aggregate queries currently run live, and upserts it.
  3. **Prerequisite:** add a scheduler process — either a cron entry (`* * * * * php artisan schedule:run`) added to the deployment, or a small supervisord program, running in Database-final's container (doesn't exist today).
  4. Rewire `AdminController::reports`, the `monthlyTrend` dashboard chart, and any similar historical-trend query to read from `daily_statistics` instead of live-aggregating.
  5. **Today's** (still-in-progress) figures still need a live query — only *closed* days are safe to precompute — so today's numbers stay as they are now, layered on top of the precomputed history.
- **Verify:** Compare `daily_statistics` rollup output against live aggregate queries for the same date range on real data; confirm dashboard/report load time drop via P0-1's tooling.

### 5.3: Cross-request permission cache
- **Problem:** §1 finding #6 — the per-instance memoization from the last pass only helps *within* one request; a fresh `User` instance (and thus a fresh `role_permissions` query) is resolved on every new request.
- **Current behavior:** 1 query to `role_permissions` per request, for nearly every authenticated request.
- **Expected impact:** At "thousands of concurrent users," this removes a small-but-constant query from the single highest-frequency code path in the app.
- **Risk level:** Low-medium — this *does* reintroduce a Redis dependency onto the auth path we deliberately kept Redis-free last pass, but the fail-open pattern (§ from the Redis audit) is now proven and in place elsewhere in this exact controller area, so the earlier objection no longer applies as strongly.
- **Change:** In `User::permissions()`, wrap the `RolePermission::where(...)` query in the same `try { Cache::remember("role_permissions:{$this->role}", 300, ...) } catch (PredisException) { fall back to direct query }` pattern already established in `DashboardController::cachedRoleData()`. Bust the key in `RolePermissionController::update()` when a role's grants change (that controller already knows which role was edited).
- **Verify:** Confirm role-permission changes take effect within the 300s TTL (acceptable staleness window — permissions changes are rare, deliberate admin actions, not real-time-critical); confirm Redis-down still degrades to a direct query, not a 500 (reuse the fail-open test approach from the Redis reliability work).

### 5.4: Partitioning — evaluated, conditionally recommended (not a default "yes")
- **Problem framing, per your ask:** "Evaluate PostgreSQL partitioning strategy... time-based partitioning for appointments, medical records, audit logs, transactions."
- **Finding:** This schema has a real architectural conflict with native Postgres declarative partitioning. Every table uses a business-key `VARCHAR(20)` primary key (`appointment_id`, `medical_record_id`, etc.) that is **not** the partition candidate column (`appointment_date`, `created_at`). Postgres requires the partition key to be part of every unique constraint on a partitioned table — so partitioning `appointment` by `appointment_date` would require changing its primary key to a composite `(appointment_id, appointment_date)`, which in turn breaks every foreign key that currently references `appointment.appointment_id` alone (`medical_record.appointment_id`, `bill.appointment_id`) — those referencing tables would need to gain their own `appointment_date` column just to complete the FK. This cascades: it's not a single migration, it's a schema redesign touching every table with an FK into a partitioned parent.
- **Expected impact if done:** Partitioning's real win here is **not** raw query speed — a well-indexed 5-10M row table performs perfectly well for OLTP access patterns in Postgres (this is well within normal single-table scale). The actual win is (a) near-instant bulk deletion of old data via `DROP PARTITION` instead of a slow `DELETE`, and (b) lower `VACUUM`/maintenance cost on the hot (recent) partition. Both only matter if you adopt a retention/archival policy that regularly removes old data.
- **Risk level:** **High** if pursued now — schema-cascading change, not reversible without significant downtime/migration work, and not justified by the query-performance data we actually have (indexes already address the query-speed concern).
- **Recommendation:** **Do not partition yet.** Revisit only once/if: (1) a retention policy is defined (P0-4's open question) that would actually benefit from partition-drop semantics, and (2) real `EXPLAIN ANALYZE` data (P0-1) shows indexed single-table scans are genuinely insufficient at your actual data volume — which is not yet demonstrated. This directly follows your "do not make speculative changes" instruction: proposing a high-risk, cascading schema change without performance data to justify it would be exactly that.
- **Verify (if later pursued):** Would require its own dedicated design doc and a maintenance-window migration plan — out of scope to detail further until the above two conditions are met.

### 5.5: Materialized view for the most report-heavy aggregate (complement to §5.2)
- **Problem:** Some report/dashboard figures need to be an accurate point-in-time snapshot but tolerate a short refresh lag (unlike `daily_statistics`, which only covers *closed* days).
- **Current behavior:** N/A yet — no materialized views exist in this schema.
- **Expected impact:** Faster than live aggregation, more current than daily rollups, for the specific slice of reporting that needs "current, but not to-the-second" data (e.g., a rolling 30-day operational summary).
- **Risk level:** Medium — Postgres materialized views require an explicit `REFRESH MATERIALIZED VIEW` (or `CONCURRENTLY` variant, which needs a unique index on the view) — another scheduled-job dependency (same prerequisite as §5.2).
- **Change:** Once §5.2's scheduler infrastructure exists, add e.g. `mv_department_workload` (patients/appointments/lab orders/bills per department, rolling 30 days) as a materialized view, refreshed on the same schedule as the daily rollup. Only worth building *after* confirming (via P0-1) which specific report screen actually needs this rather than the simpler `daily_statistics` table.
- **Verify:** Query latency on the target report screen before/after; confirm refresh job completes within its scheduled interval without blocking reads (`CONCURRENTLY`).

### 5.6: Queue-ify large CSV exports (§1 finding #15)
- **Problem:** `exportPatients`/`exportStaff` are synchronous HTTP requests; `cursor()` fixed memory, not wall-clock duration.
- **Current behavior:** A 1M+ row CSV export ties up one HTTP connection/PHP-FPM worker for the entire generation+streaming duration.
- **Expected impact:** Removes gateway-timeout failure risk entirely; frees the request thread immediately.
- **Risk level:** Medium — genuinely new infrastructure (Database-final has no queue worker at all today, Finding #3) and a UX change (export becomes "generate in background, download when ready" instead of an immediate streamed download).
- **Change:** (1) Add a queue worker process to Database-final's deployment (supervisord entry or separate container running `php artisan queue:work`, mirroring central-service's existing setup). (2) New `ExportStaffCsvJob`/`ExportPatientsCsvJob` that generates the CSV to the `documents` disk (same R2/local pattern already used for PDFs) and updates a status record. (3) Export button becomes "Request export" → polls/notifies when ready → download link, similar to the existing medical-report `status`/`regenerate` pattern in central-service.
- **Verify:** Time a 1M-row export end-to-end; confirm no gateway timeout regardless of duration; confirm the HTTP request that triggers it returns immediately.

### 5.7: Paginate/cache the remaining unbounded dropdown sources (§1 #13, #16, #17)
- **Problem:** `Doctor::with('staff')->get()` (×8 sites), `StaffShiftsApiController::index`, `Medicine::get()` (×2 sites) are all unpaginated and uncached.
- **Current behavior:** Full table scan (now index-backed, but still a full scan) on every dropdown render.
- **Expected impact:** Under the report's stated 1M+-per-table assumption these become real full scans; even at realistic staff/medicine catalog scale (hundreds to low thousands), caching removes repeated identical work.
- **Risk level:** Low.
- **Change:** Wrap each in a short-TTL (`Cache::remember`, 60-300s) fail-open cache using the same helper pattern as `cachedRoleData()`, keyed globally (these aren't per-user data) — e.g. `Cache::remember('dropdown:doctors-with-staff', 120, fn () => Doctor::with('staff')->get())`. Bust on doctor/medicine create/update if staleness beyond the TTL window is unacceptable (matching the existing `dashboard:summary` bust-on-write precedent) — likely unnecessary given the short TTL.
- **Verify:** Confirm query count drops on repeated dropdown-rendering page loads within the TTL window.

---

## 6. P2 — Nice-to-have

- **6.1** Parallelize `PatientController::show`'s 8 sequential HTTP/local calls (analysis.md §4.6 #5) — real latency win, but implementation-invasive (concurrent HTTP client usage) relative to its impact; correctness-sensitive.
- **6.2** Remove dead `medicine:lowstock` `Cache::forget()` calls with no matching write (analysis.md §4.8/A6) — pure cleanup, zero performance impact.
- **6.3** `PharmacyApiController::listBatches`'s duplicate-join `expiring` query — derive `expiring` from the already-fetched `batches` collection in PHP instead of a second query.
- **6.4** `Bill::paidAmount()` duplicate query on already-eager-loaded `payments` (analysis.md §4.6 #1) — sum the in-memory collection instead.
- **6.5** `LabApiController::enterResult()` double-fetches the same order row (analysis.md §4.6 #2) — fetch once, reuse.
- **6.6** `users.staff_id` missing unique constraint (analysis.md §5.5) — data-integrity gap more than performance, low urgency.

---

## 7. Benchmark & Testing Plan

**Existing tool, extend rather than rebuild:** `Database-final/app/Console/Commands/SeedLargeDataset.php` already bulk-generates patients/appointments/medical records/prescriptions/lab orders/bills directly via chunked query-builder inserts (bypassing `Eloquent::create()`'s per-row business-key lookup specifically to make 1M+ row generation feasible). Its current defaults (1M patients, 200K each of appointments/medical records/lab orders/bills) are below your stated targets — raise the flags:

```
php artisan seed:large \
  --patients=1000000 \
  --appointments=5000000 \
  --medical-records=10000000 \
  --lab-orders=1000000 \
  --bills=1000000 \
  --chunk=5000
```

*(Medical records reference an appointment; confirm the command's internal linking logic scales past its tested 200K default before assuming a clean 10M run — worth a dry run at a smaller multiple first, e.g. 10x defaults, before the full run.)*

**Measurement plan (once P0-1's benchmark environment exists):**
1. **Query-level:** `EXPLAIN (ANALYZE, BUFFERS)` on every query in §1's top 20, before and after each fix, saved as a before/after pair.
2. **Endpoint-level:** `k6` or `hey` against the top 10 endpoints, measuring p50/p95/p99 response time at realistic concurrency (start at 50, ramp to "thousands" per your target — likely requires a proper load-testing environment, not this sandbox).
3. **Memory:** `memory_get_peak_usage()` logged around the export endpoints specifically (the one place memory is a distinct risk from query cost).
4. **Redis:** `INFO memory` before/after the cache-extension changes, plus `redis-cli --bigkeys` to catch any unexpectedly large cached payload.
5. **Database load:** `pg_stat_statements` (needs enabling — not confirmed present) for real total-time-by-query-shape ranking, which would supersede this report's analytical ranking with actual data.

**No results are fabricated in this report** — section 1's ranking is explicitly marked analytical, and this section exists specifically to replace it with measured data.

---

## 8. Security & Healthcare-Specific Review

Builds on the earlier Redis/PHI audit (already actioned: removed dead `patient:viewed`/`mr:viewed` PHI cache writes, fixed fail-open behavior, fixed the PHI leak in `bus:relay`'s malformed-message logging). New items specific to this pass:

- **PHI in new caches:** All caching added in this report (`daily_statistics`, dropdown caches, `role_permissions` cache) is either aggregate/count data or non-PHI reference data (role→capability mappings, doctor names for a dropdown). No individual patient clinical content is proposed for caching anywhere in this plan — consistent with the earlier finding that clinical data (vitals especially) should stay uncached.
- **Audit log scalability is also a compliance question, not just a performance one** — see P0-4. I am explicitly not choosing a retention period; that requires your compliance sign-off.
- **Access control performance (§5.3):** the proposed permission cache uses a 300s TTL specifically because permission *changes* are rare, deliberate admin actions — a 5-minute worst-case propagation delay for a permission *grant* is a reasonable tradeoff; if you need permission *revocation* to take effect faster (e.g., immediately deactivating a compromised account), note that account deactivation already goes through `staff.status`/`users.status`, not the permissions cache, so this doesn't weaken incident response — worth your explicit confirmation this reasoning holds for your threat model.

---

## 9. What I need from you before implementing

Per your instruction to report first, here's what's actually ready to implement now vs. what's blocked on a decision:

**Ready to implement immediately (P0-2, P0-3, 5.1, 5.7, all of P2):** no open questions, low/medium risk, no new infrastructure dependencies beyond what already exists.

**Blocked on your input:**
- **P0-4 (audit log retention):** what retention period does your compliance requirement actually mandate?
- **5.2/5.5 (summary table / materialized views) and 5.6 (queued exports):** all three need a scheduler and/or queue-worker process added to Database-final's deployment, which doesn't exist today — confirm you want that infrastructure added (it's a deployment change, not just a code change).
- **5.4 (partitioning):** recommendation is to hold off — confirm you agree, or tell me if there's a retention/compliance driver I'm not aware of that changes this calculus.
- **P0-1 (real benchmarking):** requires a Postgres instance reachable from wherever this work continues — is one available, or should I proceed on analytical estimates only?

Tell me which of the "ready to implement immediately" items to start with, and answer the above where you have a view — I'll proceed step-by-step from there rather than batch everything at once, per your instruction.
