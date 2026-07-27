<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patient info is never overwritten directly (same "original preserved"
 * policy as medical_record) — edits are appended here as an audit trail
 * instead. Not part of the ERD; added at the user's explicit request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_adjustment', function (Blueprint $table) {
            $table->string('adjustment_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->text('allergy')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('patient_status', 20)->nullable();
            $table->string('adjusted_by', 100)->nullable();
            $table->timestamp('adjusted_at')->useCurrent();
            $table->text('reason')->nullable();
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('adjusted_by')->references('staff_id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_adjustment');
    }
};
