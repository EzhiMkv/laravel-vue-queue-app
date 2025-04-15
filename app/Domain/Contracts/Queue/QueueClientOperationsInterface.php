<?php

namespace App\Domain\Contracts\Queue;

use App\Models\Client;
use App\Models\Queue;
use App\Models\QueuePosition;

/**
 * Интерфейс для операций с клиентами в очереди.
 */
interface QueueClientOperationsInterface
{
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
}
