# Smart Hospital Management System (Laravel)

Staff-only hospital management platform built with **Laravel 13** on three data
stores: **PostgreSQL** (core relational, 39 tables), **MongoDB** (document /
versioning / audit, via `mongodb/laravel-mongodb`), and **Redis** (sessions,
cache). No page is accessible before login; every page is gated by
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
| Cache / session | Redis (`phpredis`) |
| Central Service | separate repo/app (`central-service`) — see below |
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
```
Audit logging, medical record version sync, and lab report PDF generation
run in the separate `central-service` app, not here — see Central Service
below for how to run it alongside this one.

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

## Central Service (separate app)
The architecture diagram's "Central Service" (data sync engine + file/
document processor) is a **separate Laravel app and repo**, `central-service`
(sibling directory to this one), not code in this codebase. It shares this
app's Postgres, MongoDB, and Redis instances, and is wired in two ways:

- **Async** — this app publishes plain JSON messages onto a shared,
  unprefixed Redis list (`central-service:jobs`, connection `bus` in
  `config/database.php`) via `App\Services\CentralServiceBus`. central-service
  consumes them (`php artisan bus:relay`) and processes them on its own
  queue with retries/backoff.
- **Sync** — for cases where the caller is waiting on a result (e.g. staff
  clicking "Regenerate PDF" on the Lab Reports tab), this app calls
  central-service's REST API directly via `App\Services\CentralServiceClient`,
  authenticated with a shared secret (`CENTRAL_SERVICE_API_KEY`, sent as the
  `X-Central-Service-Key` header).

To run the full stack locally, alongside this app:
```bash
cd ../central-service
php artisan serve --port=8100    # REST API
php artisan bus:relay            # drains the shared Redis bus
php artisan queue:work --tries=3 # processes central-service's own queue
```
See `central-service/README.md` for the full integration contract (message
shape, dispatch mapping, endpoints).

Documents (generated lab report PDFs) are stored via the `documents` disk in
`config/filesystems.php` — resolved identically in both apps, to Cloudflare R2
(`R2_*` env vars) if credentials are set, or the local disk otherwise.

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
