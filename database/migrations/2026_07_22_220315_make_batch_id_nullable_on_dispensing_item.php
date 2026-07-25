<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PharmacyController::dispense() uses FEFO batch selection that can
 * legitimately find no matching batch (medicine tracked at aggregate
 * stock_quantity only, no batch rows, or no single batch holds enough
 * quantity even though total stock does) and already handles that via
 * $batch?->batch_id — but the column was never made nullable to match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensing_item', function (Blueprint $table) {
            $table->string('batch_id', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dispensing_item', function (Blueprint $table) {
            $table->string('batch_id', 20)->nullable(false)->change();
        });
    }
};
