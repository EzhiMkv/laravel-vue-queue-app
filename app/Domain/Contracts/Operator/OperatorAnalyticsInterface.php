<?php

namespace App\Domain\Contracts\Operator;

use App\Models\Operator;

/**
 * Интерфейс для аналитики операторов.
 */
interface OperatorAnalyticsInterface
{
    /**
     * Получить статистику оператора.
     *
     * @param Operator $operator Оператор
     * @param string $period Период (day, week, month)
     * @return array
     */
    public function getOperatorStats(Operator $operator, string $period = 'day'): array;
}
