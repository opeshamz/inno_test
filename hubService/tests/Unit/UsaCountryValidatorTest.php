<?php

namespace Tests\Unit;

use App\Validators\UsaCountryValidator;
use Tests\TestCase;

class UsaCountryValidatorTest extends TestCase
{
    private UsaCountryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new UsaCountryValidator();
    }

    public function test_required_fields_are_correct(): void
    {
        $this->assertEquals(['ssn', 'salary', 'address'], $this->validator->requiredFields());
    }

    public function test_complete_usa_employee_passes_all_checks(): void
    {
        $employee = [
            'id'        => 1,
            'name'      => 'John',
            'last_name' => 'Doe',
            'salary'    => 75000,
            'country'   => 'USA',
            'ssn'       => '123-45-6789',
            'address'   => '123 Main St, NY',
        ];

        $result = $this->validator->validate($employee);

        $this->assertTrue($result['ssn']['complete']);
        $this->assertTrue($result['salary']['complete']);
        $this->assertTrue($result['address']['complete']);
    }

    public function test_missing_ssn_fails(): void
    {
        $employee = ['id' => 1, 'salary' => 75000, 'address' => '123 Main St', 'ssn' => null, 'country' => 'USA'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['ssn']['complete']);
    }

    public function test_zero_salary_fails(): void
    {
        $employee = ['id' => 1, 'salary' => 0, 'address' => '123 Main St', 'ssn' => '123-45', 'country' => 'USA'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['salary']['complete']);
    }

    public function test_empty_address_fails(): void
    {
        $employee = ['id' => 1, 'salary' => 50000, 'address' => '', 'ssn' => '123-45', 'country' => 'USA'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['address']['complete']);
    }

    public function test_null_salary_fails(): void
    {
        $employee = ['id' => 1, 'salary' => null, 'address' => '123 Main', 'ssn' => '123', 'country' => 'USA'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['salary']['complete']);
    }
}
