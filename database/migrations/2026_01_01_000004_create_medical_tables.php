<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 3 — medical records (immutable), adjustments, vital signs,
 * treatment plans, prescriptions, procedures and reports.
 * The prescription_item → medicine FK is added later (pharmacy migration)
 * because medicine is created after this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_record', function (Blueprint $table) {
            $table->string('medical_record_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('doctor_id', 20);
            $table->string('appointment_id', 20)->nullable();
            $table->text('symptoms')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment_notes')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
            $table->foreign('appointment_id')->references('appointment_id')->on('appointment');
            $table->foreign('created_by')->references('staff_id')->on('staff');
            $table->index('patient_id');
            $table->index('doctor_id');
        });

        Schema::create('medical_record_adjustment', function (Blueprint $table) {
            $table->string('adjustment_id', 20)->primary();
            $table->string('medical_record_id', 20);
            $table->text('symptoms')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment_notes')->nullable();
            $table->string('adjusted_by', 100)->nullable();
            $table->timestamp('adjusted_at')->useCurrent();
            $table->text('reason')->nullable();
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->foreign('adjusted_by')->references('staff_id')->on('staff');
        });

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

        Schema::create('prescription', function (Blueprint $table) {
            $table->string('prescription_id', 20)->primary();
            $table->string('medical_record_id', 20);
            $table->string('patient_id', 20);
            $table->string('doctor_id', 20);
            $table->date('prescription_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
            $table->index('patient_id');
        });

        Schema::create('prescription_item', function (Blueprint $table) {
            $table->string('prescription_item_id', 20)->primary();
            $table->string('prescription_id', 20);
            $table->string('medicine_id', 20); // FK added in pharmacy migration
            $table->string('dosage', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('duration', 100)->nullable();
            $table->text('usage_instruction')->nullable();
            $table->integer('quantity')->nullable();
            $table->foreign('prescription_id')->references('prescription_id')->on('prescription');
        });

        Schema::create('medical_procedure', function (Blueprint $table) {
            $table->string('procedure_id', 20)->primary();
            $table->string('medical_record_id', 20);
            $table->string('patient_id', 20);
            $table->string('doctor_id', 20);
            $table->string('procedure_name', 100);
            $table->text('procedure_details')->nullable();
            $table->text('outcome')->nullable();
            $table->date('procedure_date')->nullable();
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
        });

        Schema::create('medical_report', function (Blueprint $table) {
            $table->string('report_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('medical_record_id', 20)->nullable();
            $table->string('report_type', 100)->nullable();
            $table->text('report_content')->nullable();
            $table->string('generated_by', 100)->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->foreign('generated_by')->references('staff_id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_report');
        Schema::dropIfExists('medical_procedure');
        Schema::dropIfExists('prescription_item');
        Schema::dropIfExists('prescription');
        Schema::dropIfExists('treatment_plan');
        Schema::dropIfExists('vital_signs');
        Schema::dropIfExists('medical_record_adjustment');
        Schema::dropIfExists('medical_record');
    }
};
