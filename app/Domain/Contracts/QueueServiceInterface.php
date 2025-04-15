<?php

namespace App\Domain\Contracts;

use App\Models\Client;
use App\Models\Queue;
use App\Models\QueuePosition;

interface QueueServiceInterface
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
     * Добавить клиента в очередь.
     *
     * @param Queue $queue Очередь
     * @param Client $client Клиент
     * @param string $priority Приоритет (low, normal, high, vip)
     * @return QueuePosition
     */
    public function addClientToQueue(Queue $queue, Client $client, string $priority = 'normal'): QueuePosition;

    /**
     * Удалить клиента из очереди.
     *
     * @param Queue $queue Очередь
     * @param Client $client Клиент
     * @return bool
     */
    public function removeClientFromQueue(Queue $queue, Client $client): bool;

    /**
     * Получить следующего клиента в очереди.
     *
     * @param Queue $queue Очередь
     * @return Client|null
     */
    public function getNextClient(Queue $queue): ?Client;

    /**
     * Вызвать следующего клиента в очереди.
     *
     * @param Queue $queue Очередь
     * @return QueuePosition|null
     */
    public function callNextClient(Queue $queue): ?QueuePosition;

    /**
     * Получить текущее состояние очереди.
     *
     * @param Queue $queue Очередь
     * @return array
     */
    public function getQueueState(Queue $queue): array;

    /**
     * Получить статистику очереди.
     *
     * @param Queue $queue Очередь
     * @param string $period Период (day, week, month)
     * @return array
     */
    public function getQueueStats(Queue $queue, string $period = 'day'): array;
}
