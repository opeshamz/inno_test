<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use App\Services\ChecklistService;
use App\Services\HrApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * GET /api/checklists
 * GET /api/checklists?country=USA
 *
 * Without country: returns supported countries list + checklist for all of them.
 * With country:    returns checklist for that specific country only.
 * Cache is invalidated automatically when RabbitMQ events arrive.
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
        $supported = $this->checklistService->supportedCountries();

        $request->validate([
            'country' => ['sometimes', 'nullable', 'string', Rule::in($supported)],
        ]);

        $country = $request->query('country');

        // No country filter — return supported countries + all checklists
        if (! $country) {
            Log::info('[ChecklistController] No country filter — returning all countries.');

            $results = [];
            foreach ($supported as $c) {
                $results[$c] = $this->cacheService->rememberChecklist($c, function () use ($c) {
                    $employees = $this->hrApiService->getEmployeesByCountry($c);
                    return $this->checklistService->buildReport($employees);
                });
            }

            return response()->json([
                'supported_countries' => $supported,
                'data'                => $results,
            ]);
        }

        // Country filter provided — return single country checklist
        Log::info('[ChecklistController] Request received.', ['country' => $country]);

        $report = $this->cacheService->rememberChecklist($country, function () use ($country) {
            $employees = $this->hrApiService->getEmployeesByCountry($country);
            return $this->checklistService->buildReport($employees);
        });

        return response()->json([
            'supported_countries' => $supported,
            'country'             => $country,
            'data'                => $report,
        ]);
    }
}
