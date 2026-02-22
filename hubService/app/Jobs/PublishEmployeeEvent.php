<?php

namespace App\Jobs;

use App\Events\EmployeeDataUpdated;
use App\Services\CacheService;
use App\Services\ChecklistService;
use App\Services\HrApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Consumes RabbitMQ employee events published by the HR Service.
 *
 * This class MUST share the same FQCN as the publisher's job
 * (App\Jobs\PublishEmployeeEvent) so Laravel's queue driver can
 * deserialise the payload correctly.
 *
 * Responsibility chain per event:
 *   1. Extract event data
 *   2. Invalidate stale cache entries
 *   3. Re-build & cache checklist report
 *   4. Broadcast real-time update via WebSocket (Soketi)
 */
class PublishEmployeeEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 10;

    public function __construct(public readonly array $payload) {}

    public function handle(
        CacheService   $cacheService,
        ChecklistService $checklistService,
        HrApiService   $hrApiService
    ): void {
        $eventType  = $this->payload['event_type']  ?? 'Unknown';
        $country    = $this->payload['country']     ?? null;
        $data       = $this->payload['data']        ?? [];
        $employeeId = $data['employee_id']          ?? null;

        Log::info('[HubService] Processing employee event.', [
            'event_type'  => $eventType,
            'employee_id' => $employeeId,
            'country'     => $country,
        ]);

        if (! $country) {
            Log::warning('[HubService] Event has no country; skipping.', $this->payload);
            return;
        }

        try {
            // 1. Invalidate related cache entries
            if ($employeeId) {
                $cacheService->invalidateForEmployee($employeeId, $country);
            }

            // 2. Re-fetch employees from HR Service and rebuild checklist
            $employees       = $hrApiService->getEmployeesByCountry($country);
            $checklistReport = $checklistService->buildReport($employees);

            // 3. Store updated checklist in cache
            \Illuminate\Support\Facades\Cache::put(
                $cacheService->checklistKey($country),
                $checklistReport,
                CacheService::TTL_CHECKLIST
            );

            Log::info('[HubService] Checklist cache refreshed.', [
                'country'    => $country,
                'employees'  => count($employees),
                'completion' => $checklistReport['summary']['overall_completion'] ?? null,
            ]);

            // 4. Broadcast WebSocket update
            $employee = $data['employee'] ?? [];

            broadcast(new EmployeeDataUpdated(
                eventType: $eventType,
                country: $country,
                employeeId: $employeeId,
                payload: $employee,
                checklistSummary: $checklistReport['summary'],
            ));

            Log::info('[HubService] WebSocket broadcast sent.', [
                'event_type' => $eventType,
                'country'    => $country,
            ]);
        } catch (\Throwable $e) {
            Log::error('[HubService] Event processing failed.', [
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            throw $e; // let queue retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[HubService] Employee event permanently failed after retries.', [
            'payload' => $this->payload,
            'error'   => $exception->getMessage(),
        ]);
    }
}
