<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes drug_interaction/drug_substitution — not part of the ERD's
 * 36-table schema; the feature built on them was fully removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('drug_interaction');
        Schema::dropIfExists('drug_substitution');
    }

    public function down(): void
    {
        Schema::create('drug_interaction', function (Blueprint $table) {
            $table->string('interaction_id', 20)->primary();
            $table->string('medicine_id_1', 20);
            $table->string('medicine_id_2', 20);
            $table->text('interaction_effect')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->nullable();
            $table->foreign('medicine_id_1')->references('medicine_id')->on('medicine');
            $table->foreign('medicine_id_2')->references('medicine_id')->on('medicine');
        });

        Schema::create('drug_substitution', function (Blueprint $table) {
            $table->string('substitution_id', 20)->primary();
            $table->string('original_medicine_id', 20);
            $table->string('alternative_medicine_id', 20);
            $table->text('reason')->nullable();
            $table->foreign('original_medicine_id')->references('medicine_id')->on('medicine');
            $table->foreign('alternative_medicine_id')->references('medicine_id')->on('medicine');
        });
    }
};
