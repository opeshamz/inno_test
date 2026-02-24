<?php

namespace Database\Seeders;

use App\Models\ColumnConfig;
use Illuminate\Database\Seeder;

class ColumnConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (ColumnConfig::count() > 0) {
            $this->command->info('Column configs already seeded — skipping.');
            return;
        }

        $configs = [
            [
                'country' => 'USA',
                'columns' => [
                    ['key' => 'name',      'label' => 'First Name', 'sortable' => true],
                    ['key' => 'last_name', 'label' => 'Last Name',  'sortable' => true],
                    ['key' => 'salary',    'label' => 'Salary',     'sortable' => true,  'format' => 'currency'],
                    ['key' => 'ssn',       'label' => 'SSN',        'sortable' => false, 'masked' => true],
                ],
            ],
            [
                'country' => 'Germany',
                'columns' => [
                    ['key' => 'name',      'label' => 'First Name', 'sortable' => true],
                    ['key' => 'last_name', 'label' => 'Last Name',  'sortable' => true],
                    ['key' => 'salary',    'label' => 'Salary',     'sortable' => true,  'format' => 'currency'],
                    ['key' => 'goal',      'label' => 'Goal',       'sortable' => false],
                ],
            ],
        ];

        foreach ($configs as $config) {
            ColumnConfig::create($config);
        }

        $this->command->info('Seeded ' . count($configs) . ' column configs (USA + Germany).');
    }
}
