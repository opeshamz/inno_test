<?php

namespace App\Services;

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Owns all Employee business logic:
 *   - Persistence (create / update / delete)
 *   - RabbitMQ event publishing after each mutation
 *
 * The controller becomes a thin HTTP adapter that delegates here.
 */
class EmployeeService
{
    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    public function paginate(?string $country, int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::query();

        if ($country) {
            $query->where('country', $country);
        }

        return $query->paginate($perPage);
    }

    // -----------------------------------------------------------------------
    // Mutations
    // -----------------------------------------------------------------------

    public function create(array $data): Employee
    {
        $employee = Employee::create($data);

        $this->publish('EmployeeCreated', $employee);

        return $employee;
    }

    public function update(Employee $employee, array $data): Employee
    {
        $changedFields = array_keys(
            array_diff_assoc($data, $employee->only(array_keys($data)))
        );

        $employee->update($data);
        $employee->refresh();

        $this->publish('EmployeeUpdated', $employee, $changedFields);

        return $employee;
    }

    public function delete(Employee $employee): void
    {
        // Capture snapshot before the record is gone
        $snapshot = $employee->toCountryArray();

        $employee->delete();

        $this->publishDeleted($snapshot);
    }

    // -----------------------------------------------------------------------
    // Event publishing (private — callers don't need to know the details)
    // -----------------------------------------------------------------------

    private function publish(
        string $eventType,
        Employee $employee,
        array $changedFields = []
    ): void {
        $payload = [
            'event_type' => $eventType,
            'event_id'   => (string) Str::uuid(),
            'timestamp'  => now()->toIso8601String(),
            'country'    => $employee->country,
            'data'       => [
                'employee_id'    => $employee->id,
                'changed_fields' => $changedFields,
                'employee'       => $employee->toCountryArray(),
            ],
        ];

        $this->dispatch($eventType, $payload);
    }

    private function publishDeleted(array $snapshot): void
    {
        $payload = [
            'event_type' => 'EmployeeDeleted',
            'event_id'   => (string) Str::uuid(),
            'timestamp'  => now()->toIso8601String(),
            'country'    => $snapshot['country'] ?? null,
            'data'       => [
                'employee_id'    => $snapshot['id'] ?? null,
                'changed_fields' => [],
                'employee'       => $snapshot,
            ],
        ];

        $this->dispatch('EmployeeDeleted', $payload);
    }

    private function dispatch(string $eventType, array $payload): void
    {
        try {
            PublishEmployeeEvent::dispatch($payload)->onQueue('employee-events');
        } catch (\Throwable $e) {
            Log::error('[EmployeeService] Failed to publish event.', [
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
