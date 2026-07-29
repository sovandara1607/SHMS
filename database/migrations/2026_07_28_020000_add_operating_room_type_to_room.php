<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'operating_room' ("OR") as a valid room_type, per the Figma design's
 * Rooms & Bed Management module (Standard/Premium/ICU/Trauma/OR bed types).
 * Postgres-only: sqlite (tests) always runs migrations from scratch, and
 * doesn't enforce enum values as a CHECK constraint the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE room DROP CONSTRAINT room_room_type_check');
        DB::statement("ALTER TABLE room ADD CONSTRAINT room_room_type_check CHECK (room_type IN ('general', 'private', 'icu', 'emergency', 'operating_room'))");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE room DROP CONSTRAINT room_room_type_check');
        DB::statement("ALTER TABLE room ADD CONSTRAINT room_room_type_check CHECK (room_type IN ('general', 'private', 'icu', 'emergency'))");
    }
};
