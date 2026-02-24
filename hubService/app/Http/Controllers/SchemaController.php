<?php

namespace App\Http\Controllers;

use App\Models\StepSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/schema/{step_id}?country=USA
 *
 * Returns dynamic widget configuration for a given step + country.
 * Schemas are stored in the `step_schemas` table and can be updated
 * without a code deployment.
 *
 * PRD compliance:
 *  - USA  dashboard → employee_count, average_salary, completion_rate
 *  - DE   dashboard → employee_count, goal_tracking
 *  - Each widget declares data_source + real-time channel/event
 */
class SchemaController extends Controller
{
    public function show(Request $request, string $stepId): JsonResponse
    {
        $request->validate([
            'country' => ['required', 'string'],
        ]);

        $country = $request->query('country');

        $schema = StepSchema::findForStep($stepId, $country);

        if (! $schema) {
            return response()->json([
                'message' => "No schema found for step '{$stepId}' and country '{$country}'.",
            ], 404);
        }

        return response()->json([
            'country' => $country,
            'schema'  => [
                'step_id' => $schema->step_id,
                'title'   => $schema->title,
                'widgets' => $schema->widgets,
            ],
        ]);
    }
}
