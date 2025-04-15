<?php

namespace App\Domain\Services\Operator;

use App\Domain\Contracts\Operator\OperatorServiceInterface;
use App\Models\Client;
use App\Models\Operator;
use App\Models\Queue;
use App\Models\ServiceLog;

/**
 * Агрегирующий сервис для работы с операторами.
 * Реализует все интерфейсы, связанные с операторами.
 */
class OperatorService implements OperatorServiceInterface
{
    /**
     * @var OperatorManagementService
     */
    private $managementService;

    /**
     * @var OperatorQueueAssignmentService
     */
    private $queueAssignmentService;

    /**
     * @var ClientServingService
     */
    private $clientServingService;

    /**
     * @var OperatorAnalyticsService
     */
    private $analyticsService;

    /**
     * Конструктор сервиса.
     *
     * @param OperatorManagementService $managementService
     * @param OperatorQueueAssignmentService $queueAssignmentService
     * @param ClientServingService $clientServingService
     * @param OperatorAnalyticsService $analyticsService
     */
    public function __construct(
        OperatorManagementService $managementService,
        OperatorQueueAssignmentService $queueAssignmentService,
        ClientServingService $clientServingService,
        OperatorAnalyticsService $analyticsService
    ) {
        $this->managementService = $managementService;
        $this->queueAssignmentService = $queueAssignmentService;
        $this->clientServingService = $clientServingService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * {@inheritdoc}
     */
    public function createOperator(array $data): Operator
    {
        return $this->managementService->createOperator($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperator(string $operatorId): ?Operator
    {
        return $this->managementService->getOperator($operatorId);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOperator(string $operatorId, array $data): ?Operator
    {
        return $this->managementService->updateOperator($operatorId, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperators(array $filters = [])
    {
        return $this->managementService->getOperators($filters);
    }

    /**
     * {@inheritdoc}
     */
    public function assignOperatorToQueue(Operator $operator, Queue $queue): bool
    {
        return $this->queueAssignmentService->assignOperatorToQueue($operator, $queue);
    }

    /**
     * {@inheritdoc}
     */
    public function changeOperatorStatus(Operator $operator, string $status): bool
    {
        return $this->queueAssignmentService->changeOperatorStatus($operator, $status);
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableOperatorsForQueue(Queue $queue)
    {
        return $this->queueAssignmentService->getAvailableOperatorsForQueue($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function startServingClient(Operator $operator, Client $client, Queue $queue): ServiceLog
    {
        return $this->clientServingService->startServingClient($operator, $client, $queue);
    }

    /**
     * {@inheritdoc}
     */
    public function finishServingClient(Operator $operator, ServiceLog $serviceLog, string $status = 'completed', array $data = []): bool
    {
        return $this->clientServingService->finishServingClient($operator, $serviceLog, $status, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperatorStats(Operator $operator, string $period = 'day'): array
    {
        return $this->analyticsService->getOperatorStats($operator, $period);
    }
}
