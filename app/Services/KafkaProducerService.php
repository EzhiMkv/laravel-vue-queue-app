<?php

namespace App\Services;

use App\Models\Client;
use App\Models\QueuePosition;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\Message;
use Throwable;

class KafkaProducerService
{
    /**
     * Отправляет событие о добавлении клиента в Kafka
     */
    public function sendClientAddedEvent(Client $client, QueuePosition $position): void
    {
        $this->sendMessage(
            config('kafka.topics.queue_events'),
            [
                'event_type' => 'client_added',
                'timestamp' => now()->toIso8601String(),
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                ],
                'position' => $position->position,
            ]
        );
    }
    
    /**
     * Отправляет событие об удалении клиента в Kafka
     */
    public function sendClientRemovedEvent(Client $client, int $position): void
    {
        $this->sendMessage(
            config('kafka.topics.queue_events'),
            [
                'event_type' => 'client_removed',
                'timestamp' => now()->toIso8601String(),
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                ],
                'position' => $position,
            ]
        );
    }
    
    /**
     * Отправляет событие о продвижении очереди в Kafka
     */
    public function sendQueueProceededEvent(Client $client = null): void
    {
        $this->sendMessage(
            config('kafka.topics.queue_events'),
            [
                'event_type' => 'queue_proceeded',
                'timestamp' => now()->toIso8601String(),
                'client' => $client ? [
                    'id' => $client->id,
                    'name' => $client->name,
                ] : null,
                'new_queue_length' => QueuePosition::count(),
            ]
        );
    }
    
    /**
     * Отправляет сообщение в Kafka
     */
    public function sendMessage(string $topic, array $data): void
    {
        try {
            $message = new Message(
                body: $data,
                key: (string) ($data['client']['id'] ?? now()->timestamp),
                headers: [
                    'source' => 'queue-app',
                    'environment' => app()->environment(),
                ]
            );
            
            Kafka::publishOn($topic)
                ->withMessage($message)
                ->withDebugEnabled(app()->isLocal())
                ->send();
                
            Log::info("Сообщение отправлено в Kafka", [
                'topic' => $topic,
                'event_type' => $data['event_type'] ?? 'unknown',
            ]);
        } catch (Throwable $e) {
            Log::error("Ошибка отправки сообщения в Kafka", [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
} 