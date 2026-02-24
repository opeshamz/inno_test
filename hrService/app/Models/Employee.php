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
     * Extra fields are derived from CountryRuleProvider so no match arms
     * need updating when a new country is added.
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

        /** @var \App\Services\CountryRuleProvider $provider */
        $provider    = app(\App\Services\CountryRuleProvider::class);
        $extraKeys   = array_keys($provider->rulesFor($this->country));
        $extraValues = array_intersect_key($this->attributesToArray(), array_flip($extraKeys));

        return array_merge($base, $extraValues);
    }
}
