<?php

namespace App\Domain\Contracts\Queue;

use App\Models\Queue;

/**
 * Интерфейс для управления очередями.
 */
interface QueueManagementInterface
{
    /**
     * Создать новую очередь.
     *
     * @param array $data Данные очереди
     * @return Queue
     */
    public function createQueue(array $data): Queue;

    /**
     * Получить очередь по ID.
     *
     * @param string $queueId ID очереди
     * @return Queue|null
     */
    public function getQueue(string $queueId): ?Queue;

    /**
     * Получить список всех активных очередей.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveQueues();

    /**
     * Получить текущее состояние очереди.
     *
     * @param Queue $queue Очередь
     * @return array
     */
    public function getQueueState(Queue $queue): array;
}
