<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 4 — medicines, batches, and dispensing. Also wires the deferred
 * prescription_item → medicine FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine', function (Blueprint $table) {
            $table->string('medicine_id', 20)->primary();
            $table->string('medicine_name', 100);
            $table->string('medicine_type', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->enum('status', ['available', 'unavailable'])->default('available');
        });

        // deferred FK: prescription_item.medicine_id → medicine
        Schema::table('prescription_item', function (Blueprint $table) {
            $table->foreign('medicine_id')->references('medicine_id')->on('medicine');
        });

        Schema::create('medicine_batch', function (Blueprint $table) {
            $table->string('batch_id', 20)->primary();
            $table->string('medicine_id', 20);
            $table->string('batch_number', 100)->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('quantity');
            $table->enum('status', ['valid', 'expired', 'damaged'])->default('valid');
            $table->foreign('medicine_id')->references('medicine_id')->on('medicine');
            $table->index('expiry_date');
        });

        Schema::create('dispensing_record', function (Blueprint $table) {
            $table->string('dispensing_id', 20)->primary();
            $table->string('prescription_id', 20);
            $table->string('pharmacist_id', 20)->nullable();
            $table->string('patient_id', 20);
            $table->date('dispensing_date')->useCurrent();
            $table->enum('status', ['dispensed', 'cancelled'])->default('dispensed');
            $table->foreign('prescription_id')->references('prescription_id')->on('prescription');
            $table->foreign('pharmacist_id')->references('pharmacist_id')->on('pharmacist');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
        });

        Schema::create('dispensing_item', function (Blueprint $table) {
            $table->string('dispensing_item_id', 20)->primary();
            $table->string('dispensing_id', 20);
            $table->string('medicine_id', 20);
            $table->string('batch_id', 20);
            $table->integer('quantity_dispensed');
            $table->foreign('dispensing_id')->references('dispensing_id')->on('dispensing_record');
            $table->foreign('medicine_id')->references('medicine_id')->on('medicine');
            $table->foreign('batch_id')->references('batch_id')->on('medicine_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensing_item');
        Schema::dropIfExists('dispensing_record');
        Schema::dropIfExists('medicine_batch');
        Schema::table('prescription_item', function (Blueprint $table) {
            $table->dropForeign(['medicine_id']);
        });
        Schema::dropIfExists('medicine');
    }
};
