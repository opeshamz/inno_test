<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // CREATE
    // -----------------------------------------------------------------------

    public function test_can_create_usa_employee(): void
    {
        $response = $this->postJson('/api/employees', [
            'name'      => 'John',
            'last_name' => 'Doe',
            'salary'    => 75000,
            'country'   => 'USA',
            'ssn'       => '123-45-6789',
            'address'   => '123 Main St, New York, NY',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'John'])
            ->assertJsonFragment(['country' => 'USA'])
            ->assertJsonFragment(['ssn' => '123-45-6789']);

        $this->assertDatabaseHas('employees', ['name' => 'John', 'country' => 'USA']);
    }

    public function test_can_create_germany_employee(): void
    {
        $response = $this->postJson('/api/employees', [
            'name'      => 'Hans',
            'last_name' => 'Mueller',
            'salary'    => 65000,
            'country'   => 'Germany',
            'goal'      => 'Increase team productivity by 20%',
            'tax_id'    => 'DE123456789',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Hans'])
            ->assertJsonFragment(['country' => 'Germany']);

        $this->assertDatabaseHas('employees', ['name' => 'Hans', 'country' => 'Germany']);
    }

    public function test_create_fails_with_invalid_country(): void
    {
        $this->postJson('/api/employees', [
            'name'      => 'Test',
            'last_name' => 'User',
            'country'   => 'INVALID',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['country']);
    }

    public function test_create_fails_with_invalid_german_tax_id(): void
    {
        $this->postJson('/api/employees', [
            'name'      => 'Klaus',
            'last_name' => 'Weber',
            'country'   => 'Germany',
            'tax_id'    => 'INVALID',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tax_id']);
    }

    // -----------------------------------------------------------------------
    // READ
    // -----------------------------------------------------------------------

    public function test_can_list_employees_filtered_by_country(): void
    {
        Employee::create(['name' => 'Alice', 'last_name' => 'Smith', 'country' => 'USA',    'salary' => 60000, 'ssn' => '111-22-3333', 'address' => 'NY']);
        Employee::create(['name' => 'Hans',  'last_name' => 'M',    'country' => 'Germany', 'salary' => 55000, 'tax_id' => 'DE111222333', 'goal' => 'goal']);

        $response = $this->getJson('/api/employees?country=USA');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $emp) {
            $this->assertEquals('USA', $emp['country']);
        }
    }

    public function test_can_show_single_employee(): void
    {
        $employee = Employee::create(['name' => 'Bob', 'last_name' => 'Jones', 'country' => 'USA', 'salary' => 50000]);

        $this->getJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Bob']);
    }

    // -----------------------------------------------------------------------
    // UPDATE
    // -----------------------------------------------------------------------

    public function test_can_update_employee_salary(): void
    {
        $employee = Employee::create(['name' => 'Jane', 'last_name' => 'Doe', 'country' => 'USA', 'salary' => 50000]);

        $this->putJson("/api/employees/{$employee->id}", ['salary' => 80000])
            ->assertOk()
            ->assertJsonFragment(['salary' => 80000.0]);
    }

    // -----------------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------------

    public function test_can_delete_employee(): void
    {
        $employee = Employee::create(['name' => 'Tom', 'last_name' => 'X', 'country' => 'USA', 'salary' => 45000]);

        $this->deleteJson("/api/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Employee deleted successfully.']);

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }
}
