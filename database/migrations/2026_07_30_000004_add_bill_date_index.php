<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BillingApiController::index()'s unfiltered ("all statuses") list orders
 * by bill_date DESC. The composite (status, bill_date) index added in
 * 2026_07_30_000003 only helps once status is filtered — with no plain
 * index on bill_date alone, that unfiltered view still needs a full sort
 * over the whole table (confirmed via EXPLAIN ANALYZE against the real
 * seeded dataset: an external disk sort over all 200k rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            $table->index('bill_date');
        });
    }

    public function down(): void
    {
        Schema::table('bill', function (Blueprint $table) {
            $table->dropIndex(['bill_date']);
        });
    }
};
