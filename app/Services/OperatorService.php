<?php

namespace App\Services;

use App\Domain\Contracts\Operator\OperatorServiceInterface;
use App\Domain\Contracts\Operator\OperatorManagementInterface;
use App\Domain\Contracts\Operator\OperatorQueueAssignmentInterface;
use App\Domain\Contracts\Operator\ClientServingInterface;
use App\Domain\Contracts\Operator\OperatorAnalyticsInterface;
use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Models\Operator;
use App\Models\Queue;
use App\Models\Client;
use App\Models\ServiceLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OperatorService implements 
    OperatorServiceInterface,
    OperatorManagementInterface,
    OperatorQueueAssignmentInterface,
    ClientServingInterface,
    OperatorAnalyticsInterface
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
     * Конструктор сервиса операторов.
     *
     * @param CacheServiceInterface $cacheService
     * @param KafkaProducerService $kafkaProducer
     */
    public function __construct(CacheServiceInterface $cacheService, KafkaProducerService $kafkaProducer)
    {
        $this->cacheService = $cacheService;
        $this->kafkaProducer = $kafkaProducer;
    }
    
    /**
     * Создать нового оператора.
     *
     * @param array $data Данные оператора
     * @return Operator Созданный оператор
     */
    public function createOperator(array $data): Operator
    {
        try {
            DB::beginTransaction();
            
            $operator = new Operator([
                'id' => Str::uuid(),
                'user_id' => $data['user_id'],
                'status' => $data['status'] ?? 'offline',
                'current_queue_id' => $data['current_queue_id'] ?? null,
                'max_clients_per_day' => $data['max_clients_per_day'] ?? 50,
                'skills' => $data['skills'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ]);
            
            $operator->save();
            
            // Кэшируем информацию об операторе
            $this->cacheOperatorInfo($operator);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendOperatorCreatedEvent($operator);
            
            DB::commit();
            return $operator;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании оператора: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Получить оператора по ID.
     *
     * @param string $operatorId ID оператора
     * @return Operator|null Оператор или null, если не найден
     */
    public function getOperator(string $operatorId): ?Operator
    {
        // Сначала проверяем кэш
        $cacheKey = 'operator:' . $operatorId;
        $cachedOperator = $this->cacheService->get($cacheKey);
        
        if ($cachedOperator) {
            return new Operator($cachedOperator);
        }
        
        // Если нет в кэше, получаем из БД
        $operator = Operator::find($operatorId);
        
        if ($operator) {
            // Кэшируем информацию об операторе
            $this->cacheOperatorInfo($operator);
        }
        
        return $operator;
    }
    
    /**
     * Получить список операторов с фильтрацией.
     *
     * @param array $filters Фильтры для выборки
     * @return Collection Коллекция операторов
     */
    public function getOperators(array $filters = []): Collection
    {
        $query = Operator::query();
        
        // Применяем фильтры
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['queue_id'])) {
            $query->where('current_queue_id', $filters['queue_id']);
        }
        
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
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
     * Обновить данные оператора.
     *
     * @param string $operatorId ID оператора
     * @param array $data Новые данные
     * @return Operator|null Обновленный оператор или null, если не найден
     */
    public function updateOperator(string $operatorId, array $data): ?Operator
    {
        try {
            $operator = $this->getOperator($operatorId);
            
            if (!$operator) {
                return null;
            }
            
            DB::beginTransaction();
            
            // Обновляем только те поля, которые переданы
            if (isset($data['status'])) {
                $operator->status = $data['status'];
            }
            
            if (isset($data['current_queue_id'])) {
                $operator->current_queue_id = $data['current_queue_id'];
            }
            
            if (isset($data['max_clients_per_day'])) {
                $operator->max_clients_per_day = $data['max_clients_per_day'];
            }
            
            if (isset($data['skills'])) {
                $operator->skills = $data['skills'];
            }
            
            if (isset($data['metadata'])) {
                $operator->metadata = $data['metadata'];
            }
            
            $operator->save();
            
            // Обновляем кэш
            $this->cacheOperatorInfo($operator);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendOperatorUpdatedEvent($operator);
            
            DB::commit();
            return $operator;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении оператора: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Назначить оператора на очередь.
     *
     * @param Operator $operator Оператор
     * @param Queue $queue Очередь
     * @return bool Успешность операции
     */
    public function assignOperatorToQueue(Operator $operator, Queue $queue): bool
    {
        try {
            // Проверяем, доступен ли оператор
            if ($operator->status === 'offline') {
                throw new \Exception('Оператор не в сети');
            }
            
            DB::beginTransaction();
            
            // Назначаем оператора на очередь
            $operator->current_queue_id = $queue->id;
            $operator->save();
            
            // Обновляем кэш
            $this->cacheOperatorInfo($operator);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendOperatorAssignedToQueueEvent($operator, $queue);
            
            // Публикуем обновление через PubSub
            $this->cacheService->publish('queue_updates', [
                'type' => 'operator_assigned',
                'queue_id' => $queue->id,
                'operator_id' => $operator->id,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при назначении оператора на очередь: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Изменить статус оператора.
     *
     * @param Operator $operator Оператор
     * @param string $status Новый статус
     * @return bool Успешность операции
     */
    public function changeOperatorStatus(Operator $operator, string $status): bool
    {
        try {
            $validStatuses = ['available', 'busy', 'offline'];
            
            if (!in_array($status, $validStatuses)) {
                throw new \InvalidArgumentException('Недопустимый статус оператора: ' . $status);
            }
            
            DB::beginTransaction();
            
            $operator->status = $status;
            $operator->save();
            
            // Обновляем кэш
            $this->cacheOperatorInfo($operator);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendOperatorStatusChangedEvent($operator);
            
            // Если оператор перешел в оффлайн, удаляем его из очереди
            if ($status === 'offline' && $operator->current_queue_id) {
                $operator->current_queue_id = null;
                $operator->save();
                
                // Обновляем кэш еще раз
                $this->cacheOperatorInfo($operator);
            }
            
            // Публикуем обновление через PubSub
            $this->cacheService->publish('operator_updates', [
                'type' => 'operator_status_changed',
                'operator_id' => $operator->id,
                'status' => $status,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при изменении статуса оператора: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Начать обслуживание клиента.
     *
     * @param Operator $operator Оператор
     * @param Client $client Клиент
     * @param Queue $queue Очередь
     * @return ServiceLog|null Лог обслуживания или null в случае ошибки
     */
    public function startServingClient(Operator $operator, Client $client, Queue $queue): ?ServiceLog
    {
        try {
            // Проверяем, доступен ли оператор
            if ($operator->status !== 'available') {
                throw new \Exception('Оператор недоступен для обслуживания клиентов');
            }
            
            // Проверяем, назначен ли оператор на указанную очередь
            if ($operator->current_queue_id !== $queue->id) {
                throw new \Exception('Оператор не назначен на указанную очередь');
            }
            
            DB::beginTransaction();
            
            // Создаем запись о начале обслуживания
            $serviceLog = new ServiceLog([
                'id' => Str::uuid(),
                'operator_id' => $operator->id,
                'client_id' => $client->id,
                'queue_id' => $queue->id,
                'start_time' => now(),
                'status' => 'in_progress',
                'metadata' => [],
            ]);
            
            $serviceLog->save();
            
            // Меняем статус оператора на "занят"
            $operator->status = 'busy';
            $operator->save();
            
            // Обновляем кэш
            $this->cacheOperatorInfo($operator);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendClientServiceStartedEvent($operator, $client, $queue);
            
            // Публикуем обновление через PubSub
            $this->cacheService->publish('service_updates', [
                'type' => 'service_started',
                'operator_id' => $operator->id,
                'client_id' => $client->id,
                'queue_id' => $queue->id,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            DB::commit();
            return $serviceLog;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при начале обслуживания клиента: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Завершить обслуживание клиента.
     *
     * @param Operator $operator Оператор
     * @param ServiceLog $serviceLog Лог обслуживания
     * @param string $status Статус завершения
     * @param array $data Дополнительные данные
     * @return bool Успешность операции
     */
    public function finishServingClient(Operator $operator, ServiceLog $serviceLog, string $status, array $data = []): bool
    {
        try {
            // Проверяем, что это тот же оператор, который начал обслуживание
            if ($serviceLog->operator_id !== $operator->id) {
                throw new \Exception('Оператор не может завершить обслуживание, начатое другим оператором');
            }
            
            // Проверяем, что обслуживание еще не завершено
            if ($serviceLog->status !== 'in_progress') {
                throw new \Exception('Обслуживание уже завершено');
            }
            
            $validStatuses = ['completed', 'cancelled', 'redirected'];
            
            if (!in_array($status, $validStatuses)) {
                throw new \InvalidArgumentException('Недопустимый статус завершения: ' . $status);
            }
            
            DB::beginTransaction();
            
            // Обновляем запись об обслуживании
            $serviceLog->end_time = now();
            $serviceLog->status = $status;
            $serviceLog->duration = $serviceLog->end_time->diffInSeconds($serviceLog->start_time);
            
            if (isset($data['notes'])) {
                $serviceLog->notes = $data['notes'];
            }
            
            if (isset($data['metadata'])) {
                $serviceLog->metadata = $data['metadata'];
            }
            
            $serviceLog->save();
            
            // Меняем статус оператора на "доступен"
            $operator->status = 'available';
            $operator->save();
            
            // Обновляем кэш
            $this->cacheOperatorInfo($operator);
            
            // Сохраняем статистику в кэш
            $this->cacheService->increment('operator:' . $operator->id . ':clients_served');
            $this->cacheService->increment('operator:' . $operator->id . ':total_service_time', $serviceLog->duration);
            
            // Отправляем событие в Kafka
            $this->kafkaProducer->sendClientServiceFinishedEvent($operator, $serviceLog->client, $serviceLog->queue, $status);
            
            // Публикуем обновление через PubSub
            $this->cacheService->publish('service_updates', [
                'type' => 'service_finished',
                'operator_id' => $operator->id,
                'client_id' => $serviceLog->client_id,
                'queue_id' => $serviceLog->queue_id,
                'status' => $status,
                'duration' => $serviceLog->duration,
                'timestamp' => now()->toIso8601String(),
            ]);
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при завершении обслуживания клиента: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получить статистику оператора.
     *
     * @param Operator $operator Оператор
     * @param string $period Период (day, week, month, all)
     * @return array Статистика
     */
    public function getOperatorStats(Operator $operator, string $period = 'day'): array
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
        $clientsServed = $this->cacheService->get('operator:' . $operator->id . ':clients_served') ?? 0;
        $totalServiceTime = $this->cacheService->get('operator:' . $operator->id . ':total_service_time') ?? 0;
        $averageServiceTime = $clientsServed > 0 ? $totalServiceTime / $clientsServed : 0;
        
        // Получаем статистику по периоду из БД
        $periodServiceLogs = ServiceLog::where('operator_id', $operator->id)
            ->where('status', '!=', 'in_progress')
            ->where('end_time', '>=', $startDate)
            ->get();
        
        $periodClientsServed = $periodServiceLogs->count();
        $periodServiceTime = $periodServiceLogs->sum('duration');
        $periodAverageServiceTime = $periodClientsServed > 0 ? $periodServiceTime / $periodClientsServed : 0;
        
        // Получаем распределение по статусам завершения
        $statusDistribution = $periodServiceLogs->groupBy('status')->map->count();
        
        // Получаем текущий статус обслуживания
        $currentService = ServiceLog::where('operator_id', $operator->id)
            ->where('status', 'in_progress')
            ->with('client')
            ->first();
        
        $stats = [
            'clients_served_total' => $clientsServed,
            'clients_served_period' => $periodClientsServed,
            'average_service_time' => round($averageServiceTime),
            'average_service_time_formatted' => $this->formatSeconds($averageServiceTime),
            'period_average_service_time' => round($periodAverageServiceTime),
            'period_average_service_time_formatted' => $this->formatSeconds($periodAverageServiceTime),
            'status_distribution' => $statusDistribution,
            'period' => $period,
            'current_status' => $operator->status,
            'current_queue' => $operator->currentQueue ? [
                'id' => $operator->currentQueue->id,
                'name' => $operator->currentQueue->name,
            ] : null,
            'current_service' => $currentService ? [
                'client_id' => $currentService->client_id,
                'client_name' => $currentService->client->name,
                'start_time' => $currentService->start_time->toIso8601String(),
                'duration_so_far' => now()->diffInSeconds($currentService->start_time),
            ] : null,
        ];
        
        return $stats;
    }
    
    /**
     * Кэширует информацию об операторе.
     *
     * @param Operator $operator Оператор
     * @return void
     */
    protected function cacheOperatorInfo(Operator $operator): void
    {
        $cacheKey = 'operator:' . $operator->id;
        $this->cacheService->set($cacheKey, $operator->toArray(), 3600); // Кэшируем на 1 час
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
