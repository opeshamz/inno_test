<?php

namespace Database\Seeders;

use App\Models\StepSchema;
use Illuminate\Database\Seeder;

class StepSchemaSeeder extends Seeder
{
    public function run(): void
    {
        if (StepSchema::count() > 0) {
            $this->command->info('Step schemas already seeded — skipping.');
            return;
        }

        $schemas = [
            // ----------------------------------------------------------------
            // DASHBOARD — USA
            // PRD: Employee count, Average salary, Completion rate widgets
            // ----------------------------------------------------------------
            [
                'step_id' => 'dashboard',
                'country' => 'USA',
                'title'   => 'Dashboard',
                'widgets' => [
                    [
                        'id'           => 'employee_count',
                        'type'         => 'stat_card',
                        'title'        => 'Total Employees',
                        'icon'         => 'users',
                        'data_source'  => '/api/employees?country=USA&per_page=1',
                        'data_path'    => 'meta.total',
                        'channel'      => 'employees.USA',
                        'event'        => 'EmployeeDataUpdated',
                        'refresh_path' => 'meta.total',
                    ],
                    [
                        'id'          => 'average_salary',
                        'type'        => 'stat_card',
                        'title'       => 'Average Salary',
                        'icon'        => 'dollar-sign',
                        'format'      => 'currency',
                        'data_source' => '/api/employees?country=USA',
                        'data_path'   => null,
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

            // ----------------------------------------------------------------
            // DASHBOARD — Germany
            // PRD: Employee count, Goal tracking widgets
            // ----------------------------------------------------------------
            [
                'step_id' => 'dashboard',
                'country' => 'Germany',
                'title'   => 'Dashboard',
                'widgets' => [
                    [
                        'id'           => 'employee_count',
                        'type'         => 'stat_card',
                        'title'        => 'Total Employees',
                        'icon'         => 'users',
                        'data_source'  => '/api/employees?country=Germany&per_page=1',
                        'data_path'    => 'meta.total',
                        'channel'      => 'employees.Germany',
                        'event'        => 'EmployeeDataUpdated',
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
                ],
            ],

            // ----------------------------------------------------------------
            // EMPLOYEES — USA
            // ----------------------------------------------------------------
            [
                'step_id' => 'employees',
                'country' => 'USA',
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

            // ----------------------------------------------------------------
            // EMPLOYEES — Germany
            // ----------------------------------------------------------------
            [
                'step_id' => 'employees',
                'country' => 'Germany',
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

            // ----------------------------------------------------------------
            // DOCUMENTATION — Germany only (PRD step)
            // ----------------------------------------------------------------
            [
                'step_id' => 'documentation',
                'country' => 'Germany',
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
        ];

        foreach ($schemas as $schema) {
            StepSchema::create($schema);
        }

        $this->command->info('Seeded ' . count($schemas) . ' step schemas.');
    }
}
