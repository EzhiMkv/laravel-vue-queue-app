<?php

namespace App\Domain\Contracts\Client;

use App\Models\Client;

/**
 * Интерфейс для получения истории обслуживания клиента.
 */
interface ClientHistoryInterface
{
    /**
     * Получить историю обслуживания клиента.
     *
     * @param Client $client Клиент
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getClientHistory(Client $client);
}
