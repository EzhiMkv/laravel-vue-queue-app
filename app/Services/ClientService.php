<?php

namespace App\Services;

use App\Domain\Contracts\Client\ClientServiceInterface;
use App\Domain\Contracts\Client\ClientManagementInterface;
use App\Domain\Contracts\Client\ClientQueueInfoInterface;
use App\Domain\Contracts\Client\ClientHistoryInterface;
use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Models\Client;
use App\Models\QueuePosition;
use App\Models\ServiceLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClientService implements 
    ClientServiceInterface,
    ClientManagementInterface,
    ClientQueueInfoInterface,
    ClientHistoryInterface
{
    /**
     * @var CacheServiceInterface
     */
    protected $cacheService;
    
    /**
     * Конструктор сервиса клиентов.
     *
     * @param CacheServiceInterface $cacheService
     */
    public function __construct(CacheServiceInterface $cacheService)
    {
        $this->cacheService = $cacheService;
    }
    
    /**
     * Создать нового клиента.
     *
     * @param array $data Данные клиента
     * @return Client Созданный клиент
     */
    public function createClient(array $data): Client
    {
        try {
            DB::beginTransaction();
            
            $client = new Client([
                'id' => Str::uuid(),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => 'waiting',
                'metadata' => $data['metadata'] ?? [],
            ]);
            
            $client->save();
            
            // Кэшируем информацию о клиенте
            $this->cacheClientInfo($client);
            
            DB::commit();
            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при создании клиента: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Получить клиента по ID.
     *
     * @param int $clientId ID клиента
     * @return Client|null Клиент или null, если не найден
     */
    public function getClient(int $clientId): ?Client
    {
        // Сначала проверяем кэш
        $cacheKey = 'client:' . $clientId;
        $cachedClient = $this->cacheService->get($cacheKey);
        
        if ($cachedClient) {
            return new Client($cachedClient);
        }
        
        // Если нет в кэше, получаем из БД
        $client = Client::find($clientId);
        
        if ($client) {
            // Кэшируем информацию о клиенте
            $this->cacheClientInfo($client);
        }
        
        return $client;
    }
    
    /**
     * Получить список клиентов с фильтрацией.
     *
     * @param array $filters Фильтры для выборки
     * @return Collection Коллекция клиентов
     */
    public function getClients(array $filters = []): Collection
    {
        $query = Client::query();
        
        // Применяем фильтры
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('phone', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
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
     * Обновить данные клиента.
     *
     * @param int $clientId ID клиента
     * @param array $data Новые данные
     * @return Client|null Обновленный клиент или null, если не найден
     */
    public function updateClient(int $clientId, array $data): ?Client
    {
        try {
            $client = $this->getClient($clientId);
            
            if (!$client) {
                return null;
            }
            
            DB::beginTransaction();
            
            // Обновляем только те поля, которые переданы
            if (isset($data['name'])) {
                $client->name = $data['name'];
            }
            
            if (isset($data['phone'])) {
                $client->phone = $data['phone'];
            }
            
            if (isset($data['email'])) {
                $client->email = $data['email'];
            }
            
            if (isset($data['status'])) {
                $client->status = $data['status'];
            }
            
            if (isset($data['metadata'])) {
                $client->metadata = $data['metadata'];
            }
            
            $client->save();
            
            // Обновляем кэш
            $this->cacheClientInfo($client);
            
            DB::commit();
            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка при обновлении клиента: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Получить позиции клиента во всех очередях.
     *
     * @param Client $client Клиент
     * @return Collection Коллекция позиций в очередях
     */
    public function getClientPositions(Client $client): Collection
    {
        return QueuePosition::with('queue')
            ->where('client_id', $client->id)
            ->where('status', 'waiting')
            ->get();
    }
    
    /**
     * Проверить, находится ли клиент в очереди.
     *
     * @param Client $client Клиент
     * @param string $queueId ID очереди
     * @return bool Находится ли клиент в очереди
     */
    public function isClientInQueue(Client $client, string $queueId): bool
    {
        return QueuePosition::where('client_id', $client->id)
            ->where('queue_id', $queueId)
            ->exists();
    }
    
    /**
     * Получить очереди, в которых находится клиент.
     *
     * @param Client $client Клиент
     * @return Collection Коллекция очередей
     */
    public function getClientQueues(Client $client): Collection
    {
        return QueuePosition::with('queue')
            ->where('client_id', $client->id)
            ->get()
            ->pluck('queue');
    }
    
    /**
     * Получить историю обслуживания клиента.
     *
     * @param Client $client Клиент
     * @return Collection Коллекция записей об обслуживании
     */
    public function getClientHistory(Client $client): Collection
    {
        return ServiceLog::with(['operator', 'queue'])
            ->where('client_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Кэширует информацию о клиенте.
     *
     * @param Client $client Клиент
     * @return void
     */
    protected function cacheClientInfo(Client $client): void
    {
        $cacheKey = 'client:' . $client->id;
        $this->cacheService->set($cacheKey, $client->toArray(), 3600); // Кэшируем на 1 час
    }
}
