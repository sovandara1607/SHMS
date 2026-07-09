<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every search box in the app filters with `ILIKE '%term%'` (leading
 * wildcard), which a normal B-tree index can't use — Postgres falls back to
 * a sequential scan. At hundreds/thousands of rows that's invisible; at
 * ~1M+ rows (see LargeDatasetSeeder) it gets slow. pg_trgm + GIN indexes let
 * the exact same ILIKE queries stay index-backed at any scale, so no
 * application code changes are needed.
 */
return new class extends Migration
{
    private array $indexes = [
        // [table, index name, expression]
        ['patient', 'patient_full_name_trgm_idx', "(first_name || ' ' || last_name)"],
        ['patient', 'patient_phone_trgm_idx', 'phone_number'],
        ['patient', 'patient_email_trgm_idx', 'email'],
        ['patient', 'patient_id_trgm_idx', 'patient_id'],

        ['staff', 'staff_full_name_trgm_idx', "(first_name || ' ' || last_name)"],
        ['staff', 'staff_id_trgm_idx', 'staff_id'],

        ['doctor', 'doctor_id_trgm_idx', 'doctor_id'],
        ['doctor', 'doctor_specialization_trgm_idx', 'specialization'],

        ['department', 'department_id_trgm_idx', 'department_id'],
        ['department', 'department_name_trgm_idx', 'department_name'],

        ['room', 'room_id_trgm_idx', 'room_id'],
        ['room', 'room_number_trgm_idx', 'room_number'],

        ['appointment', 'appointment_id_trgm_idx', 'appointment_id'],

        ['medical_record', 'medical_record_id_trgm_idx', 'medical_record_id'],
        ['medical_record', 'medical_record_patient_id_trgm_idx', 'patient_id'],

        ['medicine', 'medicine_id_trgm_idx', 'medicine_id'],
        ['medicine', 'medicine_name_trgm_idx', 'medicine_name'],
        ['medicine', 'medicine_manufacturer_trgm_idx', 'manufacturer'],
    ];

    public function up(): void
    {
        // pg_trgm/GIN are Postgres-only. The test suite runs on sqlite (see
        // phpunit.xml) where this is a harmless no-op — the ILIKE queries
        // still work there, just without the index (fine at test-data scale).
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach ($this->indexes as [$table, $name, $expression]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} USING GIN ({$expression} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexes as [$table, $name, $expression]) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
