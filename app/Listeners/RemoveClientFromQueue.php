<?php

namespace App\Listeners;

use App\Events\ClientCreated;
use App\Events\ClientDestroyed;
use App\Models\QueuePosition;
use App\Services\QueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RemoveClientFromQueue
{
    protected QueueService $queueService;
    /**
     * Create the event listener.
     */
    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Handle the event.
     */
    public function handle(ClientDestroyed $event): void
    {
        $this->queueService->removeClientFromQueue($event->client);
    }
}
