<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 1 (core) — staff accounts, departments and the role-specific
 * staff sub-type tables. All primary keys are VARCHAR(20) business keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->string('staff_id', 20)->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('phone_number', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->date('hire_date')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('department', function (Blueprint $table) {
            $table->string('department_id', 20)->primary();
            $table->string('department_name', 100);
            $table->text('description')->nullable();
            $table->string('head_staff_id', 20)->nullable();
            $table->integer('capacity')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreign('head_staff_id')->references('staff_id')->on('staff');
        });

        // Login accounts. role carries all 7 staff roles.
        Schema::create('users', function (Blueprint $table) {
            $table->string('user_id', 20)->primary();
            $table->string('staff_id', 20)->nullable();
            $table->string('email', 100)->unique();
            $table->string('password_hash', 255);
            $table->enum('role', ['super_admin', 'admin', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'lab_technician']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->foreign('staff_id')->references('staff_id')->on('staff');
        });

        Schema::create('laboratory', function (Blueprint $table) {
            $table->string('laboratory_id', 20)->primary();
            $table->string('laboratory_name', 100)->nullable();
            $table->string('location', 100)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
        });

        Schema::create('doctor', function (Blueprint $table) {
            $table->string('doctor_id', 20)->primary();
            $table->string('staff_id', 20)->unique();
            $table->string('department_id', 20)->nullable();
            $table->string('specialization', 100)->nullable();
            $table->string('license_number', 100)->nullable()->unique();
            $table->foreign('staff_id')->references('staff_id')->on('staff');
            $table->foreign('department_id')->references('department_id')->on('department');
        });

        Schema::create('nurse', function (Blueprint $table) {
            $table->string('nurse_id', 20)->primary();
            $table->string('staff_id', 20)->unique();
            $table->string('department_id', 20)->nullable();
            $table->string('ward_name', 100)->nullable();
            $table->foreign('staff_id')->references('staff_id')->on('staff');
            $table->foreign('department_id')->references('department_id')->on('department');
        });

        Schema::create('receptionist', function (Blueprint $table) {
            $table->string('receptionist_id', 20)->primary();
            $table->string('staff_id', 20)->unique();
            $table->string('counter_number', 100)->nullable();
            $table->foreign('staff_id')->references('staff_id')->on('staff');
        });

        Schema::create('pharmacist', function (Blueprint $table) {
            $table->string('pharmacist_id', 20)->primary();
            $table->string('staff_id', 20)->unique();
            $table->string('license_number', 100)->nullable()->unique();
            $table->string('pharmacy_unit', 100)->nullable();
            $table->foreign('staff_id')->references('staff_id')->on('staff');
        });

        Schema::create('lab_technician', function (Blueprint $table) {
            $table->string('technician_id', 20)->primary();
            $table->string('staff_id', 20)->unique();
            $table->string('laboratory_id', 20)->nullable();
            $table->string('skill_area', 100)->nullable();
            $table->foreign('staff_id')->references('staff_id')->on('staff');
            $table->foreign('laboratory_id')->references('laboratory_id')->on('laboratory');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_technician');
        Schema::dropIfExists('pharmacist');
        Schema::dropIfExists('receptionist');
        Schema::dropIfExists('nurse');
        Schema::dropIfExists('doctor');
        Schema::dropIfExists('laboratory');
        Schema::dropIfExists('users');
        Schema::dropIfExists('department');
        Schema::dropIfExists('staff');
    }
};
