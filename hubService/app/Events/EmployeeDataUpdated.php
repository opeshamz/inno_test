<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when employee data changes.
 *
 * WebSocket channel strategy:
 *   Public:  employees.{COUNTRY}        – country-level feed
 *   Public:  checklists.{COUNTRY}       – checklist updates per country
 *   Public:  employees.{COUNTRY}.{ID}   – per-employee detail channel
 */
class EmployeeDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $eventType,
        public readonly string $country,
        public readonly int|string|null $employeeId,
        public readonly array $payload,
        public readonly array $checklistSummary = []
    ) {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel("employees.{$this->country}"),
            new Channel("checklists.{$this->country}"),
        ];

        if ($this->employeeId) {
            $channels[] = new Channel("employees.{$this->country}.{$this->employeeId}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'EmployeeDataUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type'       => $this->eventType,
            'employee_id'      => $this->employeeId,
            'country'          => $this->country,
            'employee'         => $this->payload,
            'checklist_summary' => $this->checklistSummary,
            'timestamp'        => now()->toIso8601String(),
        ];
    }
}
