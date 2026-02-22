<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use App\Services\ChecklistService;
use App\Services\HrApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GET /api/checklists?country=USA
 *
 * Returns a cached, aggregated checklist report for all employees
 * in the specified country.  Cache is invalidated automatically
 * when RabbitMQ events arrive.
 */
class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly CacheService     $cacheService,
        private readonly HrApiService     $hrApiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country' => ['required', 'string', 'in:USA,Germany'],
        ]);

        $country = $request->query('country');

        Log::info('[ChecklistController] Request received.', ['country' => $country]);

        $report = $this->cacheService->rememberChecklist($country, function () use ($country) {
            $employees = $this->hrApiService->getEmployeesByCountry($country);
            return $this->checklistService->buildReport($employees);
        });

        return response()->json([
            'country' => $country,
            'data'    => $report,
        ]);
    }
}
