<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColumnConfig extends Model
{
    protected $fillable = ['country', 'columns', 'active'];

    protected $casts = [
        'columns' => 'array',
        'active'  => 'boolean',
    ];

    /** Return the columns array for a country, or empty array if none found. */
    public static function forCountry(string $country): array
    {
        return static::where('country', $country)
            ->where('active', true)
            ->value('columns') ?? [];
    }
}
