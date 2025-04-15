<?php

namespace App\Listeners;

use App\Models\Client;
use App\Models\QueuePosition;
use App\Services\QueueService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\KafkaConsumerMessage;

class ProcessQueueEvent implements ShouldQueue
{
    use InteractsWithQueue;

    protected QueueService $queueService;

    /**
     * Create the event listener.
     */
    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Handle the Kafka event.
     */
    public function handle(KafkaConsumerMessage $message): void
    {
        $body = $message->getBody();
        $eventType = $body['event_type'] ?? 'unknown';
        
        Log::info("Обработка события Kafka: {$eventType}", [
            'topic' => $message->getTopicName(),
            'body' => $body,
        ]);
        
        // Обработка в зависимости от типа события
        match ($eventType) {
            'client_added' => $this->handleClientAdded($body),
            'client_removed' => $this->handleClientRemoved($body),
            'queue_proceeded' => $this->handleQueueProceeded($body),
            default => Log::warning("Неизвестный тип события: {$eventType}", ['body' => $body])
        };
    }
    
    /**
     * Обрабатывает событие добавления клиента в очередь
     */
    private function handleClientAdded(array $data): void
    {
        $clientId = $data['client']['id'] ?? null;
        $position = $data['position'] ?? null;
        
        if (!$clientId || !$position) {
            Log::warning('Некорректные данные события client_added', $data);
            return;
        }
        
        Log::info("Клиент {$clientId} добавлен в очередь на позицию {$position}");
        
        // Здесь можно добавить дополнительную логику обработки
        // Например, отправка уведомления или обновление данных
    }
    
    /**
     * Обрабатывает событие удаления клиента из очереди
     */
    private function handleClientRemoved(array $data): void
    {
        $clientId = $data['client']['id'] ?? null;
        $position = $data['position'] ?? null;
        
        if (!$clientId) {
            Log::warning('Некорректные данные события client_removed', $data);
            return;
        }
        
        Log::info("Клиент {$clientId} удален из очереди с позиции {$position}");
        
        // Здесь можно добавить дополнительную логику обработки
    }
    
    /**
     * Обрабатывает событие продвижения очереди
     */
    private function handleQueueProceeded(array $data): void
    {
        $newQueueLength = $data['new_queue_length'] ?? null;
        $clientInfo = $data['client'] ?? null;
        
        if ($clientInfo) {
            $clientId = $clientInfo['id'] ?? 'неизвестно';
            Log::info("Очередь продвинулась. Обслужен клиент {$clientId}. Новая длина очереди: {$newQueueLength}");
        } else {
            Log::info("Очередь продвинулась. Новая длина очереди: {$newQueueLength}");
        }
        
        // Здесь можно добавить обновление UI или другую логику
    }
} 