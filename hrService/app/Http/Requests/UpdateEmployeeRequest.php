<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        $country  = $this->input('country', $employee->country);

        $rules = [
            'name'      => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'salary'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'country'   => ['sometimes', 'string', 'in:USA,Germany'],
        ];

        if ($country === 'USA') {
            $rules['ssn']     = ['sometimes', 'nullable', 'string', 'max:50'];
            $rules['address'] = ['sometimes', 'nullable', 'string'];
        }

        if ($country === 'Germany') {
            $rules['goal']   = ['sometimes', 'nullable', 'string'];
            $rules['tax_id'] = ['sometimes', 'nullable', 'string', 'regex:/^DE\d{9}$/'];
        }

        return $rules;
    }
}
