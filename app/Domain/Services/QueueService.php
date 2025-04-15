<?php

namespace App\Domain\Services;

use App\Domain\Contracts\CacheServiceInterface;
use App\Domain\Contracts\QueueServiceInterface;
use App\Events\ClientAddedToQueue;
use App\Events\ClientCalledFromQueue;
use App\Events\ClientRemovedFromQueue;
use App\Events\QueueStateChanged;
use App\Models\Client;
use App\Models\Queue;
use App\Models\QueuePosition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueueService implements QueueServiceInterface
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
            $this->updateQueueCache($queue);

            DB::commit();

            // Генерируем событие
            event(new ClientAddedToQueue($queue, $client, $position));
            event(new QueueStateChanged($queue, $this->getQueueState($queue)));

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
            $this->updateQueueCache($queue);

            DB::commit();

            // Генерируем событие
            event(new ClientRemovedFromQueue($queue, $client, $position));
            event(new QueueStateChanged($queue, $this->getQueueState($queue)));

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
            $this->updateQueueCache($queue);

            DB::commit();

            // Генерируем событие
            event(new ClientCalledFromQueue($queue, $client, $position));
            event(new QueueStateChanged($queue, $this->getQueueState($queue)));

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
     * {@inheritdoc}
     */
    public function getQueueStats(Queue $queue, string $period = 'day'): array
    {
        // Определяем временной интервал
        $now = Carbon::now();
        $startDate = match($period) {
            'day' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(),
            'month' => $now->copy()->startOfMonth(),
            default => $now->copy()->startOfDay(),
        };

        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . "stats:{$queue->id}:{$period}";
        $cachedStats = $this->cacheService->get($cacheKey);

        if ($cachedStats) {
            return $cachedStats;
        }

        // Если нет в кэше, формируем и кэшируем
        $serviceLogs = DB::table('service_logs')
            ->where('queue_id', $queue->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalClients = $serviceLogs->count();
        $completedServices = $serviceLogs->where('status', 'completed')->count();
        $cancelledServices = $serviceLogs->where('status', 'cancelled')->count();
        $redirectedServices = $serviceLogs->where('status', 'redirected')->count();
        
        $serviceDurations = $serviceLogs
            ->where('status', 'completed')
            ->where('service_duration', '>', 0)
            ->pluck('service_duration')
            ->toArray();
        
        $avgServiceTime = count($serviceDurations) > 0 
            ? array_sum($serviceDurations) / count($serviceDurations) 
            : 0;
        
        $stats = [
            'total_clients' => $totalClients,
            'completed_services' => $completedServices,
            'cancelled_services' => $cancelledServices,
            'redirected_services' => $redirectedServices,
            'avg_service_time' => round($avgServiceTime, 2),
            'avg_service_time_formatted' => $this->formatSeconds($avgServiceTime),
            'completion_rate' => $totalClients > 0 
                ? round(($completedServices / $totalClients) * 100, 2) 
                : 0,
            'period' => $period,
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $now->toIso8601String(),
        ];

        // Кэшируем на более длительное время
        $ttl = match($period) {
            'day' => 3600, // 1 час
            'week' => 3600 * 6, // 6 часов
            'month' => 3600 * 12, // 12 часов
            default => 3600,
        };
        
        $this->cacheService->set($cacheKey, $stats, $ttl);

        return $stats;
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

    /**
     * Кэшировать информацию о очереди.
     *
     * @param Queue $queue Очередь
     * @return void
     */
    private function cacheQueueInfo(Queue $queue): void
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
    private function updateQueueCache(Queue $queue): void
    {
        // Очищаем кэш состояния и следующего клиента
        $this->cacheService->delete(self::CACHE_PREFIX . 'state:' . $queue->id);
        $this->cacheService->delete(self::CACHE_PREFIX . 'next_client:' . $queue->id);
        
        // Обновляем информацию о очереди
        $this->cacheQueueInfo($queue);
    }

    /**
     * Форматировать секунды в читаемый формат.
     *
     * @param int $seconds Секунды
     * @return string
     */
    private function formatSeconds(int $seconds): string
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }
}
