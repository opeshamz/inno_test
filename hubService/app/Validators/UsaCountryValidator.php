<?php

namespace App\Validators;

use App\Contracts\CountryValidatorInterface;

/**
 * Validation rules for US-based employees.
 * Required: ssn, salary > 0, address (non-empty).
 */
class UsaCountryValidator implements CountryValidatorInterface
{
    public function requiredFields(): array
    {
        return ['ssn', 'salary', 'address'];
    }

    public function validate(array $employee): array
    {
        $results = [];

        // SSN
        $results['ssn'] = [
            'complete' => ! empty($employee['ssn']),
            'message'  => ! empty($employee['ssn'])
                ? 'SSN is present.'
                : 'SSN is required for US employees.',
        ];

        // Salary
        $salary              = $employee['salary'] ?? null;
        $salaryComplete      = is_numeric($salary) && $salary > 0;
        $results['salary']   = [
            'complete' => $salaryComplete,
            'message'  => $salaryComplete
                ? 'Salary is set.'
                : 'Salary is required and must be greater than 0.',
        ];

        // Address
        $results['address'] = [
            'complete' => ! empty($employee['address']),
            'message'  => ! empty($employee['address'])
                ? 'Address is present.'
                : 'Address is required for US employees.',
        ];

        return $results;
    }
}
