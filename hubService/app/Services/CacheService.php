<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Centralised cache management for HubService.
 *
 * Key structure (predictable + easy to invalidate):
 *   checklist:{country}          – aggregated checklist for a country
 *   employees:{country}:page:{n}:{per_page} – paginated employee list
 *   employee:{id}                – single employee data from HR Service
 */
class CacheService
{
    // TTLs
    public const TTL_CHECKLIST = 300;   // 5 minutes
    public const TTL_EMPLOYEES = 300;   // 5 minutes

    // -----------------------------------------------------------------------
    // Key builders
    // -----------------------------------------------------------------------

    public function checklistKey(string $country): string
    {
        return "checklist:{$country}";
    }

    public function employeeListKey(string $country, int $page = 1, int $perPage = 15): string
    {
        return "employees:{$country}:page:{$page}:{$perPage}";
    }

    public function employeeKey(int|string $employeeId): string
    {
        return "employee:{$employeeId}";
    }

    // -----------------------------------------------------------------------
    // Invalidation helpers
    // -----------------------------------------------------------------------

    /**
     * Invalidate all cache entries related to a country checklist and
     * the specific employee when an event arrives.
     */
    public function invalidateForEmployee(int|string $employeeId, string $country): void
    {
        $keys = [
            $this->checklistKey($country),
            $this->employeeKey($employeeId),
        ];

        // Also clear all page variants by pattern (Redis SCAN-based approach)
        $this->forgetByPattern("employees:{$country}:*");

        foreach ($keys as $key) {
            Cache::forget($key);
            Log::debug('[CacheService] Cache invalidated.', ['key' => $key]);
        }
    }

    /**
     * Attempt a Redis SCAN to forget keys matching a pattern.
     * Falls gracefully back to no-op for non-Redis drivers.
     */
    public function forgetByPattern(string $pattern): void
    {
        try {
            $redis  = Cache::getStore()->getRedis();
            $cursor = 0;

            do {
                [$cursor, $keys] = $redis->scan($cursor, ['match' => config('cache.prefix') . $pattern, 'count' => 100]);
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            } while ($cursor != 0);
        } catch (\Throwable $e) {
            Log::warning('[CacheService] Pattern-based invalidation failed.', ['pattern' => $pattern, 'error' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------------------
    // Cache-aside helpers
    // -----------------------------------------------------------------------

    public function rememberChecklist(string $country, \Closure $callback): array
    {
        return Cache::remember(
            $this->checklistKey($country),
            self::TTL_CHECKLIST,
            $callback
        );
    }

    public function rememberEmployeeList(string $country, int $page, int $perPage, \Closure $callback): array
    {
        return Cache::remember(
            $this->employeeListKey($country, $page, $perPage),
            self::TTL_EMPLOYEES,
            $callback
        );
    }
}
