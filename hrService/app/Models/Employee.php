<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'last_name',
        'salary',
        'country',
        'ssn',
        'address',
        'goal',
        'tax_id',
    ];

    protected $casts = [
        'salary' => 'float',
    ];

    /**
     * Return only the fields that are relevant for the employee's country.
     */
    public function toCountryArray(): array
    {
        $base = [
            'id'        => $this->id,
            'name'      => $this->name,
            'last_name' => $this->last_name,
            'salary'    => $this->salary,
            'country'   => $this->country,
        ];

        return match ($this->country) {
            'USA'     => array_merge($base, ['ssn' => $this->ssn, 'address' => $this->address]),
            'Germany' => array_merge($base, ['goal' => $this->goal, 'tax_id' => $this->tax_id]),
            default   => $base,
        };
    }
}
