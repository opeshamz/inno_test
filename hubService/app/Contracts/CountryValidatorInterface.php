<?php

namespace App\Contracts;

/**
 * Interface for country-specific validation rules.
 * Adding support for a new country: implement this interface
 * and register it in CountryValidationServiceProvider.
 */
interface CountryValidatorInterface
{
    /**
     * Return an array of field-level validation results for the given employee data.
     *
     * @param  array  $employee  Raw employee data from HR Service.
     * @return array  [
     *     'field_name' => [
     *         'complete' => bool,
     *         'message'  => string,
     *     ],
     *     ...
     * ]
     */
    public function validate(array $employee): array;

    /**
     * Return the list of required fields for this country.
     */
    public function requiredFields(): array;
}
