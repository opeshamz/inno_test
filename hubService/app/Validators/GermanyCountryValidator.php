<?php

namespace App\Validators;

use App\Contracts\CountryValidatorInterface;

/**
 * Validation rules for Germany-based employees.
 * Required: salary > 0, goal (non-empty), tax_id (format: DE + 9 digits).
 */
class GermanyCountryValidator implements CountryValidatorInterface
{
    public function requiredFields(): array
    {
        return ['salary', 'goal', 'tax_id'];
    }

    public function validate(array $employee): array
    {
        $results = [];

        // Salary
        $salary            = $employee['salary'] ?? null;
        $salaryComplete    = is_numeric($salary) && $salary > 0;
        $results['salary'] = [
            'complete' => $salaryComplete,
            'message'  => $salaryComplete
                ? 'Salary is set.'
                : 'Salary is required and must be greater than 0.',
        ];

        // Goal
        $results['goal'] = [
            'complete' => ! empty($employee['goal']),
            'message'  => ! empty($employee['goal'])
                ? 'Goal is defined.'
                : 'Goal is required for German employees.',
        ];

        // Tax ID (DE + exactly 9 digits)
        $taxId         = $employee['tax_id'] ?? '';
        $taxIdValid    = preg_match('/^DE\d{9}$/', (string) $taxId) === 1;
        $results['tax_id'] = [
            'complete' => $taxIdValid,
            'message'  => $taxIdValid
                ? 'Tax ID is valid.'
                : 'Tax ID must be in format DE + 9 digits (e.g. DE123456789).',
        ];

        return $results;
    }
}
