<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for retrieving employee data from the HR Service.
 * All requests are made from HubService to HR Service's internal API.
 */
class HrApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.hr_service.url', env('HR_SERVICE_URL', 'http://hr-service:8001')), '/');
    }

    /**
     * Fetch all employees for a country (all pages combined).
     */
    public function getEmployeesByCountry(string $country): array
    {
        $employees = [];
        $page      = 1;

        do {
            $response = $this->request('GET', '/api/employees', [
                'country'  => $country,
                'per_page' => 100,
                'page'     => $page,
            ]);

            if (! $response) {
                break;
            }

            $data      = $response['data'] ?? [];
            $employees = array_merge($employees, $data);

            $lastPage = $response['meta']['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage);

        return $employees;
    }

    /**
     * Fetch a paginated list of employees for a country.
     */
    public function getEmployeesPaginated(string $country, int $page = 1, int $perPage = 15): array
    {
        return $this->request('GET', '/api/employees', [
            'country'  => $country,
            'per_page' => $perPage,
            'page'     => $page,
        ]) ?? ['data' => [], 'meta' => []];
    }

    // -----------------------------------------------------------------------
    // Internal HTTP wrapper
    // -----------------------------------------------------------------------

    private function request(string $method, string $path, array $params = []): ?array
    {
        try {
            $response = Http::timeout(10)
                ->baseUrl($this->baseUrl)
                ->withOptions(['verify' => false])
                ->{strtolower($method)}($path, $params);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[HrApiService] Non-successful response.', [
                'url'    => $this->baseUrl . $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[HrApiService] Request failed.', [
                'url'   => $this->baseUrl . $path,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
