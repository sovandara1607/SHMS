# Smart Hospital Management System (Laravel)

Staff-only hospital management platform built with **Laravel 13** on three data
stores: **PostgreSQL** (core relational, 39 tables), **MongoDB** (document /
versioning / audit, via `mongodb/laravel-mongodb`), and **Redis** (sessions,
cache, queue, OTP). No page is accessible before login; every page is gated by
role-based access control (Laravel **Gates** + a `permission:` middleware).

- Full system documentation: **[DOCUMENTATION.md](DOCUMENTATION.md)** (all deliverables)
- ERD (draw.io, grouped by module): **[docs/erd.drawio](docs/erd.drawio)**
- System architecture (draw.io, sketch style): **[docs/system_architecture.drawio](docs/system_architecture.drawio)**
- Data Flow Diagram (draw.io, Level 1): **[docs/data_flow_diagram.drawio](docs/data_flow_diagram.drawio)**

## Tech stack
| Concern | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Relational DB | PostgreSQL — 39 tables (Eloquent models + migrations) |
| Document DB | MongoDB — `mongodb/laravel-mongodb` |
| Cache / session / queue / OTP | Redis (`phpredis`) |
| Central Service (async) | Laravel queued jobs — see below |
| Document storage | Cloudflare R2 (S3-compatible), falls back to local disk |
| Auth | Laravel session guard (hashed passwords) |
| RBAC | Gates from `config/permissions.php` (+ DB-backed `role_permissions` for editable roles) + `permission:` middleware |

## Quick start
```bash
composer install
npm install && npm run build  # or `npm run dev` while developing
cp .env.example .env          # set PG/Mongo/Redis creds; php artisan key:generate
docker compose up -d          # Postgres + Redis + MongoDB — see below
php artisan migrate           # creates all tables
php artisan db:seed           # demo data + 7 role logins
php artisan serve             # http://127.0.0.1:8000/login
php artisan queue:work        # separate process — see Central Service below
```

## Local data stores (Docker)
`docker-compose.yml` runs Postgres 18, Redis 7 and MongoDB 7 with the same
host/port/credentials already in `.env` (`127.0.0.1:5432` / `:6379` / `:27017`,
db `smart_hospital`, user `dara`, no password), so no `.env` changes are
needed either way:
```bash
docker compose up -d      # start (idempotent, safe to leave running)
docker compose ps         # check health
docker compose down       # stop (data persists in named volumes)
```
This replaces running these three as `brew services` — don't run both at once,
they'll fight over the same ports. If you were previously on brew services:
```bash
brew services stop postgresql@18 redis mongodb-community@7.0
```
Data lives in Docker-managed volumes (`smart_hospital_pgdata`,
`smart_hospital_redisdata`, `smart_hospital_mongodata`), independent of the
brew-managed data directories — migrating existing data across the two setups
requires `pg_dump`/`pg_restore` and `mongodump`/`mongorestore` once, by hand.

## Central Service (async processing)
The architecture diagram's "Central Service" (data sync + document
processing) is implemented as Laravel **queued jobs** running on Redis, in
this same codebase — not a separate deployable service. It needs its own
long-running process alongside the web server:
```bash
php artisan queue:work --tries=3
```
Three jobs currently run through it (`app/Jobs/`):
- `LogAuditEventJob` — writes the tamper-evident audit trail to MongoDB.
- `SyncMedicalRecordVersionJob` — mirrors medical record create/adjust events
  into MongoDB version snapshots (Postgres stays the immediately-consistent
  source of truth; Mongo history is eventually consistent).
- `GenerateLabReportDocumentJob` — snapshots the result to MongoDB, renders a
  PDF (`barryvdh/laravel-dompdf`), and uploads it to the documents disk.

Documents (generated lab report PDFs) are stored via the `documents` disk in
`config/filesystems.php`, which resolves to Cloudflare R2 (`R2_*` env vars) if
credentials are set, or the local disk otherwise — safe to develop against
without real R2 credentials. If a queue job fails, it retries up to 3 times
and then lands in the `failed_jobs` table (`php artisan queue:failed`).

Demo logins (password `Password123!`): `superadmin@hospital.test`, `admin@hospital.test`,
`doctor@hospital.test`, `nurse@hospital.test`, `reception@hospital.test`,
`pharmacist@hospital.test`, `labtech@hospital.test`.

## Scale testing (search indexes + large dataset)
Every search box filters with `ILIKE '%term%'`, which can't use a plain
B-tree index. Migration `2026_01_02_000005_add_trigram_search_indexes` adds
Postgres `pg_trgm` + GIN indexes on the searched columns (patient name/phone/
email, staff name, doctor specialization, department/room/medicine name,
etc.) so those same queries stay index-backed at any scale. It's a no-op on
sqlite (the test suite's driver).

To validate at real scale, `php artisan seed:large` bulk-generates a large
dataset directly via the query builder (bypassing Eloquent's per-row business
-key lookup, which doesn't scale):
```bash
php artisan seed:large --patients=1000000 --appointments=200000 --medical-records=200000 \
  --lab-orders=200000 --bills=200000
```
Verified: ~1,000,000 patients + ~200,000 appointments/medical records +
~200,000 lab orders (with matching results) + ~200,000 bills (with items and
payments) generate in a few minutes total, and `patient` search stays sub-5ms
via `EXPLAIN ANALYZE` (vs. ~100ms for the equivalent sequential scan).

All business-key sequences are derived with a Postgres regex anchor
(`id ~ '^PREFIX[0-9]+$'`) rather than "longest/lexicographically-last id",
since some tables (e.g. `lab_test_result`, via the app's own
`Str::random(8)`-suffixed IDs) mix sequential and random-suffix keys.

## Modules
Core (patient/staff/department/room/schedule) · Appointments · Medical Records &
Treatment · Pharmacy & Inventory · Laboratory & Diagnostics · Billing & Payment.

## Roles
Super Admin · Admin · Doctor · Nurse · Receptionist · Pharmacist · Lab Technician.
Billing is a capability of Receptionist, Admin and Super Admin (no standalone
Billing Staff role).

> An earlier from-scratch PHP implementation lives in `_legacy_custom_php/` and is
> superseded by this Laravel application.
