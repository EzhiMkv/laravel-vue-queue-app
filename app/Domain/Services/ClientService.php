<?php

namespace App\Domain\Services;

use App\Domain\Contracts\CacheServiceInterface;
use App\Domain\Contracts\ClientServiceInterface;
use App\Events\ClientCreated;
use App\Events\ClientUpdated;
use App\Models\Client;
use App\Models\QueuePosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientService implements ClientServiceInterface
{
    /**
     * @var CacheServiceInterface
     */
    private $cacheService;

    /**
     * Префикс для ключей кэша.
     */
    private const CACHE_PREFIX = 'client:';

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
    public function createClient(array $data): Client
    {
        try {
            DB::beginTransaction();

            $client = new Client([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $data['status'] ?? 'waiting',
                'metadata' => $data['metadata'] ?? null,
            ]);

            $client->save();

            // Кэшируем информацию о новом клиенте
            $this->cacheClientInfo($client);

            DB::commit();

            // Генерируем событие
            event(new ClientCreated($client));

            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании клиента: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(int $clientId): ?Client
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'info:' . $clientId;
        $cachedClient = $this->cacheService->get($cacheKey);

        if ($cachedClient) {
            return new Client($cachedClient);
        }

        // Если нет в кэше, получаем из БД
        $client = Client::find($clientId);

        if ($client) {
            $this->cacheClientInfo($client);
        }

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function updateClient(int $clientId, array $data): ?Client
    {
        try {
            DB::beginTransaction();

            $client = Client::find($clientId);
            if (!$client) {
                return null;
            }

            $client->fill($data);
            $client->save();

            // Обновляем кэш
            $this->cacheClientInfo($client);

            DB::commit();

            // Генерируем событие
            event(new ClientUpdated($client));

            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении клиента: ' . $e->getMessage(), [
                'client_id' => $clientId,
                'data' => $data,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClients(array $filters = [])
    {
        $query = Client::query();

        // Применяем фильтры
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
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
     * {@inheritdoc}
     */
    public function getClientHistory(Client $client)
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'history:' . $client->id;
        $cachedHistory = $this->cacheService->get($cacheKey);

        if ($cachedHistory) {
            return new Collection($cachedHistory);
        }

        // Если нет в кэше, получаем из БД
        $history = $client->serviceLogs()
            ->with(['queue', 'operator'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Кэшируем на короткое время
        $this->cacheService->set($cacheKey, $history->toArray(), 600); // 10 минут

        return $history;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientPositions(Client $client): array
    {
        // Пытаемся получить из кэша
        $cacheKey = self::CACHE_PREFIX . 'positions:' . $client->id;
        $cachedPositions = $this->cacheService->get($cacheKey);

        if ($cachedPositions) {
            return $cachedPositions;
        }

        // Если нет в кэше, получаем из БД
        $positions = $client->positions()
            ->with('queue')
            ->whereIn('status', ['waiting', 'called'])
            ->get()
            ->map(function ($position) {
                return [
                    'id' => $position->id,
                    'queue' => [
                        'id' => $position->queue->id,
                        'name' => $position->queue->name,
                    ],
                    'position' => $position->position,
                    'status' => $position->status,
                    'priority' => $position->priority,
                    'estimated_wait_time' => $position->estimated_wait_time,
                    'formatted_wait_time' => $position->getFormattedWaitTime(),
                    'created_at' => $position->created_at->toIso8601String(),
                    'called_at' => $position->called_at ? $position->called_at->toIso8601String() : null,
                ];
            })
            ->toArray();

        // Кэшируем на короткое время
        $this->cacheService->set($cacheKey, $positions, 60); // 1 минута

        return $positions;
    }

    /**
     * {@inheritdoc}
     */
    public function isClientInQueue(Client $client, string $queueId): bool
    {
        return $client->positions()
            ->where('queue_id', $queueId)
            ->whereIn('status', ['waiting', 'called'])
            ->exists();
    }

    /**
     * Кэшировать информацию о клиенте.
     *
     * @param Client $client Клиент
     * @return void
     */
    private function cacheClientInfo(Client $client): void
    {
        $cacheKey = self::CACHE_PREFIX . 'info:' . $client->id;
        $this->cacheService->set($cacheKey, $client->toArray(), self::CACHE_TTL);

        // Очищаем связанные кэши
        $this->cacheService->delete(self::CACHE_PREFIX . 'positions:' . $client->id);
        $this->cacheService->delete(self::CACHE_PREFIX . 'history:' . $client->id);
    }
}
