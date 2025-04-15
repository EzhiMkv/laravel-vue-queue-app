<?php

namespace App\Domain\Contracts\Cache;

/**
 * Интерфейс для получения метрик кэша.
 */
interface CacheMetricsInterface
{
    /**
     * Получить метрики кэша.
     *
     * @return array
     */
    public function getMetrics(): array;
}
