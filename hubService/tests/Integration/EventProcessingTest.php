<?php

namespace Tests\Integration;

use App\Events\EmployeeDataUpdated;
use App\Jobs\PublishEmployeeEvent;
use App\Services\CacheService;
use App\Services\ChecklistService;
use App\Services\HrApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Integration test: simulates the full event pipeline.
 *
 * Flow: RabbitMQ event arrives → PublishEmployeeEvent::handle()
 *       → cache invalidation → checklist rebuild → WebSocket broadcast
 */
class EventProcessingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function makePayload(string $eventType = 'EmployeeUpdated'): array
    {
        return [
            'event_type' => $eventType,
            'event_id'   => 'test-uuid-1234',
            'timestamp'  => now()->toIso8601String(),
            'country'    => 'USA',
            'data'       => [
                'employee_id'    => 1,
                'changed_fields' => ['salary'],
                'employee'       => [
                    'id'        => 1,
                    'name'      => 'John',
                    'last_name' => 'Doe',
                    'salary'    => 80000,
                    'ssn'       => '123-45-6789',
                    'address'   => '123 Main St',
                    'country'   => 'USA',
                ],
            ],
        ];
    }

    public function test_event_processing_invalidates_cache_and_rebuilds(): void
    {
        // Pre-populate cache
        Cache::put('checklist:USA', ['summary' => ['stale' => true]], 300);

        // Mock HR API to return fresh employees
        $this->mock(HrApiService::class, function ($mock) {
            $mock->shouldReceive('getEmployeesByCountry')
                 ->with('USA')
                 ->once()
                 ->andReturn([[
                     'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
                     'salary' => 80000, 'country' => 'USA',
                     'ssn' => '123-45-6789', 'address' => '123 Main St',
                 ]]);
        });

        Event::fake();

        $job = new PublishEmployeeEvent($this->makePayload('EmployeeUpdated'));
        $job->handle(
            app(CacheService::class),
            app(ChecklistService::class),
            app(HrApiService::class)
        );

        // Cache should now contain fresh checklist (not the stale one)
        $cached = Cache::get('checklist:USA');
        $this->assertNotNull($cached);
        $this->assertArrayNotHasKey('stale', $cached['summary'] ?? []);
    }

    public function test_event_processing_broadcasts_websocket_event(): void
    {
        Event::fake();

        $this->mock(HrApiService::class, function ($mock) {
            $mock->shouldReceive('getEmployeesByCountry')
                 ->andReturn([[
                     'id' => 1, 'name' => 'John', 'last_name' => 'Doe',
                     'salary' => 80000, 'country' => 'USA',
                     'ssn' => '123-45-6789', 'address' => '123 Main St',
                 ]]);
        });

        $job = new PublishEmployeeEvent($this->makePayload('EmployeeUpdated'));
        $job->handle(
            app(CacheService::class),
            app(ChecklistService::class),
            app(HrApiService::class)
        );

        Event::assertDispatched(EmployeeDataUpdated::class, function ($event) {
            return $event->eventType === 'EmployeeUpdated'
                && $event->country === 'USA'
                && $event->employeeId === 1;
        });
    }

    public function test_event_with_missing_country_does_not_crash(): void
    {
        $payload = $this->makePayload();
        unset($payload['country']);

        $job = new PublishEmployeeEvent($payload);

        // Should return gracefully without throwing
        $job->handle(
            app(CacheService::class),
            app(ChecklistService::class),
            app(HrApiService::class)
        );

        $this->assertTrue(true); // no exception thrown
    }

    public function test_employee_created_event_builds_checklist(): void
    {
        Event::fake();

        $this->mock(HrApiService::class, function ($mock) {
            $mock->shouldReceive('getEmployeesByCountry')
                 ->with('Germany')
                 ->andReturn([[
                     'id' => 3, 'name' => 'Hans', 'last_name' => 'M',
                     'salary' => 65000, 'country' => 'Germany',
                     'goal' => 'productivity', 'tax_id' => 'DE123456789',
                 ]]);
        });

        $payload = [
            'event_type' => 'EmployeeCreated',
            'country'    => 'Germany',
            'data'       => [
                'employee_id' => 3,
                'employee'    => ['id' => 3, 'name' => 'Hans', 'last_name' => 'M', 'country' => 'Germany'],
            ],
        ];

        $job = new PublishEmployeeEvent($payload);
        $job->handle(app(CacheService::class), app(ChecklistService::class), app(HrApiService::class));

        $cached = Cache::get('checklist:Germany');
        $this->assertNotNull($cached);
        $this->assertEquals(1, $cached['summary']['total_employees']);

        Event::assertDispatched(EmployeeDataUpdated::class);
    }
}
