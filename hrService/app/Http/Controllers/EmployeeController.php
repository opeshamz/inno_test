<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    // -----------------------------------------------------------------------
    // GET /api/employees
    // -----------------------------------------------------------------------
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::query();

        if ($country = $request->query('country')) {
            $query->where('country', $country);
        }

        $employees = $query->paginate($request->query('per_page', 15));

        return EmployeeResource::collection($employees);
    }

    // -----------------------------------------------------------------------
    // POST /api/employees
    // -----------------------------------------------------------------------
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        $this->publishEvent('EmployeeCreated', $employee, []);

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    // -----------------------------------------------------------------------
    // GET /api/employees/{employee}
    // -----------------------------------------------------------------------
    public function show(Employee $employee): EmployeeResource
    {
        return new EmployeeResource($employee);
    }

    // -----------------------------------------------------------------------
    // PUT/PATCH /api/employees/{employee}
    // -----------------------------------------------------------------------
    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $validated     = $request->validated();
        $changedFields = array_keys(array_diff_assoc($validated, $employee->only(array_keys($validated))));

        $employee->update($validated);
        $employee->refresh();

        $this->publishEvent('EmployeeUpdated', $employee, $changedFields);

        return new EmployeeResource($employee);
    }

    // -----------------------------------------------------------------------
    // DELETE /api/employees/{employee}
    // -----------------------------------------------------------------------
    public function destroy(Employee $employee): JsonResponse
    {
        $snapshot = $employee->toCountryArray();
        $employee->delete();

        $this->publishEvent('EmployeeDeleted', null, [], $snapshot);

        return response()->json(['message' => 'Employee deleted successfully.']);
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------
    private function publishEvent(
        string $eventType,
        ?Employee $employee,
        array $changedFields = [],
        ?array $snapshot = null
    ): void {
        $payload = [
            'event_type'    => $eventType,
            'event_id'      => (string) Str::uuid(),
            'timestamp'     => now()->toIso8601String(),
            'country'       => $employee?->country ?? ($snapshot['country'] ?? null),
            'data'          => [
                'employee_id'    => $employee?->id ?? ($snapshot['id'] ?? null),
                'changed_fields' => $changedFields,
                'employee'       => $employee?->toCountryArray() ?? $snapshot,
            ],
        ];

        try {
            PublishEmployeeEvent::dispatch($payload)->onQueue('employee-events');
        } catch (\Throwable $e) {
            Log::error('[HR Service] Failed to publish employee event', [
                'event'     => $eventType,
                'error'     => $e->getMessage(),
                'payload'   => $payload,
            ]);
        }
    }
}
