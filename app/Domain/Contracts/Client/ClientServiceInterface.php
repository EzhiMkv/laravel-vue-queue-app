<?php

namespace App\Domain\Contracts\Client;

/**
 * Агрегирующий интерфейс для сервиса клиентов.
 */
interface ClientServiceInterface extends 
    ClientManagementInterface,
    ClientQueueInfoInterface,
    ClientHistoryInterface
{
    // Этот интерфейс объединяет все интерфейсы, связанные с клиентами
}
