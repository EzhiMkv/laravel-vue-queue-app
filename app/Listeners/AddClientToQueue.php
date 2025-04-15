<?php

namespace App\Listeners;

use App\Events\ClientCreated;
use App\Models\QueuePosition;
use App\Services\KafkaProducerService;
use App\Services\QueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AddClientToQueue implements ShouldQueue
{
    protected QueueService $queueService;
    protected KafkaProducerService $kafkaProducer;
    
    /**
     * Create the event listener.
     */
    public function __construct(QueueService $queueService, KafkaProducerService $kafkaProducer)
    {
        $this->queueService = $queueService;
        $this->kafkaProducer = $kafkaProducer;
    }

    /**
     * Handle the event.
     */
    public function handle(ClientCreated $event): void
    {
        $this->queueService->addClientToQueue($event->client);
        
        // Отправляем событие в Kafka после добавления в очередь
        $position = $event->client->position;
        if ($position) {
            $this->kafkaProducer->sendClientAddedEvent($event->client, $position);
        }
    }
}
