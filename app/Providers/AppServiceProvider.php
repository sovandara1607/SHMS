<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * RBAC: every capability string in config/permissions.php is registered
     * as a Gate ability. Routes use the `can:` middleware and Blade uses
     * @can to enforce role-based access. Admin ('*') passes everything via
     * Gate::before.
     */
    public function boot(): void
    {
        // Admin bypasses all checks.
        Gate::before(function ($user, string $ability) {
            return $user->hasPermission('*') ? true : null;
        });

        // Register one Gate per distinct capability. Sourced from both the
        // static per-role config (which also carries the baseline
        // dashboard.view/profile.* grants every role gets, outside the
        // editable catalog) AND the full Roles & Permissions catalog —
        // config alone used to miss capabilities like staff.manage/
        // report.view that only exist in the catalog, so granting them to a
        // non-admin role via the Roles & Permissions screen silently did
        // nothing: the grant was stored, but Gate::denies() always failed
        // closed because no Gate::define() was ever registered for them.
        $abilities = collect(config('permissions.permissions', []))
            ->flatten()
            ->merge(collect(\App\Http\Controllers\RolePermissionController::catalog())->flatMap(fn ($caps) => array_keys($caps)))
            ->unique()
            ->reject(fn ($a) => $a === '*');

        foreach ($abilities as $ability) {
            Gate::define($ability, fn ($user) => $user->hasPermission($ability));
        }
    }
}
