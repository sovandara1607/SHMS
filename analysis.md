# Smart Hospital Management System — Database Performance & Scalability Analysis

**Scope:** Both repositories — `Database-final` (public-facing Laravel app) and `central-service` (internal API service that owns most business logic and query construction, despite having no migrations of its own — it shares Database-final's Postgres schema). MongoDB (`medical_record_versions`, `medical_record_version_counters`, `audit_log_documents`, `lab_report_documents`) is used for eventually-consistent version history and audit logging, not primary data, and is noted where relevant but is not the focus of this relational-database report.

**Methodology note (read this first):** Per the brief, every ranking and severity judgment below assumes **every table eventually holds 1,000,000+ rows**, under **normal concurrent production usage**. This matters: several patterns the underlying investigation found to be "low risk today" (e.g. unpaginated `Doctor::with('staff')->get()` calls, currently safe because the `doctor` table has a handful of rows) are scored as **significant** in this report because, under the stated assumption, that same query would scan a table of a million rows. Where current real-world scale genuinely changes the picture (e.g. a table that is structurally incapable of growing past a few dozen rows, like `department`), that is called out explicitly rather than silently assumed away.

**What could not be fully determined from the code alone** is flagged inline with "**Cannot verify from code**" rather than guessed — this happens mainly around actual Postgres query planner behavior (`EXPLAIN` output), connection pool sizing, and production traffic patterns, none of which exist in this repository as artifacts.

---

## Part 1: Database Relationship Analysis

### 1.1 Full schema table

This codebase does **not** use Laravel's `foreignId()->constrained()` helper anywhere — every foreign key is declared via `$table->foreign('col')->references(...)->on(...)`. In Postgres, this means **a foreign key column is only indexed if a separate, explicit `->index()` call was also written**. This single fact turns out to be the most consequential index-related finding in the whole report (see Part 5), so it's called out here structurally: the table below has an explicit **FK Indexed?** column, and the honest answer for most rows is **No**.

All primary keys in this schema are `VARCHAR(20)` business keys (e.g. `PAT0001`), not auto-increment integers, except the three infrastructure tables (`role_permissions`, `hospital_settings`, `failed_jobs`), which use bigint auto-increment.

| Table | PK | Outgoing FKs | FK Indexed? | Incoming refs (who points here) | Cardinality |
|---|---|---|---|---|---|
| `patient` | patient_id | — | — | 15 tables (see §1.2) | Root/hub — no outgoing FK, most-referenced clinical entity |
| `staff` | staff_id | department_id → department | No | 19 tables via a generic "actor" column pattern (created_by/assigned_by/generated_by/received_by/booked_by) + 6 subtype tables (1:1) | Root/hub — highest raw connection count |
| `department` | department_id | head_staff_id → staff | No | staff, doctor, nurse, room | **Circular** with staff (see §1.4) |
| `users` | user_id | staff_id → staff | No | — | N:1 to staff, **not** DB-enforced 1:1 |
| `doctor` | doctor_id | staff_id → staff (UNIQUE), department_id → department | staff_id yes (unique), department_id no | appointment, medical_record, patient_doctor_assignment, prescription, medical_procedure, lab_test_order | 1:1 with staff; 1:N into 6 tables |
| `nurse` | nurse_id | staff_id → staff (UNIQUE), department_id → department | staff_id yes, department_id no | patient_nurse_assignment | 1:1 with staff |
| `receptionist` | receptionist_id | staff_id → staff (UNIQUE) | yes | — (no table FK-references it; `booked_by` is a loose staff_id string) | 1:1 with staff, otherwise dead-end |
| `pharmacist` | pharmacist_id | staff_id → staff (UNIQUE) | yes | dispensing_record | 1:1 with staff |
| `lab_technician` | technician_id | staff_id → staff (UNIQUE), laboratory_id → laboratory | staff_id yes, laboratory_id no | lab_test_order, lab_test_result | 1:1 with staff |
| `laboratory` | laboratory_id | — | — | lab_technician, laboratory_equipment | Small reference table |
| `patient_insurance` | insurance_id | patient_id → patient | **No** | — | 1:N patient→insurance |
| `room` | room_id | department_id → department | No | bed, room_assignment | 1:N into bed, room_assignment |
| `bed` | bed_id | room_id → room | **No** | room_assignment | 1:N room→bed |
| `room_assignment` | room_assignment_id | patient_id, room_id, bed_id (nullable), assigned_by (nullable) | **None of 4 indexed** | — | Leaf assignment/history table |
| `appointment` | appointment_id | patient_id, doctor_id, booked_by (nullable) | **None of 3 indexed** (only `appointment_date`, a non-FK column, is) | medical_record, bill | N:1 into 3; 1:N (optional) into 2 |
| `staff_shift` | shift_id | staff_id → staff | **No** | patient_nurse_assignment | 1:N into assignment |
| `patient_doctor_assignment` | assignment_id | patient_id, doctor_id, assigned_by | **None indexed** | — | Not a pivot — has own PK/status/role/timestamps |
| `patient_nurse_assignment` | assignment_id | patient_id, nurse_id, shift_id (nullable), assigned_by | **None indexed** | — | Not a pivot — same shape as above |
| `medical_record` | medical_record_id | patient_id, doctor_id (both **indexed**), appointment_id (nullable), created_by (nullable) | patient_id/doctor_id yes; appointment_id/created_by no | medical_record_adjustment, vital_signs, prescription, medical_procedure, medical_report, lab_test_order | 3rd-most-connected table; "immutable" by design (corrections via adjustment table) |
| `medical_record_adjustment` | adjustment_id | medical_record_id, adjusted_by | **No** | — | 1:N append-only audit trail |
| `vital_signs` | vital_sign_id | patient_id, medical_record_id (nullable), recorded_by | **No** | — | Leaf |
| `prescription` | prescription_id | medical_record_id, patient_id (**indexed**), doctor_id | patient_id yes; other two no | prescription_item, dispensing_record | 1:N into 2 |
| `prescription_item` | prescription_item_id | prescription_id, medicine_id | **No** | — | Line-item detail, not a pivot |
| `medical_procedure` | procedure_id | medical_record_id, patient_id, doctor_id | **No** | — | Leaf |
| `medical_report` | report_id | patient_id, medical_record_id (nullable), generated_by | **No** | — | Leaf; `report_file_path` added later for PDF pipeline |
| `medicine` | medicine_id | — | — | prescription_item, medicine_batch, dispensing_item | Master-data hub for pharmacy |
| `medicine_batch` | batch_id | medicine_id | **No** (but `expiry_date`, a non-FK column, is indexed for FEFO) | dispensing_item | 1:N into dispensing_item |
| `dispensing_record` | dispensing_id | prescription_id, pharmacist_id (nullable), patient_id | **No** | dispensing_item | 1:N into dispensing_item |
| `dispensing_item` | dispensing_item_id | dispensing_id, medicine_id, batch_id (nullable) | **No** | — | Leaf; batch_id made nullable later (FEFO can find no batch) |
| `lab_test_order` | test_order_id | patient_id (**indexed**), doctor_id, technician_id (nullable), medical_record_id (nullable) | patient_id + status yes; other three no | lab_test_result, lab_report | 6-table hub for lab subsystem |
| `lab_test_result` | test_result_id | test_order_id, entered_by | **No** | — | Leaf |
| `laboratory_equipment` | equipment_id | laboratory_id (nullable) | No | — | Leaf, isolated |
| `lab_report` | lab_report_id | test_order_id, patient_id, generated_by | **No** | — | Leaf; `report_file_path` for PDF pipeline |
| `bill` | bill_id | patient_id (**indexed**), appointment_id (nullable), generated_by | patient_id + status yes; other two no | bill_item, payment | 1:N into 2 |
| `bill_item` | bill_item_id | bill_id | **No** | — | Leaf; `subtotal` is a Postgres STORED GENERATED column |
| `payment` | payment_id | bill_id, received_by | **No** | — | Leaf |
| `patient_adjustment` | adjustment_id | patient_id, adjusted_by | **No** | — | Append-only audit trail, added later (not in original ERD) |
| `role_permissions` | id (bigint) | — | — | — | No FKs; point-in-time config snapshot, documented drift risk |
| `hospital_settings` | id (bigint) | — | — | — | Singleton by convention only |
| `failed_jobs` | id (bigint) | — | — | — | Standard queue infrastructure |

**No many-to-many pivot tables exist in this schema.** `patient_doctor_assignment` and `patient_nurse_assignment` look pivot-shaped (two FKs) but each has its own surrogate PK, status enum, role enum, and `assigned_at`/`ended_at` timestamps — they model a temporal assignment history with business state, not a bare junction table.

### 1.2 Ranking: most → least connected

Count = distinct other tables connected via outgoing **or** incoming FK, deduplicated.

| Rank | Table | # Relationships | Related Tables | Complexity |
|---|---|---|---|---|
| 1 | **staff** | 20 | department, users, doctor, nurse, receptionist, pharmacist, lab_technician, room_assignment, appointment, staff_shift, patient_doctor_assignment, patient_nurse_assignment, medical_record, medical_record_adjustment, vital_signs, medical_report, lab_report, bill, payment, patient_adjustment | **Very High** |
| 2 | **patient** | 15 | patient_insurance, room_assignment, appointment, patient_doctor_assignment, patient_nurse_assignment, medical_record, vital_signs, prescription, medical_procedure, medical_report, dispensing_record, lab_test_order, lab_report, bill, patient_adjustment | **Very High** |
| 3 | medical_record | 10 | patient, doctor, appointment, staff, medical_record_adjustment, vital_signs, prescription, medical_procedure, medical_report, lab_test_order | High |
| 4 | doctor | 8 | staff, department, appointment, medical_record, patient_doctor_assignment, prescription, medical_procedure, lab_test_order | High |
| 5 | lab_test_order | 6 | patient, doctor, lab_technician, medical_record, lab_test_result, lab_report | Moderate |
| 6 | appointment | 5 | patient, doctor, staff, medical_record, bill | Moderate |
| 6 | prescription | 5 | medical_record, patient, doctor, prescription_item, dispensing_record | Moderate |
| 6 | bill | 5 | patient, appointment, staff, bill_item, payment | Moderate |
| 9 | department | 4 | staff, doctor, nurse, room | Moderate |
| 9 | lab_technician | 4 | staff, laboratory, lab_test_order, lab_test_result | Moderate |
| 9 | room_assignment | 4 | patient, room, bed, staff | Moderate |
| 9 | patient_nurse_assignment | 4 | patient, nurse, staff_shift, staff | Moderate |
| 9 | dispensing_record | 4 | prescription, pharmacist, patient, dispensing_item | Moderate |
| 14 | nurse | 3 | staff, department, patient_nurse_assignment | Low-Moderate |
| 14 | room | 3 | department, bed, room_assignment | Low-Moderate |
| 14 | patient_doctor_assignment | 3 | patient, doctor, staff | Low-Moderate |
| 14 | vital_signs | 3 | patient, medical_record, staff | Low-Moderate |
| 14 | medical_procedure | 3 | medical_record, patient, doctor | Low-Moderate |
| 14 | medical_report | 3 | patient, medical_record, staff | Low-Moderate |
| 14 | medicine | 3 | prescription_item, medicine_batch, dispensing_item | Low-Moderate |
| 14 | lab_report | 3 | lab_test_order, patient, staff | Low-Moderate |
| 14 | dispensing_item | 3 | dispensing_record, medicine, medicine_batch | Low-Moderate |
| 23 | laboratory, pharmacist, bed, staff_shift, medical_record_adjustment, prescription_item, medicine_batch, lab_test_result, payment, patient_adjustment | 2 each | (see §1.1) | Low |
| 33 | users, receptionist, patient_insurance, bill_item, laboratory_equipment | 1 each | (see §1.1) | Very Low (leaf) |
| — | role_permissions, hospital_settings, failed_jobs | 0 | — | Isolated infrastructure |

### 1.3 Bottleneck analysis

- **`staff` is structurally the most connected table, but it is not the biggest query-time bottleneck.** Its 20 connections are almost entirely nullable "who did this" audit columns (`created_by`, `assigned_by`, `generated_by`, `received_by`, `booked_by`) that are rarely the driving filter of a query — they're mostly joined-in for display (a name), not filtered on. The real cost center it creates is different: because `staff` sits directly upstream of `doctor`/`nurse`/`pharmacist`/`lab_technician`/`receptionist`, almost every list/detail endpoint in the system joins through it at least once just to resolve a person's name (confirmed directly by the endpoint audit: `appointment`, `medical_record`, `lab_test_order`, `bill`'s payment/generated_by, `schedule`, `staff` listing itself — all join `staff` explicitly).
- **`patient` is the true hot table.** It is the root of the clinical and billing domain (15 direct connections), it is the table both API audits independently confirmed is explicitly "seeded to 1M+ rows for scale testing" in the code's own comments, it is searched via free-text on nearly every list screen, and it is the join target of the two heaviest endpoints found (`lab` index, 5 joins; `medical-records`/`appointments` index, 4 joins each).
- **`medical_record`** is the third bottleneck candidate: 10 connections, explicitly indexed on `patient_id`/`doctor_id` (a sign its designers already anticipated load), and the root of the single widest eager-load fan-out found anywhere in the codebase (7 sibling relations off one `show()` call — see Part 3).
- **`appointment`, `bill`, and `lab_test_order`** are second-tier bottleneck candidates: each is a 1M+-row-scale table (per the same seeding comments), each has 3+ direct clinical/financial connections, and each is the subject of a list endpoint that runs 4+ joins plus 4+ separate stat/count queries per page load (see Part 2).
- **Most frequently queried, because so much else depends on it:** `patient` (referenced from nearly every list/search/dashboard endpoint across both repos) and `staff` (joined for name-resolution almost everywhere a person needs to be displayed).
- **Highest optimization priority (ranked):** `patient` → `appointment` → `medical_record` → `bill` → `lab_test_order` → `staff`/`doctor` (join targets, not independently queried at volume) → everything else. This ordering matches the five tables the codebase's own comments call out as scale-tested, plus `staff`/`doctor` because they are joined into nearly every one of those five tables' list endpoints.

### 1.4 Circular relationships

**One confirmed cycle:** `department.head_staff_id → staff.staff_id` and `staff.department_id → department.department_id` (the latter added by a later migration). A department's head is a staff member, and any staff member (including that head) can belong to a department — two distinct relationships (headship vs. membership) that form a literal referential cycle. Both FKs are nullable, so there's no insert-order deadlock, but: neither table can be truncated independently without `CASCADE`, and naive relation-chasing (`department->headStaff->department`) could recurse if a consumer isn't careful — **no such recursive consumer was found in the application code** (see Part 3.3), so this is a schema-design risk, not an active bug today.

No other direct or indirect cycles exist in the schema graph.

---

## Part 2: Endpoint Performance Analysis

Both repos combined expose **~209 route entries** (129 in `Database-final/routes/web.php`, 80 in `central-service/routes/api.php`). The overwhelming majority of Database-final's routes are thin HTTP proxies to central-service with **zero direct SQL** — the real query construction, joins, and aggregation live almost entirely in central-service's `*ApiController` classes, plus a smaller but architecturally important set of direct-query endpoints in Database-final (`DashboardController`, parts of `AdminController`, `AuthController`, `RolePermissionController`, and scattered dropdown-population calls embedded in otherwise-proxy controllers).

### 2.1 Endpoint cost ranking

"Estimated Cost" is qualitative (Critical / High / Medium / Low), judged under the 1M+-rows-per-table assumption, factoring in: join count, number of separate DB round trips per request, whether the query is cached, and whether it hits the identified hot tables (§1.3).

| Rank | Endpoint | Tables Used | Est. Cost | Reason |
|---|---|---|---|---|
| 1 | `GET /dashboard` (all non-admin roles) | patient, appointment, staff, room, lab_test_order, payment, bill, medical_report, prescription_item, dispensing_record, medicine, medicine_batch, patient_nurse_assignment, laboratory_equipment | **Critical** | 8–10 separate synchronous queries per role, **uncached** for doctor/nurse/pharmacist/receptionist/lab_technician (only admin's variant is `Cache::remember`'d, 60s) — re-runs on every single page load for those roles |
| 2 | `GET /dashboard` (admin, cold cache) | (all of the above plus department, bed, doctor, nurse) | **Critical** | ~54 discrete queries in one request when the 60s cache is cold; two PHP loops (9-month trend, 7-day sparkline) alone generate 32 of those queries, each collapsible into one GROUP BY |
| 3 | `GET /bills` (BillingApiController@index) | bill, patient, payment, bill_item | **Critical** | 2 paginated list queries + up to 6 separate aggregate/count queries = 8 round trips; main list query does a 9-column GROUP BY purely to SUM a joined `payment` table per bill |
| 4 | `GET /lab` (LabApiController@index) | lab_test_order, patient, doctor, staff, lab_technician, lab_test_result, lab_report | **Critical** | 3 paginated queries + 4 stat queries = 7 round trips; the orders sub-query alone carries **5 joins**, the highest join count found anywhere in the codebase |
| 5 | `GET /pharmacy` (PharmacyApiController@index) | medicine, medicine_batch, prescription, patient, doctor, staff, dispensing_record, pharmacist | **High** | 4 independent paginated lists + 4 separate count queries = 8 round trips in one call |
| 6 | `GET /appointments` (AppointmentsApiController@index) | appointment, patient, doctor, staff | **High** | 4-join main query, uncached, on what is almost certainly the single most-visited list screen in the app; plus 4 separate count queries + 1 grouped calendar query = 6 total |
| 7 | `GET /medical-records` (MedicalRecordsApiController@index) | medical_record, patient, doctor, staff | **High** | 4-join query against the 3rd-most-connected table, free-text search across 4 columns via `ilike` |
| 8 | `GET /staff` and `GET /staff/export` (AdminController) | staff, users, doctor, nurse, receptionist, pharmacist, lab_technician, department | **High** | 6-join query (5 subtype LEFT JOINs + department); export variant additionally uses `->get()` instead of `->cursor()`, loading the entire filtered result set into memory before streaming — the single most memory-risky endpoint found |
| 9 | `GET /room-beds`, `GET /room-assignments` (RoomAssignmentsApiController) | bed/room_assignment, room, department, patient, staff | **Medium-High** | 4 joins each, paginated, but room/bed counts grow far slower than clinical tables |
| 10 | `GET /schedule` (StaffShiftsApiController@schedulePage) | staff_shift, staff, users | **Medium** | 2 joins + 4 separate count queries, paginated |
| 11 | `GET /reports` (AdminController@reports) | patient, appointment, medical_record, prescription, lab_test_order, payment, bill | **Medium** | 7 sequential aggregate queries with no joins, but no caching on an admin summary page |
| 12 | `GET /patients` / `GET /patients/search` (PatientsApiController) | patient, patient_insurance | **Medium** | No raw joins (correlated subquery instead), but free-text `ilike` OR across 4 columns (patient_id, name, phone, email) on the largest table in the system — see Part 5 |
| 13 | `GET /patients/{id}` (Database-final `PatientController::show`) | patient (+4 more via HTTP), doctor, nurse, staff_shift | **Medium** | Not a single heavy query — 8 **sequential, non-parallelized** round trips (5 HTTP calls to central-service + `Doctor::with('staff')->get()` + `Nurse::with('staff')->get()` + 1 more HTTP call) per page view; latency tax, not join cost |
| 14 | `POST /dispensing` (PharmacyApiController@dispense) | medicine, medicine_batch, dispensing_item | **Medium** | Not a read-scaling risk (bounded by items-per-prescription), but holds `lockForUpdate()` row locks across a variable number of round trips inside one transaction — a **concurrency/lock-contention** risk under high simultaneous dispensing volume, distinct from the read-query risks ranked above |
| 15 | `GET /medical-records/{id}` (MedicalRecordsApiController@show) | medical_record + 7 eager-loaded relations | **Low-Medium** | Heaviest single-record fan-out in the app (7 relation branches, one nested 2 levels deep) but correctly eager-loaded as one query set, not per-row — cost is fixed per request, not multiplied by table size |
| 16 | `GET /medicines/all`, `GET /medicine-batches`, `GET /lab-equipment`, `GET /laboratories/all`, `GET /staff-shifts` | medicine, medicine_batch, laboratory_equipment, laboratory, staff_shift | **Low today / High under the 1M+ hypothesis** | All unpaginated `->get()` calls; currently safe only because these particular tables are small in practice — under the brief's stated assumption that every table reaches 1M+ rows, each becomes a full unbounded table scan on every request that touches it |

### 2.2 Why these endpoints could become slow at scale

- **Dashboards (all variants) and any "index" screen with a `stats` block** repeat the same anti-pattern throughout both repos: N separate `Model::where(cond)->count()` calls where one `COUNT(*) FILTER (WHERE ...)` query would do. This pattern appears (with slightly different counts) in `AppointmentsApiController::index`, `BillingApiController::index`, `PharmacyApiController::index`, `LabApiController::index`, `RoomsApiController::index`, `StaffShiftsApiController::schedulePage`, and all 6 dashboard role variants. It is the single most repeated performance anti-pattern in the codebase — and notably, the fix pattern already exists elsewhere in the same code (`RoomsApiController`'s main query and `LabApiController`'s `pending_results` both correctly use `FILTER`/`NOT EXISTS`), it just isn't applied consistently to the `stats` blocks.
- **Free-text `ilike '%...%'` search** on `patient` (4 columns), `staff` (5 columns/conditions), `medical_record` (4 columns), `appointment` (3 columns), `medicine` (3 columns) cannot use a standard B-tree index — Postgres will fall back to a sequential or trigram-index scan. The schema does have trigram GIN indexes on several of these columns already (added in `2026_01_02_000005_add_trigram_search_indexes.php` — a genuinely good existing decision, see Part 5), but this needs verifying column-by-column against every search endpoint found, since not every `ilike` target column was confirmed to have a matching trigram index.
- **Unindexed foreign keys used as join or filter predicates** (the large majority of FK columns in this schema, per §1.1) mean that as each connected table crosses into the millions of rows, every join and every `WHERE patient_id = ?` / `WHERE doctor_id = ?` on a non-indexed FK degrades from an index lookup to a sequential scan or a much more expensive hash/merge join plan.
- **`GET /staff/export`'s unbounded `->get()`** is the one place a memory-exhaustion failure mode (not just slowness) was identified — everywhere else that reads a scale-tested table either paginates or explicitly uses `->cursor()`.

---

## Part 3: Relationship Depth

### 3.1 Deepest chain by raw schema traversal (not necessarily hit by one query)

```
patient → medical_record → prescription → dispensing_record → dispensing_item → medicine_batch → medicine
```
7 tables, 6 hops — the full pharmacy fulfillment path from a patient's clinical record through to the specific medicine batch dispensed.

**Extending further into the staffing subsystem (9 tables, 8 hops)** — illustrates how thoroughly `staff` sits upstream of the entire clinical/pharmacy chain:
```
staff → doctor → appointment → medical_record → prescription → dispensing_record → dispensing_item → medicine_batch → medicine
```

**The example chain given in the brief** (`patient → medical_record → prescription → prescription_item → medicine`, 4 hops) is the shallower "just the prescribed items" variant of the same path, without the pharmacy-fulfillment tail.

### 3.2 Deepest chain actually traversed by a single query/eager-load

This is the more operationally relevant measure — what does one endpoint actually pull in one request?

**`GET /medical-records/{id}` (`MedicalRecordsApiController::show`)** is the deepest **and** widest single eager-load found in either repo:
```
medical_record  →  prescription  →  prescription_item  →  medicine     (3 hops, one branch)
      │
      ├── patient
      ├── doctor → staff
      ├── medical_record_adjustment
      ├── medical_report
      ├── medical_procedure
      └── vital_signs
```
7 top-level relation branches off one root, the deepest branch running 3 hops. This is correctly implemented as one batched query set via Eloquent's `with([...])`, not a loop — so it does not degrade with table size the way an N+1 pattern would. It is flagged here purely as the depth/complexity benchmark for the codebase, and as the place most likely to regress into a real problem if this same eager-load tree were ever nested inside a list/loop context (it currently is not).

**Runner-up:** `GET /patients/{id}` (`PatientsApiController::show`) — two parallel 3-hop branches: `patient → patient_doctor_assignment → doctor → staff` and `patient → patient_nurse_assignment → nurse → staff` (plus a `shift` side-branch on the nurse side).

### 3.3 Circular relationships and overly complex graphs

- **Schema-level cycle:** `department ↔ staff` (see §1.4). **No application code was found that recursively traverses this cycle** — every `with()`/`load()` call and every DTO's `fromArray()` in both repos was checked; relation chains terminate cleanly at every branch (confirmed independently by the N+1/depth research pass).
- **No circular eager-loading exists in code today.** `central-service`'s `Patient` model deliberately has **no** `appointments`/`medicalRecords`/`bills` Eloquent relations at all — those cross-domain concepts are fetched only via separate REST calls from Database-final, never nested inside a DTO that could recurse.
- **Overly complex object graph:** the `MedicalRecordsApiController::show` 7-branch fan-out (§3.2) is the one place worth watching if the response shape grows further — it is already the widest single-endpoint load in the system.

---

## Part 4: Query Risk Analysis

### 4.1 N+1 query problems

| # | File | Method | Reason | Est. Impact |
|---|---|---|---|---|
| 1 | `Database-final/app/Http/Controllers/RolePermissionController.php:128-132` | `update()` | Loops over selected capabilities issuing one `RolePermission::create()` INSERT per capability inside a transaction, instead of one bulk `insert([...])` | Low real-world impact (`role_permissions` is tiny, action is infrequent), but a textbook N+1-on-writes shape |
| 2 | `central-service/app/Http/Controllers/BillingApiController.php:83-92` | `store()` | Loops `BillItem::create()` per line item instead of one bulk insert | Low (bounded by items-per-bill) |
| 3 | `central-service/app/Http/Controllers/PrescriptionsApiController.php:33-42` | `store()` | Loops `PrescriptionItem::create()` per item instead of one bulk insert | Low (bounded by items-per-prescription) |
| 4 | `central-service/app/Http/Controllers/PharmacyApiController.php:269-326` | `dispense()` | Nested loop: per prescription item, then per batch consumed under FEFO — each iteration does a locked `Medicine`/`medicine_batch` read plus a `DispensingItem` insert and a batch decrement | Not a scaling risk in the classic sense (bounded by items×batches per prescription), but extends `lockForUpdate()` lock-hold time across many round trips — a **concurrency** risk under high simultaneous dispensing |

**No classic read-side N+1** (relationship access inside a `foreach`/`map()` over a collection that wasn't eager-loaded) was found in either repo. Every DTO (`PatientDTO`, `MedicalRecordDTO`, `AppointmentDTO`, `BillDTO`, etc.) is built from data that already arrived fully joined/pre-resolved from an API response or a single eager-loaded Eloquent tree — not from live, lazily-triggered relationships inside a loop.

### 4.2 Lazy loading issues

None found as an active bug. Every `show()`-style endpoint audited uses proportionate `with([...])` eager loading (see Part 3.2 for the deepest example). Laravel's lazy-loading-prevention (`Model::preventLazyLoading()`) status is **not visible in the audited application code** — cannot verify whether it's enabled in `AppServiceProvider` for non-production environments as a safety net; worth checking directly if not already present.

### 4.3 Eager loading of unnecessary data

Not found as a systemic issue in the API layer — eager-loaded relations in `show()` endpoints are consistently the same ones consumed by that endpoint's `present()` method. The closer relative of this problem is **repeated unpaginated dropdown-population queries** (§4.4), which over-fetch by loading an entire table rather than by over-eager-loading relations on a bounded set.

### 4.4 Missing pagination

| # | Location | Table(s) | Reason it's currently masked | Severity under 1M+ assumption |
|---|---|---|---|---|
| 1 | `Database-final/app/Http/Controllers/PatientController.php:162-163` (`show()`) | doctor, nurse (`with('staff')`) | Small table today | **High** — duplicated across **8 separate call sites**: also `AppointmentController.php:64,98,125` (×3), `MedicalRecordController.php:50,70,133` (×3), `LabController.php:50-51` (×2, doctor+lab_technician) |
| 2 | `central-service/app/Http/Controllers/PharmacyApiController.php:86` (`listAllMedicines`) and inline in `dispensingPage()`:170 | medicine | Small catalog today | Medium-High — hit on every medical-record and dispensing page load |
| 3 | `central-service/app/Http/Controllers/PharmacyApiController.php:110-125` (`listBatches`) | medicine_batch (×2 queries: `batches` and `expiring`) | Small today, but this table grows with every restock/dispense event over time | High — most likely of the "currently small" group to genuinely grow unbounded |
| 4 | `Database-final/app/Http/Controllers/AdminController.php:40` (`exportStaff`) | staff + 5-way join | Uses `->get()` not `->cursor()`, unlike its own patient-export sibling | **Critical** — the one true memory-exhaustion risk found, not just a slowness risk |
| 5 | `central-service/app/Http/Controllers/LabApiController.php:207-221` | laboratory_equipment, laboratory | Small, per-hospital-scale table | Low-Medium |
| 6 | `central-service/app/Http/Controllers/StaffShiftsApiController.php:16-19` | staff_shift | Hardcoded `limit(50)` substitutes for real pagination | Medium |
| 7 | `central-service/app/Http/Controllers/RoomAssignmentsApiController.php` (`forPatient`, `bedsForRoom`) | room_assignment, bed | Scoped to one patient/room, naturally small | Low |

**No unbounded `->get()`/`::all()` was found against `patient`, `appointment`, `medical_record`, `bill`, or `lab_test_order` themselves** — the five tables the codebase's own comments confirm are scale-tested. Every list endpoint against those five correctly paginates, and the one true bulk export (`PatientController::exportPatients`) correctly streams via `->cursor()`.

### 4.5 Large SELECT * / full entity loads

No `SELECT *` equivalent (full unfiltered `Model::all()`/`get()`) was found against any of the five scale-tested tables. `PharmacyApiController::showMedicine` returns a full model with no `only()` projection — minor over-fetch, low impact (single-row, small table).

### 4.6 Multiple sequential queries that could be combined

| # | File | Method | Detail | Est. Impact |
|---|---|---|---|---|
| 1 | `central-service/app/Http/Controllers/BillingApiController.php` (`present()`, calling `Bill::paidAmount()` in `app/Models/Bill.php:33-36`) | `show`, `store`, `addItem`, `pay` | `paidAmount()` re-queries `payments()->sum(...)` even though `payments` was already eager-loaded two lines earlier and is iterated again right after — could just sum the in-memory collection | Low per-call, but fires on 4 different write/read paths |
| 2 | `central-service/app/Http/Controllers/LabApiController.php:186,195` (`enterResult()`) | — | Updates the lab order's status, then runs a **second** separate query to fetch that same order's `patient_id` — could fetch once and reuse | Low per-call, but on a high-frequency write path (lab results) |
| 3 | `central-service/app/Http/Controllers/PharmacyApiController.php:134-143` (`showBatch()`) | — | `MedicineBatch::find()` then a separate `Medicine::find()` where a single join would do | Low |
| 4 | `Database-final/app/Http/Controllers/DashboardController.php` (`adminData()`, lines 51 & 131) | — | `$stats['appointments']` (today's appointments) and `$todayCompleted` (today's completed appointments) are two separate full scans of the same date slice, combinable into one grouped query | Low-Medium (amortized by 60s cache, but full cost on every cache miss) |
| 5 | `Database-final/app/Http/Controllers/PatientController.php:114-166` (`show()`) | — | 8 sequential, non-parallelized round trips (5 HTTP + 3 local) per page view — see Part 2.1 rank 13 | Medium — latency tax multiplies with concurrent traffic, not with data volume |

### 4.7 Duplicate queries / repeated permission checks

**The single highest-frequency avoidable query in the entire application:** `Database-final/app/Models/User.php:54-65` (`permissions()`) re-queries `RolePermission::where('role', $this->role)->pluck('capability')` on **every call**, with no per-request memoization. `app/Providers/AppServiceProvider.php`'s `Gate::before` closure (line 29-31) calls it once for a wildcard check, and if that's not granted, Laravel's per-ability `Gate::define` closure (line 49) calls it **again** for the specific ability — 2 queries minimum per permission-gated request, before counting any additional explicit `@can`/`hasPermission()` check in the view/controller (the admin dashboard's KPI-card `reportUrls` blocks call it 4 more times). Since `permission:<capability>` middleware gates nearly all 120+ routes in `web.php`, this fires on **almost every authenticated page view** in the system.

### 4.8 Repeated COUNT queries

This is the most systemically repeated pattern found across the whole codebase — every one of these runs N separate `Model::where(cond)->count()` calls where a single `COUNT(*) FILTER (WHERE ...)` (or `SUM(CASE WHEN...)`) query would suffice:
- `AppointmentsApiController::index` — 4 counts (today/this_week/scheduled/cancelled)
- `BillingApiController::index` — up to 3 counts (unpaid/partially_paid/paid) plus 2 sums
- `PharmacyApiController::index` — 4 counts (total/available/low_stock/expired_batches)
- `LabApiController::index` — 4 counts (pending/in_progress/completed/pending_results)
- `RoomsApiController::index` — 4 counts (total_rooms/total_beds/available_beds/active_assignments) — note this endpoint's *main* list query already correctly uses `FILTER`, just not its stats block
- `StaffShiftsApiController::schedulePage` — 4 counts (scheduled/completed/on_leave/cancelled)
- `AdminController::reports` — 7 separate count/sum calls, no joins, uncached
- All 6 `DashboardController` role variants — see Part 2 rank 1-2; the admin variant's two PHP loops (9-month trend, 7-day sparkline) alone account for 32 of its ~54 queries

### 4.9 Repeated existence checks

`AppointmentsApiController::slotTaken()` and `RoomAssignmentsApiController::releaseAssignment()` both correctly use `exists()` as a single guard query before a write — **this is good practice, not a risk**, called out here only because it's the kind of check that's easy to get wrong (e.g. via `count() > 0` instead of `exists()`), and this codebase gets it right everywhere it was checked.

### 4.10 Inefficient joins / Cartesian joins

No true Cartesian join (missing join condition) was found anywhere in either repo. The one **inefficiency** worth flagging: `BillingApiController::index`'s main query does a `GROUP BY` on **9 columns** of the `bill` table purely to enable `SUM(payment.amount_paid)` per bill via a `LEFT JOIN` to `payment` — a correlated subquery or window function would avoid both the wide GROUP BY and any risk of a bill's total being miscounted if it were ever joined to an unexpectedly large number of payment rows.

### 4.11 Missing projections/DTOs, loading full entities for a few fields

`PharmacyApiController::showMedicine` (single-row, low impact) and `PharmacyApiController::listAllMedicines`/`dispensingPage`'s medicine list (unpaginated full models) are the only instances found. The DTO layer in Database-final (`PatientDTO`, `MedicalRecordDTO`, `AppointmentDTO`, `BillDTO`, etc.) consistently uses `only([...])` projections in the API layer's `present()` methods — this is a genuine strength of the codebase, not a gap.

---

## Part 5: Index Analysis

*(Recommendations only — no migration code, per the brief.)*

### 5.1 What's already done well

- **Trigram GIN indexes exist** on the free-text search columns of `staff`, `department`, `doctor`, `patient`, `room`, `appointment`, `medical_record`, and `medicine` (added in one dedicated migration, `2026_01_02_000005_add_trigram_search_indexes.php`) — this is exactly the right tool for `ilike '%...%'` search at scale, and its existence as a single deliberate migration suggests the original schema author was already scale-aware for search. **This needs to be cross-checked column-by-column against every `ilike` predicate found in Part 2/4** — the research did not confirm 100% coverage (e.g. `bill`/`lab_test_order`/`appointment`'s `booked_by`/`staff` name-concat searches were not individually verified against the trigram migration's exact column list).
- **`medical_record` has explicit `patient_id`/`doctor_id` indexes**, and **`bill`/`prescription`/`lab_test_order` have explicit `patient_id` indexes**, plus `bill`/`lab_test_order` have `status` indexed — these are exactly the columns their respective list/dashboard endpoints filter on most, so whoever added them was targeting the right queries.
- **`medicine_batch.expiry_date` is indexed**, directly supporting the FEFO (first-expired-first-out) dispensing logic that depends on it.

### 5.2 Missing indexes — foreign keys

Because this schema never uses `foreignId()->constrained()`, the large majority of FK columns have **no index at all** (see the full table in §1.1). The highest-priority missing FK indexes, ranked by how central their table is (§1.3) and how often the column is used as a join/filter predicate in the endpoint audit:

1. `appointment.doctor_id` — joined/filtered in nearly every appointment-related query (index, booked-slots, search, schedule)
2. `appointment.patient_id` — same
3. `medical_record.appointment_id`, `medical_record.created_by`
4. `bill.appointment_id`, `bill.generated_by`
5. `prescription.medical_record_id`, `prescription.doctor_id`
6. `lab_test_order.doctor_id`, `lab_test_order.technician_id`, `lab_test_order.medical_record_id`
7. `patient_doctor_assignment.patient_id`, `.doctor_id` — used by the doctor-role dashboard/patient-list scoping (`whereHas('doctorAssignments', ...)`) on every non-admin patient list
8. `patient_nurse_assignment.patient_id`, `.nurse_id` — same reasoning for nurse role
9. `room_assignment.patient_id`, `.room_id`, `.bed_id`
10. `staff.department_id`, `doctor.department_id`, `nurse.department_id`, `room.department_id`
11. `dispensing_record.prescription_id`, `.patient_id`; `dispensing_item.dispensing_id`, `.medicine_id`, `.batch_id`
12. `prescription_item.prescription_id`, `.medicine_id`
13. `medical_report.medical_record_id`, `.patient_id`; `medical_procedure.medical_record_id`, `.patient_id`, `.doctor_id`
14. `bill_item.bill_id`; `payment.bill_id`
15. `patient_insurance.patient_id`; `patient_adjustment.patient_id`; `medical_record_adjustment.medical_record_id`; `vital_signs.patient_id`, `.medical_record_id`; `lab_test_result.test_order_id`; `lab_report.test_order_id`, `.patient_id`

### 5.3 Composite index candidates (frequently combined filters)

- `appointment(doctor_id, appointment_date)` — directly supports `bookedSlots()` (filters both together) and the schedule/booking-conflict check (`slotTaken()`)
- `appointment(appointment_date, status)` — supports the dashboard's repeated "today's appointments by status" and "yesterday vs today" comparisons
- `lab_test_order(status, order_date)` and `lab_test_order(patient_id, status)` — supports both the lab index's status-filtered lists and the pending-results dashboard widgets
- `bill(status, bill_date)` — supports billing dashboard/report queries filtering unpaid/partially_paid bills by date range
- `medicine_batch(medicine_id, expiry_date)` — supports FEFO batch selection scoped to one medicine
- `staff_shift(staff_id, shift_date)` — supports the overlap-check (`hasOverlap()`) which currently fetches candidate shifts by staff_id and a 2-day window

### 5.4 Frequently sorted columns needing index support

`created_at` (medical_record, staff), `appointment_date`/`appointment_time`, `order_date`/`entered_at`/`generated_at` (lab tables), `dispensing_date`, `payment_date`/`bill_date`, `assigned_at` (room_assignment) — most of these are only indexed today where they double as a filter column; where a column is sorted-but-not-filtered, a plain index (not necessarily composite) on it would still help avoid a sort-after-scan plan at 1M+ rows.

### 5.5 Unique indexes

Already correctly present where they matter: `staff_id` on `doctor`/`nurse`/`receptionist`/`pharmacist`/`lab_technician` (enforcing 1:1 with staff), `email` on `users`, `(role, capability)` on `role_permissions`, `license_number` on `doctor`/`pharmacist` (nullable-unique). **Gap:** `users.staff_id` is not unique-constrained, so the schema does not actually enforce the intended 1:1 staff↔user relationship at the database level (see §1.1).

### 5.6 Covering indexes

**Cannot verify from code** whether any covering indexes (index-only scans) would meaningfully help without `EXPLAIN`/production query-plan data — this is a tuning step that should follow, not precede, adding the baseline FK/composite indexes above and observing real query plans against production-scale data.

---

## Part 6: Hotspot Analysis

Ranked by expected database load at scale, combining request-frequency intuition (these are the screens every role's normal workflow touches most often) with the query-cost findings from Part 2:

1. **Dashboards (all 6 role variants)** — the highest query-count endpoint category found, and the one every authenticated user hits first on every login and likely returns to repeatedly. Only the admin variant is cached; the other five re-run their full query set (8-10 queries each) on every single request.
2. **The permission-check middleware** (`EnsurePermission` → `Gate` → `User::permissions()`) — not a "screen" in the traditional sense, but it fires on **every single authenticated request across the entire application**, 2+ times each, uncached, making it plausibly the single highest-volume query pattern in the system in aggregate even though each individual query is cheap.
3. **Appointments list** (`GET /appointments`) — almost certainly the single most-visited list screen (scheduling is the core daily workflow for receptionists and doctors), 4-join query, uncached, plus 4 count queries.
4. **Patient search / patient list** — free-text search across 4 columns on the largest table in the system (`patient`), hit from the patient list itself, the patient-picker component (used in appointment/billing/vitals/medical-record forms), and the doctor-scoped dashboard "My Patients" widget.
5. **Billing list/dashboard** — heaviest single query shape found (9-column GROUP BY + join), plus up to 6 separate stat queries, and billing screens are checked constantly by receptionists throughout the day.
6. **Lab orders/results list** — 5-join query (highest join count in the codebase), hit by both doctors (ordering) and lab technicians (fulfilling) continuously.
7. **Pharmacy/dispensing screens** — 8 total queries per index load, plus the lock-holding `dispense()` write path under concurrent pharmacist usage.
8. **Patient detail page** (`GET /patients/{id}`) — not query-heavy in the join sense, but 8 sequential round trips per view, and this is the page every clinical workflow (documenting a visit, prescribing, billing) funnels through.
9. **Medical records list/detail** — 4-join list, 7-branch eager-load detail view; core clinical documentation screen.
10. **Reports/analytics page** (`AdminController::reports`) — lower traffic (admin-only) but 7 uncached sequential aggregate queries against the biggest tables in the system every time it's opened.

**Event/publication-style listings** (in the sense the brief's examples reference — "featured content," "event listings") don't have a direct analog in this hospital-management domain; the closest equivalents are the appointments calendar/list and the lab orders queue, both already covered above.

---

## Part 7: Scalability Score

Scores are 1 (best/lowest) to 10 (worst/highest) for Complexity and Query Cost; Risk and Priority are qualitative.

| Module | Complexity (1-10) | Query Cost (1-10) | Scalability Risk | Optimization Priority |
|---|---|---|---|---|
| Dashboard / Reporting | 9 | 9 | **High** | **Critical** |
| Auth / RBAC (permission checks) | 3 | 6 (by frequency, not per-query weight) | **High** | **Critical** |
| Appointments | 7 | 7 | High | High |
| Billing | 8 | 8 | High | High |
| Laboratory | 8 | 8 | High | High |
| Patients (core) | 6 | 6 | Medium-High | High |
| Medical Records | 7 | 5 | Medium | Medium |
| Pharmacy / Dispensing | 7 | 7 | Medium-High (concurrency, not just volume) | Medium |
| Staff / HR (Admin) | 6 | 6 | Medium | Medium |
| Rooms / Beds | 5 | 4 | Low-Medium | Low-Medium |
| Departments / Reference data | 2 | 2 | Low | Low |
| Vital Signs / Procedures / Reports (leaf clinical tables) | 3 | 3 | Low | Low |

---

## Part 8: Top 20 Optimization Opportunities

Ranked by estimated performance gain per unit of effort. **No code is changed here — recommendations only.**

| # | Location | Problem | Why it's expensive | Est. Impact | Difficulty |
|---|---|---|---|---|---|
| 1 | `User::permissions()` / `Gate` closures | Re-queries `role_permissions` 2+ times per request, no memoization | Fires on nearly every authenticated request in the app | **Very High** — removes the highest-frequency avoidable query in the system | **Easy** (per-request static cache / memoize on the User instance) |
| 2 | Missing FK indexes across ~30 columns (§5.2) | Postgres never auto-indexes FKs here since `->foreign()` is used without `->constrained()` | Every join/filter on these columns degrades from index lookup to seq scan as tables grow | **Very High** | **Easy** (pure index additions, no logic change) |
| 3 | All 6 `DashboardController` `stats` blocks + the 6 API `index()` stats blocks (§4.8) | N separate `count()`/`sum()` calls per widget | Directly multiplies dashboard/list-page query count by 3-8x | **High** | **Medium** (rewrite as single FILTER/CASE queries — pattern already exists elsewhere in the codebase to copy) |
| 4 | Non-admin `DashboardController` variants uncached | Doctor/nurse/pharmacist/receptionist/lab_technician dashboards re-run their full query set every request | Same query cost as the admin dashboard but without its 60s cache | **High** | **Easy** (apply the same `Cache::remember` pattern already used for admin) |
| 5 | `Doctor::with('staff')->get()` / `Nurse::with('staff')->get()` duplicated across 8 call sites | Unpaginated full-table fetch, uncached, repeated verbatim in 4 different controllers | Under the 1M+-rows assumption, becomes a full table scan on 8 different high-traffic endpoints | **High** | **Easy** (cache once, e.g. `Cache::remember('doctors:with-staff', ...)`, reuse everywhere) |
| 6 | `AdminController::exportStaff` uses `->get()` not `->cursor()` | Loads entire filtered 6-join result set into memory before streaming | Genuine memory-exhaustion risk, not just slowness, at 1M+ rows | **High** | **Easy** (match the pattern already used in `PatientController::exportPatients`) |
| 7 | `BillingApiController::index`'s 9-column GROUP BY + LEFT JOIN to sum payments | Wide, expensive grouped join purely to compute one derived sum per row | Heaviest single query shape found in the codebase | **High** | **Medium** (replace with a correlated subquery or window function) |
| 8 | Free-text `ilike` search columns not fully cross-checked against trigram index coverage | Any uncovered column falls back to a sequential scan per search keystroke | Patient/staff/appointment/medical-record search are among the highest-traffic actions in the app | **High** | **Medium** (audit + add missing trigram indexes) |
| 9 | Composite indexes for common filter pairs (§5.3) | Single-column indexes don't help queries that filter on two columns together | Every dashboard "today + status" style query | **Medium-High** | **Easy** |
| 10 | `AppointmentsApiController::bookedSlots`/`slotTaken` and `StaffShiftsApiController::hasOverlap` | Rely on `appointment(doctor_id, appointment_date)` / `staff_shift(staff_id, shift_date)` which aren't indexed as composites | Booking-conflict checks run on every appointment/shift create — a write-path latency cost that compounds with scheduling volume | **Medium** | **Easy** |
| 11 | `PharmacyApiController::listBatches` — two unbounded `->get()` queries with duplicate join logic | `medicine_batch` grows with every restock/dispense; also runs the same join pattern twice | Most likely of the "currently small" unpaginated queries to become a real problem over time | **Medium** | **Medium** (paginate + derive the `expiring` subset from `batches` in-memory instead of a second query) |
| 12 | `Bill::paidAmount()` re-queries payments already eager-loaded | Fires on `show`/`store`/`addItem`/`pay` | Avoidable extra query on 4 write/read paths for the second-heaviest domain (billing) | **Medium** | **Easy** (sum the already-loaded collection) |
| 13 | `LabApiController::enterResult` double-fetches the same lab order | 2 sequential queries where 1 would do | High-frequency write path (lab results) | **Low-Medium** | **Easy** |
| 14 | `RolePermissionController::update` N+1 write loop | One INSERT per selected capability instead of a bulk insert | Low real impact today (small table, infrequent action), but a textbook fix | **Low** | **Easy** |
| 15 | `BillingApiController::store` / `PrescriptionsApiController::store` per-item insert loops | N individual INSERTs instead of one bulk insert | Bounded by items-per-bill/prescription, low impact, but free to fix alongside #14 | **Low** | **Easy** |
| 16 | `PatientController::show`'s 8 sequential, non-parallelized round trips | Every HTTP call to central-service blocks before the next starts | Latency tax on the most central clinical workflow page, multiplies with concurrency | **Medium** | **Hard** (requires concurrent HTTP client usage, more invasive than a query change) |
| 17 | `PharmacyApiController::dispense()` holds row locks across many round trips | `lockForUpdate()` spans a variable, potentially large loop | Concurrency/throughput risk under simultaneous dispensing of shared medicines, not a pure read-scaling issue | **Medium** | **Hard** (requires careful transaction-shape rework, correctness-sensitive) |
| 18 | `users.staff_id` not unique-constrained | Schema doesn't enforce the intended 1:1 staff↔user relationship | Data-integrity risk more than a query-cost one, but relevant to relationship modeling accuracy | **Low** (perf) / **Medium** (integrity) | **Easy** |
| 19 | `department ↔ staff` circular FK | No active bug today, but constrains future schema evolution (can't drop/truncate either table independently) | Design risk for future migrations, not current query cost | **Low** | **Medium** (would require deciding which direction is authoritative) |
| 20 | Small-reference-table endpoints left unpaginated (`medicines/all`, `laboratories/all`, `lab-equipment`, `staff-shifts`) | Currently safe only because these tables are small in the real deployment | Only matters if catalog/equipment/shift-history growth ever approaches the 1M+ hypothesis directly | **Low today / High hypothetically** | **Easy** (add pagination now, cheaply, ahead of need) |

---

## Part 9: Overall Assessment

**Overall database architecture quality:** Solid for a system at its current stage. The schema is normalized, consistently uses append-only audit tables for anything historically sensitive (`medical_record_adjustment`, `patient_adjustment`), correctly avoids naive M:M pivots in favor of proper assignment-history tables with their own business state, and shows clear evidence that whoever built it already thought about search performance (the dedicated trigram-index migration) and about avoiding some N+1 patterns (batched `whereIn()` lookups, `NOT EXISTS` instead of a `pluck()`-then-`whereNotIn()` that would blow past Postgres's bind-parameter limit — both found directly in the code with explanatory comments). The split between `Database-final` (thin HTTP proxy layer) and `central-service` (query/business logic owner) is architecturally clean and keeps almost all the real database work in one place, which is genuinely helpful for a report like this one and would be helpful for whoever optimizes it next.

**Estimated readiness for 1M+ rows per table: Not ready without the Part 8 changes, but closer than a typical from-scratch audit finds.** The single biggest gap is structural rather than logical: this codebase's FK-declaration style (`->foreign()->references()->on()` without `->constrained()`) means the large majority of foreign keys were never indexed, and that one fact underlies most of the "High"/"Critical" cost ratings in Part 2. Fixing it is mechanically simple (pure index additions, no logic changes) and would likely resolve more of the ranked risk than any other single change in this report.

**Biggest performance risks, in order:**
1. Unindexed foreign keys across ~30 columns — the foundational issue everything else compounds on top of.
2. The repeated-COUNT-queries anti-pattern in every dashboard and list-index endpoint (6+ independent occurrences).
3. Uncached non-admin dashboards re-running their full query set on every request.
4. The permission-check re-query firing on nearly every authenticated request.
5. `AdminController::exportStaff`'s unbounded `->get()` — the one genuine memory-exhaustion risk found.

**Most critical bottlenecks:** the `patient` table (root of the clinical/billing domain, explicitly scale-tested, hit by free-text search on nearly every screen) and the dashboard endpoints (highest query count, lowest caching coverage).

**Areas that are already well designed:**
- The DTO/`present()` projection pattern (no full-entity-over-the-wire waste in the API layer).
- The existing trigram search indexes (even if coverage needs a final audit pass).
- Correct use of `exists()`/`NOT EXISTS` over `count() > 0` or bind-parameter-unsafe `whereNotIn(pluck())`.
- Correct `->cursor()` streaming for the one genuinely large bulk export that has it (`PatientController::exportPatients`).
- Correct batched `whereIn()` lookups instead of per-row loops (e.g. `MedicalRecordController`'s Mongo version-count batching, `AdminController::departments`'s staff-name resolution, `ScheduleController::index`'s role/department resolution).
- No true Cartesian joins and no circular eager-loading anywhere in either repo.

**Recommended optimization order before making any code changes:**
1. Add the missing FK indexes (§5.2) — zero logic risk, addresses the structural root cause.
2. Add the composite indexes (§5.3) for the specific filter/sort pairs identified.
3. Memoize/cache the permission check (#1 in Part 8) — small, isolated, extremely high frequency benefit.
4. Consolidate the repeated COUNT-query blocks into single FILTER/CASE queries, reusing the pattern that already exists elsewhere in this same codebase.
5. Extend the admin dashboard's caching approach to the other five role variants.
6. Fix `AdminController::exportStaff` to use `->cursor()`.
7. Everything else in the Part 8 table, roughly in the order ranked there.

This ordering front-loads the changes with the best impact-to-risk ratio (pure index additions and isolated caching) before touching anything that changes query shape or transaction/locking behavior, which is the more surgically delicate work (items 16-17 in Part 8) and should follow, not lead, the low-risk wins.
