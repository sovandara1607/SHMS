<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores vital_signs — an earlier migration in this session dropped it
 * (along with treatment_plan) as part of a feature cut; the user then asked
 * for Vital Signs specifically back. treatment_plan stays removed.
 */
return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};
