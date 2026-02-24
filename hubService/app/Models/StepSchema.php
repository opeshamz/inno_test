<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StepSchema extends Model
{
    protected $fillable = [
        'step_id',
        'country',
        'title',
        'widgets',
        'active',
    ];

    protected $casts = [
        'widgets' => 'array',
        'active'  => 'boolean',
    ];

    /** Find the active schema for a given step + country, or null. */
    public static function findForStep(string $stepId, string $country): ?self
    {
        return static::where('step_id', $stepId)
            ->where('country', $country)
            ->where('active', true)
            ->first();
    }
}
