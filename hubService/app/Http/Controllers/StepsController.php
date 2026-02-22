<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/steps?country=USA
 *
 * Returns server-driven navigation steps for the given country.
 * USA:     Dashboard, Employees
 * Germany: Dashboard, Employees, Documentation
 *
 * Adding a country: add a new entry to $stepsConfig below.
 */
class StepsController extends Controller
{
    private array $stepsConfig = [
        'USA' => [
            [
                'id'    => 'dashboard',
                'label' => 'Dashboard',
                'icon'  => 'home',
                'path'  => '/dashboard',
                'order' => 1,
            ],
            [
                'id'    => 'employees',
                'label' => 'Employees',
                'icon'  => 'users',
                'path'  => '/employees',
                'order' => 2,
            ],
        ],
        'Germany' => [
            [
                'id'    => 'dashboard',
                'label' => 'Dashboard',
                'icon'  => 'home',
                'path'  => '/dashboard',
                'order' => 1,
            ],
            [
                'id'    => 'employees',
                'label' => 'Employees',
                'icon'  => 'users',
                'path'  => '/employees',
                'order' => 2,
            ],
            [
                'id'    => 'documentation',
                'label' => 'Documentation',
                'icon'  => 'file-text',
                'path'  => '/documentation',
                'order' => 3,
            ],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country' => ['required', 'string', 'in:USA,Germany'],
        ]);

        $country = $request->query('country');
        $steps   = $this->stepsConfig[$country] ?? [];

        return response()->json([
            'country' => $country,
            'steps'   => $steps,
        ]);
    }
}
