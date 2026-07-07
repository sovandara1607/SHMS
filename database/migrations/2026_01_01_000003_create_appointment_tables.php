<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 2 + scheduling — appointments, staff shifts and the
 * doctor/nurse-patient assignment history tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment', function (Blueprint $table) {
            $table->string('appointment_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('doctor_id', 20);
            $table->string('booked_by', 100)->nullable();
            $table->date('appointment_date');
            $table->time('appointment_time');
            $table->text('reason')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
            $table->foreign('booked_by')->references('staff_id')->on('staff');
            $table->index('appointment_date');
        });

        Schema::create('staff_shift', function (Blueprint $table) {
            $table->string('shift_id', 20)->primary();
            $table->string('staff_id', 20);
            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('shift_type', ['morning', 'afternoon', 'night']);
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->foreign('staff_id')->references('staff_id')->on('staff');
        });

        Schema::create('patient_doctor_assignment', function (Blueprint $table) {
            $table->string('assignment_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('doctor_id', 20);
            $table->string('assigned_by', 100)->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->enum('role', ['main_doctor', 'consultant', 'specialist'])->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
            $table->foreign('assigned_by')->references('staff_id')->on('staff');
        });

        Schema::create('patient_nurse_assignment', function (Blueprint $table) {
            $table->string('assignment_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('nurse_id', 20);
            $table->string('shift_id', 20)->nullable();
            $table->string('assigned_by', 100)->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('nurse_id')->references('nurse_id')->on('nurse');
            $table->foreign('shift_id')->references('shift_id')->on('staff_shift');
            $table->foreign('assigned_by')->references('staff_id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_nurse_assignment');
        Schema::dropIfExists('patient_doctor_assignment');
        Schema::dropIfExists('staff_shift');
        Schema::dropIfExists('appointment');
    }
};
