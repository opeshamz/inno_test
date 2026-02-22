<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Prevent duplicate seeding
        if (Employee::count() > 0) {
            return;
        }

        $employees = [
            // USA employees
            [
                'name'      => 'John',
                'last_name' => 'Doe',
                'salary'    => 75000.00,
                'country'   => 'USA',
                'ssn'       => '123-45-6789',
                'address'   => '123 Main St, New York, NY',
            ],
            [
                'name'      => 'Sarah',
                'last_name' => 'Connor',
                'salary'    => 85000.00,
                'country'   => 'USA',
                'ssn'       => '987-65-4321',
                'address'   => '456 Oak Ave, Los Angeles, CA',
            ],
            [
                'name'      => 'Mike',
                'last_name' => 'Johnson',
                'salary'    => 0, // intentionally incomplete — salary check
                'country'   => 'USA',
                'ssn'       => null, // intentionally incomplete — SSN missing
                'address'   => '789 Pine Rd, Chicago, IL',
            ],

            // Germany employees
            [
                'name'      => 'Hans',
                'last_name' => 'Mueller',
                'salary'    => 65000.00,
                'country'   => 'Germany',
                'goal'      => 'Increase team productivity by 20%',
                'tax_id'    => 'DE123456789',
            ],
            [
                'name'      => 'Greta',
                'last_name' => 'Schmidt',
                'salary'    => 72000.00,
                'country'   => 'Germany',
                'goal'      => 'Lead digital transformation project',
                'tax_id'    => 'DE987654321',
            ],
            [
                'name'      => 'Klaus',
                'last_name' => 'Weber',
                'salary'    => 58000.00,
                'country'   => 'Germany',
                'goal'      => null, // intentionally incomplete
                'tax_id'    => 'INVALID-ID', // intentionally incomplete — bad format
            ],
        ];

        foreach ($employees as $data) {
            Employee::create($data);
        }

        $this->command->info('Seeded ' . count($employees) . ' employees (USA + Germany with intentional incomplete records).');
    }
}
