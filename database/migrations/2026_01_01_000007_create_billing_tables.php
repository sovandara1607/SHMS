<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MODULE 6 — bills, bill items (subtotal is a stored generated column)
 * and payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill', function (Blueprint $table) {
            $table->string('bill_id', 20)->primary();
            $table->string('patient_id', 20);
            $table->string('appointment_id', 20)->nullable();
            $table->string('generated_by', 100)->nullable();
            $table->date('bill_date')->useCurrent();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->foreign('patient_id')->references('patient_id')->on('patient');
            $table->foreign('appointment_id')->references('appointment_id')->on('appointment');
            $table->foreign('generated_by')->references('staff_id')->on('staff');
            $table->index('patient_id');
            $table->index('status');
        });

        Schema::create('bill_item', function (Blueprint $table) {
            $table->string('bill_item_id', 20)->primary();
            $table->string('bill_id', 20);
            $table->enum('item_type', ['service', 'medicine', 'lab_test', 'procedure', 'room']);
            $table->string('description', 255)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            // subtotal = quantity * unit_price (PostgreSQL stored generated column)
            $table->decimal('subtotal', 10, 2)->storedAs('quantity * unit_price');
            $table->foreign('bill_id')->references('bill_id')->on('bill');
        });

        Schema::create('payment', function (Blueprint $table) {
            $table->string('payment_id', 20)->primary();
            $table->string('bill_id', 20);
            $table->string('received_by', 100)->nullable();
            $table->enum('payment_method', ['cash', 'card', 'online']);
            $table->decimal('amount_paid', 10, 2);
            $table->date('payment_date')->useCurrent();
            $table->string('transaction_reference', 100)->nullable();
            $table->foreign('bill_id')->references('bill_id')->on('bill');
            $table->foreign('received_by')->references('staff_id')->on('staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment');
        Schema::dropIfExists('bill_item');
        Schema::dropIfExists('bill');
    }
};
