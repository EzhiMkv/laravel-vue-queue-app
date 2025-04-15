<?php

namespace App\Domain\Services\Client;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Client\ClientManagementInterface;
use App\Events\ClientCreated;
use App\Events\ClientUpdated;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientManagementService implements ClientManagementInterface
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
     * Кэшировать информацию о клиенте.
     *
     * @param Client $client Клиент
     * @return void
     */
    public function cacheClientInfo(Client $client): void
    {
        $cacheKey = self::CACHE_PREFIX . 'info:' . $client->id;
        $this->cacheService->set($cacheKey, $client->toArray(), self::CACHE_TTL);

        // Очищаем связанные кэши
        $this->cacheService->delete(self::CACHE_PREFIX . 'positions:' . $client->id);
        $this->cacheService->delete(self::CACHE_PREFIX . 'history:' . $client->id);
    }
}
