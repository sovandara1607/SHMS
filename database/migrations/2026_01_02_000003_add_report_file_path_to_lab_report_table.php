<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the generated PDF for a lab report lives on the documents disk
 * (Cloudflare R2 in production, local disk in dev) — written by
 * GenerateLabReportDocumentJob after the report row itself is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_report', function (Blueprint $table) {
            $table->string('report_file_path', 255)->nullable()->after('report_content');
        });
    }

    public function down(): void
    {
        Schema::table('lab_report', function (Blueprint $table) {
            $table->dropColumn('report_file_path');
        });
    }
};
