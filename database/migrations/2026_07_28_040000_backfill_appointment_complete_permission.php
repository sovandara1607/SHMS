<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Same backfill as 2026_01_02_000006_sync_role_permissions_from_config —
 * role_permissions is only ever seeded/backfilled, never live-synced from
 * config/permissions.php. appointment.complete was just added there (doctor
 * + receptionist, backing the new "Mark as Completed" action) and needs the
 * same one-time catch-up or it never reaches real users.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('permissions.permissions', []) as $role => $capabilities) {
            if (in_array('*', $capabilities, true)) {
                continue;
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
        DB::table('role_permissions')->where('capability', 'appointment.complete')->delete();
    }
};
