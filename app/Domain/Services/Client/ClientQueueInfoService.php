<?php

namespace App\Domain\Services\Client;

use App\Domain\Contracts\Cache\CacheServiceInterface;
use App\Domain\Contracts\Client\ClientQueueInfoInterface;
use App\Models\Client;

class ClientQueueInfoService implements ClientQueueInfoInterface
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
}
