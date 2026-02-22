<?php

namespace App\Services;

use App\Contracts\CountryValidatorInterface;
use App\Validators\GermanyCountryValidator;
use App\Validators\UsaCountryValidator;
use Illuminate\Support\Facades\Log;

/**
 * Determines per-employee and aggregate checklist completion status.
 * Country validators are resolved via a registry so new countries
 * can be added without touching this class.
 */
class ChecklistService
{
    /** @var array<string, CountryValidatorInterface> */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            'USA'     => new UsaCountryValidator(),
            'Germany' => new GermanyCountryValidator(),
        ];
    }

    /**
     * Build a full checklist report for a list of employees.
     *
     * @param  array  $employees  Array of employee arrays from HR Service.
     * @return array
     */
    public function buildReport(array $employees): array
    {
        $employeeChecklists = [];
        $totalFields        = 0;
        $completedFields    = 0;

        foreach ($employees as $employee) {
            $checklist = $this->buildEmployeeChecklist($employee);

            $totalFields     += $checklist['total_fields'];
            $completedFields += $checklist['completed_fields'];

            $employeeChecklists[] = $checklist;
        }

        $overallCompletion = $totalFields > 0
            ? round(($completedFields / $totalFields) * 100, 2)
            : 0;

        return [
            'summary' => [
                'total_employees'      => count($employees),
                'total_fields'         => $totalFields,
                'completed_fields'     => $completedFields,
                'incomplete_fields'    => $totalFields - $completedFields,
                'overall_completion'   => $overallCompletion,
            ],
            'employees' => $employeeChecklists,
        ];
    }

    /**
     * Build checklist for a single employee.
     */
    public function buildEmployeeChecklist(array $employee): array
    {
        $country   = $employee['country'] ?? 'UNKNOWN';
        $validator = $this->resolveValidator($country);

        if (! $validator) {
            Log::warning('[ChecklistService] No validator found for country.', ['country' => $country]);
            return $this->emptyChecklist($employee);
        }

        $fieldResults    = $validator->validate($employee);
        $completedFields = collect($fieldResults)->filter(fn($f) => $f['complete'])->count();
        $totalFields     = count($fieldResults);

        return [
            'employee_id'       => $employee['id'],
            'name'              => $employee['name'] . ' ' . $employee['last_name'],
            'country'           => $country,
            'completed_fields'  => $completedFields,
            'total_fields'      => $totalFields,
            'completion_rate'   => $totalFields > 0
                ? round(($completedFields / $totalFields) * 100, 2)
                : 0,
            'is_complete'       => $completedFields === $totalFields,
            'fields'            => $fieldResults,
        ];
    }

    private function resolveValidator(string $country): ?CountryValidatorInterface
    {
        return $this->validators[$country] ?? null;
    }

    private function emptyChecklist(array $employee): array
    {
        return [
            'employee_id'      => $employee['id'] ?? null,
            'name'             => ($employee['name'] ?? '') . ' ' . ($employee['last_name'] ?? ''),
            'country'          => $employee['country'] ?? 'UNKNOWN',
            'completed_fields' => 0,
            'total_fields'     => 0,
            'completion_rate'  => 0,
            'is_complete'      => false,
            'fields'           => [],
        ];
    }
}
