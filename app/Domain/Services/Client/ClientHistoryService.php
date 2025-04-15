<?php

namespace App\Domain\Services\Client;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Client\ClientHistoryInterface;
use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

class ClientHistoryService implements ClientHistoryInterface
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
}
