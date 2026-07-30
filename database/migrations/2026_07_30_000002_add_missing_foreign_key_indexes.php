<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This schema declares every FK via ->foreign()->references()->on() rather
 * than foreignId()->constrained(), so Postgres never auto-indexes them —
 * only columns with an explicit ->index()/->unique() got one. At 1M+ rows
 * per table (see analysis.md §5.2), every join/filter on an unindexed FK
 * degrades from an index lookup to a sequential scan. This adds a plain
 * B-tree index to every FK column that didn't already have one via a
 * unique constraint or an explicit ->index() call in the original
 * migrations. Pure index additions — no schema/behavior change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department', function (Blueprint $table) {
            $table->index('head_staff_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('staff_id');
        });

        Schema::table('doctor', function (Blueprint $table) {
            $table->index('department_id');
        });

        Schema::table('nurse', function (Blueprint $table) {
            $table->index('department_id');
        });

        Schema::table('lab_technician', function (Blueprint $table) {
            $table->index('laboratory_id');
        });

        Schema::table('patient_insurance', function (Blueprint $table) {
            $table->index('patient_id');
        });

        Schema::table('room', function (Blueprint $table) {
            $table->index('department_id');
        });

        Schema::table('bed', function (Blueprint $table) {
            $table->index('room_id');
        });

        Schema::table('room_assignment', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('room_id');
            $table->index('bed_id');
            $table->index('assigned_by');
        });

        Schema::table('appointment', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('booked_by');
        });

        Schema::table('staff_shift', function (Blueprint $table) {
            $table->index('staff_id');
        });

        Schema::table('patient_doctor_assignment', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('assigned_by');
        });

        Schema::table('patient_nurse_assignment', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('nurse_id');
            $table->index('shift_id');
            $table->index('assigned_by');
        });

        Schema::table('medical_record', function (Blueprint $table) {
            $table->index('appointment_id');
            $table->index('created_by');
        });

        Schema::table('medical_record_adjustment', function (Blueprint $table) {
            $table->index('medical_record_id');
            $table->index('adjusted_by');
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('medical_record_id');
            $table->index('recorded_by');
        });

        Schema::table('prescription', function (Blueprint $table) {
            $table->index('medical_record_id');
            $table->index('doctor_id');
        });

        Schema::table('prescription_item', function (Blueprint $table) {
            $table->index('prescription_id');
            $table->index('medicine_id');
        });

        Schema::table('medical_procedure', function (Blueprint $table) {
            $table->index('medical_record_id');
            $table->index('patient_id');
            $table->index('doctor_id');
        });

        Schema::table('medical_report', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('medical_record_id');
            $table->index('generated_by');
        });

        Schema::table('medicine_batch', function (Blueprint $table) {
            $table->index('medicine_id');
        });

        Schema::table('dispensing_record', function (Blueprint $table) {
            $table->index('prescription_id');
            $table->index('pharmacist_id');
            $table->index('patient_id');
        });

        Schema::table('dispensing_item', function (Blueprint $table) {
            $table->index('dispensing_id');
            $table->index('medicine_id');
            $table->index('batch_id');
        });

        Schema::table('lab_test_order', function (Blueprint $table) {
            $table->index('doctor_id');
            $table->index('technician_id');
            $table->index('medical_record_id');
        });

        Schema::table('lab_test_result', function (Blueprint $table) {
            $table->index('test_order_id');
            $table->index('entered_by');
        });

        Schema::table('laboratory_equipment', function (Blueprint $table) {
            $table->index('laboratory_id');
        });

        Schema::table('lab_report', function (Blueprint $table) {
            $table->index('test_order_id');
            $table->index('patient_id');
            $table->index('generated_by');
        });

        Schema::table('bill', function (Blueprint $table) {
            $table->index('appointment_id');
            $table->index('generated_by');
        });

        Schema::table('bill_item', function (Blueprint $table) {
            $table->index('bill_id');
        });

        Schema::table('payment', function (Blueprint $table) {
            $table->index('bill_id');
            $table->index('received_by');
        });

        Schema::table('patient_adjustment', function (Blueprint $table) {
            $table->index('patient_id');
            $table->index('adjusted_by');
        });
    }

    public function down(): void
    {
        Schema::table('patient_adjustment', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['adjusted_by']);
        });

        Schema::table('payment', function (Blueprint $table) {
            $table->dropIndex(['bill_id']);
            $table->dropIndex(['received_by']);
        });

        Schema::table('bill_item', function (Blueprint $table) {
            $table->dropIndex(['bill_id']);
        });

        Schema::table('bill', function (Blueprint $table) {
            $table->dropIndex(['appointment_id']);
            $table->dropIndex(['generated_by']);
        });

        Schema::table('lab_report', function (Blueprint $table) {
            $table->dropIndex(['test_order_id']);
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['generated_by']);
        });

        Schema::table('laboratory_equipment', function (Blueprint $table) {
            $table->dropIndex(['laboratory_id']);
        });

        Schema::table('lab_test_result', function (Blueprint $table) {
            $table->dropIndex(['test_order_id']);
            $table->dropIndex(['entered_by']);
        });

        Schema::table('lab_test_order', function (Blueprint $table) {
            $table->dropIndex(['doctor_id']);
            $table->dropIndex(['technician_id']);
            $table->dropIndex(['medical_record_id']);
        });

        Schema::table('dispensing_item', function (Blueprint $table) {
            $table->dropIndex(['dispensing_id']);
            $table->dropIndex(['medicine_id']);
            $table->dropIndex(['batch_id']);
        });

        Schema::table('dispensing_record', function (Blueprint $table) {
            $table->dropIndex(['prescription_id']);
            $table->dropIndex(['pharmacist_id']);
            $table->dropIndex(['patient_id']);
        });

        Schema::table('medicine_batch', function (Blueprint $table) {
            $table->dropIndex(['medicine_id']);
        });

        Schema::table('medical_report', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['medical_record_id']);
            $table->dropIndex(['generated_by']);
        });

        Schema::table('medical_procedure', function (Blueprint $table) {
            $table->dropIndex(['medical_record_id']);
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['doctor_id']);
        });

        Schema::table('prescription_item', function (Blueprint $table) {
            $table->dropIndex(['prescription_id']);
            $table->dropIndex(['medicine_id']);
        });

        Schema::table('prescription', function (Blueprint $table) {
            $table->dropIndex(['medical_record_id']);
            $table->dropIndex(['doctor_id']);
        });

        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['medical_record_id']);
            $table->dropIndex(['recorded_by']);
        });

        Schema::table('medical_record_adjustment', function (Blueprint $table) {
            $table->dropIndex(['medical_record_id']);
            $table->dropIndex(['adjusted_by']);
        });

        Schema::table('medical_record', function (Blueprint $table) {
            $table->dropIndex(['appointment_id']);
            $table->dropIndex(['created_by']);
        });

        Schema::table('patient_nurse_assignment', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['nurse_id']);
            $table->dropIndex(['shift_id']);
            $table->dropIndex(['assigned_by']);
        });

        Schema::table('patient_doctor_assignment', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['doctor_id']);
            $table->dropIndex(['assigned_by']);
        });

        Schema::table('staff_shift', function (Blueprint $table) {
            $table->dropIndex(['staff_id']);
        });

        Schema::table('appointment', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['doctor_id']);
            $table->dropIndex(['booked_by']);
        });

        Schema::table('room_assignment', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
            $table->dropIndex(['room_id']);
            $table->dropIndex(['bed_id']);
            $table->dropIndex(['assigned_by']);
        });

        Schema::table('bed', function (Blueprint $table) {
            $table->dropIndex(['room_id']);
        });

        Schema::table('room', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
        });

        Schema::table('patient_insurance', function (Blueprint $table) {
            $table->dropIndex(['patient_id']);
        });

        Schema::table('lab_technician', function (Blueprint $table) {
            $table->dropIndex(['laboratory_id']);
        });

        Schema::table('nurse', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
        });

        Schema::table('doctor', function (Blueprint $table) {
            $table->dropIndex(['department_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['staff_id']);
        });

        Schema::table('department', function (Blueprint $table) {
            $table->dropIndex(['head_staff_id']);
        });
    }
};
