# Smart Hospital Management System (Laravel)

Staff-only hospital management platform built with **Laravel 13** on three data
stores: **PostgreSQL** (core relational, 39 tables), **MongoDB** (document /
versioning / audit, via `mongodb/laravel-mongodb`), and **Redis** (sessions,
cache, OTP). No page is accessible before login; every page is gated by
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
| Cache / session / OTP | Redis (`phpredis`) |
| Auth | Laravel session guard (hashed passwords) |
| RBAC | Gates from `config/permissions.php` + `permission:` middleware |

## Quick start
```bash
composer install
cp .env.example .env          # set PG/Mongo/Redis creds; php artisan key:generate
php artisan migrate           # creates all 39 tables
php artisan db:seed           # demo data + 7 role logins
php artisan serve             # http://127.0.0.1:8000/login
```
Requires running PostgreSQL, MongoDB and Redis servers (e.g. `brew services start
postgresql@18 redis mongodb-community`).

Demo logins (password `Password123!`): `superadmin@hospital.test`, `admin@hospital.test`,
`doctor@hospital.test`, `nurse@hospital.test`, `reception@hospital.test`,
`pharmacist@hospital.test`, `labtech@hospital.test`.

## Modules
Core (patient/staff/department/room/schedule) · Appointments · Medical Records &
Treatment · Pharmacy & Inventory · Laboratory & Diagnostics · Billing & Payment.

## Roles
Super Admin · Admin · Doctor · Nurse · Receptionist · Pharmacist · Lab Technician.
Billing is a capability of Receptionist, Admin and Super Admin (no standalone
Billing Staff role).

> An earlier from-scratch PHP implementation lives in `_legacy_custom_php/` and is
> superseded by this Laravel application.
