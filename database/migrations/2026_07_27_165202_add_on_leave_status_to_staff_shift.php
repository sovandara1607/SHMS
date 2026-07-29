<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'on_leave' as a valid staff_shift status, per the Figma design's
 * Staff Schedule module (Scheduled/Completed/On Leave/Cancelled stat chips).
 * Postgres-only: sqlite (tests) always runs migrations from scratch, and the
 * original create-table migration already declares 'on_leave' in its enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE staff_shift DROP CONSTRAINT staff_shift_status_check');
        DB::statement("ALTER TABLE staff_shift ADD CONSTRAINT staff_shift_status_check CHECK (status IN ('scheduled', 'completed', 'cancelled', 'on_leave'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE staff_shift DROP CONSTRAINT staff_shift_status_check');
        DB::statement("ALTER TABLE staff_shift ADD CONSTRAINT staff_shift_status_check CHECK (status IN ('scheduled', 'completed', 'cancelled'))");
    }
};
