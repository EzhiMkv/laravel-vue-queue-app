<?php

namespace App\Domain\Contracts\Operator;

use App\Models\Operator;
use App\Models\Queue;

/**
 * Интерфейс для назначения операторов на очереди.
 */
interface OperatorQueueAssignmentInterface
{
    /**
     * Назначить оператора на очередь.
     *
     * @param Operator $operator Оператор
     * @param Queue $queue Очередь
     * @return bool
     */
    public function assignOperatorToQueue(Operator $operator, Queue $queue): bool;

    /**
     * Изменить статус оператора.
     *
     * @param Operator $operator Оператор
     * @param string $status Новый статус
     * @return bool
     */
    public function changeOperatorStatus(Operator $operator, string $status): bool;

    /**
     * Получить доступных операторов для очереди.
     *
     * @param Queue $queue Очередь
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableOperatorsForQueue(Queue $queue);
}
