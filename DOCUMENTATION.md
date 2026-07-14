# Smart Hospital Management System — System Documentation

A **staff-only** hospital management system built with **Laravel 13 (PHP 8.3+)**
on three data stores: **PostgreSQL** (core relational, 39 tables), **MongoDB**
(document / versioning / audit, via `mongodb/laravel-mongodb`), and **Redis**
(sessions, cache). No page is reachable before login; every page is gated by
role-based access control implemented with Laravel **Gates** and a `permission:`
route middleware.

> Stack verified locally: Laravel 13.12, PHP 8.4, PostgreSQL 18 (39 tables via
> migrations), MongoDB 7, Redis 7 — login, RBAC (403 → Unauthorized page),
> medical-record versioning to MongoDB and audit logging all confirmed working.

---

## Table of contents
1. [Function list per module](#1-function-list-per-module)
2. [Page list](#2-page-list)
3. [Role & permission table](#3-role--permission-table)
4. [PostgreSQL schema (39 tables)](#4-postgresql-schema-39-tables)
5. [Primary keys & foreign keys](#5-primary-keys--foreign-keys)
6. [Table relationship explanation](#6-table-relationship-explanation)
7. [ERD explanation](#7-erd-explanation)
8. [MongoDB collections & sample documents](#8-mongodb-collections--sample-documents)
9. [Redis cache usage & key examples](#9-redis-cache-usage--key-examples)
10. [System workflow](#10-system-workflow)
11. [Use case list](#11-use-case-list)
12. [Functional requirements](#12-functional-requirements)
13. [Non-functional requirements](#13-non-functional-requirements)
14. [PHP folder structure](#14-php-folder-structure)
15. [Example PHP routes](#15-example-php-routes)
16. [Security rules](#16-security-rules)
17. [System architecture](#17-system-architecture)
18. [System flow — Data Flow Diagram](#18-system-flow--data-flow-diagram)
19. [How to run](#19-how-to-run)

---

## 1. Function list per module

### Module 1 — Core System Management

**A. Patient Management** (`PatientController`, `PatientRepository`)
- `search(term)` — search by ID, name, phone, or email
- `register()` / `store()` — register new patient with personal + insurance info
- `show()` — view patient profile + history + insurance
- `update()` — update patient information
- `discharge()` — discharge patient (status → `discharged`)
- `viewHistory()` — medical history (joined from `medical_record`)
- room/bed assignment via Room Management

**B. Staff Management** (`StaffRepository`, `DoctorRepository`, `AdminController`)
- Doctor: search by name/ID/specialization, add, update, view schedule, assign to department, deactivate
- Nurse: search by name/ID/ward, add, update, assign to ward, view shift schedule, deactivate
- Receptionist: search, add, update, assign to counter, view schedule, deactivate
- Pharmacist: search, add, update, assign to pharmacy unit, view schedule, deactivate
- Lab Technician: search, add, update, assign to laboratory, view schedule, deactivate

**C. Department Management** (`DepartmentRepository`, `AdminController::departments`)
- `search(term)`, `create()`, `update()`, assign staff, transfer patient, `occupancy()`, generate report

**D. Room Management** (`RoomRepository`, `AdminController::rooms`)
- `search()` by type/floor/availability, add, update, assign patient to room/bed, `availableBeds()`, mark maintenance, release after discharge

**E. Schedule Management** (`PageController::schedule`)
- search by patient/doctor/date, create appointment slots, book, cancel (reason log), reschedule, manage staff shifts, calendar, reminders

### Module 2 — Appointment Management (`AppointmentController`, `AppointmentRepository`)
- `search(term, date)`, `store()` (book), `update()`, `cancel()` (before completion, with reason), `isSlotTaken()` (doctor availability), view details

### Module 3 — Medical Record & Treatment (`MedicalRecordController`)
- `search()` by patient ID / name / doctor / visit date
- `store()` — create record during consultation + **MongoDB v1 snapshot**
- `show()` — view history, adjustments, version snapshots
- `adjust()` — amend with **reason + adjusted_by + adjusted_at**; original preserved
- vital signs (`ClinicalController`): temperature, BP, heart rate, height, weight
- treatment plan, prescription, medical procedure, medical report (`PageController`)

### Module 4 — Pharmacy & Medicine Inventory (`PharmacyController`)
- `medicines()` — track/manage stock, low-stock alerts (Redis)
- `dispense()` — dispense to patient, **decrement inventory in a transaction (FEFO batch pick)**
- `batches()` — batch expiry tracking + near-expiry alerts
- `interactions()` — drug interaction rules
- `substitutions()` — alternative medicine suggestions

### Module 5 — Laboratory & Diagnostics (`LabController`)
- `orders()` — manage test orders, filter by status
- `updateOrderStatus()` — assign technician, track status
- `enterResult()` — enter results + **MongoDB lab report doc**
- `equipment()` — track lab equipment/resources
- `labReports()` — generate reports for doctors/patients

### Module 6 — Billing & Payment (`BillingController`)
- `store()` — generate bill after appointment/treatment
- `addItem()` — add service / medicine / lab / procedure / room charges (subtotal auto-computed)
- `show()` — bill detail + total + balance
- `pay()` — process payment, auto-update bill status
- `payments()` — payment history

---

## 2. Page list

| # | Page | Route | Primary roles |
|---|------|-------|---------------|
| 1 | Login | `/login` | public |
| 2 | Forgot Password | `/forgot-password` | public |
| 3 | Reset Password | `/reset-password` | public |
| 4 | Dashboard | `/dashboard` | all |
| 5 | Patient Management | `/patients` | receptionist, doctor, nurse |
| 6 | Staff Management | `/staff` | admin |
| 7 | Doctor Management | `/doctors` | admin |
| 8 | Nurse Management | `/staff` (filtered) | admin |
| 9 | Receptionist Management | `/staff` (filtered) | admin |
| 10 | Pharmacist Management | `/staff` (filtered) | admin |
| 11 | Lab Technician Management | `/staff` (filtered) | admin |
| 12 | Department Management | `/departments` | admin, receptionist |
| 13 | Room & Bed Management | `/rooms` | admin, receptionist, nurse |
| 14 | Schedule Management | `/schedule` | admin |
| 15 | Appointment Management | `/appointments` | receptionist, doctor |
| 16 | Medical Record | `/medical-records` | doctor, nurse |
| 17 | Treatment Management | `/treatments` | doctor |
| 18 | Prescription | `/prescriptions` | doctor, pharmacist |
| 19 | Medical Procedure | `/procedures` | doctor |
| 20 | Medical Report | `/medical-reports` | doctor |
| 21 | Pharmacy & Inventory | `/medicines` | pharmacist |
| 22 | Medicine Batch | `/medicine-batches` | pharmacist |
| 23 | Medication Dispensing | `/dispensing` | pharmacist |
| 24 | Drug Interaction | `/drug-interactions` | pharmacist |
| 25 | Drug Substitution | `/drug-substitutions` | pharmacist |
| 26 | Laboratory & Diagnostic | `/lab-orders` | lab_technician, doctor |
| 27 | Lab Test Order | `/lab-orders` | lab_technician, doctor |
| 28 | Lab Test Result | `/lab-results` | lab_technician |
| 29 | Laboratory Equipment | `/lab-equipment` | lab_technician |
| 30 | Lab Report | `/lab-reports` | lab_technician, doctor |
| 31 | Billing & Payment | `/bills` | receptionist, admin, super_admin |
| 32 | Bill Detail | `/bills/{id}` | receptionist, admin, super_admin |
| 33 | Payment History | `/payments` | receptionist, admin, super_admin |
| 34 | Reports | `/reports` | admin |
| 35 | Profile | `/profile` | all |
| 36 | Settings | `/settings` | all |
| 37 | Unauthorized | `/unauthorized` | all (403) |
| 38 | 404 Not Found | fallback | all (404) |

---

## 3. Role & permission table

Seven roles: `super_admin`, `admin`, `doctor`, `nurse`, `receptionist`,
`pharmacist`, `lab_technician`. The matrix lives in `config/permissions.php`;
`RoleMiddleware` enforces it. `super_admin` and `admin` both hold `*` (all
permissions) — `super_admin` is additionally protected from being edited away
in the Roles & Permissions UI. There is no standalone Billing role: billing is
a capability granted to Receptionist (and Admin/Super Admin).

| Capability | Super Admin | Admin | Receptionist | Doctor | Nurse | Pharmacist | Lab Tech |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Manage staff/departments/rooms/schedules/reports | ✅ | ✅ | — | — | — | — | — |
| Patient registration | ✅ | ✅ | ✅ | — | — | — | — |
| Patient view | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| Appointment manage | ✅ | ✅ | ✅ | view | — | — | — |
| Room assignment / discharge | ✅ | ✅ | ✅ | — | view | — | — |
| Create medical record / treatment | ✅ | ✅ | — | ✅ | — | — | — |
| Adjust medical record | ✅ | ✅ | — | ✅ | — | — | — |
| Prescribe medicine | ✅ | ✅ | — | ✅ | — | — | — |
| Request lab tests | ✅ | ✅ | — | ✅ | — | — | — |
| Generate medical reports | ✅ | ✅ | — | ✅ | — | — | — |
| Record vital signs | ✅ | ✅ | — | — | ✅ | — | — |
| Manage medicine inventory/batches | ✅ | ✅ | — | — | — | ✅ | — |
| Drug interaction / substitution | ✅ | ✅ | — | — | — | ✅ | — |
| Dispense medication | ✅ | ✅ | — | — | — | ✅ | — |
| Manage lab orders / results / equipment | ✅ | ✅ | — | — | — | — | ✅ |
| Generate lab reports | ✅ | ✅ | — | view | — | — | ✅ |
| Generate bills / add items | ✅ | ✅ | ✅ | — | — | — | — |
| Process payments / history | ✅ | ✅ | ✅ | — | — | — | — |
| Edit role permissions | ✅ (protected) | ✅ | — | — | — | — | — |

---

## 4. PostgreSQL schema (39 tables)

Full DDL: [`database/migrations/001_schema.sql`](database/migrations/001_schema.sql).
Tables, grouped by module:

**Core / staff (9):** `users`, `staff`, `department`, `doctor`, `nurse`,
`receptionist`, `pharmacist`, `laboratory`, `lab_technician`
**Patients / rooms (5):** `patient`, `patient_insurance`, `room`, `bed`, `room_assignment`
**Appointments / scheduling (4):** `appointment`, `staff_shift`, `patient_doctor_assignment`, `patient_nurse_assignment`
**Medical records / treatment (8):** `medical_record`, `medical_record_adjustment`, `vital_signs`, `treatment_plan`, `prescription`, `prescription_item`, `medical_procedure`, `medical_report`
**Pharmacy (6):** `medicine`, `medicine_batch`, `drug_interaction`, `drug_substitution`, `dispensing_record`, `dispensing_item`
**Laboratory (4):** `lab_test_order`, `lab_test_result`, `laboratory_equipment`, `lab_report`
**Billing (3):** `bill`, `bill_item`, `payment`

Design highlights:
- `users` holds login accounts (`password_hash`, `role`, `staff_id` FK) — separate from `staff` personal info.
- Staff sub-types (`doctor`/`nurse`/…) carry only **role-specific** columns and link 1:1 to `staff`.
- Insurance is normalized out into `patient_insurance`; rooms and beds are separate tables.
- `medical_record` is immutable; `medical_record_adjustment` stores amendments (reason, adjusted_by, adjusted_at) so the original is never lost.
- `bill_item.subtotal` is a `GENERATED ALWAYS AS (quantity * unit_price) STORED` column.

---

## 5. Primary keys & foreign keys

All PKs are `VARCHAR(20)` prefixed business keys (e.g. `PAT0001`, `DOC0001`).
Selected FK map (full set in the DDL):

| Table | PK | Foreign keys → target |
|---|---|---|
| users | user_id | staff_id → staff |
| department | department_id | head_staff_id → staff |
| doctor / nurse / receptionist / pharmacist / lab_technician | *_id | staff_id → staff (UNIQUE); department_id → department; laboratory_id → laboratory |
| patient_insurance | insurance_id | patient_id → patient |
| bed | bed_id | room_id → room |
| room | room_id | department_id → department |
| room_assignment | room_assignment_id | patient_id → patient; room_id → room; bed_id → bed; assigned_by → staff |
| appointment | appointment_id | patient_id → patient; doctor_id → doctor; booked_by → staff |
| staff_shift | shift_id | staff_id → staff |
| patient_doctor_assignment | assignment_id | patient_id → patient; doctor_id → doctor; assigned_by → staff |
| patient_nurse_assignment | assignment_id | patient_id → patient; nurse_id → nurse; shift_id → staff_shift |
| medical_record | medical_record_id | patient_id → patient; doctor_id → doctor; appointment_id → appointment; created_by → staff |
| medical_record_adjustment | adjustment_id | medical_record_id → medical_record; adjusted_by → staff |
| vital_signs | vital_sign_id | patient_id → patient; medical_record_id → medical_record |
| treatment_plan | treatment_plan_id | medical_record_id → medical_record; doctor_id → doctor |
| prescription | prescription_id | medical_record_id → medical_record; patient_id → patient; doctor_id → doctor |
| prescription_item | prescription_item_id | prescription_id → prescription; medicine_id → medicine |
| medical_procedure | procedure_id | medical_record_id → medical_record; patient_id → patient; doctor_id → doctor |
| medical_report | report_id | patient_id → patient; medical_record_id → medical_record; generated_by → staff |
| medicine_batch | batch_id | medicine_id → medicine |
| drug_interaction | interaction_id | medicine_id_1 → medicine; medicine_id_2 → medicine |
| drug_substitution | substitution_id | original_medicine_id → medicine; alternative_medicine_id → medicine |
| dispensing_record | dispensing_id | prescription_id → prescription; pharmacist_id → pharmacist; patient_id → patient |
| dispensing_item | dispensing_item_id | dispensing_id → dispensing_record; medicine_id → medicine; batch_id → medicine_batch |
| lab_test_order | test_order_id | patient_id → patient; doctor_id → doctor; technician_id → lab_technician; medical_record_id → medical_record |
| lab_test_result | test_result_id | test_order_id → lab_test_order; entered_by → lab_technician |
| laboratory_equipment | equipment_id | laboratory_id → laboratory |
| lab_report | lab_report_id | test_order_id → lab_test_order; patient_id → patient; generated_by → staff |
| bill | bill_id | patient_id → patient; appointment_id → appointment; generated_by → staff |
| bill_item | bill_item_id | bill_id → bill |
| payment | payment_id | bill_id → bill; received_by → staff |

---

## 6. Table relationship explanation

- **Account ↔ person:** `users` (1) → `staff` (1). One login per staff member. Role-specific tables (`doctor`, `nurse`, …) extend `staff` 1:1 (sub-type pattern).
- **Patient hub:** `patient` is referenced by insurance, room assignment, appointment, medical record, prescription, lab order, bill, etc. — the central entity.
- **Doctor/Nurse assignments** are history tables (assigned_at / ended_at) — many assignments over time per patient.
- **Rooms → beds:** one room has many beds; `room_assignment` records who occupied which room/bed and when (released_at).
- **Medical record → children:** a record fans out to `vital_signs`, `treatment_plan`, `prescription` (→ `prescription_item`), `medical_procedure`, `lab_test_order`, `medical_report`, and `medical_record_adjustment`.
- **Pharmacy chain:** `medicine` → `medicine_batch` (stock lots) and is consumed by `prescription_item` and `dispensing_item`. `dispensing_record` → `dispensing_item` decrements stock.
- **Lab chain:** `lab_test_order` → `lab_test_result` and `lab_report`; equipment belongs to a `laboratory`.
- **Billing chain:** `bill` → many `bill_item` → and many `payment` rows; bill status derives from payments vs total.

Cardinality summary: `staff 1—1 users`, `staff 1—1 doctor/nurse/…`,
`patient 1—N appointment/medical_record/bill`, `medical_record 1—N
prescription/lab_test_order/…`, `prescription 1—N prescription_item`,
`bill 1—N bill_item / payment`, `medicine 1—N medicine_batch`.

---

## 7. ERD explanation

The ERD ([`docs/erd.drawio`](docs/erd.drawio)) groups all 39 tables into the
**6 system modules** from the project document, each a colored, labeled band.
Open it at <https://app.diagrams.net> (File → Open) or in the VS Code *Draw.io
Integration* extension. Regenerate with `php docs/generate_erd.php`.

- **Module 1 (blue) – Core System Management:** users, staff, department, the 5 staff sub-types, laboratory, patient, patient_insurance, room, bed, room_assignment, staff_shift, patient_doctor_assignment, patient_nurse_assignment (covers Patient, Staff, Department, Room and Schedule management). `staff` and `patient` are the hubs.
- **Module 2 (orange) – Appointment Management:** appointment.
- **Module 3 (purple) – Medical Record & Treatment:** medical_record + medical_record_adjustment, vital_signs, treatment_plan, prescription, prescription_item, medical_procedure, medical_report.
- **Module 4 (teal) – Pharmacy & Inventory:** medicine, medicine_batch, drug_interaction, drug_substitution, dispensing_record, dispensing_item.
- **Module 5 (red) – Laboratory & Diagnostic:** lab_test_order, lab_test_result, laboratory_equipment, lab_report.
- **Module 6 (gray) – Billing & Payment:** bill, bill_item, payment.

Within each module band, tables wrap left-to-right. Crow's-foot edges show FK
direction (the “many” side touches the child table). Cross-module edges (e.g.
`appointment → patient`, `medical_record → doctor`, `bill → patient`) are drawn
so the central `patient` and `staff` hubs and the links between modules are
visible.

---

## 8. MongoDB collections & sample documents

Database `smart_hospital_docs`. Seed/sample: [`database/mongodb/collections.js`](database/mongodb/collections.js).

| Collection | Purpose | Written by |
|---|---|---|
| `medical_record_versions` | full version history of medical records | `MedicalRecordController` |
| `medical_report_documents` | generated medical report snapshots | medical report flow |
| `lab_report_documents` | lab report snapshots | `LabController::enterResult` |
| `prescription_snapshots` | immutable prescription copies | prescription flow |
| `treatment_summary_documents` | narrative treatment summaries | treatment flow |
| `audit_log_documents` | security/audit trail | `AuditLogger` |
| `generated_report_snapshots` | any generated report payload | reports flow |
| `uploaded_medical_documents` | metadata for uploaded scans/files | upload flow |

Sample (`medical_record_versions`):
```json
{
  "medical_record_id": "MR0001",
  "version": 2,
  "type": "adjustment",
  "reason": "Corrected diagnosis after lab results",
  "snapshot": { "symptoms": "...", "diagnosis": "Stable angina", "treatment_notes": "..." },
  "adjusted_by": "STF0002",
  "created_at": "2026-05-28T09:15:00Z"
}
```
Sample (`audit_log_documents`):
```json
{
  "action": "medical_record.adjust", "entity": "medical_record",
  "entity_id": "MR0001", "actor_id": "USR0002", "actor_role": "doctor",
  "meta": { "reason": "Corrected diagnosis" }, "ip": "127.0.0.1",
  "at": "2026-05-28T09:15:00Z"
}
```

---

## 9. Redis cache usage & key examples

Full reference: [`database/redis/keys.md`](database/redis/keys.md). Redis holds
only temporary data; PostgreSQL is the source of truth.

| Purpose | Key pattern | TTL |
|---|---|---|
| Login session mirror | `session:{php_session_id}` | 3600s |
| Dashboard summary | `dashboard:summary` | 60s |
| Doctor availability | `availability:{doctor_id}:{date}` | 120s |
| Staff schedule | `schedule:{staff_id}:{date}` | 300s |
| Room/bed availability | `rooms:availability` | 120s |
| Medicine low-stock | `medicine:lowstock` | 120s |
| Medicine expiry alert | `medicine:expiry` | 300s |
| Frequently-viewed patient | `patient:viewed:{patient_id}` | 300s |
| Recently-viewed record | `mr:viewed:{user_id}` | 600s |

```bash
redis-cli GET dashboard:summary
redis-cli TTL session:abc123
redis-cli DEL medicine:lowstock     # bust after stock change
```

---

## 10. System workflow

1. **Login** — staff submits credentials → password verified (`password_verify`)
   → session created in PHP + mirrored to Redis with TTL.
2. **Authorization** — every request passes `AuthMiddleware` (logged in + live
   Redis session) then `RoleMiddleware` (role grants the route's permission).
   Failures redirect to `/login` or `/unauthorized`.
3. **Reception** — register patient → book appointment (doctor availability
   checked) → assign room/bed.
4. **Consultation** — doctor opens patient → creates medical record (v1 snapshot
   to Mongo) → records vital signs (nurse) → treatment plan → prescription →
   lab order.
5. **Pharmacy** — pharmacist dispenses against prescription → stock decremented
   transactionally (FEFO batch) → low-stock cache busted.
6. **Laboratory** — technician picks up order → enters result (Mongo report doc)
   → order marked completed.
7. **Billing** — receptionist (or admin) generates bill → adds items → records
   payment → bill status auto-updates (unpaid/partially_paid/paid).
8. **Audit** — every state-changing action writes an `audit_log_documents` entry.
9. **Logout** — Redis session key deleted (central invalidation) + PHP session destroyed.

---

## 11. Use case list

- UC-01 Staff login / logout
- ~~UC-02 Forgot & reset password via OTP~~ (removed — self-service reset descoped; admins reset passwords via staff management instead)
- UC-03 Register / search / update / discharge patient
- UC-04 Manage patient insurance
- UC-05 Assign patient to room/bed; release on discharge
- UC-06 Add / update / deactivate staff (all 5 sub-roles)
- UC-07 Create / update department; view occupancy
- UC-08 Manage rooms & beds; mark maintenance
- UC-09 Manage staff shifts & schedules
- UC-10 Book / reschedule / cancel appointment; check doctor availability
- UC-11 Create medical record; record vital signs
- UC-12 Adjust medical record with reason (original preserved + versioned)
- UC-13 Create treatment plan; record procedure; generate medical report
- UC-14 Create prescription & items
- UC-15 Manage medicine stock & batches; expiry alerts
- UC-16 Dispense medication; auto-update inventory
- UC-17 Manage drug interactions & substitutions
- UC-18 Create lab order; assign technician; track status
- UC-19 Enter lab result; generate lab report
- UC-20 Track lab equipment
- UC-21 Generate bill; add items; process payment; view history
- UC-22 View dashboard; generate operational reports
- UC-23 Enforce RBAC; record audit logs

---

## 12. Functional requirements

- FR-01 The system shall require login before any page is accessible.
- FR-02 The system shall authenticate staff against hashed passwords.
- ~~FR-03 The system shall support password reset via time-limited OTP.~~ (removed — see UC-02)
- FR-04 The system shall enforce role-based access on every route.
- FR-05 The system shall manage patients (register, search, view, update, discharge).
- FR-06 The system shall manage staff and the 5 staff sub-roles.
- FR-07 The system shall manage departments, rooms, beds, and assignments.
- FR-08 The system shall manage appointments and check doctor availability.
- FR-09 The system shall manage medical records and preserve original versions on adjustment.
- FR-10 The system shall record vital signs.
- FR-11 The system shall manage treatment plans, prescriptions, procedures, and reports.
- FR-12 The system shall manage medicine inventory, batches, and expiry alerts.
- FR-13 The system shall dispense medicine and update inventory atomically.
- FR-14 The system shall manage drug interactions and substitutions.
- FR-15 The system shall manage lab orders, results, equipment, and reports.
- FR-16 The system shall generate bills, add bill items, process payments, and show history.
- FR-17 The system shall record audit logs for important actions.
- FR-18 The system shall validate all form input server-side.

## 13. Non-functional requirements

- NFR-01 **Security:** hashed passwords, CSRF protection, prepared statements, HTTP-only session cookies, RBAC.
- NFR-02 **Data integrity:** FK constraints, transactions for multi-table writes, generated subtotal column.
- NFR-03 **Auditability:** immutable medical records; append-only audit log.
- NFR-04 **Performance:** Redis caching for dashboards/availability/alerts; indexed hot columns.
- NFR-05 **Availability/Resilience:** graceful fallback when Redis/Mongo are unavailable (file-backed) so core functions continue.
- NFR-06 **Maintainability:** layered MVC (controllers/models/repositories/services), PSR-4 autoloading.
- NFR-07 **Portability:** runs on PHP 8.1+ with PostgreSQL; Docker-friendly.
- NFR-08 **Usability:** consistent UI, role-filtered navigation, clear flash messages.
- NFR-09 **Scalability:** stateless controllers; session/cache externalized to Redis.
- NFR-10 **Privacy:** patient & medical data access restricted by role; no hard deletes of clinical data.

---

## 14. Laravel folder structure

A standard Laravel 13 layout. Project-specific files are annotated.

```
Database-Midterm/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # Auth, Dashboard, Patient, Appointment,
│   │   │                         #   MedicalRecord, Clinical, Pharmacy, Lab,
│   │   │                         #   Billing, Admin, Page
│   │   └── Middleware/
│   │       └── EnsurePermission.php   # RBAC route guard (alias: permission:)
│   ├── Models/                   # 39 Eloquent models + Concerns/HasBusinessKey
│   ├── Services/                 # AuditLogger (MongoDB), CentralServiceBus/Client
│   └── Providers/
│       └── AppServiceProvider.php     # registers Gates from permissions config
├── bootstrap/app.php             # app bootstrap (middleware alias, 403→Unauthorized)
├── config/
│   ├── database.php              # pgsql + mongodb + redis connections
│   └── permissions.php           # RBAC matrix (roles → permissions)
├── database/
│   ├── migrations/               # 7 module migrations → all 39 tables
│   ├── seeders/DatabaseSeeder.php
│   ├── mongodb/collections.js    # MongoDB sample documents
│   ├── redis/keys.md             # Redis key reference
│   └── reference/                # original raw SQL DDL (reference)
├── public/
│   ├── index.php                 # Laravel front controller
│   └── assets/css/app.css
├── resources/views/             # Blade: layouts/, auth/, dashboard/, patient/,
│                                #   appointment/, medical/, pharmacy/, lab/,
│                                #   billing/, misc/, errors/
├── routes/web.php                # route table (auth + permission middleware)
├── storage/logs/                 # laravel.log
├── docs/                         # erd.drawio, system_architecture.drawio, data_flow_diagram.drawio
└── _legacy_custom_php/           # earlier from-scratch build (superseded)
```

---

## 15. Example Laravel routes

From [`routes/web.php`](routes/web.php). Public routes need no auth; guarded
routes use the `auth` middleware (login) plus `permission:<capability>` (RBAC).

```php
// public
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'reset']);

// authenticated + RBAC
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view');

    Route::get('/patients', [PatientController::class, 'index'])
        ->middleware('permission:patient.view');
    Route::post('/patients', [PatientController::class, 'store'])
        ->middleware('permission:patient.create');
    Route::post('/patients/{id}/discharge', [PatientController::class, 'discharge'])
        ->middleware('permission:patient.discharge');

    Route::post('/medical-records/{id}/adjust', [MedicalRecordController::class, 'adjust'])
        ->middleware('permission:medical_record.adjust');
    Route::post('/dispensing', [PharmacyController::class, 'dispense'])
        ->middleware('permission:dispensing.create');
    Route::post('/bills/{id}/pay', [BillingController::class, 'pay'])
        ->middleware('permission:payment.create');
});
```

RBAC is wired in `AppServiceProvider`: every capability in
`config/permissions.php` becomes a Gate, and `Gate::before` lets `admin` (`*`)
pass everything. Blade templates use `@can('patient.create') ... @endcan` to show
or hide actions per role.

---

## 16. Security rules

1. **Login required** — the `auth` middleware blocks all guarded routes; unauthenticated requests are redirected to the named `login` route.
2. **Hashed passwords** — `Hash::make()` (bcrypt) on store; `Auth::attempt()` verifies against the `password_hash` column (`User::getAuthPassword()`).
3. **Protected sessions** — Laravel session cookies are HTTP-only + SameSite; the session id is regenerated on login (`session()->regenerate()`); sessions are stored in **Redis**.
4. **RBAC** — `EnsurePermission` middleware checks the Gate built from `config/permissions.php`; a denied request `abort(403)`.
5. **Unauthorized redirect** — 403 / `AuthorizationException` is rendered as the dedicated **Unauthorized page** (`bootstrap/app.php` exception handler).
6. **Input validation** — every controller uses `$request->validate([...])`; Blade `@csrf` adds a CSRF token to every state-changing form (verified by Laravel's `VerifyCsrfToken`).
7. **Audit logging** — `AuditLogger` writes login, create/adjust, dispense, payment, etc. to MongoDB `audit_log_documents`.
8. **No hard deletes of clinical data** — medical records are never deleted; corrections go to `medical_record_adjustment` + MongoDB version snapshots.
9. **Adjustment provenance** — adjustments require and store reason, adjusted_by, adjusted_at.
10. **Data protection** — patient/medical access restricted by role; SQL injection prevented by Eloquent / query-builder parameter binding; XSS prevented by Blade `{{ }}` auto-escaping.

---

## 17. System architecture

Diagram: **[`docs/system_architecture.drawio`](docs/system_architecture.drawio)**
(hand-drawn / sketch style — open at <https://app.diagrams.net> or the VS Code
Draw.io extension). The diagram reflects the components the system **actually
uses** — no CDN / DNS appliance / load balancer (those would be optional
production add-ons, not part of this build).

```
 STAFF CLIENTS             GATEWAY                 APPLICATION                  DATA STORES
 ─────────────             ───────                 ───────────                  ───────────
 Admin ┄┄┄┄┄┄┄┄┄┄┄┄▶ security  ────▶ ┌──────────────────────────┐ ──▶ 🛢 PostgreSQL (39 tables, pgsql)
 Hospital Staff ────▶ Laravel        │ Laravel Application       │
 Mobile Browser ────▶ middleware:    │ index.php → Kernel → MW   │ ──▶ ⚡ Redis  (session·cache)
 Desktop Browser ───▶ auth · Gate    │ Router → Controllers ×12  │
                      RBAC · CSRF ───▶│ Eloquent ×39 · Services   │ ──▶ 🛢 MongoDB (audit·versions·lab reports)
                      · session       └──────────────────────────┘
```

**Components (all present in the codebase)**
- **Staff clients** — staff-only access (no public/patient access). Admin and the
  six staff roles reach the system from desktop or mobile **browsers** over HTTPS.
- **Security gateway** — Laravel's HTTP **middleware pipeline**: the `auth` guard
  (logged in?), the `permission:` middleware backed by **Gates** (RBAC), plus
  `VerifyCsrfToken` and the session cookie. Unauthorized requests are rejected
  here (→ `login` route / 403 Unauthorized page) before reaching a controller.
- **Laravel application** (`php artisan serve` locally; Nginx + PHP-FPM in
  production): `public/index.php` → HTTP Kernel → middleware → **Router**
  (`routes/web.php`) → **12 Controllers** → **39 Eloquent models** / **Services**
  (`AuditLogger`, `CentralServiceBus`/`CentralServiceClient`) → **25 Blade views**
  with `@can` role-filtered nav.
  Gates are built from `config/permissions.php` (admin = `*`) and, for the
  non-protected roles, the DB-backed `role_permissions` table (editable from
  the Roles & Permissions screen).
- **Central Service** (separate repo, `central-service`) — the data-sync/
  document-processing box in the architecture diagram, run as its own
  deployable Laravel app sharing this app's Postgres/MongoDB/Redis. This app
  publishes work onto a shared, unprefixed Redis list (`central-service:jobs`,
  connection `bus`) via `App\Services\CentralServiceBus`; central-service
  relays each message (`php artisan bus:relay`) into its own queued jobs:
  `LogAuditEventJob` (audit trail), `SyncMedicalRecordVersionJob` (Mongo
  version snapshots), `GenerateLabReportDocumentJob` (Mongo snapshot + PDF
  render + upload). For synchronous needs (e.g. "Regenerate PDF"), this app
  calls central-service's REST API directly via `App\Services\CentralServiceClient`
  (shared-secret `X-Central-Service-Key` header). Postgres writes stay
  synchronous/immediately consistent; the Mongo mirror and generated
  documents are eventually consistent once central-service picks the job up.
- **Data stores** — the three databases the app connects to, plus document storage:
  - **🛢 PostgreSQL** (`pgsql`, default) — relational source of truth: 39 tables, FKs, transactions, via Eloquent.
  - **⚡ Redis** (`phpredis`) — session store, cache (`dashboard:summary`, `medicine:lowstock`, `patient:viewed:*`, `mr:viewed:*`), and the shared `bus` connection to central-service.
  - **🛢 MongoDB** (`mongodb/laravel-mongodb`) — documents: `audit_log_documents`, `medical_record_versions`, `lab_report_documents`.
  - **📄 Cloudflare R2** (S3-compatible, `config/filesystems.php`'s `documents` disk) — generated lab-report PDFs; falls back to the local disk when R2 credentials aren't configured.

The application is stateless (session in Redis), so it can be horizontally scaled
behind a load balancer in production — but the current system is a single Laravel
instance (plus its queue worker process) talking to the three data services.

**Search at scale.** Every search box filters with `ILIKE '%term%'`, which
can't use a plain B-tree index (leading wildcard). Migration
`2026_01_02_000005_add_trigram_search_indexes` adds Postgres `pg_trgm` + GIN
indexes on the searched columns (patient name/phone/email, staff name,
doctor specialization, department/room/medicine name) — the same `ILIKE`
queries stay index-backed at any row count, no application code changes
needed. Verified with `php artisan seed:large` (bulk business-key generation,
bypassing Eloquent's per-row lookup): at 1,000,002 patients, patient search
runs in ~3ms via a GIN bitmap index scan (`EXPLAIN ANALYZE`), vs. ~104ms for
the equivalent sequential scan.

---

## 18. System flow — Data Flow Diagram

Diagram: **[`docs/data_flow_diagram.drawio`](docs/data_flow_diagram.drawio)**
(Level-1 DFD). Notation: **rectangles** = external entities (staff roles),
**rounded boxes** = processes (numbered 1.0–7.0), **open-ended boxes** = data
stores (D1 PostgreSQL, D2 MongoDB, D3 Redis).

**Processes**
| # | Process | Reads / writes |
|---|---------|----------------|
| 1.0 | Authentication & Access Control | D3 (session) · D2 (audit log) |
| 2.0 | Patient Management | D1 |
| 3.0 | Appointment Management | D1 · D3 (availability cache) |
| 4.0 | Medical Record & Treatment | D1 · D2 (record versions) |
| 5.0 | Pharmacy & Inventory | D1 · D3 (low-stock cache) |
| 6.0 | Laboratory & Diagnostics | D1 · D2 (lab report docs) |
| 7.0 | Billing & Payment | D1 |

**Key data flows**
- Every staff member submits **login credentials** to *1.0*, which validates against D1, writes the **session** to D3 (Redis) and an **audit entry** to D2 (MongoDB); a session token flows back to the entity.
- **Receptionist** → *2.0* (register/search patient) and *3.0* (book appointment). *3.0* checks/updates the **doctor-availability cache** in D3.
- **Doctor** → *4.0* (diagnose, prescribe) and *6.0* (order lab test). *4.0* persists to D1 and writes an immutable **version snapshot** to D2.
- **Nurse** → *4.0* (record vital signs → D1).
- **Pharmacist** → *5.0* (dispense). Stock is decremented in D1 (transaction) and the **low-stock cache** in D3 is busted.
- **Lab Technician** → *6.0* (enter results → D1) and a **lab report document** to D2.
- **Receptionist** (also Admin / Super Admin) → *7.0* (generate bill / record payment → D1).
- **Admin** → *1.0* (manage staff, roles) and reporting.

This mirrors the narrative in [§10 System workflow](#10-system-workflow): login →
authorization → reception → consultation → pharmacy → laboratory → billing, with
every state change also written to the audit store.

---

## 19. How to run

Requires PostgreSQL, MongoDB and Redis running locally (e.g.
`brew services start postgresql@18 redis mongodb-community`).

```bash
# 1. Dependencies
composer install
npm install && npm run build  # or `npm run dev` while developing

# 2. Configure
cp .env.example .env          # set DB_*, MONGODB_*, REDIS_*, R2_* credentials
php artisan key:generate

# 3. Database (PostgreSQL must exist: createdb smart_hospital)
php artisan migrate           # creates all tables
php artisan db:seed           # demo data + 7 role logins
mongosh smart_hospital_docs database/mongodb/collections.js   # (optional) sample docs

# 4. Serve
php artisan serve             # http://127.0.0.1:8000/login

# 5. Central Service (§17) — separate repo, required for audit logging,
#    medical record versioning, and lab report PDF generation to complete.
#    cd ../central-service && php artisan serve --port=8100 && php artisan
#    bus:relay && php artisan queue:work --tries=3 (see its README)
```

**Demo logins** (password `Password123!`): `superadmin@hospital.test`,
`admin@hospital.test`, `doctor@hospital.test`, `nurse@hospital.test`,
`reception@hospital.test`, `pharmacist@hospital.test`, `labtech@hospital.test`.

> A `.env.example` is provided. The earlier custom-framework implementation is
> archived in `_legacy_custom_php/` and is superseded by this Laravel app.
