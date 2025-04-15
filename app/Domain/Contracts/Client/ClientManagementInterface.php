<?php

namespace App\Domain\Contracts\Client;

use App\Models\Client;

/**
 * Интерфейс для управления клиентами.
 */
interface ClientManagementInterface
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
}
