<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the Roles & Permissions admin screen: editable capability grants per
 * role, replacing the static config/permissions.php as the source of truth
 * for every role except super_admin/admin (protected, always '*').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('capability', 60);
            $table->timestamps();
            $table->unique(['role', 'capability']);
        });

        // Seed from the current static matrix so behaviour is unchanged on upgrade.
        foreach (config('permissions.permissions', []) as $role => $capabilities) {
            if (in_array('*', $capabilities, true)) {
                continue; // super_admin / admin: protected wildcard, not stored.
            }
            foreach ($capabilities as $capability) {
                DB::table('role_permissions')->insert([
                    'role' => $role,
                    'capability' => $capability,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
