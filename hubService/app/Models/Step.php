<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Step extends Model
{
    protected $fillable = [
        'country',
        'step_id',
        'label',
        'icon',
        'path',
        'order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'order'  => 'integer',
    ];

    /** Scope: only active steps for a given country, ordered. */
    public function scopeForCountry($query, string $country)
    {
        return $query->where('country', $country)
            ->where('active', true)
            ->orderBy('order');
    }
}
