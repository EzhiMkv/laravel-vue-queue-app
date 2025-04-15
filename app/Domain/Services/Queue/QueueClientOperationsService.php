<?php

namespace App\Domain\Services\Queue;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Queue\QueueClientOperationsInterface;
use App\Events\ClientAddedToQueue;
use App\Events\ClientCalledFromQueue;
use App\Events\ClientRemovedFromQueue;
use App\Events\QueueStateChanged;
use App\Models\Client;
use App\Models\Queue;
use App\Models\QueuePosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueClientOperationsService implements QueueClientOperationsInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

    /**
     * @var QueueManagementService
     */
    private $queueManagementService;

    /**
     * Префикс для ключей кэша.
     */
    private const CACHE_PREFIX = 'queue:';

    /**
     * Время жизни кэша в секундах.
     */
    private const CACHE_TTL = 3600; // 1 час

    /**
     * Конструктор сервиса.
     *
     * @param CacheServiceInterface $cacheService
     * @param QueueManagementService $queueManagementService
     */
    public function __construct(
        CacheServiceInterface $cacheService,
        QueueManagementService $queueManagementService
    ) {
        $this->cacheService = $cacheService;
        $this->queueManagementService = $queueManagementService;
    }

    /**
     * {@inheritdoc}
     */
    public function addClientToQueue(Queue $queue, Client $client, string $priority = 'normal'): QueuePosition
    {
        try {
            DB::beginTransaction();

            // Проверяем, не находится ли клиент уже в этой очереди
            $existingPosition = $client->getPositionInQueue($queue);
            if ($existingPosition) {
                throw new \Exception("Клиент уже находится в очереди");
            }

            // Проверяем, не превышен ли лимит клиентов
            $clientCount = $queue->getClientCount();
            if ($clientCount >= $queue->max_clients) {
                throw new \Exception("Превышен лимит клиентов в очереди");
            }

            // Определяем позицию клиента
            $lastPosition = QueuePosition::where('queue_id', $queue->id)
                ->whereIn('status', ['waiting', 'called'])
                ->max('position') ?? 0;

            $position = new QueuePosition([
                'queue_id' => $queue->id,
                'client_id' => $client->id,
                'position' => $lastPosition + 1,
                'priority' => $priority,
                'estimated_wait_time' => $this->calculateEstimatedWaitTime($queue, $lastPosition + 1, $priority),
                'status' => 'waiting'
            ]);

            $position->save();

            // Обновляем статус клиента
            $client->status = 'waiting';
            $client->save();

            // Обновляем кэш
            $this->queueManagementService->updateQueueCache($queue);

            DB::commit();

            // Генерируем событие
            event(new ClientAddedToQueue($queue, $client, $position));
            event(new QueueStateChanged($queue, $this->queueManagementService->getQueueState($queue)));

            return $position;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при добавлении клиента в очередь: ' . $e->getMessage(), [
                'queue_id' => $queue->id,
                'client_id' => $client->id,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeClientFromQueue(Queue $queue, Client $client): bool
    {
        try {
            DB::beginTransaction();

            $position = $client->getPositionInQueue($queue);
            if (!$position) {
                throw new \Exception("Клиент не найден в очереди");
            }

            // Обновляем статусы
            $position->status = 'cancelled';
            $position->save();

            $client->status = 'cancelled';
            $client->save();

            // Обновляем позиции других клиентов
            QueuePosition::where('queue_id', $queue->id)
                ->where('position', '>', $position->position)
                ->whereIn('status', ['waiting', 'called'])
                ->decrement('position');

            // Обновляем кэш
            $this->queueManagementService->updateQueueCache($queue);

            DB::commit();

            // Генерируем событие
            event(new ClientRemovedFromQueue($queue, $client, $position));
            event(new QueueStateChanged($queue, $this->queueManagementService->getQueueState($queue)));

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при удалении клиента из очереди: ' . $e->getMessage(), [
                'queue_id' => $queue->id,
                'client_id' => $client->id,
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNextClient(Queue $queue): ?Client
    {
        // Получаем из кэша
        $cacheKey = self::CACHE_PREFIX . 'next_client:' . $queue->id;
        $cachedClientId = $this->cacheService->get($cacheKey);

        if ($cachedClientId) {
            return Client::find($cachedClientId);
        }

        // Если нет в кэше, получаем из БД
        $position = $this->getNextPosition($queue);
        if (!$position) {
            return null;
        }

        $client = $position->client;

        // Кэшируем
        $this->cacheService->set($cacheKey, $client->id, self::CACHE_TTL);

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function callNextClient(Queue $queue): ?QueuePosition
    {
        try {
            DB::beginTransaction();

            $position = $this->getNextPosition($queue);
            if (!$position) {
                return null;
            }

            // Обновляем статус позиции
            $position->status = 'called';
            $position->called_at = now();
            $position->save();

            // Обновляем статус клиента
            $client = $position->client;
            $client->status = 'called';
            $client->save();

            // Обновляем кэш
            $this->queueManagementService->updateQueueCache($queue);

            DB::commit();

            // Генерируем событие
            event(new ClientCalledFromQueue($queue, $client, $position));
            event(new QueueStateChanged($queue, $this->queueManagementService->getQueueState($queue)));

            return $position;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при вызове клиента из очереди: ' . $e->getMessage(), [
                'queue_id' => $queue->id,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * Получить следующую позицию в очереди.
     *
     * @param Queue $queue Очередь
     * @return QueuePosition|null
     */
    private function getNextPosition(Queue $queue): ?QueuePosition
    {
        return QueuePosition::where('queue_id', $queue->id)
            ->where('status', 'waiting')
            ->orderBy('priority', 'desc') // Сначала высокоприоритетные
            ->orderBy('position', 'asc')
            ->first();
    }

    /**
     * Рассчитать ожидаемое время ожидания.
     *
     * @param Queue $queue Очередь
     * @param int $position Позиция
     * @param string $priority Приоритет
     * @return int
     */
    private function calculateEstimatedWaitTime(Queue $queue, int $position, string $priority): int
    {
        // Базовое время - позиция * среднее время обслуживания
        $baseTime = $position * $queue->estimated_service_time;
        
        // Корректируем в зависимости от приоритета
        $priorityMultiplier = match($priority) {
            'low' => 1.5, // Низкий приоритет ждет дольше
            'high' => 0.7, // Высокий приоритет ждет меньше
            'vip' => 0.5, // VIP ждет еще меньше
            default => 1.0, // Нормальный приоритет
        };
        
        return (int) ($baseTime * $priorityMultiplier);
    }
}
