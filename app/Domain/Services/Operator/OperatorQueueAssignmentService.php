<?php

namespace App\Domain\Services\Operator;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Operator\OperatorQueueAssignmentInterface;
use App\Events\OperatorAssignedToQueue;
use App\Events\OperatorStatusChanged;
use App\Models\Operator;
use App\Models\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperatorQueueAssignmentService implements OperatorQueueAssignmentInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

    /**
     * @var OperatorManagementService
     */
    private $managementService;

    /**
     * Префикс для ключей кэша.
     */
    private const CACHE_PREFIX = 'operator:queue:';

    /**
     * Конструктор сервиса.
     *
     * @param CacheServiceInterface $cacheService
     * @param OperatorManagementService $managementService
     */
    public function __construct(
        CacheServiceInterface $cacheService,
        OperatorManagementService $managementService
    ) {
        $this->cacheService = $cacheService;
        $this->managementService = $managementService;
    }

    /**
     * {@inheritdoc}
     */
    public function assignOperatorToQueue(Operator $operator, Queue $queue): bool
    {
        try {
            DB::beginTransaction();

            $operator->current_queue_id = $queue->id;
            $operator->save();

            // Обновляем кэш
            $this->managementService->cacheOperatorInfo($operator);
            $this->clearQueueOperatorsCache($queue->id);

            DB::commit();

            // Генерируем событие
            event(new OperatorAssignedToQueue($operator, $queue));

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при назначении оператора на очередь: ' . $e->getMessage(), [
                'operator_id' => $operator->id,
                'queue_id' => $queue->id,
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function changeOperatorStatus(Operator $operator, string $status): bool
    {
        try {
            DB::beginTransaction();

            $oldStatus = $operator->status;
            $operator->status = $status;
            $operator->save();

            // Обновляем кэш
            $this->managementService->cacheOperatorInfo($operator);
            if ($operator->current_queue_id) {
                $this->clearQueueOperatorsCache($operator->current_queue_id);
            }

            DB::commit();

            // Генерируем событие
            event(new OperatorStatusChanged($operator, $oldStatus, $status));

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при изменении статуса оператора: ' . $e->getMessage(), [
                'operator_id' => $operator->id,
                'status' => $status,
                'exception' => $e
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableOperatorsForQueue(Queue $queue)
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'available:' . $queue->id;
        $cachedOperators = $this->cacheService->get($cacheKey);

        if ($cachedOperators) {
            return collect($cachedOperators)->map(function ($data) {
                return new Operator($data);
            });
        }

        // Если нет в кэше, получаем из БД
        $operators = Operator::where('status', 'available')
            ->where(function ($query) use ($queue) {
                $query->where('current_queue_id', $queue->id)
                      ->orWhereNull('current_queue_id');
            })
            ->where(function ($query) {
                $query->where('max_clients_per_day', 0)
                      ->orWhereRaw('clients_served_today < max_clients_per_day');
            })
            ->with('user')
            ->get();

        // Кэшируем на короткое время
        $this->cacheService->set($cacheKey, $operators->toArray(), 60); // 1 минута

        return $operators;
    }

    /**
     * Очистить кэш операторов для очереди.
     *
     * @param string $queueId ID очереди
     * @return void
     */
    private function clearQueueOperatorsCache(string $queueId): void
    {
        $this->cacheService->delete(self::CACHE_PREFIX . 'available:' . $queueId);
    }
}
