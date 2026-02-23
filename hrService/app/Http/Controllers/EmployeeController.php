<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Thin HTTP adapter — validates input, delegates to EmployeeService,
 * and formats the response. No business logic lives here.
 */
class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $service) {}

    // -----------------------------------------------------------------------
    // GET /api/employees
    // -----------------------------------------------------------------------
    public function index(Request $request): AnonymousResourceCollection
    {
        $employees = $this->service->paginate(
            $request->query('country'),
            (int) $request->query('per_page', 15)
        );

        return EmployeeResource::collection($employees);
    }

    // -----------------------------------------------------------------------
    // POST /api/employees
    // -----------------------------------------------------------------------
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->validated());

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
        $employee = $this->service->update($employee, $request->validated());

        return new EmployeeResource($employee);
    }

    // -----------------------------------------------------------------------
    // DELETE /api/employees/{employee}
    // -----------------------------------------------------------------------
    public function destroy(Employee $employee): JsonResponse
    {
        $this->service->delete($employee);

        return response()->json(['message' => 'Employee deleted successfully.']);
    }
}
