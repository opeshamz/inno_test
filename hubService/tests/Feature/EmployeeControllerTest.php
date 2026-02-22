<?php

namespace Tests\Feature;

use App\Services\HrApiService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function mockHrApi(string $country, array $employees): void
    {
        $this->mock(HrApiService::class, function ($mock) use ($country, $employees) {
            $mock->shouldReceive('getEmployeesPaginated')
                 ->with($country, 1, 15)
                 ->once()
                 ->andReturn([
                     'data' => $employees,
                     'meta' => ['total' => count($employees), 'last_page' => 1, 'current_page' => 1],
                 ]);
        });
    }

    public function test_requires_country_param(): void
    {
        $this->getJson('/api/employees')
             ->assertStatus(422)
             ->assertJsonValidationErrors(['country']);
    }

    public function test_usa_employees_include_column_definitions(): void
    {
        $this->mockHrApi('USA', [
            ['id' => 1, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789'],
        ]);

        $response = $this->getJson('/api/employees?country=USA');
        $response->assertOk();

        $columns = $response->json('columns');
        $keys    = array_column($columns, 'key');

        $this->assertContains('name', $keys);
        $this->assertContains('salary', $keys);
        $this->assertContains('ssn', $keys);
        $this->assertNotContains('goal', $keys);
    }

    public function test_germany_employees_include_goal_column(): void
    {
        $this->mockHrApi('Germany', [
            ['id' => 2, 'name' => 'Hans', 'last_name' => 'M', 'salary' => 65000, 'country' => 'Germany', 'goal' => 'productivity'],
        ]);

        $response = $this->getJson('/api/employees?country=Germany');
        $columns  = $response->json('columns');
        $keys     = array_column($columns, 'key');

        $this->assertContains('goal', $keys);
        $this->assertNotContains('ssn', $keys);
    }

    public function test_usa_ssn_is_masked(): void
    {
        $this->mockHrApi('USA', [
            ['id' => 1, 'name' => 'John', 'last_name' => 'Doe', 'salary' => 75000, 'country' => 'USA', 'ssn' => '123-45-6789'],
        ]);

        $response = $this->getJson('/api/employees?country=USA');
        $ssn      = $response->json('data.0.ssn');

        // Original: 123-45-6789 → masked: ***-**-6789
        $this->assertStringNotContainsString('123', $ssn ?? '');
    }

    public function test_response_includes_real_time_channel(): void
    {
        $this->mockHrApi('USA', []);

        $response = $this->getJson('/api/employees?country=USA');
        $response->assertJsonPath('channels.employees', 'employees.USA');
    }
}
