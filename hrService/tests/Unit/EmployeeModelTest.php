<?php

namespace Tests\Unit;

use App\Models\Employee;
use Tests\TestCase;

class EmployeeModelTest extends TestCase
{
    public function test_usa_employee_returns_correct_country_fields(): void
    {
        $employee = new Employee([
            'id'        => 1,
            'name'      => 'John',
            'last_name' => 'Doe',
            'salary'    => 75000,
            'country'   => 'USA',
            'ssn'       => '123-45-6789',
            'address'   => '123 Main St, New York, NY',
            'goal'      => null,
            'tax_id'    => null,
        ]);

        $array = $employee->toCountryArray();

        $this->assertArrayHasKey('ssn', $array);
        $this->assertArrayHasKey('address', $array);
        $this->assertArrayNotHasKey('goal', $array);
        $this->assertArrayNotHasKey('tax_id', $array);
        $this->assertEquals('USA', $array['country']);
    }

    public function test_germany_employee_returns_correct_country_fields(): void
    {
        $employee = new Employee([
            'id'        => 2,
            'name'      => 'Hans',
            'last_name' => 'Mueller',
            'salary'    => 65000,
            'country'   => 'Germany',
            'ssn'       => null,
            'address'   => null,
            'goal'      => 'Increase team productivity',
            'tax_id'    => 'DE123456789',
        ]);

        $array = $employee->toCountryArray();

        $this->assertArrayHasKey('goal', $array);
        $this->assertArrayHasKey('tax_id', $array);
        $this->assertArrayNotHasKey('ssn', $array);
        $this->assertArrayNotHasKey('address', $array);
        $this->assertEquals('Germany', $array['country']);
    }

    public function test_unknown_country_returns_base_fields_only(): void
    {
        $employee = new Employee([
            'id'        => 3,
            'name'      => 'Test',
            'last_name' => 'User',
            'salary'    => 50000,
            'country'   => 'Japan',
        ]);

        $array = $employee->toCountryArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('salary', $array);
        $this->assertArrayNotHasKey('ssn', $array);
        $this->assertArrayNotHasKey('goal', $array);
    }
}
