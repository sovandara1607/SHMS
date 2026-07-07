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

        // Register one Gate per distinct capability across all roles.
        $abilities = collect(config('permissions.permissions', []))
            ->flatten()
            ->unique()
            ->reject(fn ($a) => $a === '*');

        foreach ($abilities as $ability) {
            Gate::define($ability, fn ($user) => $user->hasPermission($ability));
        }
    }
}
