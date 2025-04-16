<?php

namespace App\Services;

use App\Domain\Contracts\Queue\QueueServiceInterface;
use App\Domain\Contracts\Queue\QueueManagementInterface;
use App\Domain\Contracts\Queue\QueueClientOperationsInterface;
use App\Domain\Contracts\Queue\QueueAnalyticsInterface;
use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Models\Client;
use App\Models\Queue;
use App\Models\QueuePosition;
use App\Models\Operator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class QueueService implements 
    QueueServiceInterface,
    QueueManagementInterface,
    QueueClientOperationsInterface,
    QueueAnalyticsInterface
{
    /**
     * @var CacheServiceInterface
     */
    protected $cacheService;
    
    /**
     * @var KafkaProducerService
     */
    protected $kafkaProducer;
    
    /**
     * @var Carbon
     */
    protected $startTime;
    
    /**
     * Конструктор сервиса очередей.
     *
     * @param CacheServiceInterface $cacheService
     * @param KafkaProducerService $kafkaProducer
     */
    public function __construct(CacheServiceInterface $cacheService, KafkaProducerService $kafkaProducer)
    {
        $this->cacheService = $cacheService;
        $this->kafkaProducer = $kafkaProducer;
        $this->startTime = now();
    }
    
    /**
     * Создать новую очередь.
     *
     * @param array $data Данные очереди
     * @return Queue Созданная очередь
     */
    public function createQueue(array $data): Queue
    {
        try {
            DB::beginTransaction();
            
            $queue = new Queue([
                'id' => Str::uuid(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'max_clients' => $data['max_clients'] ?? 100,
                'status' => $data['status'] ?? 'active',
                'settings' => $data['settings'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ]);
            
            $queue->save();
            
            // Кэшируем информацию об очереди
            $this->cacheQueueInfo($queue);
            
            // Отправляем событие в Kafka о создании очереди
            $this->kafkaProducer->sendQueueCreatedEvent($queue);
            
            DB::commit();
            return $queue;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании очереди: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Получить очередь по ID.
     *
     * @param string $queueId ID очереди
     * @return Queue|null Очередь или null, если не найдена
     */
    public function getQueue(string $queueId): ?Queue
    {
        // Сначала проверяем кэш
        $cacheKey = 'queue:' . $queueId;
        $cachedQueue = $this->cacheService->get($cacheKey);
        
        if ($cachedQueue) {
            return new Queue($cachedQueue);
        }
        
        // Если нет в кэше, получаем из БД
        $queue = Queue::find($queueId);
        
        if ($queue) {
            // Кэшируем информацию об очереди
            $this->cacheQueueInfo($queue);
        }
        
        return $queue;
    }
    
    /**
     * Получить список очередей с фильтрацией.
     *
     * @param array $filters Фильтры для выборки
     * @return Collection Коллекция очередей
     */
    public function getQueues(array $filters = []): Collection
    {
        $query = Queue::query();
        
        // Применяем фильтры
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        // Сортировка
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);
        
        // Пагинация
        if (isset($filters['per_page'])) {
            return $query->paginate($filters['per_page']);
        }
        
        return $query->get();
    }
    
    /**
     * Обновить данные очереди.
     *
     * @param string $queueId ID очереди
     * @param array $data Новые данные
     * @return Queue|null Обновленная очередь или null, если не найдена
     */
    public function updateQueue(string $queueId, array $data): ?Queue
    {
        try {
            $queue = $this->getQueue($queueId);
            
            if (!$queue) {
                return null;
            }
            
            DB::beginTransaction();
            
            // Обновляем только те поля, которые переданы
            if (isset($data['name'])) {
                $queue->name = $data['name'];
            }
            
            if (isset($data['description'])) {
                $queue->description = $data['description'];
            }
            
            if (isset($data['max_clients'])) {
                $queue->max_clients = $data['max_clients'];
            }
            
            if (isset($data['status'])) {
                $queue->status = $data['status'];
            }
            
            if (isset($data['settings'])) {
                $queue->settings = $data['settings'];
            }
            
            if (isset($data['metadata'])) {
                $queue->metadata = $data['metadata'];
            }
            
            $queue->save();
            
            // Обновляем кэш
            $this->cacheQueueInfo($queue);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendQueueUpdatedEvent($queue);
            
            DB::commit();
            return $queue;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении очереди: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Изменить статус очереди.
     *
     * @param Queue $queue Очередь
     * @param string $status Новый статус
     * @return bool Успешность операции
     */
    public function changeQueueStatus(Queue $queue, string $status): bool
    {
        try {
            $validStatuses = ['active', 'paused', 'closed'];
            
            if (!in_array($status, $validStatuses)) {
                throw new \InvalidArgumentException('Недопустимый статус очереди: ' . $status);
            }
            
            $queue->status = $status;
            $queue->save();
            
            // Обновляем кэш
            $this->cacheQueueInfo($queue);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendQueueStatusChangedEvent($queue);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Ошибка при изменении статуса очереди: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Добавить клиента в очередь.
     *
     * @param Queue $queue Очередь
     * @param Client $client Клиент
     * @param string $priority Приоритет (normal, high, vip)
     * @return QueuePosition|null Позиция в очереди или null в случае ошибки
     */
    public function addClientToQueue(Queue $queue, Client $client, string $priority = 'normal'): ?QueuePosition
    {
        try {
            // Проверяем, не находится ли клиент уже в очереди
            $existingPosition = QueuePosition::where('queue_id', $queue->id)
                ->where('client_id', $client->id)
                ->first();
            
            if ($existingPosition) {
                throw new \Exception('Клиент уже находится в очереди');
            }
            
            // Проверяем, не превышен ли лимит клиентов в очереди
            $currentCount = QueuePosition::where('queue_id', $queue->id)->count();
            
            if ($currentCount >= $queue->max_clients) {
                throw new \Exception('Превышен лимит клиентов в очереди');
            }
            
            DB::beginTransaction();
            
            // Определяем позицию в зависимости от приоритета
            $position = 1;
            
            if ($priority === 'normal') {
                $lastPosition = QueuePosition::where('queue_id', $queue->id)
                    ->orderBy('position', 'desc')
                    ->first();
                
                if ($lastPosition) {
                    $position = $lastPosition->position + 1;
                }
            } else if ($priority === 'high') {
                // Для высокого приоритета вставляем клиента в середину очереди
                $totalPositions = QueuePosition::where('queue_id', $queue->id)->count();
                $position = max(1, (int)($totalPositions / 2));
                
                // Сдвигаем остальных клиентов
                QueuePosition::where('queue_id', $queue->id)
                    ->where('position', '>=', $position)
                    ->increment('position');
            } else if ($priority === 'vip') {
                // VIP клиенты всегда в начале очереди
                QueuePosition::where('queue_id', $queue->id)
                    ->increment('position');
                $position = 1;
            }
            
            // Создаем позицию в очереди
            $queuePosition = new QueuePosition([
                'id' => Str::uuid(),
                'queue_id' => $queue->id,
                'client_id' => $client->id,
                'position' => $position,
                'priority' => $priority,
                'status' => 'waiting',
                'metadata' => [],
            ]);
            
            $queuePosition->save();
            
            // Инкрементируем счетчик клиентов в кэше
            $this->cacheService->increment('queue:' . $queue->id . ':client_count');
            
            // Кэшируем обновленную очередь
            $this->cacheQueueData($queue);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendClientAddedToQueueEvent($queue, $client, $queuePosition);
            
            DB::commit();
            return $queuePosition;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при добавлении клиента в очередь: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Удалить клиента из очереди.
     *
     * @param Queue $queue Очередь
     * @param Client $client Клиент
     * @param string $reason Причина удаления
     * @return bool Успешность операции
     */
    public function removeClientFromQueue(Queue $queue, Client $client, string $reason = 'cancelled'): bool
    {
        try {
            DB::beginTransaction();
            
            $queuePosition = QueuePosition::where('queue_id', $queue->id)
                ->where('client_id', $client->id)
                ->first();
            
            if (!$queuePosition) {
                throw new \Exception('Клиент не найден в очереди');
            }
            
            $position = $queuePosition->position;
            
            // Удаляем позицию
            $queuePosition->delete();
            
            // Сдвигаем позиции остальных клиентов
            QueuePosition::where('queue_id', $queue->id)
                ->where('position', '>', $position)
                ->decrement('position');
            
            // Декрементируем счетчик клиентов в кэше
            $this->cacheService->decrement('queue:' . $queue->id . ':client_count');
            
            // Кэшируем обновленную очередь
            $this->cacheQueueData($queue);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendClientRemovedFromQueueEvent($queue, $client, $reason);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при удалении клиента из очереди: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Вызвать следующего клиента в очереди.
     *
     * @param Queue $queue Очередь
     * @param Operator|null $operator Оператор, вызывающий клиента
     * @return Client|null Следующий клиент или null, если очередь пуста
     */
    public function callNextClient(Queue $queue, ?Operator $operator = null): ?Client
    {
        try {
            DB::beginTransaction();
            
            // Получаем следующего клиента в очереди
            $nextPosition = QueuePosition::where('queue_id', $queue->id)
                ->where('status', 'waiting')
                ->orderBy('position', 'asc')
                ->first();
            
            if (!$nextPosition) {
                return null; // Очередь пуста
            }
            
            $client = $nextPosition->client;
            
            // Обновляем статус позиции
            $nextPosition->status = 'called';
            $nextPosition->called_at = now();
            
            if ($operator) {
                $nextPosition->operator_id = $operator->id;
            }
            
            $nextPosition->save();
            
            // Считаем время ожидания
            $waitTime = $nextPosition->called_at->diffInSeconds($nextPosition->created_at);
            
            // Сохраняем статистику в кэш
            $this->cacheService->set('client:' . $client->id . ':wait_time', $waitTime);
            $this->cacheService->increment('queue:' . $queue->id . ':total_wait_time', $waitTime);
            $this->cacheService->increment('queue:' . $queue->id . ':clients_served');
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendClientCalledEvent($queue, $client, $operator);
            
            // Обновляем таймер обслуживания
            $this->startTime = now();
            
            DB::commit();
            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при вызове следующего клиента: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Получить следующего клиента в очереди без его вызова.
     *
     * @param Queue $queue Очередь
     * @return Client|null Следующий клиент или null, если очередь пуста
     */
    public function getNextClient(Queue $queue): ?Client
    {
        $nextPosition = QueuePosition::where('queue_id', $queue->id)
            ->where('status', 'waiting')
            ->orderBy('position', 'asc')
            ->first();
        
        if (!$nextPosition) {
            return null;
        }
        
        return $nextPosition->client;
    }
    
    /**
     * Получить позицию клиента в очереди.
     *
     * @param Queue $queue Очередь
     * @param Client $client Клиент
     * @return int|null Позиция клиента или null, если клиент не в очереди
     */
    public function getClientPositionInQueue(Queue $queue, Client $client): ?int
    {
        $queuePosition = QueuePosition::where('queue_id', $queue->id)
            ->where('client_id', $client->id)
            ->first();
        
        if (!$queuePosition) {
            return null;
        }
        
        return $queuePosition->position;
    }
    
    /**
     * Получить всех клиентов в очереди.
     *
     * @param Queue $queue Очередь
     * @param array $filters Фильтры
     * @return Collection Коллекция позиций в очереди
     */
    public function getClientsInQueue(Queue $queue, array $filters = []): Collection
    {
        // Сначала проверяем кэш
        $cacheKey = 'queue:' . $queue->id . ':clients';
        $cachedData = $this->cacheService->get($cacheKey);
        
        if ($cachedData && empty($filters)) {
            return collect($cachedData);
        }
        
        // Если нет в кэше или есть фильтры, получаем из БД
        $query = QueuePosition::with('client')
            ->where('queue_id', $queue->id);
        
        // Применяем фильтры
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        // Сортировка
        $query->orderBy('position', 'asc');
        
        $result = $query->get();
        
        // Кэшируем результат, если нет фильтров
        if (empty($filters)) {
            $this->cacheQueueData($queue);
        }
        
        return $result;
    }
    
    /**
     * Получить статистику очереди.
     *
     * @param Queue $queue Очередь
     * @param string $period Период (day, week, month, all)
     * @return array Статистика
     */
    public function getQueueStats(Queue $queue, string $period = 'day'): array
    {
        // Определяем начальную дату для периода
        $startDate = match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'all' => now()->subYears(10), // Практически "всё время"
            default => now()->startOfDay(),
        };
        
        // Получаем базовую статистику из кэша
        $clientsServed = $this->cacheService->get('queue:' . $queue->id . ':clients_served') ?? 0;
        $totalWaitTime = $this->cacheService->get('queue:' . $queue->id . ':total_wait_time') ?? 0;
        $averageWaitTime = $clientsServed > 0 ? $totalWaitTime / $clientsServed : 0;
        
        // Получаем текущее количество клиентов в очереди
        $currentClientsCount = QueuePosition::where('queue_id', $queue->id)
            ->where('status', 'waiting')
            ->count();
        
        // Получаем статистику по периоду из БД
        $periodClientsServed = QueuePosition::where('queue_id', $queue->id)
            ->where('status', 'called')
            ->where('called_at', '>=', $startDate)
            ->count();
        
        $stats = [
            'current_clients_count' => $currentClientsCount,
            'clients_served_total' => $clientsServed,
            'clients_served_period' => $periodClientsServed,
            'average_wait_time' => round($averageWaitTime),
            'average_wait_time_formatted' => $this->formatSeconds($averageWaitTime),
            'period' => $period,
            'operators_active' => Operator::where('current_queue_id', $queue->id)
                ->where('status', 'available')
                ->count(),
            'estimated_wait_time' => $this->calculateEstimatedWaitTime($queue),
            'estimated_wait_time_formatted' => $this->formatSeconds($this->calculateEstimatedWaitTime($queue)),
        ];
        
        return $stats;
    }
    
    /**
     * Кэширует информацию об очереди.
     *
     * @param Queue $queue Очередь
     * @return void
     */
    protected function cacheQueueInfo(Queue $queue): void
    {
        $cacheKey = 'queue:' . $queue->id;
        $this->cacheService->set($cacheKey, $queue->toArray(), 3600); // Кэшируем на 1 час
    }
    
    /**
     * Кэширует данные очереди в Redis.
     *
     * @param Queue $queue Очередь
     * @return Collection Данные очереди
     */
    protected function cacheQueueData(Queue $queue): Collection
    {
        $queueData = QueuePosition::with('client')
            ->where('queue_id', $queue->id)
            ->orderBy('position', 'asc')
            ->get();
        
        $cacheKey = 'queue:' . $queue->id . ':clients';
        $this->cacheService->set($cacheKey, $queueData->toArray(), 3600); // Кэшируем на 1 час
        
        // Публикуем обновление через PubSub
        $this->cacheService->publish('queue_updates', [
            'type' => 'queue_updated',
            'queue_id' => $queue->id,
            'data' => [
                'clients_count' => $queueData->count(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        
        return $queueData;
    }
    
    /**
     * Рассчитывает примерное время ожидания для новых клиентов.
     *
     * @param Queue $queue Очередь
     * @return int Время ожидания в секундах
     */
    protected function calculateEstimatedWaitTime(Queue $queue): int
    {
        // Получаем среднее время обслуживания
        $averageServiceTime = $this->cacheService->get('queue:' . $queue->id . ':average_service_time') ?? 180; // По умолчанию 3 минуты
        
        // Получаем количество активных операторов
        $activeOperators = max(1, Operator::where('current_queue_id', $queue->id)
            ->where('status', 'available')
            ->count());
        
        // Получаем количество ожидающих клиентов
        $waitingClients = QueuePosition::where('queue_id', $queue->id)
            ->where('status', 'waiting')
            ->count();
        
        // Рассчитываем примерное время ожидания
        $estimatedWaitTime = ($waitingClients / $activeOperators) * $averageServiceTime;
        
        return (int) $estimatedWaitTime;
    }
    
    /**
     * Форматирует секунды в читаемый формат времени.
     *
     * @param int $seconds Количество секунд
     * @return string Отформатированное время
     */
    protected function formatSeconds(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;
        
        $result = '';
        
        if ($hours > 0) {
            $result .= $hours . ' ч ';
        }
        
        if ($minutes > 0 || $hours > 0) {
            $result .= $minutes . ' мин ';
        }
        
        $result .= $seconds . ' сек';
        
        return $result;
    }
}
