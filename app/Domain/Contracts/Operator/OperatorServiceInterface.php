<?php

namespace App\Domain\Contracts\Operator;

/**
 * Агрегирующий интерфейс для сервиса операторов.
 */
interface OperatorServiceInterface extends 
    OperatorManagementInterface,
    OperatorQueueAssignmentInterface,
    ClientServingInterface,
    OperatorAnalyticsInterface
{
    // Этот интерфейс объединяет все интерфейсы, связанные с операторами
}
