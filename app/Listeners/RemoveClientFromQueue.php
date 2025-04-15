<?php

namespace App\Listeners;

use App\Events\ClientCreated;
use App\Events\ClientDestroyed;
use App\Models\QueuePosition;
use App\Services\KafkaProducerService;
use App\Services\QueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RemoveClientFromQueue implements ShouldQueue
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
    public function handle(ClientDestroyed $event): void
    {
        // Запоминаем позицию клиента перед удалением
        $position = $event->client->position?->position ?? 0;
        
        $this->queueService->removeClientFromQueue($event->client);
        
        // Отправляем событие в Kafka после удаления из очереди
        $this->kafkaProducer->sendClientRemovedEvent($event->client, $position);
    }
}
