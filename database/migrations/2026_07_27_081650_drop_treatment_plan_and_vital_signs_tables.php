<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes treatment_plan/vital_signs — feature cut at the user's request;
 * the ERD documents these tables but they're no longer part of the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('treatment_plan');
        Schema::dropIfExists('vital_signs');
    }

    public function down(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->string('vital_sign_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('medical_record_id', 20)->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('blood_pressure', 20)->nullable();
            $table->integer('heart_rate')->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('recorded_by', 100)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->foreign('recorded_by')->references('staff_id')->on('staff');
        });

        Schema::create('treatment_plan', function (Blueprint $table) {
            $table->string('treatment_plan_id', 20)->primary();
            $table->string('medical_record_id', 20);
            $table->string('doctor_id', 20);
            $table->text('diagnosis_summary')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('recommended_care')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
        });
    }
};
