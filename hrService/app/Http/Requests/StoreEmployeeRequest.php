<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $country = $this->input('country');

        $rules = [
            'name'      => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'salary'    => ['nullable', 'numeric', 'min:0'],
            'country'   => ['required', 'string', 'in:USA,Germany'],
        ];

        if ($country === 'USA') {
            $rules['ssn']     = ['nullable', 'string', 'max:50'];
            $rules['address'] = ['nullable', 'string'];
        }

        if ($country === 'Germany') {
            $rules['goal']   = ['nullable', 'string'];
            $rules['tax_id'] = ['nullable', 'string', 'regex:/^DE\d{9}$/'];
        }

        return $rules;
    }
}
