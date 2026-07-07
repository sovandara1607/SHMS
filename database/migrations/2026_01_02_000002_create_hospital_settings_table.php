<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row hospital-wide configuration, edited from the admin-only
 * Settings screen (Hospital Info / Operating Hours / Department Capacity).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_settings', function (Blueprint $table) {
            $table->id();
            $table->string('hospital_name', 150)->nullable();
            $table->string('hospital_code', 50)->nullable();
            $table->string('license_number', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('website', 150)->nullable();
            $table->string('established_year', 10)->nullable();
            $table->string('hours_weekday', 50)->nullable();
            $table->string('hours_saturday', 50)->nullable();
            $table->string('hours_sunday', 50)->nullable();
            $table->string('hours_emergency', 50)->nullable();
            $table->integer('total_beds')->nullable();
            $table->integer('icu_beds')->nullable();
            $table->integer('emergency_bays')->nullable();
            $table->integer('operating_rooms')->nullable();
            $table->timestamps();
        });

        DB::table('hospital_settings')->insert([
            'hospital_name' => 'Smart Hospital',
            'hours_weekday' => '06:00 AM - 10:00 PM',
            'hours_saturday' => '08:00 AM - 08:00 PM',
            'hours_sunday' => '09:00 AM - 04:00 PM',
            'hours_emergency' => '24/7',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_settings');
    }
};
