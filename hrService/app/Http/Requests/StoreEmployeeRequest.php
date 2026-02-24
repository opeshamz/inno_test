<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $country = $this->input('country');

        $provider = app(\App\Services\CountryRuleProvider::class);

        $supported = $provider->supportedCountries();

        $rules = [
            'name'      => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'salary'    => ['nullable', 'numeric', 'min:0'],
            'country'   => ['required', 'string', 'in:' . implode(',', $supported)],
        ];

        // Merge country-specific rules from the provider (scales to many countries)
        $rules = array_merge($rules, $provider->rulesFor($country));

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
