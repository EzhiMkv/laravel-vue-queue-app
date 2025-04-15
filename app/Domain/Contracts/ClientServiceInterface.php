<?php

namespace App\Domain\Contracts;

use App\Models\Client;

interface ClientServiceInterface
{
    /**
     * Создать нового клиента.
     *
     * @param array $data Данные клиента
     * @return Client
     */
    public function createClient(array $data): Client;

    /**
     * Получить клиента по ID.
     *
     * @param int $clientId ID клиента
     * @return Client|null
     */
    public function getClient(int $clientId): ?Client;

    /**
     * Обновить данные клиента.
     *
     * @param int $clientId ID клиента
     * @param array $data Новые данные
     * @return Client|null
     */
    public function updateClient(int $clientId, array $data): ?Client;

    /**
     * Получить список всех клиентов.
     *
     * @param array $filters Фильтры
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getClients(array $filters = []);

    /**
     * Получить историю обслуживания клиента.
     *
     * @param Client $client Клиент
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getClientHistory(Client $client);

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
