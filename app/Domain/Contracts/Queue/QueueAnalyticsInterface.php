<?php

namespace App\Domain\Contracts\Queue;

use App\Models\Queue;

/**
 * Интерфейс для аналитики очередей.
 */
interface QueueAnalyticsInterface
{
    /**
     * Получить статистику очереди.
     *
     * @param Queue $queue Очередь
     * @param string $period Период (day, week, month)
     * @return array
     */
    public function getQueueStats(Queue $queue, string $period = 'day'): array;
}
