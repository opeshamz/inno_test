<?php

namespace App\Http\Controllers;

use App\Models\Step;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/steps?country=USA
 *
 * Returns server-driven navigation steps for the given country.
 * Steps are stored in the `steps` database table and can be
 * managed without a code deployment.
 */
class StepsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'country' => ['required', 'string'],
        ]);

        $country = $request->query('country');

        $steps = Step::forCountry($country)
            ->get(['step_id', 'label', 'icon', 'path', 'order']);

        if ($steps->isEmpty()) {
            return response()->json([
                'message' => "No steps configured for country: {$country}",
            ], 404);
        }

        return response()->json([
            'country' => $country,
            'steps'   => $steps,
        ]);
    }
}
