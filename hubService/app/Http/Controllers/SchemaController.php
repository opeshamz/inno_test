<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/schema/{step_id}?country=USA
 *
 * Returns dynamic widget/component configuration for a given step.
 * This drives what each page renders — fully server-controlled.
 *
 * Design is frontend-agnostic: each widget declares its type,
 * data source (API URL), and real-time channel so the frontend
 * can self-configure.
 */
class SchemaController extends Controller
{
    private array $schemas = [
        'dashboard' => [
            'USA' => [
                'step_id' => 'dashboard',
                'title'   => 'Dashboard',
                'widgets' => [
                    [
                        'id'          => 'employee_count',
                        'type'        => 'stat_card',
                        'title'       => 'Total Employees',
                        'icon'        => 'users',
                        'data_source' => '/api/employees?country=USA&per_page=1',
                        'data_path'   => 'meta.total',
                        'channel'     => 'employees.USA',
                        'event'       => 'EmployeeDataUpdated',
                        'refresh_path' => 'meta.total',
                    ],
                    [
                        'id'          => 'average_salary',
                        'type'        => 'stat_card',
                        'title'       => 'Average Salary',
                        'icon'        => 'dollar-sign',
                        'format'      => 'currency',
                        'data_source' => '/api/checklists?country=USA',
                        'data_path'   => null, // computed client-side from employees list
                        'channel'     => 'employees.USA',
                        'event'       => 'EmployeeDataUpdated',
                        'description' => 'Average salary across all US employees',
                    ],
                    [
                        'id'          => 'completion_rate',
                        'type'        => 'progress_card',
                        'title'       => 'Data Completion Rate',
                        'icon'        => 'check-circle',
                        'format'      => 'percentage',
                        'data_source' => '/api/checklists?country=USA',
                        'data_path'   => 'data.summary.overall_completion',
                        'channel'     => 'checklists.USA',
                        'event'       => 'EmployeeDataUpdated',
                    ],
                ],
            ],
            'Germany' => [
                'step_id' => 'dashboard',
                'title'   => 'Dashboard',
                'widgets' => [
                    [
                        'id'          => 'employee_count',
                        'type'        => 'stat_card',
                        'title'       => 'Total Employees',
                        'icon'        => 'users',
                        'data_source' => '/api/employees?country=Germany&per_page=1',
                        'data_path'   => 'meta.total',
                        'channel'     => 'employees.Germany',
                        'event'       => 'EmployeeDataUpdated',
                        'refresh_path' => 'meta.total',
                    ],
                    [
                        'id'          => 'goal_tracking',
                        'type'        => 'list_card',
                        'title'       => 'Goal Tracking',
                        'icon'        => 'target',
                        'data_source' => '/api/employees?country=Germany',
                        'data_path'   => 'data',
                        'item_key'    => 'goal',
                        'item_label'  => 'name',
                        'channel'     => 'employees.Germany',
                        'event'       => 'EmployeeDataUpdated',
                        'description' => 'Current goals for all German employees',
                    ],
                    [
                        'id'          => 'completion_rate',
                        'type'        => 'progress_card',
                        'title'       => 'Data Completion Rate',
                        'icon'        => 'check-circle',
                        'format'      => 'percentage',
                        'data_source' => '/api/checklists?country=Germany',
                        'data_path'   => 'data.summary.overall_completion',
                        'channel'     => 'checklists.Germany',
                        'event'       => 'EmployeeDataUpdated',
                    ],
                ],
            ],
        ],
        'employees' => [
            'USA' => [
                'step_id' => 'employees',
                'title'   => 'Employees',
                'widgets' => [
                    [
                        'id'          => 'employee_table',
                        'type'        => 'data_table',
                        'title'       => 'US Employees',
                        'data_source' => '/api/employees?country=USA',
                        'channel'     => 'employees.USA',
                        'event'       => 'EmployeeDataUpdated',
                        'paginated'   => true,
                    ],
                ],
            ],
            'Germany' => [
                'step_id' => 'employees',
                'title'   => 'Employees',
                'widgets' => [
                    [
                        'id'          => 'employee_table',
                        'type'        => 'data_table',
                        'title'       => 'German Employees',
                        'data_source' => '/api/employees?country=Germany',
                        'channel'     => 'employees.Germany',
                        'event'       => 'EmployeeDataUpdated',
                        'paginated'   => true,
                    ],
                ],
            ],
        ],
        'documentation' => [
            'Germany' => [
                'step_id' => 'documentation',
                'title'   => 'Documentation',
                'widgets' => [
                    [
                        'id'          => 'tax_compliance',
                        'type'        => 'info_card',
                        'title'       => 'Tax Compliance (Steuerliche Compliance)',
                        'icon'        => 'file-text',
                        'content'     => 'German tax ID (Steuer-IdNr.) must be in format DE + 9 digits.',
                        'data_source' => '/api/checklists?country=Germany',
                        'data_path'   => 'data.summary',
                        'channel'     => 'checklists.Germany',
                        'event'       => 'EmployeeDataUpdated',
                    ],
                ],
            ],
        ],
    ];

    public function show(Request $request, string $stepId): JsonResponse
    {
        $request->validate([
            'country' => ['required', 'string', 'in:USA,Germany'],
        ]);

        $country = $request->query('country');

        $schema = $this->schemas[$stepId][$country] ?? null;

        if (! $schema) {
            return response()->json([
                'message' => "No schema found for step '{$stepId}' and country '{$country}'.",
            ], 404);
        }

        return response()->json([
            'country' => $country,
            'schema'  => $schema,
        ]);
    }
}
