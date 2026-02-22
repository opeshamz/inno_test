<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * This job is dispatched by the HR Service and consumed by the HubService.
 * It carries a structured event payload describing an employee data change.
 */
class PublishEmployeeEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(public readonly array $payload) {}

    /**
     * When the HR Service dispatches this job onto the RabbitMQ queue,
     * the HubService worker picks it up and calls this handle() method.
     * The HR Service itself never executes handle() locally — the job is
     * forwarded to the shared "employee-events" queue.
     */
    public function handle(): void
    {
        // No-op on the publisher side; the HubService overrides this.
        Log::info('[HR Service] Employee event dispatched', $this->payload);
    }
}
