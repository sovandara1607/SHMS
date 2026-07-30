<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Short-TTL, fail-open cache for small, frequently-reused reference/lookup
 * data (doctor/nurse/technician dropdown sources, medicine catalogs) that
 * would otherwise re-run the same unpaginated query on every form render.
 * Centralizes the fail-open pattern already used elsewhere (see
 * DashboardController::cachedRoleData()) so every dropdown call site
 * doesn't repeat its own try/catch.
 *
 * Only for non-PHI reference data — staff/doctor names and medicine
 * catalogs are personnel/inventory lookups, not patient clinical content.
 * Never point this at anything containing individual patient data (see
 * analysis.md's findings on the now-removed patient:viewed/mr:viewed
 * PHI-in-cache writes for why that's treated as a hard line, not a
 * judgment call per call site).
 *
 * $compute() closures passed here should return a Collection of stdClass
 * rows (never live Eloquent models — see analysis.md/scalability-plan.md;
 * caching a model graph with loaded relations is unnecessary bytes and
 * couples the cache payload to ORM internals). This class round-trips the
 * result through plain PHP arrays internally, for a more fundamental
 * reason than that: config/cache.php sets 'serializable_classes' => false
 * (a real Laravel security default, hardening against PHP Object
 * Injection via cache poisoning), which makes Illuminate\Cache\RedisStore
 * call unserialize($value, ['allowed_classes' => false]) on every read —
 * PHP's unserialize() with allowed_classes=false refuses to reconstruct
 * ANY object (not just Eloquent models — stdClass too), silently
 * replacing it with __PHP_Incomplete_Class instead. This only ever
 * surfaces on a cache *hit* (a cache miss returns the freshly-computed
 * PHP value directly, no unserialize() involved), which is exactly why
 * it's easy to miss in quick manual testing: the very first request
 * always works. Caching only plain arrays (never objects) is what
 * actually survives that restriction — DashboardController's cache has
 * always worked precisely because computeAdminSummary() already returns
 * plain arrays throughout. Do not remove this array round-trip without
 * also removing/reworking the serializable_classes restriction.
 *
 * Catches \Throwable, not just a Redis-specific exception: a caching layer
 * must never be able to take a feature down, regardless of *why* the
 * cache failed. Safe to retry $compute() on any failure only because every
 * current caller is a pure, idempotent read query — never wrap a closure
 * with side effects.
 */
class DropdownCache
{
    private const TTL = 120;

    public static function remember(string $key, callable $compute)
    {
        try {
            $rows = Cache::remember(
                "dropdown:{$key}",
                self::TTL,
                fn () => collect($compute())->map(fn ($row) => (array) $row)->all()
            );

            return collect($rows)->map(fn (array $row) => (object) $row);
        } catch (\Throwable $e) {
            Log::warning('dropdown cache unavailable, computing uncached', ['error' => $e->getMessage(), 'key' => $key]);

            return $compute();
        }
    }
}
