<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the stampede-protection behavior of DashboardController's shared
 * cachedRoleData() helper (see its docblock): repeated/concurrent callers
 * within the fresh or stale window must trigger the expensive compute()
 * closure at most once, not once per caller.
 */
class DashboardCacheStampedeTest extends TestCase
{
    private function cachedRoleData(): ReflectionMethod
    {
        $method = new ReflectionMethod(DashboardController::class, 'cachedRoleData');
        $method->setAccessible(true);

        return $method;
    }

    public function test_repeated_calls_within_the_fresh_window_recompute_only_once(): void
    {
        Cache::flush();
        $method = $this->cachedRoleData();
        $controller = new DashboardController();

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return ['value' => 'computed'];
        };

        // Simulates several requests all landing while the cached value is
        // still fresh — represents what would otherwise be N concurrent
        // requests all hitting a warm cache.
        for ($i = 0; $i < 5; $i++) {
            $result = $method->invoke($controller, 'test:stampede:fresh', $compute);
            $this->assertSame(['value' => 'computed'], $result);
        }

        $this->assertSame(1, $calls, 'compute() must only run once while the cached value is fresh');
    }

    public function test_a_stale_but_not_cold_read_serves_the_stale_value_without_synchronous_recompute(): void
    {
        Cache::flush();
        $method = $this->cachedRoleData();
        $controller = new DashboardController();

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return ['value' => "computed-{$calls}"];
        };

        $first = $method->invoke($controller, 'test:stampede:stale', $compute);
        $this->assertSame(['value' => 'computed-1'], $first);

        // Age the flexible-cache "created" marker past the 60s fresh window
        // but within the 120s stale window, so the next read takes the
        // serve-stale-and-defer-refresh path instead of a synchronous
        // recompute — this is the core stampede-prevention mechanism.
        Cache::put(
            'illuminate:cache:flexible:created:test:stampede:stale',
            now()->subSeconds(90)->getTimestamp(),
            120
        );

        $second = $method->invoke($controller, 'test:stampede:stale', $compute);

        $this->assertSame(['value' => 'computed-1'], $second, 'a stale-but-not-cold read must still return the last cached value');
        $this->assertSame(1, $calls, 'a stale-but-not-cold read must defer its refresh, not recompute inline on the request thread');
    }

    public function test_a_cold_key_computes_synchronously(): void
    {
        Cache::flush();
        $method = $this->cachedRoleData();
        $controller = new DashboardController();

        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return ['value' => 'computed'];
        };

        $result = $method->invoke($controller, 'test:stampede:cold', $compute);

        $this->assertSame(['value' => 'computed'], $result);
        $this->assertSame(1, $calls);
    }
}
