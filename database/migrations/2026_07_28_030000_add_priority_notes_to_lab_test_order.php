<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "Order Lab Test" form has always collected Priority and Clinical
 * Notes (lab/order-form.blade.php), but neither column existed — both were
 * silently discarded server-side (StoreLabOrderRequest validated them,
 * then LabApiController::storeOrder() explicitly unset() them before
 * create()). This actually persists what the form already promises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_test_order', function (Blueprint $table) {
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine')->after('test_name');
            $table->text('notes')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('lab_test_order', function (Blueprint $table) {
            $table->dropColumn(['priority', 'notes']);
        });
    }
};
