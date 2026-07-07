<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 5 — laboratory test orders, results, equipment and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_test_order', function (Blueprint $table) {
            $table->string('test_order_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('doctor_id', 20);
            $table->string('technician_id', 20)->nullable();
            $table->string('medical_record_id', 20)->nullable();
            $table->string('test_name', 100);
            $table->timestamp('order_date')->useCurrent();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('doctor_id')->references('doctor_id')->on('doctor');
            $table->foreign('technician_id')->references('technician_id')->on('lab_technician');
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
            $table->index('patient_id');
            $table->index('status');
        });

        Schema::create('lab_test_result', function (Blueprint $table) {
            $table->string('test_result_id', 20)->primary();
            $table->string('test_order_id', 20);
            $table->text('result_value')->nullable();
            $table->string('result_status', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->string('entered_by', 100)->nullable();
            $table->timestamp('entered_at')->useCurrent();
            $table->foreign('test_order_id')->references('test_order_id')->on('lab_test_order');
            $table->foreign('entered_by')->references('technician_id')->on('lab_technician');
        });

        Schema::create('laboratory_equipment', function (Blueprint $table) {
            $table->string('equipment_id', 20)->primary();
            $table->string('laboratory_id', 20)->nullable();
            $table->string('equipment_name', 100);
            $table->string('equipment_type', 100)->nullable();
            $table->enum('availability_status', ['available', 'in_use', 'maintenance'])->default('available');
            $table->date('last_maintenance_date')->nullable();
            $table->foreign('laboratory_id')->references('laboratory_id')->on('laboratory');
        });

        Schema::create('lab_report', function (Blueprint $table) {
            $table->string('lab_report_id', 20)->primary();
            $table->string('test_order_id', 20);
            $table->string('patient_id', 20);
            $table->text('report_content')->nullable();
            $table->string('generated_by', 100)->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->foreign('test_order_id')->references('test_order_id')->on('lab_test_order');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('generated_by')->references('staff_id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_report');
        Schema::dropIfExists('laboratory_equipment');
        Schema::dropIfExists('lab_test_result');
        Schema::dropIfExists('lab_test_order');
    }
};
