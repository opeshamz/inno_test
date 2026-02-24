<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Employee $employee */
        $employee = $this->route('employee');

        // Use submitted country, fall back to the employee's existing country
        $country = $this->input('country', $employee->country);

        $provider  = app(\App\Services\CountryRuleProvider::class);
        $supported = $provider->supportedCountries();

        $rules = [
            'name'      => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'salary'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'country'   => ['sometimes', 'string', 'in:' . implode(',', $supported)],
        ];

        // Merge country-specific rules with 'sometimes' prefix (partial update safe)
        $rules = array_merge($rules, $provider->rulesFor($country, 'sometimes'));

        return $rules;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'fail',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
