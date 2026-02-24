<?php

namespace Database\Seeders;

use App\Models\Step;
use Illuminate\Database\Seeder;

class StepSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent — skip if already seeded
        if (Step::count() > 0) {
            $this->command->info('Steps already seeded — skipping.');
            return;
        }

        $steps = [
            // ----- USA -----
            ['country' => 'USA', 'step_id' => 'dashboard',  'label' => 'Dashboard',  'icon' => 'home',      'path' => '/dashboard',  'order' => 1],
            ['country' => 'USA', 'step_id' => 'employees',  'label' => 'Employees',  'icon' => 'users',     'path' => '/employees',  'order' => 2],

            // ----- Germany -----
            ['country' => 'Germany', 'step_id' => 'dashboard',     'label' => 'Dashboard',     'icon' => 'home',      'path' => '/dashboard',     'order' => 1],
            ['country' => 'Germany', 'step_id' => 'employees',     'label' => 'Employees',     'icon' => 'users',     'path' => '/employees',     'order' => 2],
            ['country' => 'Germany', 'step_id' => 'documentation', 'label' => 'Documentation', 'icon' => 'file-text', 'path' => '/documentation', 'order' => 3],
        ];

        foreach ($steps as $step) {
            Step::create($step);
        }

        $this->command->info('Seeded ' . count($steps) . ' steps (USA + Germany).');
    }
}
