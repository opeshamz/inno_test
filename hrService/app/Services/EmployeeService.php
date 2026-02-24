<?php

namespace App\Services;

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Owns all Employee business logic:
 *   - Persistence (create / update / delete) wrapped in DB transactions
 *   - RabbitMQ event publishing after each successful mutation
 *   - Logging for debugging and audit trail
 *
 * Transaction strategy:
 *   DB write is wrapped in a transaction — if it fails the exception
 *   propagates and nothing is published.  If the DB succeeds but the
 *   RabbitMQ publish fails, we log and continue: data integrity takes
 *   priority over event delivery, and the HubService cache has a 5-min
 *   TTL fallback.
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
        Log::info('[EmployeeService] Creating employee.', [
            'country' => $data['country'] ?? null,
            'name'    => $data['name'] ?? null,
        ]);

        try {
            $employee = DB::transaction(fn() => Employee::create($data));
        } catch (\Throwable $e) {
            Log::error('[EmployeeService] Failed to create employee.', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            throw $e;
        }

        Log::info('[EmployeeService] Employee created.', ['id' => $employee->id]);

        $this->publish('EmployeeCreated', $employee);

        return $employee;
    }

    public function update(Employee $employee, array $data): Employee
    {
        $changedFields = array_keys(
            array_diff_assoc($data, $employee->only(array_keys($data)))
        );

        Log::info('[EmployeeService] Updating employee.', [
            'id'             => $employee->id,
            'changed_fields' => $changedFields,
        ]);

        try {
            DB::transaction(function () use ($employee, $data) {
                $employee->update($data);
                $employee->refresh();
            });
        } catch (\Throwable $e) {
            Log::error('[EmployeeService] Failed to update employee.', [
                'id'    => $employee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('[EmployeeService] Employee updated.', ['id' => $employee->id]);

        $this->publish('EmployeeUpdated', $employee, $changedFields);

        return $employee;
    }

    public function delete(Employee $employee): void
    {
        // Snapshot must be captured inside the transaction before the row is gone
        Log::info('[EmployeeService] Deleting employee.', ['id' => $employee->id]);

        try {
            $snapshot = DB::transaction(function () use ($employee) {
                $snapshot = $employee->toCountryArray();
                $employee->delete();
                return $snapshot;
            });
        } catch (\Throwable $e) {
            Log::error('[EmployeeService] Failed to delete employee.', [
                'id'    => $employee->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('[EmployeeService] Employee deleted.', ['id' => $snapshot['id'] ?? null]);

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
