<?php

namespace Tests\Unit;

use App\Jobs\PublishEmployeeEvent;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit tests for EmployeeService.
 *
 * Uses Queue::fake() so no RabbitMQ connection is needed.
 * Uses SQLite in-memory (RefreshDatabase) for the DB layer.
 */
class EmployeeServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->service = new EmployeeService();
    }

    // -----------------------------------------------------------------------
    // create()
    // -----------------------------------------------------------------------

    public function test_create_persists_employee_and_publishes_event(): void
    {
        $employee = $this->service->create([
            'name'      => 'John',
            'last_name' => 'Doe',
            'salary'    => 75000,
            'country'   => 'USA',
            'ssn'       => '123-45-6789',
            'address'   => '123 Main St',
        ]);

        $this->assertDatabaseHas('employees', ['name' => 'John', 'country' => 'USA']);
        $this->assertInstanceOf(Employee::class, $employee);

        Queue::assertPushedOn('employee-events', PublishEmployeeEvent::class, function ($job) {
            return $job->payload['event_type'] === 'EmployeeCreated'
                && $job->payload['country'] === 'USA';
        });
    }

    // -----------------------------------------------------------------------
    // update()
    // -----------------------------------------------------------------------

    public function test_update_changes_field_and_publishes_event(): void
    {
        $employee = Employee::create([
            'name' => 'Jane',
            'last_name' => 'Doe',
            'country' => 'USA',
            'salary' => 50000,
        ]);

        $updated = $this->service->update($employee, ['salary' => 90000]);

        $this->assertEquals(90000, $updated->salary);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'salary' => 90000]);

        Queue::assertPushedOn('employee-events', PublishEmployeeEvent::class, function ($job) {
            return $job->payload['event_type'] === 'EmployeeUpdated'
                && in_array('salary', $job->payload['data']['changed_fields']);
        });
    }

    public function test_update_reports_correct_changed_fields(): void
    {
        $employee = Employee::create([
            'name' => 'Hans',
            'last_name' => 'M',
            'country' => 'Germany',
            'salary' => 60000,
            'goal' => 'Old goal',
            'tax_id' => 'DE123456789',
        ]);

        $this->service->update($employee, ['goal' => 'New goal', 'salary' => 65000]);

        Queue::assertPushedOn('employee-events', PublishEmployeeEvent::class, function ($job) {
            $changed = $job->payload['data']['changed_fields'];
            return in_array('goal', $changed) && in_array('salary', $changed);
        });
    }

    // -----------------------------------------------------------------------
    // delete()
    // -----------------------------------------------------------------------

    public function test_delete_removes_record_and_publishes_event(): void
    {
        $employee = Employee::create([
            'name' => 'Tom',
            'last_name' => 'X',
            'country' => 'USA',
            'salary' => 45000,
        ]);

        $id = $employee->id;
        $this->service->delete($employee);

        $this->assertDatabaseMissing('employees', ['id' => $id]);

        Queue::assertPushedOn('employee-events', PublishEmployeeEvent::class, function ($job) use ($id) {
            return $job->payload['event_type'] === 'EmployeeDeleted'
                && $job->payload['data']['employee_id'] === $id;
        });
    }

    // -----------------------------------------------------------------------
    // paginate()
    // -----------------------------------------------------------------------

    public function test_paginate_filters_by_country(): void
    {
        Employee::create(['name' => 'Alice', 'last_name' => 'S', 'country' => 'USA',     'salary' => 1]);
        Employee::create(['name' => 'Hans',  'last_name' => 'M', 'country' => 'Germany', 'salary' => 1]);

        $result = $this->service->paginate('USA');

        $this->assertCount(1, $result->items());
        $this->assertEquals('Alice', $result->items()[0]->name);
    }

    public function test_paginate_without_country_returns_all(): void
    {
        Employee::create(['name' => 'Alice', 'last_name' => 'S', 'country' => 'USA',     'salary' => 1]);
        Employee::create(['name' => 'Hans',  'last_name' => 'M', 'country' => 'Germany', 'salary' => 1]);

        $result = $this->service->paginate(null);

        $this->assertCount(2, $result->items());
    }
}
