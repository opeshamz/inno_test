<?php

namespace App\Http\Controllers;

use App\Models\ColumnConfig;
use App\Services\CacheService;
use App\Services\HrApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/employees?country=USA&page=1&per_page=15
 *
 * Returns a paginated, country-filtered employee list with
 * column definitions loaded from the database (server-driven UI).
 * Results are cached and invalidated on RabbitMQ events.
 */
class EmployeeController extends Controller
{
    public function __construct(
        private readonly HrApiService $hrApiService,
        private readonly CacheService $cacheService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country'  => ['required', 'string'],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $country = $request->query('country');
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 15);

        $result = $this->cacheService->rememberEmployeeList(
            $country,
            $page,
            $perPage,
            fn() => $this->hrApiService->getEmployeesPaginated($country, $page, $perPage)
        );

        // Load column definitions from DB (server-driven UI + masking rules).
        $columns = ColumnConfig::forCountry($country);

        // Mask any fields flagged `masked: true` in column_configs — no hardcoded country check needed.
        $maskedKeys = collect($columns)->where('masked', true)->pluck('key')->all();

        if (! empty($maskedKeys)) {
            $result['data'] = array_map(function (array $employee) use ($maskedKeys) {
                foreach ($maskedKeys as $key) {
                    if (isset($employee[$key])) {
                        $employee[$key] = $this->maskValue((string) $employee[$key]);
                    }
                }
                return $employee;
            }, $result['data'] ?? []);
        }

        return response()->json([
            'country'  => $country,
            'columns'  => $columns,
            'data'     => $result['data'] ?? [],
            'meta'     => $result['meta'] ?? [],
            'channels' => [
                'employees' => "employees.{$country}",
            ],
        ]);
    }

    /**
     * Mask all but the last 4 digits of a value, preserving separators.
     */
    private function maskValue(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $totalDigits = strlen(preg_replace('/\D/', '', $value));
        $keepFrom    = max(0, $totalDigits - 4);
        $digitsSeen  = 0;

        return (string) preg_replace_callback('/\d/', function (array $m) use (&$digitsSeen, $keepFrom) {
            $digitsSeen++;
            return $digitsSeen > $keepFrom ? $m[0] : '*';
        }, $value);
    }
}
