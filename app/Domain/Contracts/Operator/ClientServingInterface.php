<?php

namespace App\Domain\Contracts\Operator;

use App\Models\Client;
use App\Models\Operator;
use App\Models\Queue;
use App\Models\ServiceLog;

/**
 * Интерфейс для обслуживания клиентов операторами.
 */
interface ClientServingInterface
{
    /**
     * Начать обслуживание клиента.
     *
     * @param Operator $operator Оператор
     * @param Client $client Клиент
     * @param Queue $queue Очередь
     * @return ServiceLog
     */
    public function startServingClient(Operator $operator, Client $client, Queue $queue): ServiceLog;

    /**
     * Завершить обслуживание клиента.
     *
     * @param Operator $operator Оператор
     * @param ServiceLog $serviceLog Лог обслуживания
     * @param string $status Статус завершения
     * @param array $data Дополнительные данные
     * @return bool
     */
    public function finishServingClient(Operator $operator, ServiceLog $serviceLog, string $status = 'completed', array $data = []): bool;
}
