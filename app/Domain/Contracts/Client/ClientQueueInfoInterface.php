<?php

namespace App\Domain\Contracts\Client;

use App\Models\Client;

/**
 * Интерфейс для получения информации о клиенте в очередях.
 */
interface ClientQueueInfoInterface
{
    /**
     * Получить текущие позиции клиента во всех очередях.
     *
     * @param Client $client Клиент
     * @return array
     */
    public function getClientPositions(Client $client): array;

    /**
     * Проверить, находится ли клиент в указанной очереди.
     *
     * @param Client $client Клиент
     * @param string $queueId ID очереди
     * @return bool
     */
    public function isClientInQueue(Client $client, string $queueId): bool;
}
