<?php

namespace App\Domain\Services\Operator;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Operator\OperatorManagementInterface;
use App\Events\OperatorCreated;
use App\Events\OperatorUpdated;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperatorManagementService implements OperatorManagementInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

    /**
     * Префикс для ключей кэша.
     */
    private const CACHE_PREFIX = 'operator:';

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
    public function createOperator(array $data): Operator
    {
        try {
            DB::beginTransaction();

            $operator = new Operator([
                'user_id' => $data['user_id'],
                'status' => $data['status'] ?? 'offline',
                'current_queue_id' => $data['current_queue_id'] ?? null,
                'max_clients_per_day' => $data['max_clients_per_day'] ?? 0,
                'clients_served_today' => $data['clients_served_today'] ?? 0,
                'skills' => $data['skills'] ?? null,
            ]);

            $operator->save();

            // Кэшируем информацию о новом операторе
            $this->cacheOperatorInfo($operator);

            DB::commit();

            // Генерируем событие
            event(new OperatorCreated($operator));

            return $operator;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании оператора: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOperator(string $operatorId): ?Operator
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'info:' . $operatorId;
        $cachedOperator = $this->cacheService->get($cacheKey);

        if ($cachedOperator) {
            return new Operator($cachedOperator);
        }

        // Если нет в кэше, получаем из БД
        $operator = Operator::find($operatorId);

        if ($operator) {
            $this->cacheOperatorInfo($operator);
        }

        return $operator;
    }

    /**
     * {@inheritdoc}
     */
    public function updateOperator(string $operatorId, array $data): ?Operator
    {
        try {
            DB::beginTransaction();

            $operator = Operator::find($operatorId);
            if (!$operator) {
                return null;
            }

            $operator->fill($data);
            $operator->save();

            // Обновляем кэш
            $this->cacheOperatorInfo($operator);

            DB::commit();

            // Генерируем событие
            event(new OperatorUpdated($operator));

            return $operator;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении оператора: ' . $e->getMessage(), [
                'operator_id' => $operatorId,
                'data' => $data,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOperators(array $filters = [])
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
     * Кэшировать информацию об операторе.
     *
     * @param Operator $operator Оператор
     * @return void
     */
    public function cacheOperatorInfo(Operator $operator): void
    {
        $cacheKey = self::CACHE_PREFIX . 'info:' . $operator->id;
        $this->cacheService->set($cacheKey, $operator->toArray(), self::CACHE_TTL);

        // Очищаем связанные кэши
        $this->cacheService->delete(self::CACHE_PREFIX . 'stats:' . $operator->id);
    }
}
