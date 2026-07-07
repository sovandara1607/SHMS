<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 1 (patients & rooms) — patient profiles, insurance, rooms, beds
 * and room-assignment history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient', function (Blueprint $table) {
            $table->string('patient_id', 20)->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone_number', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->text('allergy')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('patient_status', 100)->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->index('patient_status');
        });

        Schema::create('patient_insurance', function (Blueprint $table) {
            $table->string('insurance_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('insurance_provider', 100)->nullable();
            $table->string('policy_number', 100)->nullable();
            $table->text('coverage_details')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
        });

        Schema::create('room', function (Blueprint $table) {
            $table->string('room_id', 20)->primary();
            $table->string('department_id', 20)->nullable();
            $table->string('room_number', 100)->nullable();
            $table->enum('room_type', ['general', 'private', 'icu', 'emergency'])->nullable();
            $table->integer('floor_number')->nullable();
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->foreign('department_id')->references('department_id')->on('department');
        });

        Schema::create('bed', function (Blueprint $table) {
            $table->string('bed_id', 20)->primary();
            $table->string('room_id', 20);
            $table->string('bed_number', 100)->nullable();
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->foreign('room_id')->references('room_id')->on('room');
        });

        Schema::create('room_assignment', function (Blueprint $table) {
            $table->string('room_assignment_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('room_id', 20);
            $table->string('bed_id', 20)->nullable();
            $table->string('assigned_by', 100)->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('released_at')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('room_id')->references('room_id')->on('room');
            $table->foreign('bed_id')->references('bed_id')->on('bed');
            $table->foreign('assigned_by')->references('staff_id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_assignment');
        Schema::dropIfExists('bed');
        Schema::dropIfExists('room');
        Schema::dropIfExists('patient_insurance');
        Schema::dropIfExists('patient');
    }
};
