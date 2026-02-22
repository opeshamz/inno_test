<?php

namespace Tests\Feature;

use App\Services\ChecklistService;
use App\Services\HrApiService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChecklistControllerTest extends TestCase
{
    private array $usaEmployees = [
        [
            'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
            'salary' => 75000, 'country' => 'USA',
            'ssn' => '123-45-6789', 'address' => '123 Main St',
        ],
        [
            'id' => 2, 'name' => 'Mike', 'last_name' => 'J',
            'salary' => 0, 'country' => 'USA',
            'ssn' => null, 'address' => 'Some Ave',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_checklist_requires_country_param(): void
    {
        $this->getJson('/api/checklists')
             ->assertStatus(422)
             ->assertJsonValidationErrors(['country']);
    }

    public function test_checklist_rejects_invalid_country(): void
    {
        $this->getJson('/api/checklists?country=INVALID')
             ->assertStatus(422)
             ->assertJsonValidationErrors(['country']);
    }

    public function test_checklist_returns_report_for_usa(): void
    {
        $this->mock(HrApiService::class, function ($mock) {
            $mock->shouldReceive('getEmployeesByCountry')
                 ->with('USA')
                 ->once()
                 ->andReturn($this->usaEmployees);
        });

        $response = $this->getJson('/api/checklists?country=USA');

        $response->assertOk()
                 ->assertJsonStructure([
                     'country',
                     'data' => [
                         'summary' => [
                             'total_employees',
                             'overall_completion',
                             'total_fields',
                             'completed_fields',
                         ],
                         'employees',
                     ],
                 ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_employees']);
        $this->assertEquals('USA', $response->json('country'));
    }

    public function test_checklist_is_cached_on_second_call(): void
    {
        $this->mock(HrApiService::class, function ($mock) {
            // HR API should only be called ONCE; second call uses cache
            $mock->shouldReceive('getEmployeesByCountry')
                 ->with('Germany')
                 ->once()
                 ->andReturn([[
                     'id' => 1, 'name' => 'Hans', 'last_name' => 'M',
                     'salary' => 65000, 'country' => 'Germany',
                     'goal' => 'A goal', 'tax_id' => 'DE123456789',
                 ]]);
        });

        $this->getJson('/api/checklists?country=Germany')->assertOk();
        $this->getJson('/api/checklists?country=Germany')->assertOk(); // second call — should hit cache
    }
}
