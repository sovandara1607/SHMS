# Redis Key Reference — Smart Hospital Management System

Redis stores **temporary / cache** data only. The relational source of
truth is always PostgreSQL. All keys use `:`-separated namespaces and a
TTL so stale data self-expires.

| Purpose | Key pattern | Type | TTL | Set by |
|---|---|---|---|---|
| Login session mirror | `session:{php_session_id}` | string (JSON user) | `SESSION_TTL` (3600s) | `Auth::login()` |
| Dashboard summary | `dashboard:summary` | string (JSON stats) | 60s | `DashboardController` |
| Doctor availability | `availability:{doctor_id}:{date}` | string (JSON slots) | 120s | Appointment module |
| Staff schedule cache | `schedule:{staff_id}:{date}` | string (JSON) | 300s | Schedule module |
| Room/bed availability | `rooms:availability` | string (JSON) | 120s | Room module |
| Medicine low-stock | `medicine:lowstock` | string (JSON list) | 120s | `PharmacyController` |
| Medicine expiry alert | `medicine:expiry` | string (JSON list) | 300s | Pharmacy module |
| Frequently-viewed patient | `patient:viewed:{patient_id}` | string (JSON) | 300s | `PatientController::show` |
| Recently-viewed record | `mr:viewed:{user_id}` | string (record id) | 600s | `MedicalRecordController::show` |
| Latest appointment cache | `appointment:latest:{patient_id}` | string (JSON) | 120s | Appointment module |

## CLI examples

```bash
# inspect a session
redis-cli GET "session:abc123"

# see the dashboard cache and its remaining TTL
redis-cli GET dashboard:summary
redis-cli TTL dashboard:summary

# manually bust caches after a bulk import
redis-cli DEL dashboard:summary medicine:lowstock
```

## Invalidation rules
- Booking/cancelling an appointment deletes `availability:{doctor}:{date}`.
- Adding/dispensing medicine deletes `medicine:lowstock`.
- Completing a lab order deletes `dashboard:summary`.
- Logout deletes `session:{id}` (central session invalidation).
