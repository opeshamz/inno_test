<?php

namespace Tests\Unit;

use App\Services\ChecklistService;
use Tests\TestCase;

class ChecklistServiceTest extends TestCase
{
    private ChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChecklistService();
    }

    public function test_report_has_correct_summary_structure(): void
    {
        $employees = [
            [
                'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
                'salary' => 75000, 'country' => 'USA',
                'ssn' => '123-45-6789', 'address' => '123 Main St',
            ],
        ];

        $report = $this->service->buildReport($employees);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('employees', $report);
        $this->assertArrayHasKey('total_employees', $report['summary']);
        $this->assertArrayHasKey('overall_completion', $report['summary']);
        $this->assertEquals(1, $report['summary']['total_employees']);
    }

    public function test_fully_complete_usa_employee_is_100_percent(): void
    {
        $employees = [[
            'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
            'salary' => 75000, 'country' => 'USA',
            'ssn' => '123-45-6789', 'address' => '123 Main St',
        ]];

        $report = $this->service->buildReport($employees);

        $this->assertEquals(100, $report['summary']['overall_completion']);
        $this->assertTrue($report['employees'][0]['is_complete']);
    }

    public function test_incomplete_usa_employee_is_below_100_percent(): void
    {
        $employees = [[
            'id' => 1, 'name' => 'Mike', 'last_name' => 'J',
            'salary' => 0, 'country' => 'USA',
            'ssn' => null, 'address' => '123 Main',
        ]];

        $report = $this->service->buildReport($employees);

        $this->assertLessThan(100, $report['summary']['overall_completion']);
        $this->assertFalse($report['employees'][0]['is_complete']);
    }

    public function test_fully_complete_germany_employee_is_100_percent(): void
    {
        $employees = [[
            'id' => 2, 'name' => 'Hans', 'last_name' => 'Mueller',
            'salary' => 65000, 'country' => 'Germany',
            'goal' => 'Increase productivity', 'tax_id' => 'DE123456789',
        ]];

        $report = $this->service->buildReport($employees);

        $this->assertEquals(100, $report['summary']['overall_completion']);
    }

    public function test_empty_employee_list_returns_zero_completion(): void
    {
        $report = $this->service->buildReport([]);

        $this->assertEquals(0, $report['summary']['overall_completion']);
        $this->assertEquals(0, $report['summary']['total_employees']);
    }

    public function test_mixed_completion_calculates_correct_percentage(): void
    {
        $employees = [
            [
                'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
                'salary' => 75000, 'country' => 'USA',
                'ssn' => '123-45-6789', 'address' => '123 Main St', // all 3 complete
            ],
            [
                'id' => 2, 'name' => 'Mike', 'last_name' => 'J',
                'salary' => 0, 'country' => 'USA',
                'ssn' => null, 'address' => 'Some St', // 1 of 3 complete
            ],
        ];

        $report = $this->service->buildReport($employees);

        // 3 + 1 = 4 completed out of 6 total = 66.67%
        $this->assertEquals(66.67, $report['summary']['overall_completion']);
    }

    public function test_unknown_country_returns_empty_checklist(): void
    {
        $employees = [[
            'id' => 99, 'name' => 'X', 'last_name' => 'Y',
            'salary' => 50000, 'country' => 'Japan',
        ]];

        $report = $this->service->buildReport($employees);

        $this->assertEquals(0, $report['employees'][0]['total_fields']);
        $this->assertFalse($report['employees'][0]['is_complete']);
    }
}
