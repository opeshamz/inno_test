<?php

namespace Tests\Unit;

use App\Validators\GermanyCountryValidator;
use Tests\TestCase;

class GermanyCountryValidatorTest extends TestCase
{
    private GermanyCountryValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new GermanyCountryValidator();
    }

    public function test_required_fields_are_correct(): void
    {
        $this->assertEquals(['salary', 'goal', 'tax_id'], $this->validator->requiredFields());
    }

    public function test_complete_germany_employee_passes_all_checks(): void
    {
        $employee = [
            'id'        => 2,
            'name'      => 'Hans',
            'last_name' => 'Mueller',
            'salary'    => 65000,
            'country'   => 'Germany',
            'goal'      => 'Increase team productivity by 20%',
            'tax_id'    => 'DE123456789',
        ];

        $result = $this->validator->validate($employee);

        $this->assertTrue($result['salary']['complete']);
        $this->assertTrue($result['goal']['complete']);
        $this->assertTrue($result['tax_id']['complete']);
    }

    public function test_invalid_tax_id_format_fails(): void
    {
        $employee = ['id' => 2, 'salary' => 50000, 'goal' => 'A goal', 'tax_id' => 'INVALID', 'country' => 'Germany'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['tax_id']['complete']);
    }

    public function test_tax_id_with_wrong_digit_count_fails(): void
    {
        // DE + only 8 digits (should be 9)
        $employee = ['id' => 2, 'salary' => 50000, 'goal' => 'goal', 'tax_id' => 'DE12345678', 'country' => 'Germany'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['tax_id']['complete']);
    }

    public function test_tax_id_with_exactly_nine_digits_passes(): void
    {
        $employee = ['id' => 2, 'salary' => 50000, 'goal' => 'goal', 'tax_id' => 'DE123456789', 'country' => 'Germany'];
        $result   = $this->validator->validate($employee);
        $this->assertTrue($result['tax_id']['complete']);
    }

    public function test_empty_goal_fails(): void
    {
        $employee = ['id' => 2, 'salary' => 50000, 'goal' => '', 'tax_id' => 'DE123456789', 'country' => 'Germany'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['goal']['complete']);
    }

    public function test_zero_salary_fails(): void
    {
        $employee = ['id' => 2, 'salary' => 0, 'goal' => 'goal', 'tax_id' => 'DE123456789', 'country' => 'Germany'];
        $result   = $this->validator->validate($employee);
        $this->assertFalse($result['salary']['complete']);
    }
}
