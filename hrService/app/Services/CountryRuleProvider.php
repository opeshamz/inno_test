<?php

namespace App\Services;

/**
 * Provides country-specific validation rules for employees.
 * Add new countries here to keep `FormRequest` logic thin.
 */
class CountryRuleProvider
{
    /**
     * Return rule set for the given country.
     * Pass $prefix = 'sometimes' for PATCH/PUT requests so fields are optional.
     *
     * @param string|null $country
     * @param string|null $prefix  e.g. 'sometimes'
     * @return array
     */
    public function rulesFor(?string $country, ?string $prefix = null): array
    {
        $map   = $this->map();
        $rules = $map[$country] ?? [];

        if ($prefix === null) {
            return $rules;
        }

        // Prepend the prefix to every field's rule list
        return array_map(
            fn(array $fieldRules) => array_merge([$prefix], $fieldRules),
            $rules
        );
    }

    /**
     * Return a list of supported country codes.
     * Useful for keeping the `in:` validation in sync.
     *
     * @return array
     */
    public function supportedCountries(): array
    {
        return array_keys($this->map());
    }

    /**
     * Internal map of country => rules
     * Keep this small and obvious; move to per-country classes later if logic grows.
     *
     * @return array
     */
    private function map(): array
    {
        return [
            'USA' => [
                'ssn'     => ['required', 'string', 'max:50'],
                'address' => ['required', 'string'],
            ],
            'Germany' => [
                'goal'   => ['required', 'string'],
                'tax_id' => ['required', 'string', 'regex:/^DE\d{9}$/'],
            ],
        ];
    }
}
