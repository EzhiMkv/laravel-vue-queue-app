<?php

namespace App\Domain\Services\Queue;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Queue\QueueManagementInterface;
use App\Models\Queue;
use App\Models\QueuePosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueManagementService implements QueueManagementInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

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
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * {@inheritdoc}
     */
    public function createQueue(array $data): Queue
    {
        try {
            DB::beginTransaction();

            $queue = new Queue([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'standard',
                'status' => $data['status'] ?? 'active',
                'max_clients' => $data['max_clients'] ?? 100,
                'estimated_service_time' => $data['estimated_service_time'] ?? 300,
            ]);

            $queue->save();

            // Кэшируем информацию о новой очереди
            $this->cacheQueueInfo($queue);

            DB::commit();

            return $queue;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании очереди: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getQueue(string $queueId): ?Queue
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'info:' . $queueId;
        $cachedQueue = $this->cacheService->get($cacheKey);

        if ($cachedQueue) {
            return new Queue($cachedQueue);
        }

        // Если нет в кэше, получаем из БД
        $queue = Queue::find($queueId);

        if ($queue) {
            $this->cacheQueueInfo($queue);
        }

        return $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveQueues()
    {
        return Queue::where('status', 'active')->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getQueueState(Queue $queue): array
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'state:' . $queue->id;
        $cachedState = $this->cacheService->get($cacheKey);

        if ($cachedState) {
            return $cachedState;
        }

        // Если нет в кэше, формируем и кэшируем
        $positions = QueuePosition::where('queue_id', $queue->id)
            ->whereIn('status', ['waiting', 'called'])
            ->orderBy('priority', 'desc')
            ->orderBy('position', 'asc')
            ->with('client')
            ->get();

        $state = [
            'id' => $queue->id,
            'name' => $queue->name,
            'status' => $queue->status,
            'client_count' => $positions->count(),
            'positions' => $positions->map(function ($position) {
                return [
                    'id' => $position->id,
                    'position' => $position->position,
                    'client' => [
                        'id' => $position->client->id,
                        'name' => $position->client->name,
                    ],
                    'status' => $position->status,
                    'priority' => $position->priority,
                    'estimated_wait_time' => $position->estimated_wait_time,
                    'formatted_wait_time' => $position->getFormattedWaitTime(),
                    'created_at' => $position->created_at->toIso8601String(),
                    'called_at' => $position->called_at ? $position->called_at->toIso8601String() : null,
                ];
            })->values()->toArray(),
            'updated_at' => now()->toIso8601String(),
        ];

        // Кэшируем на короткое время
        $this->cacheService->set($cacheKey, $state, 60); // 1 минута

        return $state;
    }

    /**
     * Кэшировать информацию о очереди.
     *
     * @param Queue $queue Очередь
     * @return void
     */
    protected function cacheQueueInfo(Queue $queue): void
    {
        $cacheKey = self::CACHE_PREFIX . 'info:' . $queue->id;
        $this->cacheService->set($cacheKey, $queue->toArray(), self::CACHE_TTL);
    }

    /**
     * Обновить кэш очереди.
     *
     * @param Queue $queue Очередь
     * @return void
     */
    public function updateQueueCache(Queue $queue): void
    {
        // Очищаем кэш состояния и следующего клиента
        $this->cacheService->delete(self::CACHE_PREFIX . 'state:' . $queue->id);
        $this->cacheService->delete(self::CACHE_PREFIX . 'next_client:' . $queue->id);
        
        // Обновляем информацию о очереди
        $this->cacheQueueInfo($queue);
    }
}
