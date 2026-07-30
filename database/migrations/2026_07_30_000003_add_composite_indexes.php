<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for column pairs that are consistently filtered/sorted
 * together (analysis.md §5.3), not covered by the single-column FK indexes
 * added in the previous migration. A single-column index doesn't help a
 * query that filters on two columns at once; Postgres would still need a
 * bitmap-and of two separate scans or fall back to one plus a filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment', function (Blueprint $table) {
            // Booking-conflict checks (slotTaken/bookedSlots) filter both together.
            $table->index(['doctor_id', 'appointment_date']);
            // Dashboard/list "today's appointments by status" queries.
            $table->index(['appointment_date', 'status']);
        });

        Schema::table('lab_test_order', function (Blueprint $table) {
            // Status-filtered lists ordered/filtered by order_date.
            $table->index(['status', 'order_date']);
            // Per-patient pending/in-progress lookups.
            $table->index(['patient_id', 'status']);
        });

        Schema::table('bill', function (Blueprint $table) {
            // Billing dashboard/report queries filtering unpaid/partially_paid by date range.
            $table->index(['status', 'bill_date']);
        });

        Schema::table('medicine_batch', function (Blueprint $table) {
            // FEFO batch selection scoped to one medicine.
            $table->index(['medicine_id', 'expiry_date']);
        });

        Schema::table('staff_shift', function (Blueprint $table) {
            // Overlap-check (hasOverlap) fetches candidate shifts by staff_id + date window.
            $table->index(['staff_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_shift', function (Blueprint $table) {
            $table->dropIndex(['staff_id', 'shift_date']);
        });

        Schema::table('medicine_batch', function (Blueprint $table) {
            $table->dropIndex(['medicine_id', 'expiry_date']);
        });

        Schema::table('bill', function (Blueprint $table) {
            $table->dropIndex(['status', 'bill_date']);
        });

        Schema::table('lab_test_order', function (Blueprint $table) {
            $table->dropIndex(['status', 'order_date']);
            $table->dropIndex(['patient_id', 'status']);
        });

        Schema::table('appointment', function (Blueprint $table) {
            $table->dropIndex(['doctor_id', 'appointment_date']);
            $table->dropIndex(['appointment_date', 'status']);
        });
    }
};
