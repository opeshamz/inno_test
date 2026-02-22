<?php

namespace App\Http\Controllers;

use App\Services\CacheService;
use App\Services\HrApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/employees?country=USA&page=1&per_page=15
 *
 * Returns a paginated, country-filtered employee list with
 * column definitions so the frontend knows how to render each field.
 * Results are cached and invalidated on RabbitMQ events.
 */
class EmployeeController extends Controller
{
    /**
     * Column definitions per country.
     * This drives the frontend table columns (server-driven UI).
     */
    private array $columnConfig = [
        'USA' => [
            ['key' => 'name',      'label' => 'First Name', 'sortable' => true],
            ['key' => 'last_name', 'label' => 'Last Name',  'sortable' => true],
            ['key' => 'salary',    'label' => 'Salary',     'sortable' => true, 'format' => 'currency'],
            ['key' => 'ssn',       'label' => 'SSN',        'sortable' => false, 'masked' => true],
        ],
        'Germany' => [
            ['key' => 'name',      'label' => 'First Name', 'sortable' => true],
            ['key' => 'last_name', 'label' => 'Last Name',  'sortable' => true],
            ['key' => 'salary',    'label' => 'Salary',     'sortable' => true, 'format' => 'currency'],
            ['key' => 'goal',      'label' => 'Goal',       'sortable' => false],
        ],
    ];

    public function __construct(
        private readonly HrApiService $hrApiService,
        private readonly CacheService $cacheService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country'  => ['required', 'string', 'in:USA,Germany'],
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

        // Mask SSN for USA employees
        if ($country === 'USA') {
            $result['data'] = array_map(
                fn($e) => array_merge($e, ['ssn' => $this->maskSsn($e['ssn'] ?? '')]),
                $result['data']
            );
        }

        return response()->json([
            'country' => $country,
            'columns' => $this->columnConfig[$country] ?? [],
            'data'    => $result['data'] ?? [],
            'meta'    => $result['meta'] ?? [],
            // Real-time update channel for this employee list
            'channels' => [
                'employees' => "employees.{$country}",
            ],
        ]);
    }

    private function maskSsn(string $ssn): string
    {
        if (empty($ssn)) {
            return '';
        }

        // Count total digits; we keep the last 4 and mask the rest.
        // Works with any separator style: 123-45-6789 → ***-**-6789
        $totalDigits = strlen(preg_replace('/\D/', '', $ssn));
        $keepFrom    = max(0, $totalDigits - 4);
        $digitsSeen  = 0;

        return (string) preg_replace_callback('/\d/', function (array $m) use (&$digitsSeen, $keepFrom) {
            $digitsSeen++;
            return $digitsSeen > $keepFrom ? $m[0] : '*';
        }, $ssn);
    }
}
