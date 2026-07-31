<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bills should be raised against the visit that generated the charges (a
 * medical record) rather than the appointment that booked the visit —
 * an appointment can be rescheduled/cancelled independently of the record
 * documenting what was actually done. appointment_id stays in place
 * (existing bills already reference it); new bills use this column instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            $table->string('medical_record_id', 20)->nullable()->after('appointment_id');
            $table->foreign('medical_record_id')->references('medical_record_id')->on('medical_record');
        });
    }

    public function down(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            $table->dropForeign(['medical_record_id']);
            $table->dropColumn('medical_record_id');
        });
    }
};
