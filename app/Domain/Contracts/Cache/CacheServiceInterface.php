<?php

namespace App\Domain\Contracts\Cache;

/**
 * Агрегирующий интерфейс для сервиса кэширования.
 */
interface CacheServiceInterface extends 
    BasicCacheInterface,
    CounterCacheInterface,
    CollectionCacheInterface,
    PubSubInterface,
    CacheMetricsInterface
{
    // Этот интерфейс объединяет все интерфейсы, связанные с кэшированием
}
