<?php

namespace App\Domain\Contracts\Queue;

/**
 * Агрегирующий интерфейс для сервиса очередей.
 */
interface QueueServiceInterface extends 
    QueueManagementInterface,
    QueueClientOperationsInterface,
    QueueAnalyticsInterface
{
    // Этот интерфейс объединяет все интерфейсы, связанные с очередями
}
