<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * role_permissions is only seeded once, when 2026_01_02_000001 first runs —
 * it's a point-in-time snapshot of config/permissions.php, not a live view
 * of it. Every capability added to config afterwards (e.g. doctor gaining
 * prescription.create/treatment.create/procedure.create/lab_order.create/
 * medical_report.create/vital_signs.view/appointment.view/lab_result.view
 * as those workflows were built out) never reaches real users until someone
 * manually re-grants it via the Roles & Permissions screen.
 *
 * This backfills any (role, capability) pair from the current config matrix
 * that's missing from the table. It only adds — it never removes a row, so
 * it can't clobber a grant an admin deliberately added or take away one
 * they deliberately kept, even if config no longer lists it.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('permissions.permissions', []) as $role => $capabilities) {
            if (in_array('*', $capabilities, true)) {
                continue; // super_admin / admin: protected wildcard, not stored.
            }
            foreach ($capabilities as $capability) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role,
                    'capability' => $capability,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Backfill-only; nothing meaningful to revert without risking
        // removing a grant an admin added independently afterwards.
    }
};
