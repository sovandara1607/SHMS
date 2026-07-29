<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Room & Bed Management (Figma) shows a per-room daily rate; the schema never had one. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room', function (Blueprint $table) {
            $table->decimal('rate_per_day', 10, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('room', function (Blueprint $table) {
            $table->dropColumn('rate_per_day');
        });
    }
};
