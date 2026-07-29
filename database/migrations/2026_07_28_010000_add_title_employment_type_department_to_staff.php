<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff List (Figma) shows a job title, an employment type, and a
 * department for every role — including receptionist/pharmacist/
 * lab_technician/admin, whose subtype tables carry no department_id.
 * These stay on `staff` directly rather than duplicating a department_id
 * per subtype table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('title', 150)->nullable()->after('last_name');
            $table->enum('employment_type', ['full_time', 'part_time'])->nullable()->after('status');
            $table->string('department_id', 20)->nullable()->after('employment_type');
            $table->foreign('department_id')->references('department_id')->on('department');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['title', 'employment_type', 'department_id']);
        });
    }
};
